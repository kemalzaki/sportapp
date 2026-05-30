<?php
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require_login();
header('Content-Type: application/json');
$u = current_user(); $uid = (int)$u['id'];

// ===== Export GPX / KML (revisi 31 Mei 2026) =====
// Bisa diimpor ke Google My Maps (mymaps.google.com → Import), Google Earth, Strava, dll.
if ($_SERVER['REQUEST_METHOD']==='GET' && ($_GET['export'] ?? '') !== '') {
    $sid = (int)$_GET['export'];
    $fmt = strtolower($_GET['fmt'] ?? 'gpx');
    $own = db_one("SELECT id, jarak_m, durasi_dtk, kalori, mulai_at, selesai_at FROM run_sessions WHERE id=$1 AND user_id=$2", [$sid, $uid]);
    if (!$own) { http_response_code(404); exit('Sesi tidak ditemukan.'); }
    $pts = db_all("SELECT lat, lng, ts FROM run_points WHERE session_id=$1 ORDER BY id ASC", [$sid]);
    $name = 'Lari-'.date('Ymd-Hi', strtotime($own['mulai_at'])).'-'.round(((float)$own['jarak_m'])/1000,2).'km';
    if ($fmt === 'kml') {
        header('Content-Type: application/vnd.google-earth.kml+xml; charset=utf-8');
        header('Content-Disposition: attachment; filename="'.$name.'.kml"');
        echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        echo "<kml xmlns=\"http://www.opengis.net/kml/2.2\"><Document>\n";
        echo "<name>".htmlspecialchars($name)."</name>\n";
        echo "<description>Jarak ".round(((float)$own['jarak_m'])/1000,2)." km · Durasi ".(int)$own['durasi_dtk']." dtk · ".(int)$own['kalori']." kkal</description>\n";
        echo "<Style id=\"runLine\"><LineStyle><color>ff2626dc</color><width>4</width></LineStyle></Style>\n";
        echo "<Placemark><styleUrl>#runLine</styleUrl><LineString><tessellate>1</tessellate><coordinates>\n";
        foreach ($pts as $p) echo ((float)$p['lng']).",".((float)$p['lat']).",0\n";
        echo "</coordinates></LineString></Placemark>\n";
        echo "</Document></kml>";
        exit;
    }
    // Default: GPX
    header('Content-Type: application/gpx+xml; charset=utf-8');
    header('Content-Disposition: attachment; filename="'.$name.'.gpx"');
    echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
    echo "<gpx version=\"1.1\" creator=\"SportApp\" xmlns=\"http://www.topografix.com/GPX/1/1\">\n";
    echo "<metadata><name>".htmlspecialchars($name)."</name><time>".date('c', strtotime($own['mulai_at']))."</time></metadata>\n";
    echo "<trk><name>".htmlspecialchars($name)."</name><trkseg>\n";
    foreach ($pts as $p) {
        $t = !empty($p['ts']) ? date('c', strtotime($p['ts'])) : '';
        echo "<trkpt lat=\"".((float)$p['lat'])."\" lon=\"".((float)$p['lng'])."\">".($t?"<time>".$t."</time>":"")."</trkpt>\n";
    }
    echo "</trkseg></trk></gpx>";
    exit;
}

header('Content-Type: application/json');

// ===== Ambil titik rute sebuah sesi (untuk lihat riwayat rute) =====
if ($_SERVER['REQUEST_METHOD']==='GET' && ($_GET['route'] ?? '') !== '') {
    $sid = (int)$_GET['route'];
    $own = db_one("SELECT id, jarak_m, durasi_dtk, kalori, mulai_at FROM run_sessions WHERE id=$1 AND user_id=$2", [$sid, $uid]);
    if (!$own) { echo json_encode(['ok'=>false]); exit; }
    $pts = db_all("SELECT lat, lng FROM run_points WHERE session_id=$1 ORDER BY id ASC", [$sid]);
    echo json_encode(['ok'=>true, 'session'=>$own, 'points'=>array_map(function($p){ return [(float)$p['lat'], (float)$p['lng']]; }, $pts)]);
    exit;
}

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
