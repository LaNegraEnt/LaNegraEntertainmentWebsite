<?php
// Aangeroepen door opdrachtbevestiging-akkoord.html zodra de klant akkoord geeft:
// slaat de order op, maakt de Revolut-betaling van € 50 aan, mailt jou en de klant het akkoord
// en geeft de Revolut-betaalpagina terug.
require __DIR__ . '/_boeking.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') bk_out(['ok' => false, 'message' => 'Method not allowed'], 405);
if (!lnc_van_eigen_site()) bk_out(['ok' => false, 'message' => 'Niet toegestaan'], 403);
if (!bk_rate('betaling', 10)) bk_out(['ok' => false, 'message' => 'Te veel pogingen. Probeer het over een uur opnieuw of mail ons via ' . LNC_MAIL_TO . '.'], 429);

$o = [];
foreach (BK_LINK_FIELDS as $k) $o[$k] = bk_clean($_POST[$k] ?? '', in_array($k, ['opmerking', 'extras'], true) ? 2000 : 300);

if ($o['naam'] === '-' || !filter_var($o['email'], FILTER_VALIDATE_EMAIL) || $o['totaal'] === '-') {
    bk_out(['ok' => false, 'message' => 'Naam, e-mailadres of totaalbedrag ontbreekt'], 422);
}

// Klopt de link nog met de verstuurde offerte? Oude links (zonder sig) worden geaccepteerd maar gemarkeerd.
$sig = (string)($_POST['sig'] ?? '');
$verified = $sig !== '' && $sig !== '-' && hash_equals(bk_sign($o), $sig);

// Zelfde offerte opnieuw (betaling afgebroken)? Dan dezelfde order gebruiken en geen nieuwe akkoordmails sturen.
$herhaling = false;
try {
    $pointer = $verified ? bk_orders_dir() . '/sig-' . preg_replace('/[^a-f0-9]/', '', $sig) . '.txt' : '';
    $vorige = $pointer && is_file($pointer) ? bk_load_order(trim((string)file_get_contents($pointer))) : null;
} catch (Throwable $e) {
    error_log('betaling-aanmaken-live.php: ' . $e->getMessage());
    bk_out(['ok' => false, 'message' => 'Betaling aanmaken mislukt'], 500);
}
if ($vorige) {
    if (($vorige['status'] ?? '') === 'paid') bk_out(['ok' => false, 'message' => 'De aanbetaling voor deze boeking is al ontvangen'], 409);
    $o = $vorige;
    $herhaling = true;
} else {
    $o += [
        'order_id'     => 'LN-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4))),
        'aanbetaling'  => bk_euro(BK_AANBETALING),
        'restbetaling' => bk_euro(max(bk_euro_to_float($o['totaal']) - BK_AANBETALING, 0)),
        'link_ok'      => $verified,
        'status'       => 'akkoord',
        'created_at'   => date('c'),
    ];
}
$orderId = $o['order_id'];

try {
    // Herhaalpoging: nog openstaande Revolut-betaling hergebruiken
    if ($herhaling && !empty($o['revolut_order_id'])) {
        $o = bk_verwerk_betaling($o);
        if ($o['status'] === 'paid') bk_out(['ok' => false, 'message' => 'De aanbetaling voor deze boeking is al ontvangen'], 409);
        if (in_array($o['revolut_state'] ?? '', ['pending', 'processing'], true) && !empty($o['checkout_url'])) {
            bk_out(['ok' => true, 'order_id' => $orderId, 'checkout_url' => $o['checkout_url']]);
        }
    }
    $order = bk_revolut('POST', 'api/orders', [
        'amount'              => (int)round(BK_AANBETALING * 100),   // in centen
        'currency'            => 'EUR',
        'description'         => 'Aanbetaling La Negra Entertainment ' . $orderId,
        'redirect_url'        => LNC_SITE . '/bedankt.html?order_id=' . rawurlencode($orderId),
        'merchant_order_data' => ['reference' => $orderId],
        'customer'            => ['email' => $o['email']],
    ]);
    if (empty($order['id']) || empty($order['checkout_url'])) throw new RuntimeException('Revolut gaf geen order-id of betaallink terug');
    $o['revolut_order_id'] = $order['id'];
    $o['revolut_state']    = strtolower((string)($order['state'] ?? 'pending'));
    $o['checkout_url']     = $order['checkout_url'];
    $o['status']           = 'payment_created';
    bk_save_order($o);
    if ($pointer && !$herhaling) file_put_contents($pointer, $orderId);
    bk_revolut_webhook_aanmelden();
} catch (Throwable $e) {
    error_log('betaling-aanmaken-live.php: ' . $e->getMessage());
    $geenSleutel = strpos($e->getMessage(), 'REVOLUT_SECRET_KEY') === 0;
    bk_out(['ok' => false, 'message' => $geenSleutel ? 'Betalen is tijdelijk niet mogelijk (Revolut nog niet ingesteld)' : 'Betaling aanmaken mislukt'], 500);
}

