<?php
// Gedeelde functies voor de boekingsflow:
//   offerte-rider.html → stuur-offerte.php → opdrachtbevestiging-akkoord.html
//   → betaling-aanmaken-live.php (akkoordmails + Revolut-betaling)
//   → revolut-webhook.php / betaling-status.php (definitieve bevestiging via bk_verwerk_betaling)
// Dit bestand wordt alleen ge-include; direct openen geeft 404.
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) { http_response_code(404); exit; }

require_once __DIR__ . '/config.php';                // REVOLUT_SECRET_KEY – staat alleen op de server, niet in GitHub
if (is_file(__DIR__ . '/config-revolut.php')) require_once __DIR__ . '/config-revolut.php';   // door de pipeline geplaatst uit het GitHub-secret
require_once __DIR__ . '/lanegra-chat/config.php';   // LNC_MAIL_TO / LNC_MAIL_FROM
require_once __DIR__ . '/lanegra-chat/_mail.php';

date_default_timezone_set('Europe/Amsterdam');


// Vaste aanbetaling: wordt nooit uit de link of het formulier overgenomen
const BK_AANBETALING = 50.00;

// Velden in de akkoord-link; de handtekening (sig) dekt precies deze velden
const BK_LINK_FIELDS = ['naam', 'email', 'tel', 'datum', 'locatie', 'pakket', 'danseressen', 'extras', 'photobooth', 'opmerking', 'reiskosten', 'subtotaal', 'btw', 'totaal'];

function bk_out(array $d, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($d, JSON_UNESCAPED_UNICODE);
    exit;
}

function bk_clean($v, int $max = 300): string {
    $v = is_scalar($v) ? (string)$v : '';
    $v = trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $v));
    $v = mb_substr($v, 0, $max);
    return $v === '' ? '-' : $v;
}

/** "€ 1.260,50" → 1260.5 */
function bk_euro_to_float(string $v): float {
    $v = preg_replace('/[^\d,.-]/', '', $v);
    $v = str_replace('.', '', $v);
    return (float)str_replace(',', '.', $v);
}

function bk_euro(float $v): string {
    return '€ ' . number_format($v, 2, ',', '.');
}

/** 2027-06-14 → 14-06-2027; andere notaties blijven zoals ze zijn */
function bk_datum(string $d): string {
    return preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $d, $m) ? "$m[3]-$m[2]-$m[1]" : $d;
}

function bk_voornaam(string $naam): string {
    return explode(' ', trim($naam))[0] ?? '';
}

/** Taal van de klant: 'en' via de Engelse offertepagina, anders 'nl'. Hoort niet bij de handtekening (alleen weergave). */
function bk_lang($o): string {
    $l = is_array($o) ? ($o['lang'] ?? '') : (string)$o;
    return $l === 'en' ? 'en' : 'nl';
}

/** Kies de Nederlandse of Engelse tekst. */
function bk_t(string $lang, string $nl, string $en): string {
    return $lang === 'en' ? $en : $nl;
}

/** Lege keuzes ("Geen", "None", "-") niet tonen. */
function bk_leeg(string $v): bool {
    return in_array($v, ['-', 'Geen', 'None', '0'], true);
}

// ---------- Misbruik beperken: max. aantal verzoeken per bezoeker (IP) per uur ----------
function bk_rate(string $bucket, int $max): bool {
    $dir = dirname(bk_orders_dir()) . '/rate';
    if (!is_dir($dir)) @mkdir($dir, 0750, true);
    $f = $dir . '/' . $bucket . '_' . hash('sha256', $_SERVER['REMOTE_ADDR'] ?? '') . '.json';
    $nu = time();
    $hits = is_file($f) ? (json_decode((string)@file_get_contents($f), true) ?: []) : [];
    $hits = array_values(array_filter($hits, function ($t) use ($nu) { return $t > $nu - 3600; }));
    if (count($hits) >= $max) return false;
    $hits[] = $nu;
    @file_put_contents($f, json_encode($hits), LOCK_EX);
    return true;
}

// ---------- Akkoord-link ondertekenen (zodat bedragen in de link niet ongemerkt te wijzigen zijn) ----------
function bk_secret(): string {
    if (defined('LNE_LINK_SECRET')) return LNE_LINK_SECRET;
    // Eenmalig aangemaakte geheime sleutel naast de orders (niet in GitHub)
    $file = dirname(bk_orders_dir()) . '/link-secret.txt';
    if (!is_file($file)) file_put_contents($file, bin2hex(random_bytes(32)), LOCK_EX);
    return trim((string)file_get_contents($file));
}

