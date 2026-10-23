<?php
// Buiten gebruik: vervangen door revolut-webhook.php (zie _boeking.php).
// Dit bestand is bewust leeg gemaakt, zodat er geen Mollie-sleutel of open mailscript meer op de server staat.
http_response_code(410);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => false, 'error' => 'gone']);
