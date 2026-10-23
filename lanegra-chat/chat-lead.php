<?php
// Ontvangt een aanvraag uit de chat, mailt hem naar jou, stuurt de klant een bevestiging en bewaart een CSV-backup.
// De server draait PHP 8.3; de pipeline controleert de syntax op dezelfde versie.
require_once __DIR__ . '/_common.php';

date_default_timezone_set('Europe/Amsterdam');

$in = lnc_guard();
lnc_protect_dir();

// Gegevens komen uit de chat/JS als {lead:{...}} of, zonder JS, als los formulier
$viaJs = is_array($in['lead'] ?? null);
$L = $viaJs ? $in['lead'] : $in;

// Spam-checks: honeypot (altijd) + te snel ingevuld (alleen als de JS de tijd meestuurt)
$honeypot = !empty($in['website']) || !empty($L['website']);
$teSnel   = isset($in['t']) && (int)$in['t'] < 8;
if ($honeypot || $teSnel) lnc_out(['ok' => true]); // stil negeren
if (!lnc_rate('lead', 5)) lnc_out(['ok' => false, 'error' => 'Je hebt al meerdere aanvragen verstuurd. Mail ons gerust direct via ' . LNC_MAIL_TO . '.'], 429);
$f = [
    'Type event'  => lnc_clean($L['type'] ?? '', 80),
    'Act'         => lnc_clean($L['act'] ?? '', 80),
    'Datum'       => lnc_clean($L['datum'] ?? '', 40),
    'Plaats'      => lnc_clean($L['plaats'] ?? '', 120),
    'Gasten'      => lnc_clean($L['gasten'] ?? '', 40),
    'Naam'        => lnc_clean($L['naam'] ?? '', 100),
    'E-mail'      => lnc_clean($L['email'] ?? '', 150),
    'Telefoon'    => lnc_clean($L['telefoon'] ?? '', 40),
    'Opmerking'   => lnc_clean($L['opmerking'] ?? '', 1000),
    'Taal'        => strtoupper(lnc_clean($in['lang'] ?? 'nl', 2)),
];
if ($f['Naam'] === '' || !filter_var($f['E-mail'], FILTER_VALIDATE_EMAIL)) {
    lnc_out(['ok' => false, 'error' => 'Naam of e-mailadres ontbreekt of klopt niet.'], 422);
}

// Datum uit het formulier (2027-06-14) leesbaar maken (14-06-2027)
if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $f['Datum'], $d)) $f['Datum'] = $d[3] . '-' . $d[2] . '-' . $d[1];

$src = is_array($in['source'] ?? null) ? $in['source'] : [];
$srcTxt = [];
foreach (['utm_source', 'utm_medium', 'utm_campaign', 'gclid', 'fbclid', 'referrer', 'landing'] as $k) {
    if (!empty($src[$k])) $srcTxt[$k] = lnc_clean($src[$k], 200);
}
$page = lnc_clean($in['page'] ?? '', 300);

// Gesprek; zit er een bot-bericht in, dan kwam de aanvraag uit de chat, anders uit het formulier
$gesprek = [];
$viaChat = false;
foreach (array_slice(is_array($in['transcript'] ?? null) ? $in['transcript'] : [], -20) as $m) {
    $isKlant = ($m['role'] ?? '') === 'user';
    if (!$isKlant) $viaChat = true;
    $gesprek[] = [$isKlant, lnc_clean($m['content'] ?? '', 800)];
}
$kanaal = $viaChat ? 'de website-chat' : 'het aanvraagformulier';

// ---------- Mail-opmaak ----------
const LNC_SITE = 'https://lanegra-entertainment.nl';
const LNC_PHONE = '+31 6 25 17 80 50';
// Het logo gaat als bijlage in de mail mee (cid:), want Outlook blokkeert afbeeldingen van externe websites
const LNC_LOGO_FILE = __DIR__ . '/../assets/img/logo.png';
const LNC_LOGO_CID = 'logo@lanegra-entertainment.nl';