function bk_sign(array $f): string {
    $s = '';
    foreach (BK_LINK_FIELDS as $k) $s .= $k . '=' . ($f[$k] ?? '-') . "\n";
    return substr(hash_hmac('sha256', $s, bk_secret()), 0, 32);
}

/** Handtekening voor de koppeling betaling ↔ agenda-afspraak (agenda-id in de akkoord-link). */
function bk_agenda_sig(string $eventId): string {
    return substr(hash_hmac('sha256', 'agenda|' . $eventId, bk_secret()), 0, 24);
}

function bk_akkoord_link(array $f): string {
    $q = [];
    foreach (BK_LINK_FIELDS as $k) $q[$k] = $f[$k] ?? '-';
    $q['sig'] = bk_sign($f);
    if (bk_lang($f) === 'en') return LNC_SITE . '/en/confirm-booking.html?' . http_build_query($q + ['lang' => 'en']);
    return LNC_SITE . '/opdrachtbevestiging-akkoord.html?' . http_build_query($q);
}

// ---------- Orders (buiten public_html als dat kan) ----------
function bk_orders_dir(): string {
    $kandidaten = [__DIR__ . '/../private_data/orders', __DIR__ . '/lanegra-chat/data/orders'];
    foreach ($kandidaten as $dir) {
        if ((is_dir($dir) || @mkdir($dir, 0750, true)) && is_writable($dir)) {
            // Valt hij terug op een map binnen de site, dan die map afschermen
            $data = dirname($dir);
            if (strpos($dir, '/lanegra-chat/') !== false && !is_file($data . '/.htaccess')) {
                @file_put_contents($data . '/.htaccess', "Require all denied\nDeny from all\n");
            }
            return $dir;
        }
    }
    throw new RuntimeException('Geen schrijfbare map voor orders');
}

function bk_order_file(string $orderId): string {
    return bk_orders_dir() . '/' . preg_replace('/[^A-Za-z0-9_-]/', '_', $orderId) . '.json';
}

function bk_load_order(string $orderId): ?array {
    $file = bk_order_file($orderId);
    if (!is_file($file)) return null;
    $o = json_decode((string)file_get_contents($file), true);
    return is_array($o) ? $o : null;
}

function bk_save_order(array $o): void {
    file_put_contents(bk_order_file($o['order_id']), json_encode($o, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

// ---------- Revolut Business (Merchant API) ----------
// In config.php op de server: const REVOLUT_SECRET_KEY = 'sk_...';  (optioneel: const REVOLUT_SANDBOX = true;)
const BK_REVOLUT_API_VERSION = '2024-09-01';

function bk_revolut_base(): string {
    return defined('REVOLUT_SANDBOX') && REVOLUT_SANDBOX ? 'https://sandbox-merchant.revolut.com' : 'https://merchant.revolut.com';
}

function bk_revolut(string $method, string $path, ?array $body = null): array {
    if (!defined('REVOLUT_SECRET_KEY') || REVOLUT_SECRET_KEY === '') throw new RuntimeException('REVOLUT_SECRET_KEY ontbreekt in config.php');
    $ch = curl_init(bk_revolut_base() . '/' . ltrim($path, '/'));
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . REVOLUT_SECRET_KEY,
            'Revolut-Api-Version: ' . BK_REVOLUT_API_VERSION,
            'Content-Type: application/json',
            'Accept: application/json',
        ],
    ]);
    if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
    $res  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    $data = json_decode((string)$res, true);
    if ($res === false || $code < 200 || $code >= 300 || !is_array($data)) {
        throw new RuntimeException('Revolut ' . $method . ' ' . $path . ' → ' . $code . ' ' . ($err ?: substr((string)$res, 0, 300)));
    }
    return $data;
}

/**
 * Haalt de actuele status van de betaling op bij Revolut (de bron van waarheid) en verstuurt
 * bij een geslaagde betaling precies één keer de definitieve bevestiging. Geeft de bijgewerkte order terug.
 */
