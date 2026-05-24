<?php
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require_login();
header('Content-Type: application/json');
$u = current_user(); $uid = (int)$u['id'];
$pid = (int)($_REQUEST['post_id'] ?? 0);
if ($pid <= 0) { echo json_encode(['ok'=>false]); exit; }

if ($_SERVER['REQUEST_METHOD']==='POST') {
    @pg_query_params(db(),
      "INSERT INTO post_views(post_id,user_id) VALUES($1,$2) ON CONFLICT DO NOTHING",
      [$pid, $uid]);
}
$rows = db_all("SELECT u.id, u.nama, u.foto_url, v.viewed_at
                FROM post_views v JOIN users u ON u.id=v.user_id
                WHERE v.post_id=$1 ORDER BY v.viewed_at DESC LIMIT 50", [$pid]);
$total = (int)(db_val("SELECT COUNT(*) FROM post_views WHERE post_id=$1", [$pid]) ?? 0);
echo json_encode(['ok'=>true, 'total'=>$total, 'viewers'=>$rows]);
