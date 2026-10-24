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
$teSnel   = (int)($in['t'] ?? 0) < 8;   // chat en formulier sturen altijd mee hoe lang de bezoeker bezig was
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

require_once __DIR__ . '/_mail.php';
$spam = lnc_spam_reden(['naam' => $f['Naam'], 'plaats' => $f['Plaats'], 'opmerking' => $f['Opmerking']]);
if ($spam !== '') { lnc_spam_log('chat/formulier', $spam, $f); lnc_out(['ok' => true]); }   // stil negeren

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

require_once __DIR__ . '/_mail.php';

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
$sent = lnc_mail(LNC_MAIL_TO, $subject, $html, $text, $f['E-mail'], 'Nieuwe aanvraag via website');

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
            'steps' => ['Stel via de knop hieronder je offerte samen: kies je act en extra\'s en je ziet direct het totaalbedrag.', 'Je ontvangt je opdrachtbevestiging per mail. Klopt alles? Geef akkoord en betaal de aanbetaling van € 50,-.', 'Je datum staat definitief vast. Daarna: ¡fiesta!'],
            'q' => 'Iets anders in gedachten, of staat jouw wens er niet tussen? Dan maken we graag een offerte op maat. Mail naar', 'q_of' => 'of bel',
            'bye' => 'Muzikale groet,',
            'btn' => 'Bekijk onze acts',
            'offerte' => 'Stel je offerte samen',
            'foot' => 'Je ontvangt deze mail omdat je een aanvraag deed op lanegra-entertainment.nl.',
        ],
        'en' => [
            'subject' => 'We have received your request – La Negra Entertainment',
            'hi' => 'Hi', 'title' => 'Thank you for your request!',
            'intro' => 'Your request has reached us. Below is a summary of what you sent us.',
            'card' => 'Your request',
            'labels' => ['Type of event', 'Act', 'Date', 'Location', 'Guests', 'Message'],
            'next' => 'What happens next?',
            'steps' => ['Build your quote using the button below: choose your act and extras and see the total straight away.', 'You will receive your booking confirmation by email. All correct? Give your approval and pay the € 50 deposit.', 'Your date is confirmed. Then: ¡fiesta!'],
            'q' => 'Have something else in mind, or is what you want not listed? We are happy to make a custom quote. Email', 'q_of' => 'or call',
            'bye' => 'Musical greetings,',
            'btn' => 'View our acts',
            'offerte' => 'Build your quote',
            'foot' => 'You are receiving this email because you sent a request via lanegra-entertainment.nl.',
        ],
        'es' => [
            'subject' => 'Hemos recibido tu solicitud – La Negra Entertainment',
            'hi' => 'Hola', 'title' => '¡Gracias por tu solicitud!',
            'intro' => 'Tu solicitud nos ha llegado correctamente. Aquí tienes un resumen de lo que nos enviaste.',
            'card' => 'Tu solicitud',
            'labels' => ['Tipo de evento', 'Acto', 'Fecha', 'Lugar', 'Invitados', 'Mensaje'],
            'next' => '¿Qué pasa ahora?',
            'steps' => ['Crea tu presupuesto con el botón de abajo: elige tu acto y extras y verás el total al instante.', 'Recibirás la confirmación de tu reserva por correo. ¿Todo correcto? Da tu aprobación y paga el anticipo de 50 €.', 'Tu fecha queda confirmada. Y luego: ¡fiesta!'],
            'q' => '¿Tienes otra idea o no encuentras lo que buscas? Te hacemos un presupuesto a medida. Escribe a', 'q_of' => 'o llama al',
            'bye' => 'Un saludo musical,',
            'btn' => 'Ver nuestros actos',
            'offerte' => 'Crea tu presupuesto',
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
          . '<div style="margin:0 0 20px">' . lnc_mail_button(LNC_SITE . '/offerte-rider.html', $t['offerte'], true) . lnc_mail_button($actsUrl, $t['btn'], false) . '</div>'
          . '<p style="font-size:15px;line-height:1.6;margin:0 0 18px;padding:12px 14px;background:#FBF8F1;border-radius:10px">' . lnc_e($t['q'])
          . ' <a href="mailto:' . LNC_MAIL_TO . '" style="color:#D74B27;font-weight:bold;text-decoration:none">' . LNC_MAIL_TO . '</a> ' . lnc_e($t['q_of'])
          . ' <a href="tel:+31625178050" style="color:#D74B27;font-weight:bold;text-decoration:none">' . LNC_PHONE . '</a>.</p>'
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
    $text .= "\n" . $t['offerte'] . ': ' . LNC_SITE . "/offerte-rider.html\n";
    $text .= "\n" . $t['q'] . ' ' . LNC_MAIL_TO . ' ' . $t['q_of'] . ' ' . LNC_PHONE . ".\n\n" . $t['bye'] . "\nLa Negra Entertainment\n" . LNC_SITE . "\n";

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
