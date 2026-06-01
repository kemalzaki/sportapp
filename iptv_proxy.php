<?php
/**
 * IPTV Proxy – Revisi 1 Jun 2026
 * Memutar stream HLS (.m3u8 / .ts / .aac / .key) lewat server agar:
 *  - Tidak terkena mixed-content (HTTP di halaman HTTPS)
 *  - Bypass CORS (banyak server IPTV tidak kirim Access-Control-Allow-Origin)
 *  - Mobile browser (Android Chrome, iOS Safari) bisa memainkan stream
 *
 * Penggunaan: /iptv_proxy.php?u=<base64url(URL_ASLI)>
 * Manifest .m3u8 akan di-rewrite agar setiap segmen juga lewat proxy ini.
 */

@set_time_limit(0);
ignore_user_abort(true);

function b64url_decode($s){
  $s = strtr($s, '-_', '+/');
  $pad = strlen($s) % 4; if ($pad) $s .= str_repeat('=', 4-$pad);
  return base64_decode($s);
}
function b64url_encode($s){
  return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
}

$u = isset($_GET['u']) ? b64url_decode($_GET['u']) : '';
if (!$u || !preg_match('#^https?://#i', $u)) {
  http_response_code(400);
  header('Content-Type: text/plain; charset=utf-8');
  echo "Bad request"; exit;
}

// Whitelist sederhana: hanya host yang berakhir di domain umum IPTV / streaming.
// (Opsional, bisa dihapus jika ingin terbuka. Untuk lokal aman.)
$host = parse_url($u, PHP_URL_HOST) ?: '';
// Tidak ada batasan host untuk fleksibilitas; cukup pastikan bukan IP internal.
if (preg_match('/^(127\.|10\.|192\.168\.|169\.254\.|0\.)/', $host) || $host === 'localhost') {
  http_response_code(403); echo "Blocked"; exit;
}

$range = $_SERVER['HTTP_RANGE'] ?? '';
$ua    = 'Mozilla/5.0 (Linux; Android 12) AppleWebKit/537.36 SportApp-IPTV/1.0';

$ch = curl_init($u);
curl_setopt_array($ch, [
  CURLOPT_FOLLOWLOCATION => true,
  CURLOPT_MAXREDIRS      => 5,
  CURLOPT_CONNECTTIMEOUT => 8,
  CURLOPT_TIMEOUT        => 25,
  CURLOPT_USERAGENT      => $ua,
  CURLOPT_SSL_VERIFYPEER => false,
  CURLOPT_SSL_VERIFYHOST => 0,
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_HEADER         => true,
  CURLOPT_HTTPHEADER     => array_filter([
    'Accept: */*',
    'Referer: '.((parse_url($u, PHP_URL_SCHEME) ?: 'https').'://'.$host.'/'),
    $range ? "Range: $range" : null,
  ]),
]);
$resp = curl_exec($ch);
if ($resp === false) {
  http_response_code(502);
  header('Content-Type: text/plain');
  echo "Upstream error: ".curl_error($ch); exit;
}
$status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$hsize    = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$ctype    = curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: 'application/octet-stream';
$finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL) ?: $u;
curl_close($ch);

$rawHead = substr($resp, 0, $hsize);
$body    = substr($resp, $hsize);

http_response_code($status);
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Range, Origin, Accept');
header('Access-Control-Expose-Headers: Content-Length, Content-Range, Accept-Ranges');

// Teruskan header penting upstream
foreach (explode("\r\n", $rawHead) as $line) {
  if (stripos($line, 'Content-Length:')   === 0 ||
      stripos($line, 'Content-Range:')    === 0 ||
      stripos($line, 'Accept-Ranges:')    === 0 ||
      stripos($line, 'Cache-Control:')    === 0) {
    header($line);
  }
}

$isManifest = (stripos($ctype, 'mpegurl') !== false) ||
              preg_match('/\.m3u8(\?|$)/i', $finalUrl);

if ($isManifest) {
  header('Content-Type: application/vnd.apple.mpegurl');

  // Rewrite setiap URI di manifest agar lewat proxy
  $base = preg_replace('#/[^/]*$#', '/', $finalUrl);
  $self = $_SERVER['SCRIPT_NAME'];

  $rewrite = function($u) use ($base, $self) {
    $u = trim($u);
    if ($u === '' || $u[0] === '#') return $u;
    if (!preg_match('#^https?://#i', $u)) {
      // URL relatif → jadikan absolut
      if ($u[0] === '/') {
        $p = parse_url($base);
        $u = $p['scheme'].'://'.$p['host'].(isset($p['port'])?':'.$p['port']:'').$u;
      } else {
        $u = $base . $u;
      }
    }
    return $self.'?u='.b64url_encode($u);
  };

  $out = [];
  foreach (preg_split("/\r\n|\n|\r/", $body) as $line) {
    if ($line === '' || $line[0] !== '#') {
      $out[] = $rewrite($line);
    } else {
      // Rewrite URI="..." di dalam tag (EXT-X-KEY, EXT-X-MEDIA, EXT-X-MAP, dll)
      $line = preg_replace_callback('/URI="([^"]+)"/', function($m) use ($rewrite){
        return 'URI="'.$rewrite($m[1]).'"';
      }, $line);
      $out[] = $line;
    }
  }
  echo implode("\n", $out);
} else {
  header('Content-Type: '.$ctype);
  echo $body;
}
