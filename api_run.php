<?php
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/ai_gemini.php';
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

// ===== Revisi 15 Jun 2026: load rute tersimpan (Route Builder) =====
if ($_SERVER['REQUEST_METHOD']==='GET' && ($_GET['route_load'] ?? '') !== '') {
    $rid = (int)$_GET['route_load'];
    $r = db_one("SELECT id, user_id, nama, jarak_m, elevasi_pref, surface_pref, is_public, geojson FROM run_routes WHERE id=$1", [$rid]);
    if (!$r) { echo json_encode(['ok'=>false]); exit; }
    $isPub = ($r['is_public']===true || $r['is_public']==='t' || $r['is_public']==='1');
    if (!$isPub && (int)$r['user_id'] !== $uid) { echo json_encode(['ok'=>false,'err'=>'forbidden']); exit; }
    $gj = is_array($r['geojson']) ? $r['geojson'] : json_decode($r['geojson'], true);
    $coords = $gj['coords'] ?? [];
    echo json_encode(['ok'=>true, 'id'=>(int)$r['id'], 'nama'=>$r['nama'], 'jarak_m'=>(float)$r['jarak_m'], 'coords'=>$coords]);
    exit;
}

// ===== Revisi 15 Jun 2026: Heatmap (pribadi / publik / night) =====
if ($_SERVER['REQUEST_METHOD']==='GET' && ($_GET['heatmap'] ?? '') !== '') {
    $mode = $_GET['heatmap'];
    $limit = 5000;
    if ($mode === 'pribadi') {
        $rows = db_all("SELECT p.lat, p.lng FROM run_points p JOIN run_sessions s ON s.id=p.session_id WHERE s.user_id=$1 ORDER BY p.id DESC LIMIT $limit", [$uid]);
    } elseif ($mode === 'night') {
        $rows = db_all("SELECT p.lat, p.lng FROM run_points p WHERE EXTRACT(HOUR FROM p.ts) >= 18 OR EXTRACT(HOUR FROM p.ts) < 5 ORDER BY p.id DESC LIMIT $limit");
    } else { // publik
        $rows = db_all("SELECT lat, lng FROM run_points ORDER BY id DESC LIMIT $limit");
    }
    $pts = array_map(function($r){ return [(float)$r['lat'], (float)$r['lng'], 0.6]; }, $rows);
    echo json_encode(['ok'=>true, 'points'=>$pts]);
    exit;
}


if ($_SERVER['REQUEST_METHOD']!=='POST') { echo json_encode(['ok'=>false]); exit; }
if (($_POST['csrf'] ?? '') !== ($_SESSION['csrf'] ?? '')) { echo json_encode(['ok'=>false,'err'=>'csrf']); exit; }
$a = $_POST['_action'] ?? '';

/* ===== Revisi 15 Juni 2026 — AI Route from Image =====
 * Terima upload gambar (screenshot peta dengan rute), kirim ke OpenAI Vision (gpt-4o-mini).
 * AI diminta mengembalikan urutan nama tempat/landmark di sepanjang rute. Server lalu
 * geocode tiap nama via Nominatim (OpenStreetMap) menjadi koordinat [lat,lng].
 * Mengembalikan { ok, coords:[[lat,lng],...], note }.
 */