function lnc_e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/** Gemeenschappelijke omlijsting: donkere kop met logo, witte kaart, voettekst. */
function lnc_mail_layout(string $preheader, string $body, string $footer): string {
    return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>'
        . '<body style="margin:0;padding:0;background:#F4F1EA">'
        . '<div style="display:none;max-height:0;overflow:hidden;opacity:0">' . lnc_e($preheader) . '</div>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F4F1EA"><tr><td align="center" style="padding:24px 12px">'
        . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:100%;max-width:600px;background:#FFFFFF;border-radius:16px;overflow:hidden;font-family:Arial,Helvetica,sans-serif;color:#1F231E">'
        . '<tr><td align="center" style="background:#1F231E;padding:22px 24px 18px">'
        . '<img src="' . (is_readable(LNC_LOGO_FILE) ? 'cid:' . LNC_LOGO_CID : LNC_SITE . '/assets/img/logo.png') . '" width="200" alt="La Negra Entertainment" style="display:block;width:200px;max-width:70%;height:auto;border:0;color:#FDD22C;font-size:22px;font-weight:bold">'
        . '</td></tr>'
        . '<tr><td style="height:5px;background:#FDD22C;font-size:0;line-height:0">&nbsp;</td></tr>'
        . '<tr><td style="padding:28px 28px 8px">' . $body . '</td></tr>'
        . '<tr><td style="padding:18px 28px 26px;font-size:12px;line-height:1.6;color:#8A8C88;border-top:1px solid #EEEAE0">' . $footer . '</td></tr>'
        . '</table></td></tr></table></body></html>';
}

/** Blok met een kopje en label/waarde-regels; lege waarden worden overgeslagen. Waarden zijn al ge-escaped HTML. */
function lnc_mail_card(string $title, array $rows): string {
    $html = '';
    foreach ($rows as $label => $value) {
        if ($value === '') continue;
        $html .= '<tr><td style="padding:7px 0;width:38%;font-size:13px;color:#8A8C88;vertical-align:top">' . lnc_e($label) . '</td>'
               . '<td style="padding:7px 0;font-size:15px;font-weight:bold;color:#1F231E;vertical-align:top">' . $value . '</td></tr>';
    }
    if ($html === '') return '';
    return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#FBF8F1;border-left:4px solid #FDD22C;border-radius:10px;margin:0 0 18px">'
         . '<tr><td style="padding:16px 18px 10px">'
         . '<div style="font-size:12px;font-weight:bold;letter-spacing:.12em;text-transform:uppercase;color:#D74B27;margin-bottom:6px">' . lnc_e($title) . '</div>'
         . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0">' . $html . '</table>'
         . '</td></tr></table>';
}

function lnc_mail_button(string $href, string $label, bool $primary): string {
    $style = $primary ? 'background:#FDD22C;color:#1F231E;border:2px solid #FDD22C' : 'background:#FFFFFF;color:#1F231E;border:2px solid #1F231E';
    return '<a href="' . lnc_e($href) . '" style="display:inline-block;margin:0 8px 8px 0;padding:11px 18px;border-radius:999px;font-size:14px;font-weight:bold;text-decoration:none;' . $style . '">' . lnc_e($label) . '</a>';
}

/** Verstuurt een HTML-mail met platte-tekstversie (beter voor spamfilters) en het logo ingesloten. */
function lnc_mail(string $to, string $subject, string $html, string $text, string $replyTo = ''): bool {
    $alt = 'lnc_alt_' . bin2hex(random_bytes(8));
    $alternative = '--' . $alt . "\r\n"
          . "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
          . chunk_split(base64_encode($text)) . "\r\n"
          . '--' . $alt . "\r\n"
          . "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
          . chunk_split(base64_encode($html)) . "\r\n"
          . '--' . $alt . "--\r\n";

    $logo = is_readable(LNC_LOGO_FILE) ? file_get_contents(LNC_LOGO_FILE) : false;
    if ($logo !== false) {
        // multipart/related: tekst+HTML, met het logo als ingesloten afbeelding
        $rel = 'lnc_rel_' . bin2hex(random_bytes(8));
        $contentType = 'multipart/related; type="multipart/alternative"; boundary="' . $rel . '"';
        $body = '--' . $rel . "\r\n"
              . 'Content-Type: multipart/alternative; boundary="' . $alt . '"' . "\r\n\r\n"
              . $alternative . "\r\n"
              . '--' . $rel . "\r\n"
              . "Content-Type: image/png; name=\"logo.png\"\r\nContent-Transfer-Encoding: base64\r\n"
              . 'Content-ID: <' . LNC_LOGO_CID . ">\r\nContent-Disposition: inline; filename=\"logo.png\"\r\n\r\n"
              . chunk_split(base64_encode($logo)) . "\r\n"
              . '--' . $rel . "--\r\n";
    } else {
        $contentType = 'multipart/alternative; boundary="' . $alt . '"';
        $body = $alternative;
    }

    $from = '=?UTF-8?B?' . base64_encode(LNC_MAIL_FROM_NAME) . '?= <' . LNC_MAIL_FROM . '>';
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: ' . $contentType,
        'From: ' . $from,
    ];
    if ($replyTo) $headers[] = 'Reply-To: ' . $replyTo;
    return @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, implode("\r\n", $headers), '-f' . LNC_MAIL_FROM);
}

