<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

function lnc_out(array $d, int $code = 200): void {
    http_response_code($code);
    echo json_encode($d, JSON_UNESCAPED_UNICODE);
    exit;
}

function lnc_guard(): array {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') lnc_out(['ok' => false, 'error' => 'Method not allowed'], 405);

    // Alleen eigen domein
    $origin = $_SERVER['HTTP_ORIGIN'] ?? ($_SERVER['HTTP_REFERER'] ?? '');
    $host = $origin ? parse_url($origin, PHP_URL_HOST) : '';
    if (!$host || !in_array(strtolower($host), LNC_ALLOWED_HOSTS, true)) {
        lnc_out(['ok' => false, 'error' => 'Niet toegestaan'], 403);
    }

    $raw = file_get_contents('php://input', false, null, 0, 65536);
    $data = json_decode($raw ?: '', true);
    // Fallback: gewone formulier-post (bijv. als JavaScript niet laadt)
    if (!is_array($data) && !empty($_POST)) $data = $_POST;
    if (!is_array($data)) lnc_out(['ok' => false, 'error' => 'Ongeldige aanvraag'], 400);
    return $data;
}

function lnc_ip(): string {
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/** Eenvoudige rate limit op basis van bestand per IP. */
function lnc_rate(string $bucket, int $max, int $window = 3600): bool {
    $dir = LNC_DATA_DIR . '/rate';
    if (!is_dir($dir)) @mkdir($dir, 0750, true);
    $f = $dir . '/' . $bucket . '_' . hash('sha256', lnc_ip()) . '.json';
    $now = time();
    $hits = is_file($f) ? (json_decode((string)@file_get_contents($f), true) ?: []) : [];
    $hits = array_values(array_filter($hits, function ($t) use ($now, $window) { return $t > $now - $window; }));
    if (count($hits) >= $max) return false;
    $hits[] = $now;
    @file_put_contents($f, json_encode($hits), LOCK_EX);
    return true;
}

function lnc_clean($v, int $max = 300): string {
    $v = is_scalar($v) ? (string)$v : '';
    $v = trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $v));
    return mb_substr($v, 0, $max);
}

function lnc_protect_dir(): void {
    if (!is_dir(LNC_DATA_DIR)) @mkdir(LNC_DATA_DIR, 0750, true);
    $ht = LNC_DATA_DIR . '/.htaccess';
    if (!is_file($ht)) @file_put_contents($ht, "Require all denied\nDeny from all\n");
}
