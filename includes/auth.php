<?php
if (session_status() === PHP_SESSION_NONE) session_start();

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

/* ---------- Captcha sederhana (math) ---------- */
function captcha_new(): array {
    $a = random_int(1, 9); $b = random_int(1, 9);
    $_SESSION['captcha_answer'] = $a + $b;
    return [$a, $b];
}
function captcha_check(string $answer): bool {
    return isset($_SESSION['captcha_answer']) && (int)$answer === (int)$_SESSION['captcha_answer'];
}