// ---------- Mail naar jou ----------
$tel = preg_replace('/[^\d+]/', '', $f['Telefoon']);
$wa  = preg_replace('/^0(?=6)/', '31', ltrim($tel, '+'));  // 06... -> 316... voor WhatsApp

$knoppen = lnc_mail_button('mailto:' . $f['E-mail'] . '?subject=' . rawurlencode('Je aanvraag bij La Negra Entertainment'), 'Beantwoord per mail', true);
if ($tel !== '') {
    $knoppen .= lnc_mail_button('tel:' . $tel, 'Bel ' . $f['Telefoon'], false);
    if (preg_match('/^316\d{8}$/', $wa)) $knoppen .= lnc_mail_button('https://wa.me/' . $wa, 'WhatsApp', false);
}

$gesprekHtml = '';
if ($viaChat) {
    foreach ($gesprek as $g) {
        $gesprekHtml .= $g[0]
            ? '<div style="margin:6px 0 6px 40px;padding:8px 12px;background:#FDD22C;border-radius:12px 12px 2px 12px;font-size:13px;color:#1F231E">' . nl2br(lnc_e($g[1])) . '</div>'
            : '<div style="margin:6px 40px 6px 0;padding:8px 12px;background:#F1EEE6;border-radius:12px 12px 12px 2px;font-size:13px;color:#1F231E">' . nl2br(lnc_e($g[1])) . '</div>';
    }
}

$herkomst = [];
foreach ($srcTxt as $k => $v) $herkomst[] = '<b>' . lnc_e($k) . ':</b> ' . lnc_e($v);
if ($page !== '') $herkomst[] = '<b>pagina:</b> ' . lnc_e($page);

$body = '<div style="font-size:26px;font-weight:bold;line-height:1.25;margin:0 0 4px">Nieuwe aanvraag</div>'
      . '<div style="font-size:14px;color:#8A8C88;margin:0 0 20px">Via ' . $kanaal . ' &middot; ' . date('d-m-Y H:i') . '</div>'
      . '<div style="margin:0 0 14px">' . $knoppen . '</div>'
      . lnc_mail_card('Het event', [
            'Soort event' => lnc_e($f['Type event']),
            'Act'         => lnc_e($f['Act']),
            'Datum'       => lnc_e($f['Datum']),
            'Plaats'      => lnc_e($f['Plaats']),
            'Gasten'      => lnc_e($f['Gasten']),
        ])
      . lnc_mail_card('Contact', [
            'Naam'     => lnc_e($f['Naam']),
            'E-mail'   => '<a href="mailto:' . lnc_e($f['E-mail']) . '" style="color:#D74B27">' . lnc_e($f['E-mail']) . '</a>',
            'Telefoon' => $tel !== '' ? '<a href="tel:' . lnc_e($tel) . '" style="color:#D74B27">' . lnc_e($f['Telefoon']) . '</a>' : '',
            'Taal'     => lnc_e($f['Taal']),
        ])
      . ($f['Opmerking'] !== '' ? lnc_mail_card('Wensen of vragen', ['Bericht' => nl2br(lnc_e($f['Opmerking']))]) : '')
      . ($gesprekHtml !== '' ? '<div style="font-size:12px;font-weight:bold;letter-spacing:.12em;text-transform:uppercase;color:#D74B27;margin:4px 0 8px">Gesprek in de chat</div>' . $gesprekHtml . '<div style="height:12px"></div>' : '');

$html = lnc_mail_layout(
    $f['Naam'] . ' – ' . $f['Type event'] . ' ' . $f['Datum'],
    $body,
    ($herkomst ? 'Herkomst: ' . implode(' &middot; ', $herkomst) . '<br>' : '') . 'Tip: beantwoord deze mail, dan gaat je antwoord direct naar ' . lnc_e($f['E-mail']) . '.'
);

$text = "Nieuwe aanvraag via $kanaal (" . date('d-m-Y H:i') . ")\n\n";
foreach ($f as $k => $v) if ($v !== '') $text .= "$k: $v\n";
if ($viaChat) {
    $text .= "\nGesprek:\n";
    foreach ($gesprek as $g) $text .= ($g[0] ? 'Klant: ' : 'Bot: ') . $g[1] . "\n";
}

$subject = 'Nieuwe aanvraag: ' . implode(' – ', array_filter([$f['Type event'], $f['Datum'], $f['Naam']], 'strlen'));
$sent = lnc_mail(LNC_MAIL_TO, $subject, $html, $text, $f['E-mail']);

