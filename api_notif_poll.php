<?php
// Endpoint polling untuk PWA push sederhana — kembalikan notifikasi baru sejak terakhir ditampilkan.
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/migrations_v7.php';

header('Content-Type: application/json; charset=utf-8');
$u = current_user();
if (!$u) { echo json_encode(['items'=>[]]); exit; }
$uid = (int)$u['id'];

try {
    $last = (int) (db_val("SELECT last_notif_id FROM push_seen WHERE user_id=$1", [$uid]) ?? 0);
    $items = db_all(
        "SELECT id, judul, isi, url FROM notifications
         WHERE user_id=$1 AND id > $2 ORDER BY id ASC LIMIT 10",
        [$uid, $last]
    );
    if ($items) {
        $maxId = (int) end($items)['id'];
        db_exec("INSERT INTO push_seen(user_id,last_notif_id,updated_at) VALUES($1,$2,now())
                 ON CONFLICT (user_id) DO UPDATE SET last_notif_id=EXCLUDED.last_notif_id, updated_at=now()",
                [$uid, $maxId]);
    }
    echo json_encode(['items' => $items], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode(['items' => [], 'error' => 'db']);
}
