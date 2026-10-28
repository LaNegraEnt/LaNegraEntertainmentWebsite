<?php
// Ontvangt de samengestelde offerte van offerte-rider.html (assets/js/offerte.js),
// mailt jou en de klant, en geeft de persoonlijke akkoord-link terug.
require __DIR__ . '/_boeking.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: offerte-rider.html');
    exit;
}

$p = $_POST;
$lang = bk_lang((string)($p['lang'] ?? ''));

// Spam: niet vanaf de eigen site, lokveld ingevuld, of binnen 15 seconden verstuurd → stil negeren
if (!lnc_van_eigen_site() || !empty($p['website']) || (int)($p['t'] ?? 0) < 15) {
    lnc_spam_log('offerte', !lnc_van_eigen_site() ? 'herkomst' : (!empty($p['website']) ? 'lokveld' : 'te snel'), $p);
    bk_out(['ok' => true]);
}
if (!bk_rate('offerte', 6)) bk_out(['ok' => false, 'message' => bk_t($lang, 'Te veel pogingen. Probeer het over een uur opnieuw of mail ons via ', 'Too many attempts. Please try again in an hour or email us at ') . LNC_MAIL_TO . '.'], 429);

// Extra acts: offerte.js stuurt "Ja (+€…)" / "Yes (+€…)" of "Nee" / "No" per act, en de workshop als "Salsa, Percussie / 1.5 uur (+€…)"
$extras = [];
$labels = $lang === 'en'
    ? ['vuurspuwer' => 'Fire breather / Limbo show', 'steelband' => 'Steel band', 'latin_entertainment_band' => 'Latin Entertainment Band', 'dj' => 'DJ']
    : ['vuurspuwer' => 'Vuurspuwer / Limboshow', 'steelband' => 'Steelband', 'latin_entertainment_band' => 'Latin Entertainment Band', 'dj' => 'DJ'];
foreach ($labels as $k => $label) {
    if (preg_match('/^(Ja|Yes)/i', (string)($p[$k] ?? ''))) $extras[] = $label;
}
$workshop = bk_clean($p['workshop'] ?? '', 200);
if (!bk_leeg($workshop)) $extras[] = 'Workshop ' . trim(preg_replace('#\s*/.*$#', '', $workshop));

$o = [
    'naam'        => bk_clean($p['naam'] ?? '', 160),
    'email'       => bk_clean($p['email'] ?? '', 190),
    'tel'         => bk_clean($p['telefoon'] ?? '', 80),
    'datum'       => bk_clean($p['datum_evenement'] ?? '', 120),
    'locatie'     => bk_clean($p['locatie'] ?? '', 200),
    'pakket'      => bk_clean($p['pakket'] ?? '', 300),
    'danseressen' => bk_clean($p['danseressen'] ?? '', 120),
    'extras'      => $extras ? implode(' + ', $extras) : bk_t($lang, 'Geen', 'None'),
    'photobooth'  => bk_clean($p['photobooth'] ?? '', 120),
    'opmerking'   => bk_clean($p['opmerkingen'] ?? '', 2000),
    'reiskosten'  => bk_clean($p['reiskosten'] ?? '', 80),
    'subtotaal'   => bk_clean($p['subtotaal_excl_btw'] ?? '', 80),
    'btw'         => bk_clean($p['btw_9pct'] ?? '', 80),
    'totaal'      => bk_clean($p['totaal_incl_btw'] ?? '', 80),
    'lang'        => $lang,
];

if ($o['naam'] === '-' || !filter_var($o['email'], FILTER_VALIDATE_EMAIL) || $o['totaal'] === '-') {
    bk_out(['ok' => false, 'error' => bk_t($lang, 'Naam, e-mailadres of totaalbedrag ontbreekt.', 'Name, email address or total is missing.')], 422);
}

$spam = lnc_spam_reden(['naam' => $o['naam'], 'locatie' => $o['locatie'], 'opmerking' => $o['opmerking']]);
if ($spam !== '') { lnc_spam_log('offerte', $spam, $o); bk_out(['ok' => true]); }

$link = bk_akkoord_link($o);
$datum = bk_datum($o['datum']);

