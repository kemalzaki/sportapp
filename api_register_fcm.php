<?php
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
send_security_headers(); enforce_session_timeout();
require_login();
header('Content-Type: application/json');

$u = current_user();
csrf_check();
rate_limit_or_die('fcm:'.$u['id'], 10, 60);

$token = trim($_POST['token'] ?? '');
if ($token === '' || strlen($token) > 500) { echo json_encode(['ok'=>false]); exit; }

try {
    db_exec("INSERT INTO fcm_tokens(user_id,token,device) VALUES($1,$2,$3) ON CONFLICT (user_id,token) DO NOTHING",
        [(int)$u['id'], $token, substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 100)]);
    echo json_encode(['ok'=>true]);
} catch (Throwable $e) { echo json_encode(['ok'=>false,'err'=>'db']); }
