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
function csrf_check() {
    if (($_POST['csrf'] ?? '') !== ($_SESSION['csrf'] ?? '')) {
        http_response_code(400); die('CSRF token invalid.');
    }
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