// ---------- Bevestiging naar klant ----------
if (LNC_SEND_AUTOREPLY) {
    $T = [
        'nl' => [
            'subject' => 'We hebben je aanvraag ontvangen – La Negra Entertainment',
            'hi' => 'Hoi', 'title' => 'Bedankt voor je aanvraag!',
            'intro' => 'Je aanvraag is goed bij ons binnengekomen. Hieronder zie je wat je hebt doorgegeven.',
            'card' => 'Jouw aanvraag',
            'labels' => ['Soort event', 'Act', 'Datum', 'Plaats', 'Gasten', 'Opmerking'],
            'next' => 'Wat gebeurt er nu?',
            'steps' => ['We checken de beschikbaarheid op jouw datum.', 'Je ontvangt zo snel mogelijk een offerte op maat.', 'Na je akkoord leggen we alles vast. Daarna: ¡fiesta!'],
            'q' => 'Vragen of iets vergeten? Beantwoord gewoon deze mail of bel ons op',
            'bye' => 'Muzikale groet,',
            'btn' => 'Bekijk onze acts',
            'offerte' => 'Stel je offerte samen', 'offerte_txt' => 'Weet je al wat je wilt? Stel direct je offerte samen: kies je act en extra\'s en je ziet meteen het totaalbedrag.',
            'foot' => 'Je ontvangt deze mail omdat je een aanvraag deed op lanegra-entertainment.nl.',
        ],
        'en' => [
            'subject' => 'We have received your request – La Negra Entertainment',
            'hi' => 'Hi', 'title' => 'Thank you for your request!',
            'intro' => 'Your request has reached us. Below is a summary of what you sent us.',
            'card' => 'Your request',
            'labels' => ['Type of event', 'Act', 'Date', 'Location', 'Guests', 'Message'],
            'next' => 'What happens next?',
            'steps' => ['We check availability on your date.', 'You will receive a tailor-made quote as soon as possible.', 'Once you agree, we confirm everything. Then: ¡fiesta!'],
            'q' => 'Questions or forgot something? Simply reply to this email or call us at',
            'bye' => 'Musical greetings,',
            'btn' => 'View our acts',
            'offerte' => 'Build your quote', 'offerte_txt' => 'Already know what you want? Build your quote right away: choose your act and extras and see the total straight away.',
            'foot' => 'You are receiving this email because you sent a request via lanegra-entertainment.nl.',
        ],
        'es' => [
            'subject' => 'Hemos recibido tu solicitud – La Negra Entertainment',
            'hi' => 'Hola', 'title' => '¡Gracias por tu solicitud!',
            'intro' => 'Tu solicitud nos ha llegado correctamente. Aquí tienes un resumen de lo que nos enviaste.',
            'card' => 'Tu solicitud',
            'labels' => ['Tipo de evento', 'Acto', 'Fecha', 'Lugar', 'Invitados', 'Mensaje'],
            'next' => '¿Qué pasa ahora?',
            'steps' => ['Comprobamos la disponibilidad en tu fecha.', 'Recibirás un presupuesto a medida lo antes posible.', 'Con tu visto bueno lo dejamos todo confirmado. Y luego: ¡fiesta!'],
            'q' => '¿Preguntas o se te olvidó algo? Responde a este correo o llámanos al',
            'bye' => 'Un saludo musical,',
            'btn' => 'Ver nuestros actos',
            'offerte' => 'Crea tu presupuesto', 'offerte_txt' => '¿Ya sabes lo que quieres? Crea tu presupuesto ahora: elige tu acto y extras y verás el total al instante.',
            'foot' => 'Recibes este correo porque enviaste una solicitud en lanegra-entertainment.nl.',
        ],
    ];
    $lang = strtolower($f['Taal']);
    $t = $T[$lang] ?? $T['nl'];
    $voornaam = explode(' ', $f['Naam'])[0];
    $actsUrl = LNC_SITE . '/' . ($lang === 'en' || $lang === 'es' ? $lang . '/' : '') . 'index.html#acts';

    $stappen = '';
    foreach ($t['steps'] as $i => $stap) {
        $stappen .= '<tr><td style="width:36px;padding:6px 0;vertical-align:top">'
                  . '<div style="width:26px;height:26px;line-height:26px;border-radius:50%;background:#1F231E;color:#FDD22C;font-size:13px;font-weight:bold;text-align:center">' . ($i + 1) . '</div></td>'
                  . '<td style="padding:9px 0 6px;font-size:15px;line-height:1.5">' . lnc_e($stap) . '</td></tr>';
    }

    $waarden = [$f['Type event'], $f['Act'], $f['Datum'], $f['Plaats'], $f['Gasten'], $f['Opmerking']];
    $kaart = [];
    foreach ($t['labels'] as $i => $label) $kaart[$label] = nl2br(lnc_e($waarden[$i]));

    $body = '<div style="font-size:15px;color:#8A8C88;margin:0 0 4px">' . lnc_e($t['hi']) . ' ' . lnc_e($voornaam) . ',</div>'
          . '<div style="font-size:26px;font-weight:bold;line-height:1.25;margin:0 0 10px">' . lnc_e($t['title']) . '</div>'
          . '<p style="font-size:15px;line-height:1.6;margin:0 0 20px">' . lnc_e($t['intro']) . '</p>'
          . lnc_mail_card($t['card'], $kaart)
          . '<div style="font-size:18px;font-weight:bold;margin:8px 0 6px">' . lnc_e($t['next']) . '</div>'
          . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 18px">' . $stappen . '</table>'
          . '<p style="font-size:15px;line-height:1.6;margin:0 0 18px">' . lnc_e($t['q']) . ' <a href="tel:+31625178050" style="color:#D74B27;font-weight:bold;text-decoration:none">' . LNC_PHONE . '</a>.</p>'
          . '<p style="font-size:15px;line-height:1.6;margin:0 0 12px">' . lnc_e($t['offerte_txt']) . '</p>'
          . '<div style="margin:0 0 20px">' . lnc_mail_button(LNC_SITE . '/offerte-rider.html', $t['offerte'], true) . lnc_mail_button($actsUrl, $t['btn'], false) . '</div>'
          . '<p style="font-size:15px;line-height:1.6;margin:0 0 20px">' . lnc_e($t['bye']) . '<br><b>La Negra Entertainment</b><br>'
          . '<span style="color:#8A8C88;font-size:13px">Feel the Rhythm. Live the Experience.</span></p>';

    $footer = lnc_e($t['foot']) . '<br>'
            . '<a href="' . LNC_SITE . '" style="color:#8A8C88">lanegra-entertainment.nl</a> &middot; '
            . '<a href="mailto:' . LNC_MAIL_TO . '" style="color:#8A8C88">' . LNC_MAIL_TO . '</a> &middot; '
            . '<a href="https://www.instagram.com/lanegraentertainment/" style="color:#8A8C88">Instagram</a>';

    $text = $t['hi'] . ' ' . $voornaam . ",\n\n" . $t['title'] . "\n" . $t['intro'] . "\n\n";
    foreach ($t['labels'] as $i => $label) if ($waarden[$i] !== '') $text .= $label . ': ' . $waarden[$i] . "\n";
    $text .= "\n" . $t['next'] . "\n";
    foreach ($t['steps'] as $i => $stap) $text .= ($i + 1) . '. ' . $stap . "\n";
    $text .= "\n" . $t['offerte_txt'] . "\n" . LNC_SITE . "/offerte-rider.html\n";
    $text .= "\n" . $t['q'] . ' ' . LNC_PHONE . ".\n\n" . $t['bye'] . "\nLa Negra Entertainment\n" . LNC_SITE . "\n";

    lnc_mail($f['E-mail'], $t['subject'], lnc_mail_layout($t['title'], $body, $footer), $text, LNC_MAIL_TO);
}

