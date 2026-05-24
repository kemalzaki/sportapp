<?php
// === Persistent login (WhatsApp-style): cookie & session bertahan ~1 tahun ===
if (session_status() === PHP_SESSION_NONE) {
    $lifetime = 60 * 60 * 24 * 365; // 1 tahun
    @ini_set('session.gc_maxlifetime', (string)$lifetime);
    @ini_set('session.cookie_lifetime', (string)$lifetime);
    $params = session_get_cookie_params();
    session_set_cookie_params([
        'lifetime' => $lifetime,
        'path'     => $params['path'] ?? '/',
        'domain'   => $params['domain'] ?? '',
        'secure'   => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
    // Perpanjang cookie tiap request agar tidak expire selama user masih aktif
    if (!empty($_SESSION['user'])) {
        setcookie(session_name(), session_id(), [
            'expires'  => time() + $lifetime,
            'path'     => $params['path'] ?? '/',
            'domain'   => $params['domain'] ?? '',
            'secure'   => !empty($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}

if (!function_exists('current_user')) {
function current_user() {
    return $_SESSION['user'] ?? null;
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

} // end function_exists guard

if (!function_exists('captcha_new')) {
/* ---------- Captcha sederhana (math) ---------- */
function captcha_new(): array {
    $a = random_int(1, 9); $b = random_int(1, 9);
    $_SESSION['captcha_answer'] = $a + $b;
    return [$a, $b];
}
function captcha_check(string $answer): bool {
    return isset($_SESSION['captcha_answer']) && (int)$answer === (int)$_SESSION['captcha_answer'];
}
} // end captcha guard
