<?php
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require_login();
header('Content-Type: application/json');
$u = current_user();
$uid = (int)$u['id'];

if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (($_POST['csrf'] ?? '') !== ($_SESSION['csrf'] ?? '')) {
        echo json_encode(['ok'=>false,'err'=>'csrf']); exit;
    }
    rate_limit_or_die('dm:'.$uid, 60, 60);
    $to = (int)($_POST['to'] ?? 0);
    $pesan = trim($_POST['pesan'] ?? '');
    if ($to <= 0 || $to === $uid || $pesan === '') { echo json_encode(['ok'=>false]); exit; }
    if (mb_strlen($pesan) > 2000) $pesan = mb_substr($pesan, 0, 2000);
    if (!db_one("SELECT id FROM users WHERE id=$1", [$to])) { echo json_encode(['ok'=>false,'err'=>'no_user']); exit; }
    db_exec("INSERT INTO dm_messages(sender_id,receiver_id,pesan) VALUES($1,$2,$3)", [$uid,$to,$pesan]);
    echo json_encode(['ok'=>true]); exit;
}

if (isset($_GET['find'])) {
    $q = '%'.strtolower(trim($_GET['find'])).'%';
    $rows = db_all("SELECT id,nama,username,foto_url FROM users
                    WHERE id<>$1 AND (LOWER(nama) LIKE $2 OR LOWER(COALESCE(username,'')) LIKE $2)
                    ORDER BY nama LIMIT 15", [$uid, $q]);
    echo json_encode($rows); exit;
}

$peer  = (int)($_GET['peer']  ?? 0);
$since = (int)($_GET['since'] ?? 0);
if ($peer <= 0) { echo json_encode(['messages'=>[]]); exit; }

$rows = db_all("SELECT id, sender_id, receiver_id, pesan, created_at
                FROM dm_messages
                WHERE id > $1
                  AND ((sender_id=$2 AND receiver_id=$3) OR (sender_id=$3 AND receiver_id=$2))
                ORDER BY id ASC LIMIT 200", [$since, $uid, $peer]);

// tandai dibaca pesan yang masuk
db_exec("UPDATE dm_messages SET read_at=now() WHERE receiver_id=$1 AND sender_id=$2 AND read_at IS NULL",
    [$uid, $peer]);

echo json_encode(['messages'=>$rows]);
