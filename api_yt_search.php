<?php
/**
 * Revisi 19 Juni 2026 Part R — Pencarian YouTube tanpa API key.
 * YouTube IFrame "listType=search" sudah dimatikan tahun 2020 sehingga embed
 * pencarian menampilkan "Video Tidak Tersedia". Endpoint ini melakukan
 * scraping ringan ke halaman hasil pencarian publik YouTube lalu mengambil
 * videoId pertama untuk di-embed.
 */
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require_login();
header('Content-Type: application/json');

$u = current_user(); $uid = (int)$u['id'];
rate_limit_or_die('yt_search:'.$uid, 60, 300);

$q = trim((string)($_GET['q'] ?? $_POST['q'] ?? ''));
if ($q === '') { echo json_encode(['ok'=>false,'err'=>'query kosong']); exit; }
if (mb_strlen($q) > 120) $q = mb_substr($q, 0, 120);

/* Revisi 22 Juni 2026 R7 — filter kategori (olahraga|survival).
 * Server menambahkan kata kunci wajib dari tabel search_keywords supaya
 * hasil hanya menampilkan video relevan dengan topik. Jika user mengetik
 * kata kunci yang sudah cocok dengan salah satu kata kunci aktif,
 * pencarian tetap dijalankan apa adanya. Selain itu kata kunci pertama
 * dari kategori disisipkan ke query agar hasil tetap di topik. */
$cat = $_GET['cat'] ?? $_POST['cat'] ?? '';
if (in_array($cat, ['olahraga','survival'], true)) {
    try {
        @db_exec("CREATE TABLE IF NOT EXISTS search_keywords (
            id BIGSERIAL PRIMARY KEY, kategori VARCHAR(20) NOT NULL, kata TEXT NOT NULL,
            aktif BOOLEAN NOT NULL DEFAULT TRUE, urutan INTEGER NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT now())");
        $kws = db_all("SELECT kata FROM search_keywords WHERE kategori=$1 AND aktif=TRUE ORDER BY urutan, id", [$cat]);
        $words = array_map(fn($r)=>mb_strtolower(trim($r['kata'])), $kws);
        $qLower = mb_strtolower($q);
        $hasTopic = false;
        foreach ($words as $w) if ($w!=='' && mb_strpos($qLower, $w)!==false) { $hasTopic = true; break; }
        if (!$hasTopic && !empty($words)) {
            // Sisipkan kata kunci utama (urutan pertama) supaya hasil tetap di topik.
            $q = $words[0].' '.$q;
        }
    } catch (Throwable $e) { /* tidak fatal */ }
}

$url = 'https://www.youtube.com/results?search_query='.rawurlencode($q).'&hl=id&gl=ID';
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0 Safari/537.36',
    CURLOPT_HTTPHEADER => [
        'Accept-Language: id,en;q=0.8',
        'Accept: text/html,application/xhtml+xml',
    ],
]);
if (getenv('GEMINI_INSECURE_SSL') === '1') {
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
}
$html = curl_exec($ch);
$code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($html === false || $code < 200 || $code >= 300) {
    echo json_encode(['ok'=>false,'err'=>'gagal akses YouTube (HTTP '.$code.')']);
    exit;
}

// Ambil daftar videoId unik dari payload — yang pertama umumnya hasil teratas.
$ids = [];
if (preg_match_all('/"videoId":"([A-Za-z0-9_-]{11})"/', $html, $m)) {
    foreach ($m[1] as $id) { if (!isset($ids[$id])) $ids[$id] = true; }
}
$ids = array_keys($ids);
if (!$ids) { echo json_encode(['ok'=>false,'err'=>'tidak ada hasil']); exit; }

echo json_encode([
    'ok'    => true,
    'video' => $ids[0],
    'ids'   => array_slice($ids, 0, 5),
    'q'     => $q,
]);
