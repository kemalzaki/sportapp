<?php
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
$u = current_user();
if ($u) {
    $m = (int)($_POST['mode'] ?? 0) === 1 ? 1 : 0;
    db_exec("UPDATE users SET dark_mode=$1 WHERE id=$2", [$m, (int)$u['id']]);
}
header('Content-Type: application/json'); echo json_encode(['ok'=>true]);
