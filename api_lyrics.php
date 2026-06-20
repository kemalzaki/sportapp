<?php
/**
 * api_lyrics.php — Revisi 20 Juni 2026
 *
 * Pencarian lirik cepat untuk flyover.php (Subtitle Karaoke).
 * Sebelumnya halaman memanggil lyrics.ovh langsung dari browser (kadang
 * lambat / lirik tidak ditemukan / kena CORS). Endpoint ini:
 *   1) Memanggil lrclib.net (sumber utama, banyak lirik & sering punya
 *      timestamp LRC) dan lyrics.ovh secara PARALEL via curl_multi.
 *   2) Memilih hasil terbaik (lrclib > lyrics.ovh) dengan timeout pendek.
 *   3) Mengembalikan JSON: { ok, lyrics, lrc, source }.
 *
 * Pakai cache sesi PHP 1 jam supaya pencarian berulang instan.
 *
 * Pemakaian:  GET /api_lyrics.php?artist=Coldplay&title=Yellow
 */
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require_login();
header('Content-Type: application/json; charset=utf-8');

$u = current_user(); $uid = (int)$u['id'];
if (function_exists('rate_limit_or_die')) {
    rate_limit_or_die('lyrics:'.$uid, 90, 300);
}

$artist = trim((string)($_GET['artist'] ?? ''));
$title  = trim((string)($_GET['title']  ?? ''));
if ($title === '' && $artist === '') {
    echo json_encode(['ok'=>false,'err'=>'artist atau title wajib diisi']); exit;
}
if (mb_strlen($artist) > 120) $artist = mb_substr($artist, 0, 120);
if (mb_strlen($title)  > 200) $title  = mb_substr($title,  0, 200);

// Cache singkat di session supaya pencarian sama tidak diulang.
$cacheKey = 'lyr_'.md5(strtolower($artist.'|'.$title));
if (!empty($_SESSION[$cacheKey]) && (time() - ($_SESSION[$cacheKey]['_t'] ?? 0)) < 3600) {
    echo json_encode($_SESSION[$cacheKey]['data']); exit;
}

function _curl_easy($url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5,   // lebih cepat dari lyrics.ovh default
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT      => 'SportApp/1.0 (+lyrics-proxy)',
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    ]);
    if (getenv('GEMINI_INSECURE_SSL') === '1') {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    }
    return $ch;
}

// Build kandidat URL — paralel.
$urls = [];
$urls['lrclib'] = 'https://lrclib.net/api/get?'.http_build_query([
    'artist_name' => $artist,
    'track_name'  => $title,
]);
if ($artist !== '' && $title !== '') {
    $urls['lyovh'] = 'https://api.lyrics.ovh/v1/'.rawurlencode($artist).'/'.rawurlencode($title);
}
$urls['lrclib_search'] = 'https://lrclib.net/api/search?'.http_build_query([
    'q' => trim($artist.' '.$title),
]);

$mh = curl_multi_init();
$handles = [];
foreach ($urls as $k => $u) {
    $h = _curl_easy($u);
    curl_multi_add_handle($mh, $h);
    $handles[$k] = $h;
}
$active = null;
do {
    $status = curl_multi_exec($mh, $active);
    if ($active) curl_multi_select($mh, 0.5);
} while ($active && $status === CURLM_OK);

$results = [];
foreach ($handles as $k => $h) {
    $body = curl_multi_getcontent($h);
    $code = (int)curl_getinfo($h, CURLINFO_HTTP_CODE);
    $results[$k] = ['code'=>$code, 'body'=>$body];
    curl_multi_remove_handle($mh, $h);
    curl_close($h);
}
curl_multi_close($mh);

$out = ['ok'=>false, 'lyrics'=>'', 'lrc'=>'', 'source'=>''];

// 1) lrclib.net (paling cepat & sering ada LRC bertimestamp)
$j1 = ($results['lrclib']['code'] === 200) ? json_decode($results['lrclib']['body'], true) : null;
if (is_array($j1)) {
    $lrc = trim((string)($j1['syncedLyrics'] ?? ''));
    $pln = trim((string)($j1['plainLyrics']  ?? ''));
    if ($lrc !== '' || $pln !== '') {
        $out = ['ok'=>true, 'lyrics'=>$pln ?: $lrc, 'lrc'=>$lrc, 'source'=>'lrclib.net'];
    }
}

// 2) fallback ke lyrics.ovh (plain lyrics only)
if (!$out['ok'] && isset($results['lyovh'])) {
    $j2 = ($results['lyovh']['code'] === 200) ? json_decode($results['lyovh']['body'], true) : null;
    if (is_array($j2) && !empty($j2['lyrics']) && trim($j2['lyrics']) !== '') {
        $out = ['ok'=>true, 'lyrics'=>trim($j2['lyrics']), 'lrc'=>'', 'source'=>'lyrics.ovh'];
    }
}

// 3) fallback ke lrclib search (ambil hasil pertama yang punya lirik)
if (!$out['ok']) {
    $j3 = ($results['lrclib_search']['code'] === 200) ? json_decode($results['lrclib_search']['body'], true) : null;
    if (is_array($j3) && !empty($j3[0])) {
        foreach ($j3 as $item) {
            $lrc = trim((string)($item['syncedLyrics'] ?? ''));
            $pln = trim((string)($item['plainLyrics']  ?? ''));
            if ($lrc !== '' || $pln !== '') {
                $out = ['ok'=>true, 'lyrics'=>$pln ?: $lrc, 'lrc'=>$lrc, 'source'=>'lrclib.net (search)'];
                break;
            }
        }
    }
}

if (!$out['ok']) {
    $out = ['ok'=>false, 'err'=>'lirik tidak ditemukan di lrclib.net / lyrics.ovh',
            'lyrics'=>'', 'lrc'=>'', 'source'=>''];
}

$_SESSION[$cacheKey] = ['_t'=>time(), 'data'=>$out];
echo json_encode($out);
