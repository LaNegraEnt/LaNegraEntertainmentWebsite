<?php
// Gedeelde mail-opmaak voor de chat, het aanvraagformulier en de boekingsflow (offerte, akkoord, aanbetaling).
// Vereist LNC_MAIL_FROM / LNC_MAIL_FROM_NAME uit config.php.

const LNC_SITE = 'https://lanegra-entertainment.nl';
const LNC_PHONE = '+31 6 25 17 80 50';
// Het logo gaat als bijlage in de mail mee (cid:), want Outlook blokkeert afbeeldingen van externe websites
const LNC_LOGO_FILE = __DIR__ . '/../assets/img/logo.png';
const LNC_LOGO_CID = 'logo@lanegra-entertainment.nl';

function lnc_e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/** Gemeenschappelijke omlijsting: donkere kop met logo, witte kaart, voettekst. */
function lnc_mail_layout(string $preheader, string $body, string $footer): string {
    return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>'
        . '<body style="margin:0;padding:0;background:#F4F1EA">'
        . '<div style="display:none;max-height:0;overflow:hidden;opacity:0">' . lnc_e($preheader) . '</div>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F4F1EA"><tr><td align="center" style="padding:24px 12px">'
        . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:100%;max-width:600px;background:#FFFFFF;border-radius:16px;overflow:hidden;font-family:Arial,Helvetica,sans-serif;color:#1F231E">'
        . '<tr><td align="center" style="background:#1F231E;padding:22px 24px 18px">'
        . '<img src="' . (is_readable(LNC_LOGO_FILE) ? 'cid:' . LNC_LOGO_CID : LNC_SITE . '/assets/img/logo.png') . '" width="200" alt="La Negra Entertainment" style="display:block;width:200px;max-width:70%;height:auto;border:0;color:#FDD22C;font-size:22px;font-weight:bold">'
        . '</td></tr>'
        . '<tr><td style="height:5px;background:#FDD22C;font-size:0;line-height:0">&nbsp;</td></tr>'
        . '<tr><td style="padding:28px 28px 8px">' . $body . '</td></tr>'
        . '<tr><td style="padding:18px 28px 26px;font-size:12px;line-height:1.6;color:#8A8C88;border-top:1px solid #EEEAE0">' . $footer . '</td></tr>'
        . '</table></td></tr></table></body></html>';
}

/** Blok met een kopje en label/waarde-regels; lege waarden worden overgeslagen. Waarden zijn al ge-escaped HTML. */
function lnc_mail_card(string $title, array $rows): string {
    $html = '';
    foreach ($rows as $label => $value) {
        if ($value === '') continue;
        $html .= '<tr><td style="padding:7px 0;width:38%;font-size:13px;color:#8A8C88;vertical-align:top">' . lnc_e($label) . '</td>'
               . '<td style="padding:7px 0;font-size:15px;font-weight:bold;color:#1F231E;vertical-align:top">' . $value . '</td></tr>';
    }
    if ($html === '') return '';
    return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#FBF8F1;border-left:4px solid #FDD22C;border-radius:10px;margin:0 0 18px">'
         . '<tr><td style="padding:16px 18px 10px">'
         . '<div style="font-size:12px;font-weight:bold;letter-spacing:.12em;text-transform:uppercase;color:#D74B27;margin-bottom:6px">' . lnc_e($title) . '</div>'
         . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0">' . $html . '</table>'
         . '</td></tr></table>';
}

function lnc_mail_button(string $href, string $label, bool $primary): string {
    $style = $primary ? 'background:#FDD22C;color:#1F231E;border:2px solid #FDD22C' : 'background:#FFFFFF;color:#1F231E;border:2px solid #1F231E';
    return '<a href="' . lnc_e($href) . '" style="display:inline-block;margin:0 8px 8px 0;padding:11px 18px;border-radius:999px;font-size:14px;font-weight:bold;text-decoration:none;' . $style . '">' . lnc_e($label) . '</a>';
}

/** Verstuurt een HTML-mail met platte-tekstversie (beter voor spamfilters) en het logo ingesloten. */
// Meldingen aan jezelf komen van een apart adres, anders tonen mailapps ze als "Note to Self"
const LNC_MAIL_WEBSITE = 'website@lanegra-entertainment.nl';

