<?php
/**
 * Revisi 26 Juni 2026 — Ekstraksi MP3 dari URL YouTube untuk Rekam Video
 * (flyover.php). Endpoint LOKAL: butuh `yt-dlp` & `ffmpeg` di PATH server PHP
 * (atau set env YT_DLP_BIN / FFMPEG_BIN ke path absolut).
 *
 * Perbaikan #5 (26 Jun 2026):
 *   - Deteksi binary lintas platform (Linux/macOS/Windows) tanpa membuat
 *     file "NUL" di Linux.
 *   - Mendukung path absolut via env: YT_DLP_BIN, FFMPEG_BIN.
 *   - Memeriksa beberapa lokasi umum: /usr/local/bin, /opt/homebrew/bin,
 *     ~/.local/bin, C:\ProgramData\chocolatey\bin, dll.
 *   - Pesan error lebih jelas dengan instruksi instalasi per OS.
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
rate_limit_or_die('yt_mp3:'.$uid, 12, 600);

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

if (is_file($outFile) && filesize($outFile) > 10*1024) {
    echo json_encode(['ok'=>true,'url'=>$publicUrl,'cached'=>true,'title'=>$title,'artist'=>$artist]); exit;
}

/* ---------- Resolver binary lintas platform ---------- */
function _is_windows(): bool { return stripos(PHP_OS, 'WIN') === 0; }

function _which(string $bin): ?string {
    // 1) Env override (path absolut)
    $envKey = strtoupper(str_replace('-', '_', $bin)).'_BIN';
    $env = getenv($envKey);
    if ($env && is_file($env) && is_executable($env)) return $env;

    // 2) `where` (Windows) / `command -v` (POSIX)
    $cmd = _is_windows()
        ? 'where '.escapeshellarg($bin).' 2> NUL'
        : 'command -v '.escapeshellarg($bin).' 2>/dev/null';
    $out = []; $rc = 0;
    @exec($cmd, $out, $rc);
    if ($rc === 0 && !empty($out[0]) && is_file(trim($out[0]))) return trim($out[0]);

    // 3) Kandidat lokasi umum
    $home = getenv('HOME') ?: '';
    $candidates = _is_windows()
        ? [
            'C:\\Program Files\\yt-dlp\\'.$bin.'.exe',
            'C:\\ProgramData\\chocolatey\\bin\\'.$bin.'.exe',
            'C:\\tools\\'.$bin.'\\'.$bin.'.exe',
          ]
        : [
            '/usr/local/bin/'.$bin,
            '/usr/bin/'.$bin,
            '/opt/homebrew/bin/'.$bin,
            '/snap/bin/'.$bin,
            $home.'/.local/bin/'.$bin,
          ];
    foreach ($candidates as $c) {
        if (is_file($c) && is_executable($c)) return $c;
    }
    return null;
}

$ytdlp  = _which('yt-dlp');
$ffmpeg = _which('ffmpeg');

if (!$ytdlp || !$ffmpeg) {
    $missing = [];
    if (!$ytdlp)  $missing[] = 'yt-dlp';
    if (!$ffmpeg) $missing[] = 'ffmpeg';
    $instr = _is_windows()
        ? "Windows: `winget install yt-dlp` dan `winget install Gyan.FFmpeg` (atau pakai Chocolatey: `choco install yt-dlp ffmpeg`). Pastikan PATH ter-update lalu restart server PHP."
        : "Linux/macOS: `sudo apt install -y ffmpeg && pip install -U yt-dlp` atau di macOS `brew install yt-dlp ffmpeg`. Jika tidak bisa diinstal global, set env YT_DLP_BIN dan FFMPEG_BIN ke path absolut binary, lalu restart PHP-FPM/Apache.";
    echo json_encode([
        'ok'=>false,
        'err'=>'Tool ekstraksi belum terpasang: '.implode(', ', $missing).'.',
        'instruksi'=>$instr,
        'env_override'=>'YT_DLP_BIN, FFMPEG_BIN',
    ]); exit;
}

/* ---------- Eksekusi ---------- */
$ytUrl = 'https://www.youtube.com/watch?v='.$vid;
$ffDir = dirname($ffmpeg);
$cmd = escapeshellarg($ytdlp)
     . ' -x --audio-format mp3 --audio-quality 5 --no-playlist'
     . ' --ffmpeg-location '.escapeshellarg($ffDir)
     . ' -o '.escapeshellarg($outDir.'/'.$vid.'.%(ext)s').' '
     . escapeshellarg($ytUrl).' 2>&1';
$out = []; $rc = 0;
@exec($cmd, $out, $rc);

if ($rc !== 0 || !is_file($outFile)) {
    echo json_encode([
        'ok'=>false,
        'err'=>'Ekstraksi gagal. Periksa koneksi atau coba videoId lain.',
        'detail'=>implode("\n", array_slice($out, -10)),
        'cmd_rc'=>$rc,
    ]); exit;
}

echo json_encode(['ok'=>true,'url'=>$publicUrl,'title'=>$title,'artist'=>$artist]);
