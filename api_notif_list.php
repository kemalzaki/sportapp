<?php
// Revisi 4 Jun 2026 — daftar 15 notifikasi terakhir untuk dropdown lonceng.
// Juga menandai semua notifikasi sebagai dibaca jika parameter ?mark=1.
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$u = current_user();
if (!$u) { echo json_encode(['items'=>[], 'unread'=>0, 'auth'=>false]); exit; }
$uid = (int)$u['id'];

try {
    if (!empty($_GET['mark'])) {
        @db_exec("UPDATE notifications SET dibaca=1 WHERE user_id=$1 AND dibaca=0", [$uid]);
    }
    $items = db_all(
        "SELECT id, jenis, judul, isi, url, dibaca, dibuat_pada
         FROM notifications WHERE user_id=$1
         ORDER BY id DESC LIMIT 15",
        [$uid]
    );
    $unread = (int) db_val("SELECT COUNT(*) FROM notifications WHERE user_id=$1 AND dibaca=0", [$uid]);
    echo json_encode(['items'=>$items, 'unread'=>$unread, 'auth'=>true], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode(['items'=>[], 'unread'=>0, 'auth'=>true, 'error'=>'db']);
}