// ---------- Akkoordmails (mislukt mailen houdt de betaling niet tegen) ----------
if ($herhaling) bk_out(['ok' => true, 'order_id' => $orderId, 'checkout_url' => $o['checkout_url']]);

$datum = bk_datum($o['datum']);
$akkoordLink = bk_akkoord_link($o);

$waarschuwing = $verified ? '' :
    '<p style="font-size:14px;line-height:1.6;margin:0 0 18px;padding:12px 14px;background:#FDECEA;border-left:4px solid #D74B27">'
    . '<b>Let op:</b> deze link komt niet overeen met een offerte van de website (oude link of aangepaste bedragen). Controleer de bedragen voordat je bevestigt.</p>';
$body = '<div style="font-size:26px;font-weight:bold;line-height:1.25;margin:0 0 4px">Akkoord ontvangen</div>'
      . '<div style="font-size:14px;color:#8A8C88;margin:0 0 20px">Order ' . lnc_e($orderId) . ' &middot; ' . date('d-m-Y H:i') . '</div>'
      . '<p style="font-size:15px;line-height:1.6;margin:0 0 18px">' . lnc_e($o['naam']) . ' heeft akkoord gegeven op de opdrachtbevestiging en is doorgestuurd naar de aanbetaling van ' . lnc_e($o['aanbetaling']) . '. Je krijgt opnieuw bericht zodra de betaling binnen is.</p>'
      . $waarschuwing . bk_contact_card($o) . bk_boeking_card($o, 'De boeking') . bk_bedragen_card($o, true)
      . '<div style="margin:0 0 20px">' . lnc_mail_button(bk_agenda_link($o), 'Zet in agenda', true) . '</div>';
lnc_mail(LNC_MAIL_TO, 'Akkoord ontvangen: ' . $o['naam'] . ' – ' . $datum,
    lnc_mail_layout('Akkoord van ' . $o['naam'], $body, 'Order ' . lnc_e($orderId) . ($verified ? '' : ' &middot; link niet geverifieerd')),
    "Akkoord ontvangen van {$o['naam']} ({$o['email']}) voor $datum.\nPakket: {$o['pakket']}\nTotaal: {$o['totaal']}\nOrder: $orderId\n", $o['email']);

$body = '<div style="font-size:15px;color:#8A8C88;margin:0 0 4px">Hoi ' . lnc_e(bk_voornaam($o['naam'])) . ',</div>'
      . '<div style="font-size:26px;font-weight:bold;line-height:1.25;margin:0 0 10px">We hebben je akkoord ontvangen</div>'
      . '<p style="font-size:15px;line-height:1.6;margin:0 0 20px">Bedankt voor je vertrouwen in La Negra Entertainment! Met de aanbetaling van <b>' . lnc_e($o['aanbetaling']) . '</b> leg je je datum definitief vast. Zodra die binnen is, krijg je van ons de definitieve bevestiging.</p>'
      . bk_boeking_card($o, 'Je boeking') . bk_bedragen_card($o, true)
      . '<p style="font-size:15px;line-height:1.6;margin:0 0 12px">Is de betaling niet gelukt of heb je het venster gesloten? Via de knop hieronder kun je de aanbetaling opnieuw starten.</p>'
      . '<div style="margin:0 0 20px">' . lnc_mail_button($akkoordLink, 'Aanbetaling doen', true) . '</div>'
      . '<p style="font-size:15px;line-height:1.6;margin:0 0 20px">Muzikale groet,<br><b>La Negra Entertainment</b></p>';
lnc_mail($o['email'], 'We hebben je akkoord ontvangen – La Negra Entertainment',
    lnc_mail_layout('We hebben je akkoord ontvangen', $body, bk_footer_klant()),
    "Hoi " . bk_voornaam($o['naam']) . ",\n\nWe hebben je akkoord ontvangen. Met de aanbetaling van {$o['aanbetaling']} leg je je datum definitief vast.\n"
    . "Betaling niet gelukt? Start hem opnieuw via: $akkoordLink\n\nMuzikale groet,\nLa Negra Entertainment\n", LNC_MAIL_TO);

bk_out(['ok' => true, 'order_id' => $orderId, 'checkout_url' => $o['checkout_url']]);
