<?php
/**
 * Revisi 24 Juni 2026 — Ekstraksi MP3 dari URL YouTube untuk dipasang ke
 * Rekam Video (flyover.php). Endpoint LOKAL: butuh `yt-dlp` & `ffmpeg`
 * tersedia di PATH server PHP. Hasil disimpan ke /uploads/yt_mp3/ dan
 * URL publiknya dikembalikan sebagai JSON sehingga klien tinggal memuatnya
 * ke <audio id="musicAudio"> dan ikut terekam ke video.
 *
 * Tidak dibutuhkan tabel baru di PostgreSQL — hanya folder file.
 *
 * Request:  GET /api_yt_mp3.php?v=<videoId>&title=<opsional>&artist=<opsional>
 * Response: { ok:true, url:"/uploads/yt_mp3/<id>.mp3", title, artist }
 */
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require_login();
header('Content-Type: application/json');

$u = current_user(); $uid = (int)$u['id'];
rate_limit_or_die('yt_mp3:'.$uid, 12, 600); // maks 12 ekstraksi / 10 menit

$vid = trim((string)($_GET['v'] ?? $_POST['v'] ?? ''));
if (!preg_match('/^[A-Za-z0-9_\-]{6,20}$/', $vid)) {
    echo json_encode(['ok'=>false,'err'=>'videoId tidak valid']); exit;
}
$title  = trim((string)($_GET['title']  ?? ''));
$artist = trim((string)($_GET['artist'] ?? ''));

$outDir = __DIR__.'/uploads/yt_mp3';
if (!is_dir($outDir)) @mkdir($outDir, 0775, true);
$outFile = $outDir.'/'.$vid.'.mp3';
$publicUrl = '/uploads/yt_mp3/'.$vid.'.mp3';

// Cache: bila sudah pernah diekstrak, langsung kembalikan.
if (is_file($outFile) && filesize($outFile) > 10*1024) {
    echo json_encode(['ok'=>true,'url'=>$publicUrl,'cached'=>true,'title'=>$title,'artist'=>$artist]); exit;
}

// Pastikan tool tersedia
function _bin_exists($bin) {
    $out = []; $rc = 0;
    @exec((stripos(PHP_OS,'WIN')===0?'where ':'command -v ').escapeshellarg($bin).' 2>NUL', $out, $rc);
    return $rc === 0 && !empty($out);
}
if (!_bin_exists('yt-dlp') || !_bin_exists('ffmpeg')) {
    echo json_encode(['ok'=>false,'err'=>'yt-dlp / ffmpeg belum terpasang di server. Install dulu: `pip install yt-dlp` dan `apt install ffmpeg` (atau setara di OS Anda).']); exit;
}

$ytUrl = 'https://www.youtube.com/watch?v='.$vid;
// -x: extract audio; --audio-format mp3; -o: output template; --no-playlist; quiet
$cmd = 'yt-dlp -x --audio-format mp3 --audio-quality 5 --no-playlist '
     . '-o '.escapeshellarg($outDir.'/'.$vid.'.%(ext)s').' '
     . escapeshellarg($ytUrl).' 2>&1';
$out = []; $rc = 0;
@exec($cmd, $out, $rc);

if ($rc !== 0 || !is_file($outFile)) {
    echo json_encode(['ok'=>false,'err'=>'Ekstraksi gagal','detail'=>implode("\n", array_slice($out,-8)), 'cmd_rc'=>$rc]); exit;
}

echo json_encode(['ok'=>true,'url'=>$publicUrl,'title'=>$title,'artist'=>$artist]);
