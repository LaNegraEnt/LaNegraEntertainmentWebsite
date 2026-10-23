<?php
// Gedeelde functies voor de boekingsflow:
//   offerte-rider.html → stuur-offerte.php → opdrachtbevestiging-akkoord.html
//   → betaling-aanmaken-live.php (akkoordmails + Mollie) → mollie-webhook-live.php (definitieve bevestiging)
// Dit bestand wordt alleen ge-include; direct openen geeft 404.
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) { http_response_code(404); exit; }

require_once __DIR__ . '/config.php';                // MOLLIE_API_KEY – staat alleen op de server, niet in GitHub
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

// ---------- Akkoord-link ondertekenen (zodat bedragen in de link niet ongemerkt te wijzigen zijn) ----------
function bk_secret(): string {
    return defined('LNE_LINK_SECRET') ? LNE_LINK_SECRET : hash('sha256', 'lne-akkoord-link|' . MOLLIE_API_KEY);
}

function bk_sign(array $f): string {
    $s = '';
    foreach (BK_LINK_FIELDS as $k) $s .= $k . '=' . ($f[$k] ?? '-') . "\n";
    return substr(hash_hmac('sha256', $s, bk_secret()), 0, 32);
}

function bk_akkoord_link(array $f): string {
    $q = [];
    foreach (BK_LINK_FIELDS as $k) $q[$k] = $f[$k] ?? '-';
    $q['sig'] = bk_sign($f);
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

// ---------- Mollie (rechtstreeks via de API, geen vendor/-map nodig) ----------
function bk_mollie(string $method, string $path, ?array $body = null): array {
    if (!defined('MOLLIE_API_KEY') || MOLLIE_API_KEY === '') throw new RuntimeException('MOLLIE_API_KEY ontbreekt in config.php');
    $ch = curl_init('https://api.mollie.com/v2/' . ltrim($path, '/'));
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . MOLLIE_API_KEY, 'Content-Type: application/json'],
    ]);
    if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
    $res  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    $data = json_decode((string)$res, true);
    if ($res === false || $code < 200 || $code >= 300 || !is_array($data)) {
        throw new RuntimeException('Mollie ' . $method . ' ' . $path . ' → ' . $code . ' ' . ($err ?: ($data['detail'] ?? substr((string)$res, 0, 200))));
    }
    return $data;
}

// ---------- Mailblokken ----------
function bk_boeking_card(array $o, string $titel): string {
    return lnc_mail_card($titel, [
        'Naam'        => lnc_e($o['naam']),
        'Datum'       => lnc_e(bk_datum($o['datum'])),
        'Locatie'     => lnc_e($o['locatie']),
        'Pakket'      => lnc_e($o['pakket']),
        'Danseressen' => in_array($o['danseressen'], ['-', 'Geen', '0'], true) ? '' : lnc_e($o['danseressen']),
        'Extra acts'  => $o['extras'] === 'Geen' || $o['extras'] === '-' ? '' : lnc_e($o['extras']),
        'Photobooth'  => $o['photobooth'] === 'Geen' || $o['photobooth'] === '-' ? '' : lnc_e($o['photobooth']),
        'Opmerking'   => $o['opmerking'] === '-' ? '' : nl2br(lnc_e($o['opmerking'])),
    ]);
}

function bk_bedragen_card(array $o, bool $metBetaling): string {
    $rows = [
        'Reiskosten'          => lnc_e($o['reiskosten']),
        'Subtotaal excl. btw' => lnc_e($o['subtotaal']),
        'Btw (9%)'            => lnc_e($o['btw']),
        'Totaal incl. btw'    => '<span style="color:#D74B27">' . lnc_e($o['totaal']) . '</span>',
    ];
    if ($metBetaling) {
        $rows['Aanbetaling']  = lnc_e($o['aanbetaling']);
        $rows['Restbetaling'] = lnc_e($o['restbetaling']);
    }
    return lnc_mail_card('Bedragen', $rows);
}

function bk_contact_card(array $o): string {
    $tel = preg_replace('/[^\d+]/', '', $o['tel']);
    return lnc_mail_card('Klant', [
        'Naam'     => lnc_e($o['naam']),
        'E-mail'   => '<a href="mailto:' . lnc_e($o['email']) . '" style="color:#D74B27">' . lnc_e($o['email']) . '</a>',
        'Telefoon' => $tel !== '' ? '<a href="tel:' . lnc_e($tel) . '" style="color:#D74B27">' . lnc_e($o['tel']) . '</a>' : '',
    ]);
}

function bk_footer_klant(): string {
    return 'Vragen? Beantwoord deze mail of bel <a href="tel:+31625178050" style="color:#8A8C88">' . LNC_PHONE . '</a>.<br>'
         . '<a href="' . LNC_SITE . '" style="color:#8A8C88">lanegra-entertainment.nl</a> &middot; KVK 67106951 &middot; BTW NL002420332B84';
}

/** Vult een mailsjabloon (opdracht-mail.html, definitieve-opdrachtbevestiging-mail.html) met ge-escapete waarden. */
function bk_template(string $file, array $vars): ?string {
    $path = __DIR__ . '/' . $file;
    if (!is_file($path)) return null;
    $map = [];
    foreach ($vars as $k => $v) $map['{{' . $k . '}}'] = lnc_e($v);
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