// ---------- Mail naar jou ----------
$body = '<div style="font-size:26px;font-weight:bold;line-height:1.25;margin:0 0 4px">Nieuwe offerte samengesteld</div>'
      . '<div style="font-size:14px;color:#8A8C88;margin:0 0 20px">Via de offertepagina &middot; ' . date('d-m-Y H:i') . '</div>'
      . '<p style="font-size:15px;line-height:1.6;margin:0 0 18px">De klant heeft de link naar de opdrachtbevestiging per mail ontvangen. Je krijgt opnieuw bericht zodra de klant akkoord geeft.'
      . ($lang === 'en' ? ' <b>Engelstalige klant</b> (offerte via de Engelse pagina).' : '') . '</p>'
      . bk_contact_card($o) . bk_boeking_card($o, 'De boeking') . bk_bedragen_card($o, false)
      . '<div style="margin:0 0 20px">' . lnc_mail_button($link, 'Bekijk de opdrachtbevestiging', true) . '</div>';
$text = "Nieuwe offerte via de offertepagina\n\n";
foreach ($o as $k => $v) $text .= ucfirst($k) . ": $v\n";
$text .= "\nOpdrachtbevestiging: $link\n";

$sent = lnc_mail(LNC_MAIL_TO, 'Nieuwe offerte: ' . $o['naam'] . ' – ' . $datum,
    lnc_mail_layout('Nieuwe offerte van ' . $o['naam'], $body, 'Beantwoord deze mail om direct de klant te mailen.'), $text, $o['email'], 'Nieuwe offerte via website');

// ---------- Mail naar de klant (sjabloon opdracht-mail.html / opdracht-mail-en.html) ----------
$vars = [
    'AANHEF' => bk_t($lang, 'Hoi ', 'Hi ') . bk_voornaam($o['naam']) . ',', 'NAAM' => $o['naam'], 'DATUM' => $datum, 'LOCATIE' => $o['locatie'],
    'PAKKET' => $o['pakket'], 'REISKOSTEN' => $o['reiskosten'], 'TOTAAL' => $o['totaal'], 'OPDRACHT_LINK' => $link,
];
$html = bk_template(bk_t($lang, 'opdracht-mail.html', 'opdracht-mail-en.html'), $vars) ?? lnc_mail_layout(bk_t($lang, 'Je opdrachtbevestiging staat klaar', 'Your booking confirmation is ready'),
    '<p>' . bk_t($lang, 'Hoi ', 'Hi ') . lnc_e(bk_voornaam($o['naam'])) . ',</p><p>' . bk_t($lang, 'Je opdrachtbevestiging staat klaar.', 'Your booking confirmation is ready.') . '</p>'
    . lnc_mail_button($link, bk_t($lang, 'Bekijk & bevestig je boeking', 'View & confirm your booking'), true), bk_footer_klant($lang));
$text = $lang === 'en'
    ? "Hi " . bk_voornaam($o['naam']) . ",\n\nThank you for your request to La Negra Entertainment. Your booking confirmation is ready.\n"
      . "Check your booking, give your approval and secure your date with the deposit of " . bk_euro(BK_AANBETALING) . ":\n$link\n\n"
      . "Package: {$o['pakket']}\nDate: $datum\nVenue: {$o['locatie']}\nTotal incl. VAT: {$o['totaal']}\n\nMusical greetings,\nLa Negra Entertainment\n"
    : "Hoi " . bk_voornaam($o['naam']) . ",\n\nBedankt voor je aanvraag bij La Negra Entertainment. Je opdrachtbevestiging staat klaar.\n"
      . "Controleer je boeking, geef akkoord en leg je datum vast met de aanbetaling van " . bk_euro(BK_AANBETALING) . ":\n$link\n\n"
      . "Pakket: {$o['pakket']}\nDatum: $datum\nLocatie: {$o['locatie']}\nTotaal incl. btw: {$o['totaal']}\n\nMuzikale groet,\nLa Negra Entertainment\n";
lnc_mail($o['email'], bk_t($lang, 'Je opdrachtbevestiging staat klaar – La Negra Entertainment', 'Your booking confirmation is ready – La Negra Entertainment'), $html, $text, LNC_MAIL_TO);

bk_out($sent ? ['ok' => true] : ['ok' => false, 'error' => bk_t($lang, 'Versturen lukte niet. Mail ons via ', 'Sending failed. Please email us at ') . LNC_MAIL_TO . '.'], $sent ? 200 : 500);
