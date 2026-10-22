<?php
// Geeft de chat- en formulier-leads terug aan leads-overzicht.html, alleen met de juiste sleutel (LNC_ADMIN_KEY in config.php).
require_once __DIR__ . '/_common.php';

$in = lnc_guard();
if (!lnc_rate('admin', 20)) lnc_out(['ok' => false, 'error' => 'Te veel pogingen. Probeer het over een uur opnieuw.'], 429);
if (LNC_ADMIN_KEY === '' || strlen(LNC_ADMIN_KEY) < 12) lnc_out(['ok' => false, 'error' => 'Zet eerst een LNC_ADMIN_KEY (minstens 12 tekens) in config.php.'], 403);
if (!hash_equals(LNC_ADMIN_KEY, (string)($in['key'] ?? ''))) lnc_out(['ok' => false, 'error' => 'Onjuiste sleutel.'], 403);

$rows = [];
$csv = LNC_DATA_DIR . '/leads.csv';
if (is_file($csv) && ($fh = fopen($csv, 'r'))) {
    $head = fgetcsv($fh);
    while ($head && ($r = fgetcsv($fh)) !== false) {
        if (count($r) < 2) continue;
        $rows[] = array_combine($head, array_pad(array_slice($r, 0, count($head)), count($head), ''));
    }
    fclose($fh);
}
lnc_out(['ok' => true, 'rows' => $rows]);