function bk_verwerk_betaling(array $o): array {
    if (empty($o['revolut_order_id'])) return $o;
    $state = strtolower((string)(bk_revolut('GET', 'api/orders/' . rawurlencode($o['revolut_order_id']))['state'] ?? ''));
    $o['revolut_state'] = $state;
    $o['updated_at'] = date('c');
    if ($state === 'completed') {
        $o['status'] = 'paid';
        $o['paid_at'] = $o['paid_at'] ?? date('c');
    } elseif (($o['status'] ?? '') !== 'paid' && in_array($state, ['failed', 'cancelled'], true)) {
        $o['status'] = 'failed';
    }
    bk_save_order($o);
    if ($o['status'] === 'paid' && empty($o['bevestiging_verstuurd'])) {
        $o['bevestiging_verstuurd'] = date('c');   // eerst vastleggen, zodat gelijktijdige aanroepen niet dubbel mailen
        bk_save_order($o);
        bk_mail_bevestiging($o);
    }
    if ($o['status'] === 'paid') $o = bk_agenda_bijwerken($o);
    return $o;
}

/**
 * Kwam de boeking uit de agenda? Dan de afspraak op "GEBOEKT" zetten. Lukt dat niet, dan krijg je één keer
 * een mail met de reden; bij een volgende statuscontrole (bedankt.html) wordt het opnieuw geprobeerd.
 */
function bk_agenda_bijwerken(array $o): array {
    if (empty($o['agenda_event']) || !empty($o['agenda_bijgewerkt'])) return $o;
    try {
        require_once __DIR__ . '/agenda/_google.php';
        lna_bevestig_boeking($o['agenda_event'], $o['order_id']);
        $o['agenda_bijgewerkt'] = date('c');
        unset($o['agenda_fout']);
    } catch (Throwable $e) {
        $o['agenda_fout'] = substr($e->getMessage(), 0, 300);
        error_log('Agenda bijwerken na betaling: ' . $o['agenda_fout']);
        if (empty($o['agenda_fout_gemeld'])) {
            $o['agenda_fout_gemeld'] = date('c');
            lnc_mail(LNC_MAIL_TO, 'Let op: agenda niet bijgewerkt – ' . $o['naam'],
                lnc_mail_layout('Agenda niet bijgewerkt',
                    '<p style="font-size:15px;line-height:1.6">De aanbetaling van ' . lnc_e($o['naam']) . ' (order ' . lnc_e($o['order_id']) . ') is binnen, '
                    . 'maar de afspraak in "La Negra optredens" kon niet automatisch op GEBOEKT worden gezet. Pas hem zelf aan, zodat hij niet na 48 uur verloopt.</p>'
                    . '<p style="font-size:13px;color:#8A8C88">Reden: ' . lnc_e($o['agenda_fout']) . '</p>', 'Order ' . lnc_e($o['order_id'])),
                "Agenda niet bijgewerkt voor order {$o['order_id']} ({$o['naam']}). Reden: {$o['agenda_fout']}\n", '', 'Agenda via website');
        }
    }
    bk_save_order($o);
    return $o;
}

/** Eenmalig de webhook bij Revolut aanmelden (best effort; betaling-status.php vangt het ook op zonder webhook). */
function bk_revolut_webhook_aanmelden(): void {
    $flag = dirname(bk_orders_dir()) . '/revolut-webhook.json';
    if (is_file($flag)) return;
    $url = LNC_SITE . '/revolut-webhook.php';
    foreach (['api/1.0/webhooks', 'api/webhooks'] as $path) {
        try {
            $res = bk_revolut('POST', $path, ['url' => $url, 'events' => ['ORDER_COMPLETED', 'ORDER_PAYMENT_FAILED', 'ORDER_CANCELLED']]);
            file_put_contents($flag, json_encode(['path' => $path, 'id' => $res['id'] ?? null, 'at' => date('c')]), LOCK_EX);
            return;
        } catch (Throwable $e) {
            error_log('Revolut webhook aanmelden via ' . $path . ': ' . $e->getMessage());
        }
    }
}

