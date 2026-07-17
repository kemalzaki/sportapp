<?php
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/ai_router.php';
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
    // Revisi R33 — Export GeoJSON
    if ($fmt === 'geojson' || $fmt === 'json') {
        header('Content-Type: application/geo+json; charset=utf-8');
        header('Content-Disposition: attachment; filename="'.$name.'.geojson"');
        $coords = [];
        foreach ($pts as $p) { $coords[] = [ (float)$p['lng'], (float)$p['lat'] ]; }
        echo json_encode([
            'type' => 'FeatureCollection',
            'features' => [[
                'type' => 'Feature',
                'properties' => [
                    'name' => $name,
                    'jarak_km' => round(((float)$own['jarak_m'])/1000, 3),
                    'durasi_dtk' => (int)$own['durasi_dtk'],
                    'kalori' => (int)$own['kalori'],
                    'mulai_at' => $own['mulai_at'] ?? null,
                    'selesai_at' => $own['selesai_at'] ?? null,
                ],
                'geometry' => [ 'type' => 'LineString', 'coordinates' => $coords ]
            ]]
        ], JSON_UNESCAPED_SLASHES);
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
    @set_time_limit(120);
    rate_limit_or_die('ai_route:'.$uid, 5, 600);
    if (empty($_FILES['image']['tmp_name']) || !is_uploaded_file($_FILES['image']['tmp_name'])) {
        echo json_encode(['ok'=>false,'err'=>'gambar tidak ada']); exit;
    }
    $hint = trim((string)($_POST['hint'] ?? ''));

    /* ===== Revisi 18 Juni 2026 — Strategi 2-tahap =====
     * 1) Minta Gemini langsung mengembalikan ESTIMASI [lat,lng] berurutan
     *    dengan membaca skala/orientasi map pada screenshot.
     * 2) Fallback ke metode lama (landmark + Nominatim) bila tahap 1 kurang.
     */
    $coords = []; $note = ''; $places = []; $failures = [];

    // -------- Tahap 1: koordinat langsung --------
    $promptCoord =
        "Anda menerima SCREENSHOT peta (Strava/Google Maps/Komoot/dll) yang menampilkan satu RUTE olahraga ".
        "(biasanya garis berwarna biru/merah/oranye) di INDONESIA. ".
        "TUGAS: Perkirakan koordinat [lat,lng] dari MINIMAL 15 dan maksimal 40 titik mengikuti garis rute, ".
        "berurutan dari START ke FINISH. Manfaatkan: skala/legenda peta, kompas, nama jalan, kontur kota, ".
        "serta posisi marker start (lingkaran hijau) dan finish (lingkaran hitam/bendera). ".
        "Jika peta tidak menampilkan koordinat eksplisit, perkirakan se-realistis mungkin ".
        "(boleh meleset 100-300 m, ini hanya untuk animasi flyover, bukan navigasi). ".
        "Balas HANYA JSON valid tanpa fence: ".
        '{"coords":[[lat,lng],[lat,lng]], "area":"<kota/kabupaten>", "note":"<1 kalimat>"}'.
        ($hint!=='' ? " Petunjuk area dari pengguna: $hint" : "");
    $g1 = ai_vision($promptCoord, $_FILES['image']['tmp_name'],
            ['json'=>true,'temperature'=>0.1,'max_tokens'=>4096]);
    if ($g1['ok']) {
        $obj1 = ai_extract_json($g1['text']);
        $raw  = $obj1['coords'] ?? [];
        $note = (string)($obj1['note'] ?? '');
        if (is_array($raw)) {
            foreach ($raw as $pt) {
                if (!is_array($pt) || count($pt) < 2) continue;
                $la = (float)$pt[0]; $ln = (float)$pt[1];
                if ($la >= -11.5 && $la <= 6.5 && $ln >= 94.5 && $ln <= 141.5) {
                    $coords[] = [$la, $ln];
                }
            }
        }
        if (count($coords) >= 10) {
            echo json_encode([
                'ok'=>true,
                'coords'=>$coords,
                'note'=>($note ?: 'Estimasi koordinat langsung dari Gemini Vision.').' Sumber: AI vision (perkiraan).',
                'mode'=>'direct_coords',
            ]); exit;
        }
    }

    // -------- Tahap 2: fallback landmark + Nominatim --------
    $prompt = "Anda menerima SCREENSHOT PETA / STRAVA yang menampilkan sebuah RUTE (lari/jalan/sepeda) di INDONESIA. "
            . "Identifikasi 8-15 LANDMARK / nama jalan / persimpangan / titik penting secara BERURUTAN sepanjang rute. "
            . "WAJIB cantumkan nama kota/kabupaten Indonesia + ', Indonesia' pada tiap entri agar geocoding tidak salah negara. "
            . "Balas HANYA JSON valid tanpa fence: {\"places\":[\"Nama lengkap, Kota, Indonesia\"], \"note\":\"<1 kalimat>\"}. "
            . ($hint!=='' ? "Petunjuk area dari pengguna: $hint" : "");
    $g = ai_vision($prompt, $_FILES['image']['tmp_name'],
            ['json'=>true,'temperature'=>0.2,'max_tokens'=>4096]);
    if (!$g['ok']) { echo json_encode(['ok'=>false,'err'=>'Gemini: '.$g['err'].($g1['ok']?'':' | tahap1: '.$g1['err'])]); exit; }
    $obj = ai_extract_json($g['text']);
    $places = is_array($obj['places'] ?? null) ? $obj['places'] : [];
    if (count($places) < 2) {
        $lines = preg_split('/\r?\n/', (string)$g['text']);
        foreach ($lines as $ln) {
            $ln = trim($ln);
            if ($ln === '' || strpbrk($ln, '{}[]":`') !== false) continue;
            $ln = trim(preg_replace('/^[\-\*\d\.\)]+\s*/','', $ln));
            if (strlen($ln) > 4 && strlen($ln) < 120 && substr_count($ln, ' ') < 12) $places[] = $ln;
        }
    }
    $places = array_map(function($p){
        $p = trim((string)$p);
        if ($p !== '' && stripos($p, 'indonesia') === false) $p .= ', Indonesia';
        return $p;
    }, $places);
    $places = array_slice(array_values(array_filter(array_unique($places))), 0, 12);
    if (count($places) < 2) { echo json_encode(['ok'=>false,'err'=>'AI tidak menemukan landmark. Raw: '.substr($g['text'],0,200)]); exit; }
    $coords = []; $failures = [];
    foreach ($places as $place) {
        $q = trim((string)$place); if ($q==='') continue;
        $parts = array_map('trim', explode(',', $q));
        $tail2 = count($parts)>=2 ? implode(', ', array_slice($parts,-2)) : $q;
        $kota  = count($parts)>=2 ? $parts[count($parts)-2] : $parts[0];
        $variants = [
            ['q'=>$q,     'cc'=>'id'],
            ['q'=>$q,     'cc'=>''],
            ['q'=>$tail2, 'cc'=>'id'],
            ['q'=>$kota.', Indonesia', 'cc'=>'id'],
        ];
        $found = null;
        foreach ($variants as $v) {
            if ($v['q']==='') continue;
            $url = 'https://nominatim.openstreetmap.org/search?format=json&limit=1'
                 . ($v['cc']?'&countrycodes='.$v['cc']:'') . '&q='.urlencode($v['q']);
            $ch2 = curl_init($url);
            $copt = [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>10,
                CURLOPT_USERAGENT=>'SportAppBot/1.0 (admin@local)',
                CURLOPT_HTTPHEADER=>['Accept-Language: id,en']];
            if (getenv('GEMINI_INSECURE_SSL') === '1') { $copt[CURLOPT_SSL_VERIFYPEER]=false; $copt[CURLOPT_SSL_VERIFYHOST]=0; }
            curl_setopt_array($ch2, $copt);
            $r2 = curl_exec($ch2); curl_close($ch2);
            $arr = json_decode($r2 ?: '[]', true);
            if (is_array($arr) && !empty($arr[0]['lat'])) {
                $found = [(float)$arr[0]['lat'], (float)$arr[0]['lon']];
                break;
            }
            usleep(550*1000);
        }
        if ($found) $coords[] = $found; else $failures[] = $q;
    }
    if (count($coords) < 2) {
        echo json_encode(['ok'=>false,'err'=>'Geocoding gagal untuk: '.implode(' | ', $failures).'. Coba petunjuk area yg lebih spesifik.']); exit;
    }
    echo json_encode([
        'ok'=>true,
        'coords'=>$coords,
        'places'=>$places,
        'note'=>($obj['note'] ?? '').' (mode landmark + Nominatim)',
        'gagal_geocode'=>$failures,
        'mode'=>'landmark_geocode',
    ]);
    exit;
}

