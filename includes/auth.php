<?php
if (session_status() === PHP_SESSION_NONE) session_start();

function app_cookie_secure(): bool {
    $https = strtolower((string)($_SERVER['HTTPS'] ?? ''));
    return (!empty($_SERVER['HTTPS']) && $https !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443')
        || (strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
}
function app_auth_secret(): string {
    $secret = getenv('APP_AUTH_SECRET') ?: getenv('SESSION_SECRET') ?: '';
    if ($secret === '') $secret = __DIR__ . '|sportapp-local-auth-v1';
    return hash('sha256', $secret);
}
function app_set_cookie(string $name, string $value, int $expires): void {
    if (headers_sent()) return;
    setcookie($name, $value, [
        'expires'  => $expires,
        'path'     => '/',
        'secure'   => app_cookie_secure(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}
function app_login_cookie_set(array $user): void {
    $payload = base64_encode(json_encode([
        'id' => (int)($user['id'] ?? 0),
        'exp' => time() + 60*60*24*30,
    ], JSON_UNESCAPED_SLASHES));
    $sig = hash_hmac('sha256', $payload, app_auth_secret());
    app_set_cookie('hf_auth', $payload . '.' . $sig, time() + 60*60*24*30);
}
function app_login_cookie_clear(): void {
    app_set_cookie('hf_auth', '', time() - 3600);
}
function app_login_cookie_user(): ?array {
    $token = (string)($_COOKIE['hf_auth'] ?? '');
    if ($token === '' || !str_contains($token, '.')) return null;
    [$payload, $sig] = explode('.', $token, 2);
    $expected = hash_hmac('sha256', $payload, app_auth_secret());
    if (!hash_equals($expected, $sig)) return null;
    $data = json_decode(base64_decode($payload, true) ?: '', true);
    if (!is_array($data) || empty($data['id']) || (int)($data['exp'] ?? 0) < time()) {
        app_login_cookie_clear();
        return null;
    }
    try {
        $u = db_one("SELECT id, nama, email, role FROM users WHERE id=$1", [(int)$data['id']]);
        if (!$u) { app_login_cookie_clear(); return null; }
        $_SESSION['user'] = ['id'=>(int)$u['id'],'nama'=>$u['nama'],'email'=>$u['email']??'','role'=>$u['role']];
        $_SESSION['last_activity'] = time();
        app_login_cookie_set($_SESSION['user']);
        return $_SESSION['user'];
    } catch (Throwable $e) { return null; }
}
function current_user() {
    return $_SESSION['user'] ?? app_login_cookie_user();
}
function require_login() {
    if (!current_user()) { header('Location: /login.php'); exit; }
}
function require_role($roles) {
    require_login();
    $roles = (array)$roles;
    if (!in_array($_SESSION['user']['role'], $roles, true)) {
        http_response_code(403);
        die('Akses ditolak.');
    }
}
function csrf_token() {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
/* Revisi 2 Juli 2026 #2:
 * Bila token CSRF tidak cocok, jangan die() polos ("CSRF token invalid.").
 * Tampilkan popup HTML yang rapi dengan pilihan: Refresh Halaman / Kembali ke Login.
 * Alasan umum: session expired, dibuka 2 tab, atau cookie diblokir.
 */
function csrf_valid(): bool {
    return isset($_POST['csrf'], $_SESSION['csrf']) && hash_equals((string)$_SESSION['csrf'], (string)$_POST['csrf']);
}
function csrf_check() {
    if (csrf_valid()) return;
    // Untuk AJAX (JSON) — kirim 419 supaya JS bisa handle
    $isAjax = (strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest')
           || (str_contains((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json'));
    if ($isAjax) {
        http_response_code(419);
        header('Content-Type: application/json');
        echo json_encode(['ok'=>false,'error'=>'CSRF token invalid.','code'=>'csrf_expired']);
        exit;
    }
    http_response_code(419);
    csrf_render_popup();
    exit;
}
function csrf_render_popup(): void {
    $back = htmlspecialchars($_SERVER['HTTP_REFERER'] ?? '/login.php', ENT_QUOTES);
    ?><!doctype html><html lang="id"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Sesi Kedaluwarsa</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
body{margin:0;min-height:100dvh;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#0ea5e9,#6366f1);font-family:system-ui,'Plus Jakarta Sans',sans-serif;padding:1rem}
.csrf-card{background:#fff;border-radius:20px;max-width:420px;width:100%;padding:1.75rem 1.6rem;box-shadow:0 25px 60px -20px rgba(0,0,0,.35);text-align:center}
.csrf-ico{width:72px;height:72px;border-radius:50%;background:#fee2e2;color:#b91c1c;display:flex;align-items:center;justify-content:center;font-size:2rem;margin:0 auto .8rem}
.csrf-btn{border-radius:12px;padding:.75rem 1rem;font-weight:600;width:100%}
</style></head><body>
<div class="csrf-card">
  <div class="csrf-ico"><i class="bi bi-shield-exclamation"></i></div>
  <h4 class="fw-bold mb-1">Sesi Anda Kedaluwarsa</h4>
  <p class="text-muted small mb-3">Token keamanan (CSRF) tidak valid. Ini biasanya terjadi karena halaman dibuka terlalu lama, browser memblokir cookie, atau Anda membuka lebih dari satu tab. Silakan muat ulang halaman lalu login kembali.</p>
  <div class="d-grid gap-2">
    <button type="button" class="btn btn-primary csrf-btn" onclick="location.reload()"><i class="bi bi-arrow-clockwise"></i> Muat Ulang Halaman</button>
    <a class="btn btn-outline-secondary csrf-btn" href="/login.php"><i class="bi bi-box-arrow-in-left"></i> Kembali ke Halaman Login</a>
  </div>
</div>
</body></html><?php
}

/* ---------- Captcha sederhana (math) ---------- */
function captcha_new(): array {
    $a = random_int(1, 9); $b = random_int(1, 9);
    $_SESSION['captcha_answer'] = $a + $b;
    return [$a, $b];
}
function captcha_check(string $answer): bool {
    return isset($_SESSION['captcha_answer']) && (int)$answer === (int)$_SESSION['captcha_answer'];
}