// ---------- Mailblokken ----------
/** $lang = 'en' voor mails aan Engelstalige klanten; mails aan jou blijven Nederlands. */
function bk_boeking_card(array $o, string $titel, string $lang = 'nl'): string {
    $en = $lang === 'en';
    return lnc_mail_card($titel, [
        $en ? 'Name' : 'Naam'            => lnc_e($o['naam']),
        $en ? 'Date' : 'Datum'           => lnc_e(bk_datum($o['datum'])),
        $en ? 'Venue' : 'Locatie'        => lnc_e($o['locatie']),
        $en ? 'Package' : 'Pakket'       => lnc_e($o['pakket']),
        $en ? 'Dancers' : 'Danseressen'  => bk_leeg($o['danseressen']) ? '' : lnc_e($o['danseressen']),
        $en ? 'Extra acts' : 'Extra acts'=> bk_leeg($o['extras']) ? '' : lnc_e($o['extras']),
        'Photobooth'                     => bk_leeg($o['photobooth']) ? '' : lnc_e($o['photobooth']),
        $en ? 'Comments' : 'Opmerking'   => $o['opmerking'] === '-' ? '' : nl2br(lnc_e($o['opmerking'])),
    ]);
}

function bk_bedragen_card(array $o, bool $metBetaling, string $lang = 'nl'): string {
    $en = $lang === 'en';
    $rows = [
        $en ? 'Travel costs' : 'Reiskosten'                  => lnc_e($o['reiskosten']),
        $en ? 'Subtotal excl. VAT' : 'Subtotaal excl. btw'   => lnc_e($o['subtotaal']),
        $en ? 'VAT (9%)' : 'Btw (9%)'                        => lnc_e($o['btw']),
        $en ? 'Total incl. VAT' : 'Totaal incl. btw'         => '<span style="color:#D74B27">' . lnc_e($o['totaal']) . '</span>',
    ];
    if ($metBetaling) {
        $rows[$en ? 'Deposit' : 'Aanbetaling']  = lnc_e($o['aanbetaling']);
        $rows[$en ? 'Balance' : 'Restbetaling'] = lnc_e($en && $o['restbetaling'] === 'Volgens offerte' ? 'As per quote' : $o['restbetaling']);
    }
    return lnc_mail_card($en ? 'Amounts' : 'Bedragen', $rows);
}

function bk_contact_card(array $o): string {
    $tel = preg_replace('/[^\d+]/', '', $o['tel']);
    return lnc_mail_card('Klant', [
        'Naam'     => lnc_e($o['naam']),
        'E-mail'   => '<a href="mailto:' . lnc_e($o['email']) . '" style="color:#D74B27">' . lnc_e($o['email']) . '</a>',
        'Telefoon' => $tel !== '' ? '<a href="tel:' . lnc_e($tel) . '" style="color:#D74B27">' . lnc_e($o['tel']) . '</a>' : '',
    ]);
}

function bk_footer_klant(string $lang = 'nl'): string {
    return bk_t($lang, 'Vragen? Beantwoord deze mail of bel', 'Questions? Reply to this email or call') . ' <a href="tel:+31625178050" style="color:#8A8C88">' . LNC_PHONE . '</a>.<br>'
         . '<a href="' . LNC_SITE . '" style="color:#8A8C88">lanegra-entertainment.nl</a> &middot; KVK 67106951 &middot; ' . bk_t($lang, 'BTW', 'VAT') . ' NL002420332B84';
}

/** Vult een mailsjabloon (opdracht-mail.html, definitieve-opdrachtbevestiging-mail.html) met ge-escapete waarden. */
function bk_template(string $file, array $vars): ?string {
    $path = __DIR__ . '/' . $file;
    if (!is_file($path)) return null;
    $map = [];
    foreach ($vars as $k => $v) $map['{{' . $k . '}}'] = lnc_e($v);
    // Logo als ingesloten afbeelding (Outlook blokkeert afbeeldingen van de website)
    $map[LNC_SITE . '/assets/img/logo.png'] = 'cid:' . LNC_LOGO_CID;
    return strtr((string)file_get_contents($path), $map);
}

function bk_agenda_link(array $o): string {
    $start = strtotime(($o['datum'] ?? '') . ' 18:00') ?: time() + 86400;
    return 'https://calendar.google.com/calendar/render?action=TEMPLATE'
        . '&text=' . rawurlencode('La Negra boeking – ' . $o['naam'])
        . '&dates=' . gmdate('Ymd\THis\Z', $start) . '/' . gmdate('Ymd\THis\Z', $start + 3 * 3600)
        . '&location=' . rawurlencode($o['locatie'])
        . '&details=' . rawurlencode("Pakket: {$o['pakket']}\nTotaal: {$o['totaal']}\nKlant: {$o['naam']} ({$o['email']}, {$o['tel']})");
}

