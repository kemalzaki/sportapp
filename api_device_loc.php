<?php
// Heartbeat lokasi handphone — dipanggil otomatis dari header.php tiap 2 menit.
// Memungkinkan admin melacak posisi member jika HP hilang/lupa simpan.
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require_login();
header('Content-Type: application/json');
$u = current_user(); $uid = (int)$u['id'];
if ($_SERVER['REQUEST_METHOD']!=='POST') { echo json_encode(['ok'=>false]); exit; }
if (($_POST['csrf'] ?? '') !== ($_SESSION['csrf'] ?? '')) { echo json_encode(['ok'=>false,'err'=>'csrf']); exit; }
$lat = isset($_POST['lat']) ? (float)$_POST['lat'] : null;
$lng = isset($_POST['lng']) ? (float)$_POST['lng'] : null;
$acc = isset($_POST['acc']) && $_POST['acc']!=='' ? (float)$_POST['acc'] : null;
$lbl = substr($_POST['device'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 120);
if ($lat===null || $lng===null || $lat<-90 || $lat>90 || $lng<-180 || $lng>180) { echo json_encode(['ok'=>false,'err'=>'coord']); exit; }
db_exec("INSERT INTO device_locations(user_id,lat,lng,accuracy_m,device_label,updated_at) VALUES($1,$2,$3,$4,$5,now())
         ON CONFLICT (user_id) DO UPDATE SET lat=EXCLUDED.lat, lng=EXCLUDED.lng, accuracy_m=EXCLUDED.accuracy_m, device_label=EXCLUDED.device_label, updated_at=now()",
         [$uid,$lat,$lng,$acc,$lbl]);
db_exec("INSERT INTO device_location_history(user_id,lat,lng,accuracy_m) VALUES($1,$2,$3,$4)", [$uid,$lat,$lng,$acc]);
// trim history per user, simpan 200 terbaru
db_exec("DELETE FROM device_location_history WHERE user_id=$1 AND id NOT IN (SELECT id FROM device_location_history WHERE user_id=$1 ORDER BY id DESC LIMIT 200)", [$uid]);
echo json_encode(['ok'=>true]);