/** $vanNaam gezet = melding aan jezelf: afzender wordt "$vanNaam" <website@…> in plaats van info@. */
function lnc_mail(string $to, string $subject, string $html, string $text, string $replyTo = '', string $vanNaam = ''): bool {
    $alt = 'lnc_alt_' . bin2hex(random_bytes(8));
    $alternative = '--' . $alt . "\r\n"
          . "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
          . chunk_split(base64_encode($text)) . "\r\n"
          . '--' . $alt . "\r\n"
          . "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
          . chunk_split(base64_encode($html)) . "\r\n"
          . '--' . $alt . "--\r\n";

    $logo = is_readable(LNC_LOGO_FILE) ? file_get_contents(LNC_LOGO_FILE) : false;
    if ($logo !== false) {
        // multipart/related: tekst+HTML, met het logo als ingesloten afbeelding
        $rel = 'lnc_rel_' . bin2hex(random_bytes(8));
        $contentType = 'multipart/related; type="multipart/alternative"; boundary="' . $rel . '"';
        $body = '--' . $rel . "\r\n"
              . 'Content-Type: multipart/alternative; boundary="' . $alt . '"' . "\r\n\r\n"
              . $alternative . "\r\n"
              . '--' . $rel . "\r\n"
              . "Content-Type: image/png; name=\"logo.png\"\r\nContent-Transfer-Encoding: base64\r\n"
              . 'Content-ID: <' . LNC_LOGO_CID . ">\r\nContent-Disposition: inline; filename=\"logo.png\"\r\n\r\n"
              . chunk_split(base64_encode($logo)) . "\r\n"
              . '--' . $rel . "--\r\n";
    } else {
        $contentType = 'multipart/alternative; boundary="' . $alt . '"';
        $body = $alternative;
    }

    $from = $vanNaam !== ''
        ? '=?UTF-8?B?' . base64_encode($vanNaam) . '?= <' . LNC_MAIL_WEBSITE . '>'
        : '=?UTF-8?B?' . base64_encode(LNC_MAIL_FROM_NAME) . '?= <' . LNC_MAIL_FROM . '>';
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: ' . $contentType,
        'From: ' . $from,
    ];
    if ($replyTo) $headers[] = 'Reply-To: ' . $replyTo;
    return @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, implode("\r\n", $headers), '-f' . LNC_MAIL_FROM);
}

// ---------- Spamfilter (gedeeld door chat, aanvraagformulier en offerte) ----------

/** Geeft een reden terug als de tekst op spam lijkt, anders ''. */
function lnc_spam_reden(array $velden): string {
    $tekst = implode("\n", array_map('strval', $velden));
    if (preg_match_all('#https?://|www\.|\[url|<a\s#i', $tekst) >= 2) return 'links';
    if (preg_match('/[\p{Cyrillic}\p{Han}\p{Hangul}\p{Thai}\p{Arabic}]/u', $tekst)) return 'schrift';
    if (preg_match('/\b(seo|backlinks?|crypto|bitcoin|casino|viagra|cialis|porn|forex|loan|ranking|google (first|top) page|web ?design services?|guest post|whatsapp marketing|lead generation)\b/i', $tekst)) return 'woorden';
    $naam = (string)($velden['naam'] ?? '');
    if ($naam !== '' && preg_match('#https?://|www\.|\.(com|ru|xyz|top)\b|\d{5,}#i', $naam)) return 'naam';
    return '';
}

/** Bewaart afgewezen spam in een afgeschermd logbestand, zodat een echte klant nooit stilletjes verdwijnt. */
function lnc_spam_log(string $bron, string $reden, array $velden): void {
    $dir = defined('LNC_DATA_DIR') ? LNC_DATA_DIR : __DIR__ . '/data';
    if (!is_dir($dir)) @mkdir($dir, 0750, true);
    if (!is_file($dir . '/.htaccess')) @file_put_contents($dir . '/.htaccess', "Require all denied\nDeny from all\n");
    if ($fh = @fopen($dir . '/spam-log.csv', 'a')) {
        fputcsv($fh, [date('Y-m-d H:i'), $bron, $reden, $_SERVER['REMOTE_ADDR'] ?? '', json_encode($velden, JSON_UNESCAPED_UNICODE)]);
        fclose($fh);
    }
}

/** Komt het verzoek van de eigen website? (Browsers sturen bij POST altijd Origin of Referer mee.) */
function lnc_van_eigen_site(): bool {
    $bron = $_SERVER['HTTP_ORIGIN'] ?? ($_SERVER['HTTP_REFERER'] ?? '');
    $host = strtolower((string)parse_url($bron, PHP_URL_HOST));
    return in_array($host, ['lanegra-entertainment.nl', 'www.lanegra-entertainment.nl'], true);
}
