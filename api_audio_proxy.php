<?php
/**
 * api_audio_proxy.php — Revisi 22 Juni 2026 R7
 *
 * Proxy audio remote (mis. iTunes preview .m4a) ke browser dengan header CORS
 * yang benar, supaya fitur "Trim Audio" di flyover.php bisa decode WebAudio
 * tanpa terkena error "Failed to fetch" / CORS pada audio sumber.
 *
 * Pemakaian: GET /api_audio_proxy.php?u=<URL audio absolut>
 * Pembatasan: hanya host whitelist (mzstatic.com / itunes.apple.com / audio-ssl.itunes.apple.com)
 *             agar tidak dipakai sebagai open-proxy umum.
 */
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require_login();

$u = current_user(); $uid = (int)$u['id'];
if (function_exists('rate_limit_or_die')) rate_limit_or_die('audio_proxy:'.$uid, 120, 300);

$url = (string)($_GET['u'] ?? '');
if ($url === '' || !preg_match('~^https?://~i', $url)) { http_response_code(400); exit('bad url'); }

$host = parse_url($url, PHP_URL_HOST) ?: '';
$allowedHosts = [
    'audio-ssl.itunes.apple.com',
    'itunes.apple.com',
];
$allowedSuffix = ['.mzstatic.com', '.apple.com'];
$ok = in_array(strtolower($host), $allowedHosts, true);
if (!$ok) {
    foreach ($allowedSuffix as $sfx) {
        if (str_ends_with(strtolower($host), $sfx)) { $ok = true; break; }
    }
}
if (!$ok) { http_response_code(403); exit('host tidak diizinkan'); }

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 25,
    CURLOPT_CONNECTTIMEOUT => 8,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_USERAGENT      => 'Mozilla/5.0 SportApp Audio Proxy',
]);
if (getenv('GEMINI_INSECURE_SSL') === '1') {
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
}
$body = curl_exec($ch);
$code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
$ctype= (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
curl_close($ch);

if ($body === false || $code < 200 || $code >= 400) {
    http_response_code(502); echo 'gagal ambil sumber (HTTP '.$code.')'; exit;
}

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Cache-Control: public, max-age=3600');
header('Content-Type: '.($ctype ?: 'audio/mp4'));
header('Content-Length: '.strlen($body));
echo $body;
