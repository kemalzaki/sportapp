<?php
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require_login();
header('Content-Type: application/json');
$u = current_user(); $uid = (int)$u['id'];

if ($_SERVER['REQUEST_METHOD']!=='POST') { echo json_encode(['ok'=>false]); exit; }
if (($_POST['csrf'] ?? '') !== ($_SESSION['csrf'] ?? '')) { echo json_encode(['ok'=>false,'err'=>'csrf']); exit; }
$a = $_POST['_action'] ?? '';

if ($a === 'start') {
    rate_limit_or_die('run_start:'.$uid, 10, 600);
    // close any aktif lama
    db_exec("UPDATE run_sessions SET status='dibatalkan', selesai_at=now() WHERE user_id=$1 AND status='aktif'", [$uid]);
    $r = pg_query_params(db(), "INSERT INTO run_sessions(user_id) VALUES($1) RETURNING id", [$uid]);
    $id = (int)(pg_fetch_row($r)[0] ?? 0);
    echo json_encode(['ok'=>true,'id'=>$id]); exit;
}

if ($a === 'point') {
    $sid = (int)($_POST['session_id'] ?? 0);
    if (!$sid) { echo json_encode(['ok'=>false]); exit; }
    $own = db_one("SELECT id FROM run_sessions WHERE id=$1 AND user_id=$2 AND status='aktif'", [$sid,$uid]);
    if (!$own) { echo json_encode(['ok'=>false,'err'=>'not_active']); exit; }
    $lat = (float)$_POST['lat']; $lng = (float)$_POST['lng'];
    $acc = isset($_POST['acc']) ? (float)$_POST['acc'] : null;
    $spd = isset($_POST['spd']) && $_POST['spd']!=='' ? (float)$_POST['spd'] : null;
    db_exec("INSERT INTO run_points(session_id,lat,lng,accuracy_m,speed_mps) VALUES($1,$2,$3,$4,$5)",
        [$sid,$lat,$lng,$acc,$spd]);
    db_exec("UPDATE run_sessions SET jarak_m=$1 WHERE id=$2", [(float)$_POST['total_m'], $sid]);
    echo json_encode(['ok'=>true]); exit;
}

if ($a === 'stop') {
    $sid = (int)($_POST['session_id'] ?? 0);
    $jarak = (float)($_POST['total_m'] ?? 0);
    $dur   = (int)($_POST['durasi'] ?? 0);
    // estimasi kalori: ~ 1 kkal / kg / km, asumsi 65 kg
    $kal = (int)round(($jarak/1000) * 65);
    db_exec("UPDATE run_sessions SET selesai_at=now(), jarak_m=$1, durasi_dtk=$2, kalori=$3, status='selesai' WHERE id=$4 AND user_id=$5",
        [$jarak,$dur,$kal,$sid,$uid]);
    echo json_encode(['ok'=>true]); exit;
}

if ($a === 'delete') {
    $sid = (int)($_POST['session_id'] ?? 0);
    if ($sid > 0) {
        db_exec("DELETE FROM run_points WHERE session_id=$1", [$sid]);
        db_exec("DELETE FROM run_sessions WHERE id=$1 AND user_id=$2", [$sid, $uid]);
    }
    echo json_encode(['ok'=>true]); exit;
}

echo json_encode(['ok'=>false]);
