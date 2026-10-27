<?php
// Kleine Google Calendar-koppeling via een service-account (geen extra bibliotheken nodig).
// Alleen de agenda LNA_CALENDAR_ID ("La Negra optredens") wordt gelezen en beschreven, nooit je privé-agenda.
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) { http_response_code(404); exit; }
require_once __DIR__ . '/config.php';

function lna_data_dir(): string {
    if (!is_dir(LNA_DATA_DIR)) @mkdir(LNA_DATA_DIR, 0750, true);
    $ht = LNA_DATA_DIR . '/.htaccess';
    if (!is_file($ht)) @file_put_contents($ht, "Require all denied\nDeny from all\n");
    return LNA_DATA_DIR;
}

function lna_b64url(string $s): string { return rtrim(strtr(base64_encode($s), '+/', '-_'), '='); }

function lna_http(string $method, string $url, array $headers = [], ?string $body = null): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => $headers]);
    if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    $res = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch); curl_close($ch);
    if ($res === false) throw new RuntimeException('Verbinding met Google mislukt: ' . $err);
    return [$code, $res === '' ? [] : (json_decode($res, true) ?? [])];
}

function lna_token(): string {
    if (defined('LNA_TEST_TOKEN')) return LNA_TEST_TOKEN;
    $cache = lna_data_dir() . '/token.json';
    if (is_file($cache)) {
        $t = json_decode((string)file_get_contents($cache), true);
        if (!empty($t['access_token']) && ($t['exp'] ?? 0) > time() + 60) return $t['access_token'];
    }
    if (defined('LNA_SERVICE_JSON_B64')) {
        $sa = json_decode((string)base64_decode(LNA_SERVICE_JSON_B64), true);   // uit de GitHub-secret
    } elseif (is_file(LNA_SERVICE_JSON)) {
        $sa = json_decode((string)file_get_contents(LNA_SERVICE_JSON), true);
    } else {
        throw new RuntimeException('Google-sleutel ontbreekt (secret GOOGLE_SERVICE_ACCOUNT_JSON).');
    }
    if (empty($sa['client_email']) || empty($sa['private_key'])) throw new RuntimeException('Google-sleutel is ongeldig.');
    $now = time();
    $head = lna_b64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
    $claim = lna_b64url(json_encode(['iss' => $sa['client_email'], 'scope' => 'https://www.googleapis.com/auth/calendar',
        'aud' => $sa['token_uri'] ?? 'https://oauth2.googleapis.com/token', 'iat' => $now, 'exp' => $now + 3600]));
    $sig = '';
    if (!openssl_sign("$head.$claim", $sig, $sa['private_key'], 'sha256WithRSAEncryption')) throw new RuntimeException('Ondertekenen mislukt.');
    [$code, $d] = lna_http('POST', $sa['token_uri'] ?? 'https://oauth2.googleapis.com/token', ['Content-Type: application/x-www-form-urlencoded'],
        http_build_query(['grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => "$head.$claim." . lna_b64url($sig)]));
    if ($code !== 200 || empty($d['access_token'])) throw new RuntimeException('Google gaf geen toegang (controleer de sleutel).');
    @file_put_contents($cache, json_encode(['access_token' => $d['access_token'], 'exp' => $now + (int)($d['expires_in'] ?? 3600)]), LOCK_EX);
    return $d['access_token'];
}

function lna_api(string $method, string $path, array $query = [], ?array $body = null): array {
    $url = (defined('LNA_API_BASE') ? LNA_API_BASE : 'https://www.googleapis.com/calendar/v3') . '/calendars/' . rawurlencode(LNA_CALENDAR_ID) . $path . ($query ? '?' . http_build_query($query) : '');
    [$code, $d] = lna_http($method, $url, ['Authorization: Bearer ' . lna_token(), 'Content-Type: application/json'], $body === null ? null : json_encode($body));
    if ($code >= 400) throw new RuntimeException('Google Agenda-fout ' . $code . ': ' . ($d['error']['message'] ?? ''));
    return $d;
}

/** Alle afspraken tussen twee momenten; verlopen opties worden direct opgeruimd. */
function lna_events(DateTimeInterface $from, DateTimeInterface $to): array {
    $out = []; $page = null;
    do {
        $q = ['timeMin' => $from->format(DATE_RFC3339), 'timeMax' => $to->format(DATE_RFC3339), 'singleEvents' => 'true',
              'orderBy' => 'startTime', 'maxResults' => 250, 'timeZone' => LNA_TIMEZONE];
        if ($page) $q['pageToken'] = $page;
        $d = lna_api('GET', '/events', $q);
        foreach ($d['items'] ?? [] as $e) {
            $p = $e['extendedProperties']['private'] ?? [];
            if (($p['lnStatus'] ?? '') === 'optie' && (int)($p['lnExpires'] ?? 0) > 0 && (int)$p['lnExpires'] < time()
                && stripos(ltrim($e['summary'] ?? ''), 'OPTIE') === 0) {   // haal 'OPTIE' uit de titel om hem te houden
                try { lna_api('DELETE', '/events/' . rawurlencode($e['id'])); } catch (Throwable $t) {}
                continue;
            }
            if (($e['transparency'] ?? '') === 'transparent' || ($e['status'] ?? '') === 'cancelled') continue;
            $out[] = $e;
        }
        $page = $d['nextPageToken'] ?? null;
    } while ($page);
    return $out;
}

/** Na ontvangen aanbetaling: de afspraak van "OPTIE – wacht op aanbetaling" naar "GEBOEKT" (groen, verloopt niet meer). */
function lna_bevestig_boeking(string $eventId, string $orderId): void {
    $ev = lna_api('GET', '/events/' . rawurlencode($eventId));
    $titel = preg_replace('/^OPTIE[^–]*–\s*(wacht op aanbetaling\s*–\s*)?/u', '', (string)($ev['summary'] ?? ''));
    lna_api('PATCH', '/events/' . rawurlencode($eventId), [], [
        'summary'     => 'GEBOEKT – ' . $titel,
        'colorId'     => '10',
        'description' => trim(($ev['description'] ?? '') . "\n\nAanbetaling ontvangen – order $orderId."),
        'extendedProperties' => ['private' => ['lnStatus' => 'boeking', 'lnExpires' => '0', 'lnOrder' => $orderId]],
    ]);
}