/** Definitieve bevestiging na ontvangen aanbetaling: klant (sjabloon) en jij. */
function bk_mail_bevestiging(array $o): void {
    $datum = bk_datum($o['datum']);
    $lang = bk_lang($o);
    $rest = $lang === 'en' && $o['restbetaling'] === 'Volgens offerte' ? 'As per quote' : $o['restbetaling'];

    $html = bk_template(bk_t($lang, 'definitieve-opdrachtbevestiging-mail.html', 'definitieve-opdrachtbevestiging-mail-en.html'), [
        'AANHEF' => bk_t($lang, 'Hoi ', 'Hi ') . bk_voornaam($o['naam']) . ',', 'NAAM' => $o['naam'], 'DATUM' => $datum, 'LOCATIE' => $o['locatie'],
        'PAKKET' => $o['pakket'], 'REISKOSTEN' => $o['reiskosten'], 'TOTAAL' => $o['totaal'],
        'AANBETALING' => $o['aanbetaling'], 'RESTBETALING' => $rest,
    ]) ?? lnc_mail_layout(bk_t($lang, 'Je boeking is bevestigd', 'Your booking is confirmed'),
        bk_boeking_card($o, bk_t($lang, 'Je boeking', 'Your booking'), $lang) . bk_bedragen_card($o, true, $lang), bk_footer_klant($lang));
    $tekst = $lang === 'en'
        ? "Hi " . bk_voornaam($o['naam']) . ",\n\nWe have received your deposit of {$o['aanbetaling']}. Your booking for $datum is now final.\n"
          . "Package: {$o['pakket']}\nVenue: {$o['locatie']}\nTotal incl. VAT: {$o['totaal']}\nBalance: $rest\n\nMusical greetings,\nLa Negra Entertainment\n"
        : "Hoi " . bk_voornaam($o['naam']) . ",\n\nWe hebben je aanbetaling van {$o['aanbetaling']} ontvangen. Je boeking voor $datum is definitief vastgelegd.\n"
          . "Pakket: {$o['pakket']}\nLocatie: {$o['locatie']}\nTotaal incl. btw: {$o['totaal']}\nRestbetaling: {$o['restbetaling']}\n\nMuzikale groet,\nLa Negra Entertainment\n";
    $klantOk = lnc_mail($o['email'], bk_t($lang, 'Je boeking is definitief bevestigd – La Negra Entertainment', 'Your booking is confirmed – La Negra Entertainment'),
        $html, $tekst, LNC_MAIL_TO);

    $body = '<div style="font-size:26px;font-weight:bold;line-height:1.25;margin:0 0 4px">Aanbetaling ontvangen</div>'
          . '<div style="font-size:14px;color:#8A8C88;margin:0 0 20px">Order ' . lnc_e($o['order_id']) . ' &middot; Revolut ' . lnc_e($o['revolut_order_id'] ?? '') . '</div>'
          . '<p style="font-size:15px;line-height:1.6;margin:0 0 18px">De boeking van ' . lnc_e($o['naam']) . ' is definitief. ' . ($lang === 'en' ? '<b>Engelstalige klant.</b> ' : '')
          . ($klantOk ? 'De klant heeft de definitieve bevestiging per mail ontvangen.' : '<b>Let op:</b> de bevestigingsmail naar de klant kon niet worden verstuurd.') . '</p>'
          . bk_contact_card($o) . bk_boeking_card($o, 'De boeking') . bk_bedragen_card($o, true)
          . '<div style="margin:0 0 20px">' . lnc_mail_button(bk_agenda_link($o), 'Zet in agenda', true) . '</div>';
    lnc_mail(LNC_MAIL_TO, 'Aanbetaling ontvangen: ' . $o['naam'] . ' – ' . $datum,
        lnc_mail_layout('Aanbetaling ontvangen van ' . $o['naam'], $body, 'Order ' . lnc_e($o['order_id'])),
        "Aanbetaling van {$o['aanbetaling']} ontvangen van {$o['naam']} ({$o['email']}) voor $datum.\nOrder: {$o['order_id']}\n", $o['email'], 'Aanbetaling via website');
}
