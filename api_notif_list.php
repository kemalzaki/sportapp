<?php
// Revisi 4 Jun 2026 — daftar 15 notifikasi terakhir untuk dropdown lonceng.
// Revisi R8 Juli 2026 — filter per-komunitas: user hanya melihat notifikasi
//   yang berasal dari komunitas mereka sendiri, atau broadcast tanpa komunitas.
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/notifications.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$u = current_user();
if (!$u) { echo json_encode(['items'=>[], 'unread'=>0, 'auth'=>false]); exit; }
$uid = (int)$u['id'];
notifications_ensure_migration();

try {
    $kid = _notif_user_komunitas_id($uid);
    if (!empty($_GET['mark'])) {
        if ($kid !== null) {
            @db_exec("UPDATE notifications SET dibaca=1
                      WHERE user_id=$1 AND dibaca=0
                        AND (komunitas_id IS NULL OR komunitas_id=$2)",
                     [$uid, $kid]);
        } else {
            @db_exec("UPDATE notifications SET dibaca=1
                      WHERE user_id=$1 AND dibaca=0 AND komunitas_id IS NULL",
                     [$uid]);
        }
    }
    if ($kid !== null) {
        $items = db_all(
            "SELECT id, jenis, judul, isi, url, dibaca, dibuat_pada
             FROM notifications
             WHERE user_id=$1 AND (komunitas_id IS NULL OR komunitas_id=$2)
             ORDER BY id DESC LIMIT 15",
            [$uid, $kid]
        );
        $unread = (int) db_val(
            "SELECT COUNT(*) FROM notifications
             WHERE user_id=$1 AND dibaca=0
               AND (komunitas_id IS NULL OR komunitas_id=$2)",
            [$uid, $kid]);
    } else {
        $items = db_all(
            "SELECT id, jenis, judul, isi, url, dibaca, dibuat_pada
             FROM notifications
             WHERE user_id=$1 AND komunitas_id IS NULL
             ORDER BY id DESC LIMIT 15",
            [$uid]
        );
        $unread = (int) db_val(
            "SELECT COUNT(*) FROM notifications
             WHERE user_id=$1 AND dibaca=0 AND komunitas_id IS NULL",
            [$uid]);
    }
    echo json_encode(['items'=>$items, 'unread'=>$unread, 'auth'=>true], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode(['items'=>[], 'unread'=>0, 'auth'=>true, 'error'=>'db']);
}