/* ===== Revisi 18 Juni 2026 — AI Lyrics untuk Flyover Subtitle =====
 * Input: title (judul lagu) + artist (opsional) + duration (detik audio).
 * Output: JSON { ok, lines:[{t:detik, line:"..."}], note }.
 */
if ($a === 'ai_song_lyrics') {
    rate_limit_or_die('ai_lyric:'.$uid, 20, 600);
    $title = trim((string)($_POST['title'] ?? ''));
    $artist= trim((string)($_POST['artist'] ?? ''));
    $dur   = max(5, min(900, (int)($_POST['duration'] ?? 180)));
    if ($title === '') { echo json_encode(['ok'=>false,'err'=>'judul kosong']); exit; }
    $prompt = "Anda adalah ahli musik. Berikan LIRIK lagu berjudul \"$title\""
            . ($artist!=='' ? " oleh \"$artist\"" : "")
            . " untuk ditampilkan sebagai SUBTITLE karaoke pada video sepanjang $dur detik. "
            . "Bagi lirik menjadi BARIS-BARIS pendek (maks 60 karakter/baris). "
            . "Perkirakan timing tiap baris: distribusikan secara wajar dari 0 hingga $dur detik "
            . "(mungkin intro 5-12 detik kosong, lalu verse, chorus, dst). "
            . "Jika lagu instrumental atau tidak Anda kenali, gunakan placeholder kreatif singkat (mis. \"Instrumental\"). "
            . "Balas HANYA JSON valid tanpa fence: "
            . '{"lines":[{"t":0,"line":"..."},{"t":12.5,"line":"..."}], "note":"<sumber/catatan>"}';
    $g = ai_chat($prompt, ['json'=>true,'temperature'=>0.3,'max_tokens'=>2048]);
    if (!$g['ok']) { echo json_encode(['ok'=>false,'err'=>'Gemini: '.$g['err']]); exit; }
    $obj = ai_extract_json($g['text']);
    $lines = is_array($obj['lines'] ?? null) ? $obj['lines'] : [];
    $out = [];
    foreach ($lines as $l) {
        if (!is_array($l)) continue;
        $t = (float)($l['t'] ?? -1);
        $tx = trim((string)($l['line'] ?? ''));
        if ($tx === '' || $t < 0 || $t > $dur+5) continue;
        $out[] = ['t'=>$t, 'line'=>$tx];
    }
    if (!$out) { echo json_encode(['ok'=>false,'err'=>'Lirik tidak ditemukan. Raw: '.substr($g['text'],0,200)]); exit; }
    usort($out, function($a,$b){ return $a['t'] <=> $b['t']; });
    echo json_encode(['ok'=>true,'lines'=>$out,'note'=>(string)($obj['note'] ?? '')]);
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

    /* ============================================================
     * Revisi Juli 2026 R14 — Auto-isi Upload Harian dari hasil lari.
     * Setelah sesi lari selesai, otomatis buat baris upload_harian
     * dengan referensi ke run_sessions.id agar bisa ditampilkan
     * sebagai peta GPX Mapbox pada tabel Aktivitas Saya di upload.php.
     * ============================================================ */
    $upload_id = 0;
    try {
        @db_exec("ALTER TABLE upload_harian ADD COLUMN IF NOT EXISTS gpx_session_id BIGINT");
        @db_exec("CREATE INDEX IF NOT EXISTS upload_harian_gpx_idx ON upload_harian(gpx_session_id)");
        if ($sid > 0 && $jarak > 0 && $dur > 0) {
            $km  = $jarak / 1000.0;
            $mnt = (int)round($dur / 60.0);
            $paceStr = '';
            if ($km > 0.05) {
                $paceSec = (int)round($dur / $km);
                $pm = intdiv($paceSec, 60);
                $ps = $paceSec % 60;
                $paceStr = $pm."'".str_pad((string)$ps,2,'0',STR_PAD_LEFT).'"/km';
            }
            // Cegah duplikat untuk sesi yang sama
            $exists = db_one("SELECT id FROM upload_harian WHERE user_id=$1 AND gpx_session_id=$2", [$uid, $sid]);
            if (!$exists) {
                $r = pg_query_params(db(),
                  "INSERT INTO upload_harian(user_id,tanggal,jenis,durasi_menit,jarak_km,kalori,pace,deskripsi,file_path,gdrive_url,gpx_session_id)
                   VALUES($1,$2,'Jogging',$3,$4,$5,$6,$7,NULL,NULL,$8) RETURNING id",
                   [$uid, date('Y-m-d'), $mnt, round($km,2), $kal, $paceStr,
                    'Diisi otomatis dari Tracking Jalur (GPX #'.$sid.').', $sid]);
                $upload_id = (int)(pg_fetch_row($r)[0] ?? 0);
            } else {
                $upload_id = (int)$exists['id'];
            }
        }
    } catch (Throwable $e) { /* jangan blok stop */ }

    echo json_encode(['ok'=>true, 'upload_id'=>$upload_id]); exit;
}

