<?php
// Mollie roept dit aan bij elke statuswijziging van een betaling (alleen met het betaling-id).
// Bij "paid" krijgen klant en jij de definitieve bevestiging – precies één keer per order.
require __DIR__ . '/_boeking.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }

$paymentId = preg_replace('/[^A-Za-z0-9_]/', '', (string)($_POST['id'] ?? ''));
if ($paymentId === '') { http_response_code(400); exit; }

try {
    $payment = bk_mollie('GET', 'payments/' . $paymentId);
    $o = bk_load_order((string)($payment['metadata']['order_id'] ?? ''));
    if (!$o) { http_response_code(200); exit; }   // onbekende order: niets te doen

    $o['mollie_status'] = $payment['status'];
    $o['updated_at']    = date('c');

    if ($payment['status'] === 'paid') {
        $o['status']     = 'paid';
        $o['payment_id'] = $payment['id'];
        $o['paid_at']    = $o['paid_at'] ?? date('c');
    } elseif (($o['status'] ?? '') !== 'paid' && in_array($payment['status'], ['failed', 'canceled', 'expired'], true)) {
        $o['status'] = $payment['status'];
    }
    bk_save_order($o);

    if ($o['status'] === 'paid' && empty($o['bevestiging_verstuurd'])) {
        $datum = bk_datum($o['datum']);

        // Klant: sjabloon definitieve-opdrachtbevestiging-mail.html
        $html = bk_template('definitieve-opdrachtbevestiging-mail.html', [
            'AANHEF' => 'Hoi ' . bk_voornaam($o['naam']) . ',', 'NAAM' => $o['naam'], 'DATUM' => $datum, 'LOCATIE' => $o['locatie'],
            'PAKKET' => $o['pakket'], 'REISKOSTEN' => $o['reiskosten'], 'TOTAAL' => $o['totaal'],
            'AANBETALING' => $o['aanbetaling'], 'RESTBETALING' => $o['restbetaling'],
        ]) ?? lnc_mail_layout('Je boeking is bevestigd', bk_boeking_card($o, 'Je boeking') . bk_bedragen_card($o, true), bk_footer_klant());
        $klantOk = lnc_mail($o['email'], 'Je boeking is definitief bevestigd – La Negra Entertainment', $html,
            "Hoi " . bk_voornaam($o['naam']) . ",\n\nWe hebben je aanbetaling van {$o['aanbetaling']} ontvangen. Je boeking voor $datum is definitief vastgelegd.\n"
            . "Pakket: {$o['pakket']}\nLocatie: {$o['locatie']}\nTotaal incl. btw: {$o['totaal']}\nRestbetaling: {$o['restbetaling']}\n\nMuzikale groet,\nLa Negra Entertainment\n", LNC_MAIL_TO);

        // Jij
        $body = '<div style="font-size:26px;font-weight:bold;line-height:1.25;margin:0 0 4px">Aanbetaling ontvangen</div>'
              . '<div style="font-size:14px;color:#8A8C88;margin:0 0 20px">Order ' . lnc_e($o['order_id']) . ' &middot; Mollie ' . lnc_e($o['payment_id']) . '</div>'
              . '<p style="font-size:15px;line-height:1.6;margin:0 0 18px">De boeking van ' . lnc_e($o['naam']) . ' is definitief. '
              . ($klantOk ? 'De klant heeft de definitieve bevestiging per mail ontvangen.' : '<b>Let op:</b> de bevestigingsmail naar de klant kon niet worden verstuurd.') . '</p>'
              . bk_contact_card($o) . bk_boeking_card($o, 'De boeking') . bk_bedragen_card($o, true)
              . '<div style="margin:0 0 20px">' . lnc_mail_button(bk_agenda_link($o), 'Zet in agenda', true) . '</div>';
        lnc_mail(LNC_MAIL_TO, 'Aanbetaling ontvangen: ' . $o['naam'] . ' – ' . $datum,
            lnc_mail_layout('Aanbetaling ontvangen van ' . $o['naam'], $body, 'Order ' . lnc_e($o['order_id'])),
            "Aanbetaling van {$o['aanbetaling']} ontvangen van {$o['naam']} ({$o['email']}) voor $datum.\nOrder: {$o['order_id']}\n", $o['email']);

        $o['bevestiging_verstuurd'] = date('c');
        bk_save_order($o);
    }

    http_response_code(200);
} catch (Throwable $e) {
    error_log('mollie-webhook-live.php: ' . $e->getMessage());
    http_response_code(500);   // Mollie probeert het later opnieuw
}
