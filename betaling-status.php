<?php
// Geeft bedankt.html de status van een aanbetaling (alleen de status, geen persoonsgegevens).
// Vraagt de status zelf op bij Revolut, zodat de bevestiging ook verstuurd wordt als de webhook (nog) niet binnen is.
require __DIR__ . '/_boeking.php';

$orderId = (string)($_GET['order_id'] ?? '');
if (!preg_match('/^LN-\d{8}-[A-F0-9]{8}$/', $orderId)) bk_out(['ok' => false], 400);

try {
    $o = bk_load_order($orderId);
    if (!$o) bk_out(['ok' => false], 404);
    if (($o['status'] ?? '') !== 'paid') $o = bk_verwerk_betaling($o);
    else $o = bk_agenda_bijwerken($o);   // eerder mislukte agenda-update opnieuw proberen
    bk_out(['ok' => true, 'status' => $o['status'] === 'paid' ? 'paid' : ($o['status'] === 'failed' ? 'failed' : 'open'),
            'agenda' => empty($o['agenda_event']) ? 'geen' : (!empty($o['agenda_bijgewerkt']) ? 'bijgewerkt' : 'fout')]);
} catch (Throwable $e) {
    error_log('betaling-status.php: ' . $e->getMessage());
    bk_out(['ok' => false], 500);
}