if ($a === 'delete') {
    $sid = (int)($_POST['session_id'] ?? 0);
    if ($sid > 0) {
        // Pastikan sesi memang milik user ini sebelum kaskade hapus.
        $own = db_one("SELECT id FROM run_sessions WHERE id=$1 AND user_id=$2", [$sid, $uid]);
        if ($own) {
            db_exec("DELETE FROM run_points WHERE session_id=$1", [$sid]);
            // Revisi Juli 2026 R42 — Tombol "Buang" pada halaman Finish
            // harus benar-benar membuang aktivitas. Action=stop otomatis
            // menyisipkan baris upload_harian (gpx_session_id=sid); baris
            // itu wajib ikut dihapus supaya tidak muncul di upload.php
            // maupun riwayat.php.
            @db_exec("DELETE FROM upload_harian WHERE user_id=$1 AND gpx_session_id=$2", [$uid, $sid]);
            db_exec("DELETE FROM run_sessions WHERE id=$1 AND user_id=$2", [$sid, $uid]);
        }
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

// ===== Revisi 17 Juni 2026 Part I: Edit (rename / publik toggle) rute tersimpan =====
if ($a === 'route_update') {
    $id   = (int)($_POST['id'] ?? 0);
    $nama = trim((string)($_POST['nama'] ?? ''));
    if ($nama === '') $nama = 'Rute';
    $elev = (string)($_POST['elevasi_pref'] ?? 'apa-saja');
    $surf = (string)($_POST['surface_pref'] ?? 'apa-saja');
    $pub  = ($_POST['is_public'] ?? '0') === '1';
    if ($id>0) {
        db_exec("UPDATE run_routes SET nama=$1, elevasi_pref=$2, surface_pref=$3, is_public=$4 WHERE id=$5 AND user_id=$6",
            [$nama,$elev,$surf,$pub?'t':'f',$id,$uid]);
    }
    echo json_encode(['ok'=>true]); exit;
}

echo json_encode(['ok'=>false]);
