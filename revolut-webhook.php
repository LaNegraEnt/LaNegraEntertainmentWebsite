<?php
// Revolut meldt hier statuswijzigingen van een betaling. De inhoud van de melding wordt niet vertrouwd:
// we nemen alleen het order-id over en vragen de echte status zelf op bij Revolut (bk_verwerk_betaling).
require __DIR__ . '/_boeking.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }

$payload = json_decode((string)file_get_contents('php://input', false, null, 0, 65536), true);
$ref = (string)($payload['merchant_order_ext_ref'] ?? '');
$revolutId = preg_replace('/[^A-Za-z0-9_-]/', '', (string)($payload['order_id'] ?? ''));

try {
    // Onze referentie (LN-…) staat in merchant_order_ext_ref; zo niet, dan de order zoeken op het Revolut-id
    $o = preg_match('/^LN-\d{8}-[A-F0-9]{8}$/', $ref) ? bk_load_order($ref) : null;
    if (!$o && $revolutId !== '') {
        foreach (glob(bk_orders_dir() . '/LN-*.json') ?: [] as $file) {
            $kandidaat = json_decode((string)file_get_contents($file), true);
            if (($kandidaat['revolut_order_id'] ?? '') === $revolutId) { $o = $kandidaat; break; }
        }
    }
    if ($o) bk_verwerk_betaling($o);
    http_response_code(200);
} catch (Throwable $e) {
    error_log('revolut-webhook.php: ' . $e->getMessage());
    http_response_code(500);   // Revolut probeert het later opnieuw
}
