<?php
// Agenda voor klanten: vrije dagen tonen, tijden checken en een optie of boeking in Google Agenda zetten.
require_once __DIR__ . '/_google.php';
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

function out(array $d, int $code = 200): void { http_response_code($code); echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }
function clean($v, int $max = 300): string { $v = is_scalar($v) ? (string)$v : ''; return mb_substr(trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $v)), 0, $max); }
function rate(string $b, int $max, int $win = 3600): bool {
    $dir = lna_data_dir() . '/rate'; if (!is_dir($dir)) @mkdir($dir, 0750, true);
    $f = $dir . '/' . $b . '_' . hash('sha256', $_SERVER['REMOTE_ADDR'] ?? '') . '.json';
    $h = array_values(array_filter(is_file($f) ? (json_decode((string)file_get_contents($f), true) ?: []) : [], fn($t) => $t > time() - $win));
    if (count($h) >= $max) return false; $h[] = time(); @file_put_contents($f, json_encode($h), LOCK_EX); return true;
}

$tz = new DateTimeZone(LNA_TIMEZONE);
if (LNA_CALENDAR_ID === '') out(['ok' => false, 'error' => 'De agenda is nog niet gekoppeld.'], 503);

try {
    $action = $_GET['action'] ?? '';

    // ── Opruimen van verlopen opties (optioneel via cronjob: agenda-api.php?action=opruimen) ──
    if ($action === 'opruimen') {
        $now = new DateTimeImmutable('now', $tz);
        lna_events($now->modify('-1 day'), $now->modify('+' . LNA_MAX_MONTHS_AHEAD . ' months'));
        out(['ok' => true]);
    }

    // ── Maandoverzicht: per dag 'deels' of 'vol' ──
    if ($action === 'month' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        if (!preg_match('/^\d{4}-\d{2}$/', $_GET['ym'] ?? '')) out(['ok' => false, 'error' => 'Ongeldige maand'], 400);
        $from = new DateTimeImmutable($_GET['ym'] . '-01 00:00', $tz); $to = $from->modify('+1 month');
        $days = [];
        foreach (lna_events($from, $to) as $e) {
            if (isset($e['start']['date'])) {           // hele-dag-afspraak = dag vol
                $d = new DateTimeImmutable($e['start']['date'], $tz); $end = new DateTimeImmutable($e['end']['date'], $tz);
                for (; $d < $end; $d = $d->modify('+1 day')) $days[$d->format('Y-m-d')] = 'vol';
            } else {
                $k = (new DateTimeImmutable($e['start']['dateTime']))->setTimezone($tz)->format('Y-m-d');
                if (($days[$k] ?? '') !== 'vol') $days[$k] = 'deels';
            }
        }
        out(['ok' => true, 'days' => $days, 'minDaysAhead' => LNA_MIN_DAYS_AHEAD]);
    }

    // ── Eén dag: welke tijden zijn bezet ──
    if ($action === 'day' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date'] ?? '')) out(['ok' => false, 'error' => 'Ongeldige datum'], 400);
        $from = new DateTimeImmutable($_GET['date'] . ' 00:00', $tz);
        $busy = []; $vol = false;
        foreach (lna_events($from, $from->modify('+1 day +6 hours')) as $e) {
            if (isset($e['start']['date'])) { if ($e['start']['date'] <= $_GET['date']) $vol = true; continue; }
            $s = (new DateTimeImmutable($e['start']['dateTime']))->setTimezone($tz); $en = (new DateTimeImmutable($e['end']['dateTime']))->setTimezone($tz);
            if ($s >= $from->modify('+1 day') ) continue;
            $busy[] = ['start' => $s->format('H:i'), 'end' => $en->format('H:i')];
        }
        out(['ok' => true, 'vol' => $vol, 'busy' => $busy, 'buffer' => LNA_BUFFER_MIN]);
    }

    // ── Reserveren ──
    if ($action === 'reserve' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        require_once __DIR__ . '/../_boeking.php';   // mail-opmaak, spamfilter en ondertekende akkoord-link
        if (!lnc_van_eigen_site()) out(['ok' => false, 'error' => 'Niet toegestaan'], 403);
        $in = json_decode((string)file_get_contents('php://input', false, null, 0, 20000), true);
        if (!is_array($in)) out(['ok' => false, 'error' => 'Ongeldige aanvraag'], 400);
        if (!empty($in['website']) || (int)($in['t'] ?? 0) < 8) {                 // lokveld of te snel: stil negeren
            lnc_spam_log('agenda', !empty($in['website']) ? 'lokveld' : 'te snel', $in);
            out(['ok' => true, 'mode' => 'optie']);
        }
        if (!rate('reserve', 6)) out(['ok' => false, 'error' => 'Te veel aanvragen. Mail ons via ' . LNC_MAIL_TO . '.'], 429);

        $f = ['datum' => clean($in['datum'] ?? '', 10), 'start' => clean($in['start'] ?? '', 5), 'eind' => clean($in['eind'] ?? '', 5),
              'act' => clean($in['act'] ?? '', 80), 'naam' => clean($in['naam'] ?? '', 100), 'email' => clean($in['email'] ?? '', 150),
              'telefoon' => clean($in['telefoon'] ?? '', 40), 'plaats' => clean($in['plaats'] ?? '', 120), 'opmerking' => clean($in['opmerking'] ?? '', 800),
              'keuze' => ($in['keuze'] ?? '') === 'direct' ? 'direct' : 'optie'];
        if ($f['naam'] === '' || !filter_var($f['email'], FILTER_VALIDATE_EMAIL)) out(['ok' => false, 'error' => 'Vul je naam en een geldig e-mailadres in.'], 422);
        $spam = lnc_spam_reden(['naam' => $f['naam'], 'plaats' => $f['plaats'], 'opmerking' => $f['opmerking']]);
        if ($spam !== '') { lnc_spam_log('agenda', $spam, $f); out(['ok' => true, 'mode' => 'optie']); }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['datum']) || !preg_match('/^\d{2}:\d{2}$/', $f['start']) || !preg_match('/^\d{2}:\d{2}$/', $f['eind']))
            out(['ok' => false, 'error' => 'Kies een datum en tijden.'], 422);
        $s = new DateTimeImmutable($f['datum'] . ' ' . $f['start'], $tz); $e = new DateTimeImmutable($f['datum'] . ' ' . $f['eind'], $tz);
        if ($e <= $s) $e = $e->modify('+1 day');                         // eindtijd na middernacht
        if ($s < (new DateTimeImmutable('today', $tz))->modify('+' . LNA_MIN_DAYS_AHEAD . ' days')) out(['ok' => false, 'error' => 'Deze datum is te kort dag om online te reserveren. Bel of app ons even.'], 422);
        if ($s > (new DateTimeImmutable('today', $tz))->modify('+' . LNA_MAX_MONTHS_AHEAD . ' months')) out(['ok' => false, 'error' => 'Zo ver vooruit kun je nog niet online reserveren. Mail of bel ons gerust.'], 422);
        if (($e->getTimestamp() - $s->getTimestamp()) > 12 * 3600) out(['ok' => false, 'error' => 'Kies een tijdsblok van maximaal 12 uur.'], 422);

        // Opnieuw controleren of het nog vrij is (inclusief speling)
        $b = LNA_BUFFER_MIN;
        foreach (lna_events($s->modify("-$b minutes")->modify('-1 day'), $e->modify("+$b minutes")) as $ev) {
            if (isset($ev['start']['date'])) {
                if ($ev['start']['date'] <= $f['datum'] && $ev['end']['date'] > $f['datum']) out(['ok' => false, 'error' => 'Deze dag is helaas volgeboekt.'], 409);
                continue;
            }
            $es = new DateTimeImmutable($ev['start']['dateTime']); $ee = new DateTimeImmutable($ev['end']['dateTime']);
            if ($es < $e->modify("+$b minutes") && $ee > $s->modify("-$b minutes"))
                out(['ok' => false, 'error' => 'Dit tijdstip is net niet meer vrij. Kies een andere tijd; we hebben ' . $b . ' minuten nodig tussen twee optredens.'], 409);
        }

        // Ook een directe boeking is eerst een optie die verloopt als de aanbetaling uitblijft;
        // na betaling maakt de Revolut-webhook er "GEBOEKT" van (lna_bevestig_boeking).
        $isDirect = $f['keuze'] === 'direct';
        $expires = time() + LNA_OPTION_HOURS * 3600;
        $verloopt = (new DateTimeImmutable('@' . $expires))->setTimezone($tz)->format('d-m-Y H:i');
        $title = 'OPTIE ' . LNA_OPTION_HOURS . 'u – ' . ($isDirect ? 'wacht op aanbetaling – ' : '') . $f['act'] . ' – ' . $f['naam'];
        $desc = "Naam: {$f['naam']}\nE-mail: {$f['email']}\nTelefoon: {$f['telefoon']}\nPlaats: {$f['plaats']}\nAct: {$f['act']}\nTijd: {$f['start']}–{$f['eind']}\nOpmerking: {$f['opmerking']}\n\n"
              . ($isDirect ? 'Klant is doorgestuurd naar de aanbetaling. Zonder betaling verloopt deze afspraak op ' : 'Optie verloopt automatisch op ') . $verloopt . '.';
        $ev = lna_api('POST', '/events', [], [
            'summary' => $title, 'description' => $desc, 'location' => $f['plaats'],
            'start' => ['dateTime' => $s->format(DATE_RFC3339), 'timeZone' => LNA_TIMEZONE],
            'end' => ['dateTime' => $e->format(DATE_RFC3339), 'timeZone' => LNA_TIMEZONE],
            'colorId' => '5',
            'extendedProperties' => ['private' => ['lnStatus' => 'optie', 'lnExpires' => (string)$expires]],
        ]);

        // CSV-backup
        if ($fh = @fopen(lna_data_dir() . '/reserveringen.csv', 'a')) {
            fputcsv($fh, [date('Y-m-d H:i'), $f['keuze'], $f['datum'], $f['start'], $f['eind'], $f['act'], $f['naam'], $f['email'], $f['telefoon'], $f['plaats'], $f['opmerking'], $ev['id'] ?? '']);
            fclose($fh);
        }

        // Akkoord-link voor de aanbetaling (zelfde ondertekende flow als de offertepagina) + koppeling met de afspraak
        $dagen = ['zondag','maandag','dinsdag','woensdag','donderdag','vrijdag','zaterdag'];
        $maanden = ['','januari','februari','maart','april','mei','juni','juli','augustus','september','oktober','november','december'];
        $dag = $dagen[(int)$s->format('w')] . ' ' . $s->format('j') . ' ' . $maanden[(int)$s->format('n')] . ' ' . $s->format('Y');
        $o = ['naam' => $f['naam'], 'email' => $f['email'], 'tel' => $f['telefoon'] ?: '-', 'datum' => $s->format('Y-m-d'),
              'locatie' => $f['plaats'] ?: '-', 'pakket' => $f['act'] . ' (' . $f['start'] . '–' . $f['eind'] . ')', 'danseressen' => '-',
              'extras' => 'Geen', 'photobooth' => 'Geen', 'opmerking' => $f['opmerking'] ?: '-', 'reiskosten' => 'Volgens offerte',
              'subtotaal' => 'Volgens offerte', 'btw' => 'Volgens offerte', 'totaal' => 'Volgens offerte'];
        $betaalLink = bk_akkoord_link($o) . '&' . http_build_query(['agenda' => $ev['id'] ?? '', 'asig' => bk_agenda_sig($ev['id'] ?? '')]);

        // Mail naar jou
        $kaart = lnc_mail_card($isDirect ? 'Directe boeking (wacht op aanbetaling)' : 'Optie ' . LNA_OPTION_HOURS . ' uur', [
            'Datum'     => lnc_e($dag),
            'Tijd'      => lnc_e($f['start'] . ' – ' . $f['eind']),
            'Act'       => lnc_e($f['act']),
            'Plaats'    => lnc_e($f['plaats']),
            'Opmerking' => nl2br(lnc_e($f['opmerking'])),
            'Verloopt'  => lnc_e($verloopt),
        ]);
        $body = '<div style="font-size:26px;font-weight:bold;line-height:1.25;margin:0 0 4px">' . ($isDirect ? 'Nieuwe boeking via de agenda' : 'Nieuwe optie via de agenda') . '</div>'
              . '<div style="font-size:14px;color:#8A8C88;margin:0 0 20px">Staat als geel blok in je agenda "La Negra optredens" &middot; ' . date('d-m-Y H:i') . '</div>'
              . bk_contact_card(['naam' => $f['naam'], 'email' => $f['email'], 'tel' => $f['telefoon']]) . $kaart
              . '<p style="font-size:14px;line-height:1.6;margin:0 0 20px;color:#8A8C88">'
              . ($isDirect ? 'Zodra de aanbetaling binnen is, wordt de afspraak automatisch groen ("GEBOEKT").' : 'Wil je de optie houden? Haal "OPTIE" uit de titel in je agenda, dan verloopt hij niet.') . '</p>';
        lnc_mail(LNC_MAIL_TO, ($isDirect ? 'Nieuwe boeking via agenda: ' : 'Nieuwe optie via agenda: ') . $s->format('d-m-Y') . ' – ' . $f['naam'],
            lnc_mail_layout(($isDirect ? 'Boeking' : 'Optie') . ' van ' . $f['naam'], $body, 'Beantwoord deze mail om direct de klant te mailen.'),
            "Nieuwe " . ($isDirect ? 'boeking' : 'optie') . " via de agenda: $dag {$f['start']}–{$f['eind']}\n$desc\n", $f['email'], 'Nieuwe reservering via website');

        // Mail naar de klant
        $voornaam = explode(' ', $f['naam'])[0];
        $klantKaart = lnc_mail_card('Je reservering', ['Datum' => lnc_e($dag), 'Tijd' => lnc_e($f['start'] . ' – ' . $f['eind']), 'Act' => lnc_e($f['act']), 'Plaats' => lnc_e($f['plaats'])]);
        $body = '<div style="font-size:15px;color:#8A8C88;margin:0 0 4px">Hoi ' . lnc_e($voornaam) . ',</div>'
              . '<div style="font-size:26px;font-weight:bold;line-height:1.25;margin:0 0 10px">' . ($isDirect ? 'Je datum is genoteerd' : 'We houden je datum vast') . '</div>'
              . '<p style="font-size:15px;line-height:1.6;margin:0 0 20px">' . ($isDirect
                    ? 'Rond je boeking af met de aanbetaling van € 50,-. Zonder aanbetaling houden we de datum tot ' . lnc_e($verloopt) . ' voor je vast.'
                    : 'We houden deze datum ' . LNA_OPTION_HOURS . ' uur gratis en vrijblijvend voor je vast, tot ' . lnc_e($verloopt) . '. We nemen snel contact met je op. Weet je al wat je wilt? Stel dan alvast je offerte samen.') . '</p>'
              . $klantKaart
              . '<div style="margin:0 0 20px">' . ($isDirect ? lnc_mail_button($betaalLink, 'Aanbetaling doen', true) : lnc_mail_button(LNC_SITE . '/offerte-rider.html', 'Stel je offerte samen', true)) . '</div>'
              . '<p style="font-size:15px;line-height:1.6;margin:0 0 20px">Muzikale groet,<br><b>La Negra Entertainment</b></p>';
        lnc_mail($f['email'], $isDirect ? 'Je datum is genoteerd – La Negra Entertainment' : 'We houden je datum vast – La Negra Entertainment',
            lnc_mail_layout($isDirect ? 'Je datum is genoteerd' : 'We houden je datum vast', $body, bk_footer_klant()),
            "Hoi $voornaam,\n\n" . ($isDirect ? "Rond je boeking af met de aanbetaling: $betaalLink\n" : "We houden $dag {$f['start']}–{$f['eind']} tot $verloopt voor je vast.\n") . "\nMuzikale groet,\nLa Negra Entertainment\n", LNC_MAIL_TO);

        $res = ['ok' => true, 'mode' => $f['keuze'], 'expires' => $verloopt];
        if ($isDirect) $res['redirect'] = $betaalLink;
        out($res);
    }
    out(['ok' => false, 'error' => 'Onbekende actie'], 400);
} catch (Throwable $t) {
    @file_put_contents(lna_data_dir() . '/fouten.log', date('c') . ' ' . $t->getMessage() . "\n", FILE_APPEND | LOCK_EX);
    out(['ok' => false, 'error' => 'De agenda is even niet bereikbaar. Mail of app ons gerust via info@lanegra-entertainment.nl.'], 502);
}
