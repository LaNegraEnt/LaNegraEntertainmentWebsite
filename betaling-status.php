<?php
// Geeft bedankt.html de status van een aanbetaling (alleen de status, geen persoonsgegevens).
require __DIR__ . '/_boeking.php';

$orderId = (string)($_GET['order_id'] ?? '');
if (!preg_match('/^LN-\d{8}-[A-F0-9]{8}$/', $orderId)) bk_out(['ok' => false], 400);

try {
    $o = bk_load_order($orderId);
    if (!$o) bk_out(['ok' => false], 404);
    // Webhook nog niet binnen? Dan zelf even bij Mollie navragen.
    if (($o['status'] ?? '') !== 'paid' && !empty($o['payment_id'])) {
        $status = bk_mollie('GET', 'payments/' . $o['payment_id'])['status'] ?? '';
        if ($status === 'paid') $o['status'] = 'paid';
        elseif (in_array($status, ['failed', 'canceled', 'expired'], true)) $o['status'] = $status;
    }
    bk_out(['ok' => true, 'status' => $o['status'] === 'paid' ? 'paid' : (in_array($o['status'], ['failed', 'canceled', 'expired'], true) ? 'failed' : 'open')]);
} catch (Throwable $e) {
    error_log('betaling-status.php: ' . $e->getMessage());
    bk_out(['ok' => false], 500);
}
