<?php
// Security hardening helpers untuk SportApp v3
require_once __DIR__ . '/auth.php';

// ---------- Security Headers + CSP ----------
function send_security_headers(): void {
    if (headers_sent()) return;
    header("X-Content-Type-Options: nosniff");
    header("X-Frame-Options: SAMEORIGIN");
    header("Referrer-Policy: strict-origin-when-cross-origin");
    header("Permissions-Policy: geolocation=(self), camera=(self)");
    // CSP: izinkan CDN yang sudah dipakai (bootstrap/quill/chartjs/fcm)
    $csp = "default-src 'self'; "
     . "img-src 'self' data: blob: https: http: https://*.googleapis.com https://*.gstatic.com https://*.google.com; "
     . "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdn.quilljs.com https://fonts.googleapis.com https://unpkg.com https://maps.googleapis.com https://maps.gstatic.com; "
     . "font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net data:; "
     . "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdn.quilljs.com https://www.gstatic.com https://www.googleapis.com https://unpkg.com https://maps.googleapis.com https://maps.gstatic.com; "
     . "connect-src 'self' https://fcmregistrations.googleapis.com https://fcm.googleapis.com https://nominatim.openstreetmap.org https://*.tile.openstreetmap.org https://maps.googleapis.com https://maps.gstatic.com https://*.googleapis.com https://api.alquran.cloud https://equran.id https://api.quran.com https://everyayah.com https://cdn.islamic.network https://raw.githubusercontent.com wss: https:; "

     /* Revisi 6 Juni 2026 (revisi-2): izinkan embed YouTube + media (IPTV HLS) */
     . "frame-src 'self' https://www.youtube.com https://youtube.com https://www.youtube-nocookie.com; "
     . "media-src 'self' blob: data: https:; "

     . "frame-ancestors 'self'; "
     . "base-uri 'self'; "
     . "form-action 'self'; "
     . "worker-src 'self' blob:;";
    header("Content-Security-Policy: $csp");
}

// ---------- Session expiration ----------
function enforce_session_timeout(int $minutes = 43200): void {
    // Default 30 hari — member tetap login bila terus membuka aplikasi.
    if (!isset($_SESSION['user'])) return;
    $now = time();
    if (isset($_SESSION['last_activity']) && ($now - $_SESSION['last_activity'] > $minutes * 60)) {
        session_unset(); session_destroy();
        header('Location: /login.php?expired=1'); exit;
    }
    $_SESSION['last_activity'] = $now;
}

// ---------- Rate Limit ----------
function rate_limit(string $bucket, int $max, int $perSeconds): bool {
    try {
        db_exec("DELETE FROM rate_limit WHERE ts < now() - ($1 || ' seconds')::interval", [$perSeconds]);
        $n = (int) db_val("SELECT COUNT(*) FROM rate_limit WHERE bucket=$1", [$bucket]);
        if ($n >= $max) return false;
        db_exec("INSERT INTO rate_limit(bucket) VALUES($1)", [$bucket]);
        return true;
    } catch (Throwable $e) { return true; }
}
function rate_limit_or_die(string $bucket, int $max=20, int $perSeconds=60): void {
    if (!rate_limit($bucket, $max, $perSeconds)) {
        http_response_code(429);
        die("Terlalu banyak permintaan. Coba lagi sebentar lagi.");
    }
}

// ---------- XSS sanitize untuk konten HTML (Quill) ----------
function sanitize_html(string $html): string {
    // strip semua script/style/iframe/object/embed dan event handler
    $html = preg_replace('#<\s*(script|style|iframe|object|embed|link|meta)[^>]*>.*?<\s*/\s*\1\s*>#is', '', $html);
    $html = preg_replace('#<\s*(script|style|iframe|object|embed|link|meta)[^>]*/?>#i', '', $html);
    $html = preg_replace('#\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $html);
    $html = preg_replace('#(href|src)\s*=\s*("javascript:[^"]*"|\'javascript:[^\']*\')#i', '$1="#"', $html);
    // hanya izinkan tag whitelist
    $allowed = '<p><br><b><strong><i><em><u><s><strike><ul><ol><li><a><span><div><blockquote><h1><h2><h3><h4><h5><h6><pre><code><img>';
    $html = strip_tags($html, $allowed);
    return $html;
}

// ---------- Upload MIME validation ----------
function validate_image_upload(array $file, int $maxBytes = 20_000_000): array {
    if (!isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
        return [false, 'Upload gagal.'];
    }
    if ($file['size'] > $maxBytes) return [false, 'File terlalu besar (>20MB).'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    $allowed = [
        'image/jpeg'=>'jpg','image/pjpeg'=>'jpg','image/jpg'=>'jpg',
        'image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif',
        'image/bmp'=>'bmp','image/x-ms-bmp'=>'bmp',
        'image/heic'=>'heic','image/heif'=>'heif',
        'image/avif'=>'avif','image/tiff'=>'tiff','image/svg+xml'=>'svg',
    ];
    if (!isset($allowed[$mime])) {
        // fallback by extension agar foto dari HP (HEIC/JFIF) tetap bisa lewat
        $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        $extMap = ['jpg'=>'jpg','jpeg'=>'jpg','jfif'=>'jpg','png'=>'png','webp'=>'webp','gif'=>'gif','bmp'=>'bmp','heic'=>'heic','heif'=>'heif','avif'=>'avif','tif'=>'tiff','tiff'=>'tiff'];
        if (isset($extMap[$ext]) && str_starts_with((string)$mime, 'image/')) return [true, $extMap[$ext]];
        return [false, 'Tipe file tidak diizinkan: '.$mime];
    }
    $head = file_get_contents($file['tmp_name'], false, null, 0, 4096);
    if (preg_match('/<\?php|<script\b/i', $head)) return [false, 'File mencurigakan ditolak.'];
    return [true, $allowed[$mime]];
}

// ---------- Password hashing helpers ----------
function hash_password(string $plain): string {
    return password_hash($plain, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT);
}
function verify_password(string $plain, string $hash): bool {
    return password_verify($plain, $hash);
}

// ---------- Login attempt logging ----------
function log_login_attempt(string $email, bool $ok): void {
    try {
        db_exec("INSERT INTO login_attempts(email,ip,success) VALUES($1,$2,$3)",
            [$email, $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', $ok ? 1 : 0]);
    } catch (Throwable $e) {}
}
function too_many_failed_logins(string $email): bool {
    try {
        $n = (int) db_val("SELECT COUNT(*) FROM login_attempts WHERE email=$1 AND success=0 AND created_at > now() - interval '10 minutes'", [$email]);
        return $n >= 8;
    } catch (Throwable $e) { return false; }
}
