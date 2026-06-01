<?php
/**
 * IPTV Proxy – Revisi 4 Jun 2026
 * Memutar stream HLS (.m3u8 / .ts / .aac / .key) lewat server agar:
 *  - Tidak terkena mixed-content
 *  - Bypass CORS
 *  - Mobile browser (Android Chrome, iOS Safari) bisa memainkan stream
 *
 * FIX MOBILE (4 Jun 2026):
 *  - Saat manifest .m3u8 di-rewrite, JANGAN forward Content-Length / Accept-Ranges
 *    /Content-Range upstream — body sudah berubah panjangnya. Browser sebelumnya
 *    memotong manifest karena panjang lama tidak cocok → playlist parse error
 *    di Android Chrome (hls.js) sehingga video tidak pernah jalan.
 *  - Header CORS yang konsisten + OPTIONS handler agar preflight tidak gagal.
 *  - HEAD request balikan 200 cepat (beberapa player Android cek HEAD dulu).
 *  - User-Agent realistik supaya CDN streaming tidak menolak.
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

// CORS untuk semua method
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, HEAD, OPTIONS');
header('Access-Control-Allow-Headers: Range, Origin, Accept, Content-Type');
header('Access-Control-Expose-Headers: Content-Length, Content-Range, Accept-Ranges, Content-Type');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
  http_response_code(204); exit;
}

$u = isset($_GET['u']) ? b64url_decode($_GET['u']) : '';
if (!$u || !preg_match('#^https?://#i', $u)) {
  http_response_code(400);
  header('Content-Type: text/plain; charset=utf-8');
  echo "Bad request"; exit;
}

$host = parse_url($u, PHP_URL_HOST) ?: '';
if (preg_match('/^(127\.|10\.|192\.168\.|169\.254\.|0\.)/', $host) || $host === 'localhost') {
  http_response_code(403); echo "Blocked"; exit;
}

$range  = $_SERVER['HTTP_RANGE'] ?? '';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
// UA realistik (Chrome Android stabil) – CDN streaming kadang tolak UA generik
$ua = 'Mozilla/5.0 (Linux; Android 12; Mobile) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Mobile Safari/537.36';

$ch = curl_init($u);
curl_setopt_array($ch, [
  CURLOPT_NOBODY         => ($method === 'HEAD'),
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
    'Accept-Encoding: identity',
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
$body    = ($method === 'HEAD') ? '' : substr($resp, $hsize);

http_response_code($status);

$isManifest = (stripos($ctype, 'mpegurl') !== false) ||
              preg_match('/\.m3u8(\?|$)/i', $finalUrl);

if ($isManifest) {
  header('Content-Type: application/vnd.apple.mpegurl');
  // JANGAN forward Content-Length/Accept-Ranges/Content-Range untuk manifest yang akan kita rewrite!
  // (body berubah panjang -> Android Chrome akan memotong playlist & gagal play)
  header('Cache-Control: no-store, max-age=0');

  if ($method === 'HEAD') { exit; }

  $base = preg_replace('#/[^/]*$#', '/', $finalUrl);
  $self = $_SERVER['SCRIPT_NAME'];

  $rewrite = function($u) use ($base, $self) {
    $u = trim($u);
    if ($u === '' || $u[0] === '#') return $u;
    if (!preg_match('#^https?://#i', $u)) {
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
      $line = preg_replace_callback('/URI="([^"]+)"/', function($m) use ($rewrite){
        return 'URI="'.$rewrite($m[1]).'"';
      }, $line);
      $out[] = $line;
    }
  }
  $rewritten = implode("\n", $out);
  header('Content-Length: '.strlen($rewritten));
  echo $rewritten;
} else {
  // Segment (ts/aac/key/dll) — teruskan header penting upstream
  foreach (explode("\r\n", $rawHead) as $line) {
    if (stripos($line, 'Content-Length:')   === 0 ||
        stripos($line, 'Content-Range:')    === 0 ||
        stripos($line, 'Accept-Ranges:')    === 0 ||
        stripos($line, 'Cache-Control:')    === 0) {
      header($line);
    }
  }
  header('Content-Type: '.$ctype);
  if ($method === 'HEAD') { exit; }
  echo $body;
}