// ---------- CSV-backup ----------
$csv = LNC_DATA_DIR . '/leads.csv';
$new = !is_file($csv);
if ($fh = @fopen($csv, 'a')) {
    if (flock($fh, LOCK_EX)) {
        if ($new) fputcsv($fh, array_merge(['Tijd'], array_keys($f), ['Bron', 'Medium', 'Campagne', 'Referrer', 'Landing', 'Pagina']));
        $rij = array_merge([date('Y-m-d H:i')], array_values($f), [
            $srcTxt['utm_source'] ?? '', $srcTxt['utm_medium'] ?? '', $srcTxt['utm_campaign'] ?? '',
            $srcTxt['referrer'] ?? '', $srcTxt['landing'] ?? '', $page,
        ]);
        // Bescherming tegen Excel-formules (=, +, -, @ aan het begin)
        $rij = array_map(function ($v) { return ($v !== '' && strpbrk($v[0], '=+-@') !== false) ? "'" . $v : $v; }, array_map('strval', $rij));
        fputcsv($fh, $rij);
        flock($fh, LOCK_UN);
    }
    fclose($fh);
}

lnc_out($sent ? ['ok' => true] : ['ok' => false, 'error' => 'Versturen lukte niet. Mail ons via ' . LNC_MAIL_TO . '.'], $sent ? 200 : 500);
