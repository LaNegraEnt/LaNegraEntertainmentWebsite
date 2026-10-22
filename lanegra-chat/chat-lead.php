<?php
// Ontvangt een aanvraag uit de chat, mailt hem naar jou, stuurt de klant een bevestiging en bewaart een CSV-backup.
require_once __DIR__ . '/_common.php';

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

$src = is_array($in['source'] ?? null) ? $in['source'] : [];
$srcTxt = [];
foreach (['utm_source', 'utm_medium', 'utm_campaign', 'gclid', 'fbclid', 'referrer', 'landing'] as $k) {
    if (!empty($src[$k])) $srcTxt[$k] = lnc_clean($src[$k], 200);
}
$page = lnc_clean($in['page'] ?? '', 300);

// Gesprek (alleen voor jouw mail)
$transcript = '';
foreach (array_slice(is_array($in['transcript'] ?? null) ? $in['transcript'] : [], -20) as $m) {
    $who = ($m['role'] ?? '') === 'user' ? 'Klant' : 'Bot';
    $transcript .= '<p style="margin:4px 0"><b>' . $who . ':</b> ' . nl2br(htmlspecialchars(lnc_clean($m['content'] ?? '', 800))) . '</p>';
}

// ---------- Mail naar jou ----------
$e = fn($s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
$rows = '';
foreach ($f as $k => $v) {
    if ($v === '') continue;
    $rows .= '<tr><td style="padding:6px 12px;color:#777777;white-space:nowrap">' . $e($k) . '</td><td style="padding:6px 12px"><b>' . nl2br($e($v)) . '</b></td></tr>';
}
$srcRows = '';
foreach ($srcTxt as $k => $v) $srcRows .= '<tr><td style="padding:3px 12px;color:#777777">' . $e($k) . '</td><td style="padding:3px 12px">' . $e($v) . '</td></tr>';

$subject = 'Nieuwe aanvraag: ' . implode(' – ', array_filter([$f['Type event'], $f['Datum'], $f['Naam']], 'strlen'));
$html = '<div style="font-family:Arial,sans-serif;max-width:640px">'
      . '<div style="background:#1F231E;color:#FDD22C;padding:16px 20px;font-size:18px;font-weight:bold">Nieuwe aanvraag via de website-chat</div>'
      . '<table style="border-collapse:collapse;width:100%;font-size:14px">' . $rows . '</table>'
      . ($srcRows ? '<h4 style="margin:18px 12px 4px">Herkomst</h4><table style="font-size:12px">' . $srcRows . '<tr><td style="padding:3px 12px;color:#777777">pagina</td><td style="padding:3px 12px">' . $e($page) . '</td></tr></table>' : '')
      . ($transcript ? '<h4 style="margin:18px 12px 4px">Gesprek</h4><div style="font-size:13px;padding:0 12px;color:#333">' . $transcript . '</div>' : '')
      . '</div>';

function lnc_mail(string $to, string $subject, string $html, string $replyTo = ''): bool {
    $from = '=?UTF-8?B?' . base64_encode(LNC_MAIL_FROM_NAME) . '?= <' . LNC_MAIL_FROM . '>';
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . $from,
    ];
    if ($replyTo) $headers[] = 'Reply-To: ' . $replyTo;
    return @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $html, implode("\r\n", $headers), '-f' . LNC_MAIL_FROM);
}

$sent = lnc_mail(LNC_MAIL_TO, $subject, $html, $f['E-mail']);

// ---------- Bevestiging naar klant ----------
if (LNC_SEND_AUTOREPLY) {
    $lang = strtolower($f['Taal']);
    $T = [
        'nl' => ['Hoi', 'Bedankt voor je aanvraag! We hebben het volgende ontvangen:', 'in', 'gasten',
                 'We checken de beschikbaarheid en sturen je zo snel mogelijk een offerte op maat. Vragen in de tussentijd? Reageer gewoon op deze mail of bel +31 6 25 17 80 50.',
                 'Muzikale groet,', 'We hebben je aanvraag ontvangen – La Negra Entertainment'],
        'en' => ['Hi', 'Thank you for your request! We have received the following:', 'in', 'guests',
                 'We will check availability and send you a tailor-made quote as soon as possible. Any questions in the meantime? Simply reply to this email or call +31 6 25 17 80 50.',
                 'Musical greetings,', 'We have received your request – La Negra Entertainment'],
        'es' => ['Hola', '¡Gracias por tu solicitud! Hemos recibido lo siguiente:', 'en', 'invitados',
                 'Comprobaremos la disponibilidad y te enviaremos un presupuesto a medida lo antes posible. ¿Alguna pregunta mientras tanto? Responde a este correo o llama al +31 6 25 17 80 50.',
                 'Un saludo musical,', 'Hemos recibido tu solicitud – La Negra Entertainment'],
    ][$lang] ?? null;
    if (!$T) { $T = ['Hoi', 'Bedankt voor je aanvraag! We hebben het volgende ontvangen:', 'in', 'gasten', 'We nemen snel contact met je op.', 'Muzikale groet,', 'We hebben je aanvraag ontvangen – La Negra Entertainment']; }
    $voornaam = explode(' ', $f['Naam'])[0];
    // Alleen ingevulde gegevens tonen (geen lege "·" of "in" regels)
    $regels = array_filter([
        implode(' &middot; ', array_map($e, array_filter([$f['Type event'], $f['Act']], 'strlen'))),
        trim($e($f['Datum']) . ($f['Datum'] !== '' && $f['Plaats'] !== '' ? ' ' . $T[2] . ' ' : '') . $e($f['Plaats'])),
        $f['Gasten'] !== '' ? $e($f['Gasten']) . ' ' . $T[3] : '',
        $f['Opmerking'] !== '' ? nl2br($e($f['Opmerking'])) : '',
    ], 'strlen');
    $conf = '<div style="font-family:Arial,sans-serif;max-width:600px;color:#1f231e">'
          . '<div style="background:#1f231e;color:#fdd22c;padding:18px 22px;font-size:20px;font-weight:bold">La Negra Entertainment</div>'
          . '<div style="padding:20px 22px;font-size:15px;line-height:1.55">'
          . '<p>' . $T[0] . ' ' . $e($voornaam) . ',</p>'
          . '<p>' . $T[1] . '</p>'
          . '<p style="background:#fff6d2;padding:12px 16px;border-left:4px solid #fdd22c">'
          . implode('<br>', $regels) . '</p>'
          . '<p>' . $T[4] . '</p>'
          . '<p>' . $T[5] . '<br><b>La Negra Entertainment</b></p></div></div>';
    lnc_mail($f['E-mail'], $T[6], $conf, LNC_MAIL_TO);
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
        $rij = array_map(fn($v) => ($v !== '' && strpbrk($v[0], '=+-@') !== false) ? "'" . $v : $v, array_map('strval', $rij));
        fputcsv($fh, $rij);
        flock($fh, LOCK_UN);
    }
    fclose($fh);
}

lnc_out($sent ? ['ok' => true] : ['ok' => false, 'error' => 'Versturen lukte niet. Mail ons via ' . LNC_MAIL_TO . '.'], $sent ? 200 : 500);