if ($a === 'ai_route_from_image') {
    @set_time_limit(120); // Revisi 17 Juni 2026 — proses bisa 30-60dtk (Gemini + Nominatim)
    rate_limit_or_die('ai_route:'.$uid, 5, 600);
    if (empty($_FILES['image']['tmp_name']) || !is_uploaded_file($_FILES['image']['tmp_name'])) {
        echo json_encode(['ok'=>false,'err'=>'gambar tidak ada']); exit;
    }
    $hint = trim((string)($_POST['hint'] ?? ''));
    $prompt = "Anda menerima SCREENSHOT PETA / STRAVA yang menampilkan sebuah RUTE (lari/jalan/sepeda). "
            . "Identifikasi 5-10 LANDMARK / nama jalan / persimpangan / titik penting secara BERURUTAN sepanjang rute. "
            . "Sertakan area kota/kabupaten supaya tidak ambigu. "
            . "Balas HANYA JSON valid: {\"places\":[\"Nama lengkap + Kota\", ...], \"note\":\"<1 kalimat singkat>\"}. "
            . ($hint!=='' ? "Petunjuk area dari pengguna: $hint" : "");
    $g = gemini_vision($prompt, $_FILES['image']['tmp_name'],
            ['json'=>true,'temperature'=>0.2,'max_tokens'=>700]);
    if (!$g['ok']) { echo json_encode(['ok'=>false,'err'=>'Gemini: '.$g['err']]); exit; }
    $obj = gemini_extract_json($g['text']);
    $places = is_array($obj['places'] ?? null) ? $obj['places'] : [];
    // Revisi 17 Juni 2026 — fallback parsing: pisah baris kalau JSON gagal
    if (count($places) < 2) {
        $lines = preg_split('/\r?\n/', (string)$g['text']);
        foreach ($lines as $ln) {
            $ln = trim(preg_replace('/^[\-\*\d\.\)]+\s*/','', $ln));
            if (strlen($ln) > 4 && strlen($ln) < 120 && substr_count($ln, ' ') < 12) $places[] = $ln;
        }
    }
    $places = array_slice(array_values(array_filter(array_unique($places))), 0, 8);
    if (count($places) < 2) { echo json_encode(['ok'=>false,'err'=>'AI tidak menemukan landmark. Raw: '.substr($g['text'],0,200)]); exit; }
    // Geocoding OSM (Nominatim) — paralel sequential, sleep 500ms
    $coords = []; $failures = [];
    foreach ($places as $place) {
        $q = trim((string)$place); if ($q==='') continue;
        $url = 'https://nominatim.openstreetmap.org/search?format=json&limit=1&q='.urlencode($q);
        $ch2 = curl_init($url);
        $copt = [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>10,
            CURLOPT_USERAGENT=>'SportAppBot/1.0 (admin@local)'];
        if (getenv('GEMINI_INSECURE_SSL') === '1') { $copt[CURLOPT_SSL_VERIFYPEER]=false; $copt[CURLOPT_SSL_VERIFYHOST]=0; }
        curl_setopt_array($ch2, $copt);
        $r2 = curl_exec($ch2); curl_close($ch2);
        $arr = json_decode($r2 ?: '[]', true);
        if (is_array($arr) && !empty($arr[0]['lat'])) {
            $coords[] = [(float)$arr[0]['lat'], (float)$arr[0]['lon']];
        } else {
            $failures[] = $q;
        }
        usleep(500*1000); // 0.5s — Nominatim cukup toleran utk <2req/s
    }
    if (count($coords) < 2) {
        echo json_encode(['ok'=>false,'err'=>'Geocoding gagal untuk: '.implode(' | ', $failures)]); exit;
    }
    echo json_encode(['ok'=>true, 'coords'=>$coords, 'places'=>$places, 'note'=>$obj['note'] ?? '', 'gagal_geocode'=>$failures]);
    exit;
}


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

// ===== Revisi 15 Jun 2026: simpan rute hasil Route Builder =====
if ($a === 'route_save') {
    $nama = trim((string)($_POST['nama'] ?? 'Rute'));
    if ($nama === '') $nama = 'Rute';
    $jarak = (float)($_POST['jarak_m'] ?? 0);
    $elev  = (string)($_POST['elevasi_pref'] ?? 'apa-saja');
    $surf  = (string)($_POST['surface_pref'] ?? 'apa-saja');
    $pub   = ($_POST['is_public'] ?? '0') === '1';
    $coordsRaw = $_POST['coords'] ?? '[]';
    $coords = json_decode($coordsRaw, true);
    if (!is_array($coords) || count($coords) < 2) { echo json_encode(['ok'=>false,'err'=>'coords']); exit; }
    $gj = json_encode(['type'=>'Feature','coords'=>$coords]);
    $r = pg_query_params(db(),
        "INSERT INTO run_routes(user_id,nama,jarak_m,elevasi_pref,surface_pref,geojson,is_public) VALUES($1,$2,$3,$4,$5,$6::jsonb,$7) RETURNING id",
        [$uid,$nama,$jarak,$elev,$surf,$gj, $pub?'t':'f']);
    $id = (int)(pg_fetch_row($r)[0] ?? 0);
    echo json_encode(['ok'=>true,'id'=>$id]); exit;
}

if ($a === 'route_delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id>0) db_exec("DELETE FROM run_routes WHERE id=$1 AND user_id=$2", [$id,$uid]);
    echo json_encode(['ok'=>true]); exit;
}

echo json_encode(['ok'=>false]);
