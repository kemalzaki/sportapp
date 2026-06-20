<?php
require __DIR__.'/../config/db.php';
require __DIR__.'/../includes/auth.php';
require __DIR__.'/../includes/helpers.php';
require_role('admin');
$pageTitle='Manajemen Tempat';

/* ============================================================
 * Revisi 20 Juni 2026 R3 — Tambah kolom rute hiking/camping:
 *   gpx_path TEXT, parkir_info TEXT, run_route_id BIGINT
 * Idempotent (ALTER ... IF NOT EXISTS) — aman dijalankan berulang.
 * ============================================================ */
@db_exec("ALTER TABLE tempat ADD COLUMN IF NOT EXISTS gpx_path TEXT");
@db_exec("ALTER TABLE tempat ADD COLUMN IF NOT EXISTS parkir_info TEXT");
@db_exec("ALTER TABLE tempat ADD COLUMN IF NOT EXISTS run_route_id BIGINT");

/* Jenis olahraga yang dianggap "outdoor trail" (butuh GPX) */
function is_trail_jenis($namaJenis){
    $n = mb_strtolower(trim((string)$namaJenis));
    return in_array($n, ['hiking','camping'], true);
}

/* Helper: simpan upload GPX, mengembalikan path relatif atau null */
function save_gpx_upload($field='gpx_file'){
    if (empty($_FILES[$field]) || ($_FILES[$field]['error'] ?? 4) !== 0) return null;
    $tmp = $_FILES[$field]['tmp_name'];
    $orig = $_FILES[$field]['name'] ?? 'route.gpx';
    $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
    if ($ext !== 'gpx') return null;
    $sz = (int)($_FILES[$field]['size'] ?? 0);
    if ($sz <= 0 || $sz > 8 * 1024 * 1024) return null; // maks 8 MB
    $head = @file_get_contents($tmp, false, null, 0, 512);
    if (!$head || stripos($head, '<gpx') === false) return null;
    $dir = __DIR__.'/../uploads/gpx';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $name = 'gpx_'.date('Ymd_His').'_'.bin2hex(random_bytes(3)).'.gpx';
    $dest = $dir.'/'.$name;
    if (!@move_uploaded_file($tmp, $dest)) return null;
    return '/uploads/gpx/'.$name;
}

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    $a = $_POST['_action'] ?? 'create';
    if ($a==='delete') {
        // Hapus juga file GPX yg terkait bila ada
        $old = db_one("SELECT gpx_path FROM tempat WHERE id=$1", [(int)$_POST['id']]);
        if ($old && !empty($old['gpx_path'])) {
            $p = __DIR__.'/..'.$old['gpx_path'];
            if (is_file($p)) @unlink($p);
        }
        db_exec("DELETE FROM tempat WHERE id=$1", [(int)$_POST['id']]);
    } elseif ($a==='edit') {
        $jenisId = ($_POST['jenis_id'] ?? '') !== '' ? (int)$_POST['jenis_id'] : null;
        $jenisRow = $jenisId ? db_one("SELECT nama FROM jenis_olahraga WHERE id=$1", [$jenisId]) : null;
        $isTrail = $jenisRow ? is_trail_jenis($jenisRow['nama']) : false;

        // Khusus hiking/camping: kosongkan lat/lng. Selain itu: pertahankan / update.
        if ($isTrail) { $lat = null; $lng = null; }
        else {
            $lat = ($_POST['lat'] ?? '') !== '' ? (float)$_POST['lat'] : null;
            $lng = ($_POST['lng'] ?? '') !== '' ? (float)$_POST['lng'] : null;
        }
        $tampil = !empty($_POST['tampil_booking']) ? 'true' : 'false';

        // GPX: kalau ada upload baru, ganti; kalau ada flag hapus_gpx, hapus.
        $newGpx = $isTrail ? save_gpx_upload('gpx_file') : null;
        $hapusGpx = !empty($_POST['hapus_gpx']);
        $gpxSql = ''; $extraParams = [];
        $existing = db_one("SELECT gpx_path FROM tempat WHERE id=$1", [(int)$_POST['id']]);
        $gpxFinal = $existing['gpx_path'] ?? null;
        if ($newGpx) {
            if ($gpxFinal) { $p = __DIR__.'/..'.$gpxFinal; if (is_file($p)) @unlink($p); }
            $gpxFinal = $newGpx;
        } elseif ($hapusGpx && $gpxFinal) {
            $p = __DIR__.'/..'.$gpxFinal; if (is_file($p)) @unlink($p);
            $gpxFinal = null;
        }
        if (!$isTrail) { $gpxFinal = null; }

        $parkir = $isTrail ? (trim($_POST['parkir_info'] ?? '') ?: null) : null;
        $runRouteId = ($isTrail && ($_POST['run_route_id'] ?? '') !== '') ? (int)$_POST['run_route_id'] : null;

        db_exec("UPDATE tempat SET nama=$1, alamat=$2, harga_lapang=$3, harga_per_jam=$4,
                   harga_tiket=$5, harga_parkir=$6, status_booking=$7, catatan=$8,
                   pic_user_id=$9, kontak_wa=$10, jenis_id=$11, lat=$12, lng=$13, tampil_booking=$14,
                   gpx_path=$15, parkir_info=$16, run_route_id=$17
                 WHERE id=$18",
            [trim($_POST['nama']), trim($_POST['alamat'] ?? ''),
             (float)($_POST['harga_lapang'] ?? 0), (float)($_POST['harga_per_jam'] ?? 0),
             (float)($_POST['harga_tiket'] ?? 0), (float)($_POST['harga_parkir'] ?? 0),
             $_POST['status_booking'] ?? 'tersedia', trim($_POST['catatan'] ?? ''),
             ($_POST['pic_user_id'] ?? '') !== '' ? (int)$_POST['pic_user_id'] : null,
             trim($_POST['kontak_wa'] ?? '') ?: null,
             $jenisId, $lat, $lng, $tampil,
             $gpxFinal, $parkir, $runRouteId,
             (int)$_POST['id']]);
    } elseif ($a==='toggle_booking') {
        db_exec("UPDATE tempat SET tampil_booking = NOT tampil_booking WHERE id=$1", [(int)$_POST['id']]);
    } else {
        $jenisId = ($_POST['jenis_id'] ?? '') !== '' ? (int)$_POST['jenis_id'] : null;
        $jenisRow = $jenisId ? db_one("SELECT nama FROM jenis_olahraga WHERE id=$1", [$jenisId]) : null;
        $isTrail = $jenisRow ? is_trail_jenis($jenisRow['nama']) : false;
        if ($isTrail) { $lat = null; $lng = null; }
        else {
            $lat = ($_POST['lat'] ?? '') !== '' ? (float)$_POST['lat'] : null;
            $lng = ($_POST['lng'] ?? '') !== '' ? (float)$_POST['lng'] : null;
        }
        $tampil = !empty($_POST['tampil_booking']) ? 'true' : 'false';
        $gpxFinal  = $isTrail ? save_gpx_upload('gpx_file') : null;
        $parkir    = $isTrail ? (trim($_POST['parkir_info'] ?? '') ?: null) : null;
        $runRouteId= ($isTrail && ($_POST['run_route_id'] ?? '') !== '') ? (int)$_POST['run_route_id'] : null;

        db_exec("INSERT INTO tempat(nama,alamat,harga_lapang,harga_per_jam,harga_tiket,harga_parkir,status_booking,catatan,pic_user_id,kontak_wa,jenis_id,lat,lng,tampil_booking,gpx_path,parkir_info,run_route_id)
                 VALUES($1,$2,$3,$4,$5,$6,$7,$8,$9,$10,$11,$12,$13,$14,$15,$16,$17)",
            [trim($_POST['nama']), trim($_POST['alamat'] ?? ''),
             (float)($_POST['harga_lapang'] ?? 0), (float)($_POST['harga_per_jam'] ?? 0),
             (float)($_POST['harga_tiket'] ?? 0), (float)($_POST['harga_parkir'] ?? 0),
             $_POST['status_booking'] ?? 'tersedia', trim($_POST['catatan'] ?? ''),
             ($_POST['pic_user_id'] ?? '') !== '' ? (int)$_POST['pic_user_id'] : null,
             trim($_POST['kontak_wa'] ?? '') ?: null,
             $jenisId, $lat, $lng, $tampil,
             $gpxFinal, $parkir, $runRouteId]);
    }
    header('Location: tempat.php'); exit;
}

// ===== Filter & Sort =====
$q       = trim($_GET['q'] ?? '');
$fStatus = $_GET['status'] ?? '';
$fJenis  = $_GET['jenis']  ?? '';
$sort    = $_GET['sort']   ?? 'nama';
$dir     = strtolower($_GET['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

$allowSort = ['nama'=>'t.nama','status'=>'t.status_booking','harga_lapang'=>'t.harga_lapang',
              'harga_per_jam'=>'t.harga_per_jam','harga_tiket'=>'t.harga_tiket','harga_parkir'=>'t.harga_parkir',
              'pic'=>'u.nama','jenis'=>'jo.nama','created_at'=>'t.created_at'];
$sortSql = $allowSort[$sort] ?? 't.nama';

$where = []; $params = []; $i = 1;
if ($q !== '')      { $where[] = "(t.nama ILIKE \$$i OR t.alamat ILIKE \$$i)"; $params[] = "%$q%"; $i++; }
if ($fStatus !== ''){ $where[] = "t.status_booking = \$$i"; $params[] = $fStatus; $i++; }
if ($fJenis !== '') { $where[] = "t.jenis_id = \$$i"; $params[] = (int)$fJenis; $i++; }
$whereSql = $where ? ('WHERE '.implode(' AND ',$where)) : '';

$rows = db_all("SELECT t.*, u.nama AS pic_nama, u.foto_url AS pic_foto, jo.nama AS jenis_nama
                FROM tempat t
                LEFT JOIN users u ON u.id = t.pic_user_id
                LEFT JOIN jenis_olahraga jo ON jo.id = t.jenis_id
                $whereSql
                ORDER BY $sortSql $dir NULLS LAST", $params);

$admins = db_all("SELECT id, nama FROM users WHERE role='admin' ORDER BY nama");
$jenisList = db_all("SELECT id, nama FROM jenis_olahraga ORDER BY nama");
$statuses = ['tersedia','booked','renovasi','tutup'];

/* Rute tersimpan milik admin (untuk dropdown hiking/camping) */
$adminId = (int)($_SESSION['user_id'] ?? ($_SESSION['uid'] ?? 0));
$savedRoutes = [];
try {
  $savedRoutes = db_all("SELECT id, nama, jarak_m FROM run_routes
                         WHERE user_id=$1 OR is_public = true
                         ORDER BY id DESC LIMIT 100", [$adminId]);
} catch (Throwable $e) { $savedRoutes = []; }

/* Set ID jenis yang trail untuk dipakai di JS */
$trailJenisIds = [];
foreach ($jenisList as $jn) {
    if (is_trail_jenis($jn['nama'])) $trailJenisIds[] = (int)$jn['id'];
}

function sort_link($key, $label, $sort, $dir){
    $nextDir = ($sort===$key && $dir==='asc') ? 'desc' : 'asc';
    $arrow = $sort===$key ? ($dir==='asc' ? ' <i class="bi bi-caret-up-fill small"></i>' : ' <i class="bi bi-caret-down-fill small"></i>') : '';
    $qs = $_GET; $qs['sort']=$key; $qs['dir']=$nextDir;
    return '<a class="text-decoration-none text-dark" href="?'.http_build_query($qs).'">'.$label.$arrow.'</a>';
}

include __DIR__.'/../includes/header.php'; ?>
<script>window.TRAIL_JENIS_IDS = <?= json_encode($trailJenisIds) ?>;</script>

<h2 class="mb-3"><i class="bi bi-geo-alt text-primary"></i> Manajemen Tempat</h2>
<p class="text-muted">Daftar lapangan / GOR beserta detail biaya, PIC admin, kontak WA, dan jenis olahraga.</p>

<div class="card shadow-sm mb-3"><div class="card-header"><i class="bi bi-plus-circle me-1 text-primary"></i> Tambah Tempat</div>
<div class="card-body">
  <form method="post" class="row g-2" enctype="multipart/form-data" id="newForm" data-trail-form="1">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <input type="hidden" name="_action" value="create">
    <div class="col-md-3"><label class="form-label small fw-semibold">Nama Tempat</label><input class="form-control" name="nama" required></div>
    <div class="col-md-3"><label class="form-label small fw-semibold">Alamat</label><input class="form-control" name="alamat"></div>
    <div class="col-md-3"><label class="form-label small fw-semibold">PIC (Admin)</label>
      <select class="form-select" name="pic_user_id">
        <option value="">— pilih PIC —</option>
        <?php foreach($admins as $ad): ?><option value="<?= (int)$ad['id'] ?>"><?= htmlspecialchars($ad['nama']) ?></option><?php endforeach; ?>
      </select></div>
    <div class="col-md-3"><label class="form-label small fw-semibold"><i class="bi bi-whatsapp text-success"></i> Kontak WA</label>
      <input class="form-control" name="kontak_wa" placeholder="cth: 08123456789"></div>

    <div class="col-md-3"><label class="form-label small fw-semibold">Jenis Olahraga</label>
      <select class="form-select" name="jenis_id">
        <option value="">— pilih jenis —</option>
        <?php foreach($jenisList as $jn): ?><option value="<?= (int)$jn['id'] ?>"><?= htmlspecialchars($jn['nama']) ?></option><?php endforeach; ?>
      </select></div>
    <div class="col-md-2"><label class="form-label small fw-semibold">Harga Lapang</label><input type="number" step="0.01" min="0" class="form-control" name="harga_lapang" value="0"></div>
    <div class="col-md-2"><label class="form-label small fw-semibold">Harga / Jam</label><input type="number" step="0.01" min="0" class="form-control" name="harga_per_jam" value="0"></div>
    <div class="col-md-2"><label class="form-label small fw-semibold">Harga Tiket</label><input type="number" step="0.01" min="0" class="form-control" name="harga_tiket" value="0"></div>
    <div class="col-md-2"><label class="form-label small fw-semibold">Harga Parkir</label><input type="number" step="0.01" min="0" class="form-control" name="harga_parkir" value="0"></div>

    <div class="col-md-3"><label class="form-label small fw-semibold">Status</label>
      <select class="form-select" name="status_booking">
        <?php foreach($statuses as $s): ?><option><?= $s ?></option><?php endforeach; ?>
      </select></div>
    <div class="col-md-6"><label class="form-label small fw-semibold">Catatan</label><input class="form-control" name="catatan" placeholder="cth: butuh DP, jam buka, dll"></div>
    <div class="col-md-3 d-flex align-items-end"><div class="form-check">
      <input class="form-check-input" type="checkbox" name="tampil_booking" id="newTampil" value="1">
      <label class="form-check-label small fw-semibold" for="newTampil"><i class="bi bi-calendar2-check text-primary"></i> Tampilkan di Booking</label>
    </div></div>

    <!-- Revisi 20 Juni 2026 R3 — Panel khusus Hiking/Camping -->
    <div class="col-12 trail-panel" style="display:none">
      <div class="border rounded p-2 bg-success-subtle">
        <div class="fw-bold small mb-2"><i class="bi bi-tree text-success"></i> Khusus Hiking / Camping — Rute Perjalanan</div>
        <div class="row g-2">
          <div class="col-md-6">
            <label class="form-label small fw-semibold"><i class="bi bi-cloud-upload"></i> Upload File .GPX (rute)</label>
            <input type="file" name="gpx_file" class="form-control" accept=".gpx,application/gpx+xml">
            <small class="text-muted">Maks 8 MB. Bisa diekspor dari Strava, Google My Maps, dll.</small>
          </div>
          <div class="col-md-6">
            <label class="form-label small fw-semibold"><i class="bi bi-signpost-2"></i> Atau pilih Rute Tersimpan (run.php)</label>
            <select class="form-select" name="run_route_id">
              <option value="">— tidak memakai rute tersimpan —</option>
              <?php foreach($savedRoutes as $rr): ?>
                <option value="<?= (int)$rr['id'] ?>"><?= htmlspecialchars($rr['nama']) ?> · <?= round(((float)$rr['jarak_m'])/1000,2) ?> km</option>
              <?php endforeach; ?>
            </select>
            <small class="text-muted">Bila keduanya diisi, file GPX yang dipakai untuk visualisasi peta.</small>
          </div>
          <div class="col-12">
            <label class="form-label small fw-semibold"><i class="bi bi-p-square text-primary"></i> Tempat Parkir yang Disarankan</label>
            <textarea name="parkir_info" rows="2" class="form-control" placeholder="cth: Parkir di basecamp Cibodas, biaya Rp 10.000 / motor, jaga 24 jam."></textarea>
          </div>
        </div>
      </div>
    </div>

    <!-- Lat/Lng + Map Picker (selain hiking/camping) -->
    <div class="col-12 nontrail-section">
    <div class="row g-2">
    <div class="col-md-3"><label class="form-label small fw-semibold"><i class="bi bi-geo-alt-fill text-danger"></i> Latitude</label>
      <input class="form-control" name="lat" id="newLat" placeholder="cth: -6.9214" inputmode="decimal"></div>
    <div class="col-md-3"><label class="form-label small fw-semibold"><i class="bi bi-geo-alt-fill text-danger"></i> Longitude</label>
      <input class="form-control" name="lng" id="newLng" placeholder="cth: 107.6072" inputmode="decimal"></div>
    <div class="col-md-6"><label class="form-label small fw-semibold">Cari lokasi (Nominatim)</label>
      <div class="input-group"><input class="form-control" id="newPlaceQuery" placeholder="cth: GBK Senayan"><button type="button" id="newSearchBtn" class="btn btn-outline-primary"><i class="bi bi-search"></i></button></div>
      <div id="newSearchResults" class="list-group mt-1" style="max-height:140px;overflow:auto;"></div>
    </div>
    <div class="col-12"><label class="form-label small fw-semibold">Pin lokasi (klik / drag marker)</label>
      <div id="newMap" style="height:260px;border-radius:8px;border:1px solid #ddd;"></div>
      <small class="text-muted">Klik peta untuk memindahkan pin. Lat/Lng akan auto-terisi.</small>
    </div>
    </div>
    </div>

    <div class="col-12"><button class="btn btn-primary"><i class="bi bi-plus-lg"></i> Tambah</button></div>
  </form>
</div></div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin=""/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script>
function setupMapPicker(mapId, latId, lngId, queryId, btnId, resultsId, initialLat, initialLng){
  if(typeof L==='undefined') return;
  var el=document.getElementById(mapId); if(!el || el.dataset.ready) return;
  el.dataset.ready='1';
  var lat=parseFloat(initialLat)|| -6.2, lng=parseFloat(initialLng)||106.816666;
  var map=L.map(mapId).setView([lat,lng], initialLat?15:11);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'© OpenStreetMap'}).addTo(map);
  setTimeout(function(){ map.invalidateSize(); }, 250);
  var marker=L.marker([lat,lng],{draggable:true}).addTo(map);
  function setLL(ll){
    document.getElementById(latId).value=ll.lat.toFixed(6);
    document.getElementById(lngId).value=ll.lng.toFixed(6);
  }
  if(initialLat && initialLng) setLL({lat:lat,lng:lng});
  marker.on('dragend',function(e){ setLL(e.target.getLatLng()); });
  map.on('click',function(e){ marker.setLatLng(e.latlng); setLL(e.latlng); });
  var qEl=document.getElementById(queryId), rEl=document.getElementById(resultsId);
  function doSearch(){
    var q=qEl.value.trim(); if(!q) return;
    rEl.innerHTML='<div class="list-group-item small text-muted">Mencari...</div>';
    fetch('https://nominatim.openstreetmap.org/search?format=json&limit=6&q='+encodeURIComponent(q),{headers:{'Accept':'application/json'}})
      .then(function(r){return r.json();})
      .then(function(items){
        if(!items.length){ rEl.innerHTML='<div class="list-group-item small text-muted">Tidak ditemukan.</div>'; return; }
        rEl.innerHTML='';
        items.forEach(function(it){
          var a=document.createElement('a'); a.href='#'; a.className='list-group-item list-group-item-action small';
          a.textContent=it.display_name;
          a.onclick=function(ev){ ev.preventDefault();
            var ll={lat:parseFloat(it.lat),lng:parseFloat(it.lon)};
            map.setView([ll.lat,ll.lng],16); marker.setLatLng(ll); setLL(ll); rEl.innerHTML='';
          };
          rEl.appendChild(a);
        });
      }).catch(function(){ rEl.innerHTML='<div class="list-group-item small text-danger">Gagal mencari.</div>'; });
  }
  document.getElementById(btnId).addEventListener('click', doSearch);
  qEl.addEventListener('keydown',function(e){ if(e.key==='Enter'){ e.preventDefault(); doSearch(); } });
}
document.addEventListener('DOMContentLoaded', function(){
  setupMapPicker('newMap','newLat','newLng','newPlaceQuery','newSearchBtn','newSearchResults','','');
  // setup edit map picker ketika modal dibuka
  document.querySelectorAll('.modal[id^="tpE"]').forEach(function(m){
    m.addEventListener('shown.bs.modal', function(){
      var id=m.id.replace('tpE','');
      setupMapPicker('editMap'+id,'editLat'+id,'editLng'+id,'editPlaceQuery'+id,'editSearchBtn'+id,'editSearchResults'+id,
        document.getElementById('editLat'+id).value, document.getElementById('editLng'+id).value);
    });
  });
});
</script>

<!-- ===== Filter & Sort ===== -->
<div class="card shadow-sm mb-3"><div class="card-body">
  <form class="row g-2 align-items-end" method="get">
    <div class="col-md-4"><label class="form-label small fw-semibold">Cari nama / alamat</label>
      <input class="form-control form-control-sm" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="🔍 ketik kata kunci...">
    </div>
    <div class="col-md-3"><label class="form-label small fw-semibold">Status</label>
      <select class="form-select form-select-sm" name="status">
        <option value="">Semua</option>
        <?php foreach($statuses as $s): ?><option value="<?= $s ?>" <?= $fStatus===$s?'selected':'' ?>><?= $s ?></option><?php endforeach; ?>
      </select></div>
    <div class="col-md-3"><label class="form-label small fw-semibold">Jenis Olahraga</label>
      <select class="form-select form-select-sm" name="jenis">
        <option value="">Semua</option>
        <?php foreach($jenisList as $jn): ?><option value="<?= (int)$jn['id'] ?>" <?= (string)$fJenis===(string)$jn['id']?'selected':'' ?>><?= htmlspecialchars($jn['nama']) ?></option><?php endforeach; ?>
      </select></div>
    <div class="col-md-2 d-flex gap-1">
      <button class="btn btn-sm btn-primary w-100"><i class="bi bi-funnel"></i> Filter</button>
      <a class="btn btn-sm btn-outline-secondary" href="tempat.php" title="Reset"><i class="bi bi-x-lg"></i></a>
    </div>
  </form>
</div></div>

<div class="card shadow-sm"><div class="table-responsive"><table class="table table-hover mb-0 align-middle" data-paginate="10">
  <thead><tr>
    <th>#</th>
    <th><?= sort_link('nama','Nama',$sort,$dir) ?></th>
    <th>Alamat</th>
    <th><?= sort_link('jenis','Jenis',$sort,$dir) ?></th>
    <th><?= sort_link('pic','PIC',$sort,$dir) ?></th>
    <th>Kontak</th>
    <th class="text-end"><?= sort_link('harga_lapang','Lapang',$sort,$dir) ?></th>
    <th class="text-end"><?= sort_link('harga_per_jam','/Jam',$sort,$dir) ?></th>
    <th class="text-end"><?= sort_link('harga_tiket','Tiket',$sort,$dir) ?></th>
    <th class="text-end"><?= sort_link('harga_parkir','Parkir',$sort,$dir) ?></th>
    <th><?= sort_link('status','Status',$sort,$dir) ?></th>
    <th class="text-center">Booking</th>
    <th class="text-end">Aksi</th>
  </tr></thead><tbody>
  <?php foreach($rows as $i=>$r):
    $waDigits = preg_replace('/\D+/', '', $r['kontak_wa'] ?? '');
    if ($waDigits && str_starts_with($waDigits, '0')) $waDigits = '62'.substr($waDigits,1);
  ?>
    <tr>
      <td class="text-muted"><?= $i+1 ?></td>
      <td class="fw-semibold"><?= htmlspecialchars($r['nama']) ?>
        <?php if(!empty($r['catatan'])): ?><br><small class="text-muted"><?= htmlspecialchars($r['catatan']) ?></small><?php endif; ?>
      </td>
      <td class="text-muted small"><?= htmlspecialchars($r['alamat'] ?? '') ?: '—' ?></td>
      <td><?= $r['jenis_nama'] ? '<span class="pill">'.htmlspecialchars($r['jenis_nama']).'</span>' : '<span class="text-muted small">—</span>' ?></td>
      <td><?= $r['pic_nama'] ? user_name_with_avatar($r['pic_foto'] ?? null, $r['pic_nama'], false, 24) : '<span class="text-muted small">—</span>' ?></td>
      <td>
        <?php if($waDigits): ?>
          <a href="https://wa.me/<?= htmlspecialchars($waDigits) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-success">
            <i class="bi bi-whatsapp"></i> <?= htmlspecialchars($r['kontak_wa']) ?>
          </a>
        <?php else: ?><span class="text-muted small">—</span><?php endif; ?>
      </td>
      <td class="text-end small">Rp <?= number_format((float)$r['harga_lapang'],0,',','.') ?></td>
      <td class="text-end small">Rp <?= number_format((float)$r['harga_per_jam'],0,',','.') ?></td>
      <td class="text-end small">Rp <?= number_format((float)($r['harga_tiket'] ?? 0),0,',','.') ?></td>
      <td class="text-end small">Rp <?= number_format((float)($r['harga_parkir'] ?? 0),0,',','.') ?></td>
      <td>
        <?php $st=$r['status_booking']; $cls=$st==='tersedia'?'success':($st==='booked'?'warning':'secondary'); ?>
        <span class="badge bg-<?= $cls ?>"><?= htmlspecialchars($st) ?></span>
      </td>
      <td class="text-center">
        <?php $tampil = ($r['tampil_booking']==='t' || $r['tampil_booking']===true || $r['tampil_booking']==='1'); ?>
        <form method="post" class="d-inline">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="_action" value="toggle_booking"><input type="hidden" name="id" value="<?= $r['id'] ?>">
          <button class="btn btn-sm <?= $tampil?'btn-success':'btn-outline-secondary' ?>" title="<?= $tampil?'Tampil di Booking (klik untuk sembunyikan)':'Disembunyikan (klik untuk tampilkan)' ?>">
            <i class="bi bi-<?= $tampil?'calendar2-check':'calendar2-x' ?>"></i> <?= $tampil?'Ya':'Tidak' ?>
          </button>
        </form>
      </td>
      <td class="text-end text-nowrap">
        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#tpE<?= $r['id'] ?>"><i class="bi bi-pencil"></i></button>
        <form method="post" class="d-inline" onsubmit="return confirm('Hapus tempat ini?')">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="_action" value="delete"><input type="hidden" name="id" value="<?= $r['id'] ?>">
          <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
        </form>
      </td>
    </tr>
  <?php endforeach; if(!$rows): ?><tr><td colspan="13" class="text-center text-muted py-3">Tidak ada tempat sesuai filter.</td></tr><?php endif; ?>
  </tbody></table></div></div>

<?php foreach($rows as $r): ?>
<div class="modal fade" id="tpE<?= $r['id'] ?>" tabindex="-1"><div class="modal-dialog modal-dialog-centered modal-lg"><form method="post" class="modal-content" enctype="multipart/form-data" data-trail-form="1">
  <input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="_action" value="edit"><input type="hidden" name="id" value="<?= $r['id'] ?>">
  <div class="modal-header"><h5 class="modal-title"><i class="bi bi-pencil-square"></i> Edit Tempat</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <div class="row g-2">
      <div class="col-md-6"><label class="form-label small fw-semibold">Nama</label><input class="form-control" name="nama" value="<?= htmlspecialchars($r['nama']) ?>" required></div>
      <div class="col-md-6"><label class="form-label small fw-semibold">Alamat</label><input class="form-control" name="alamat" value="<?= htmlspecialchars($r['alamat'] ?? '') ?>"></div>
      <div class="col-md-6"><label class="form-label small fw-semibold">PIC (Admin)</label>
        <select class="form-select" name="pic_user_id">
          <option value="">— pilih PIC —</option>
          <?php foreach($admins as $ad): ?><option value="<?= (int)$ad['id'] ?>" <?= (string)$r['pic_user_id']===(string)$ad['id']?'selected':'' ?>><?= htmlspecialchars($ad['nama']) ?></option><?php endforeach; ?>
        </select></div>
      <div class="col-md-6"><label class="form-label small fw-semibold"><i class="bi bi-whatsapp text-success"></i> Kontak WA</label>
        <input class="form-control" name="kontak_wa" value="<?= htmlspecialchars($r['kontak_wa'] ?? '') ?>" placeholder="cth: 08123456789"></div>
      <div class="col-md-6"><label class="form-label small fw-semibold">Jenis Olahraga</label>
        <select class="form-select" name="jenis_id">
          <option value="">— pilih jenis —</option>
          <?php foreach($jenisList as $jn): ?><option value="<?= (int)$jn['id'] ?>" <?= (string)$r['jenis_id']===(string)$jn['id']?'selected':'' ?>><?= htmlspecialchars($jn['nama']) ?></option><?php endforeach; ?>
        </select></div>
      <div class="col-md-6"><label class="form-label small fw-semibold">Status</label>
        <select class="form-select" name="status_booking">
          <?php foreach($statuses as $s): ?><option <?= $s===$r['status_booking']?'selected':'' ?>><?= $s ?></option><?php endforeach; ?>
        </select></div>
      <div class="col-md-3"><label class="form-label small fw-semibold">Harga Lapang</label><input type="number" step="0.01" min="0" class="form-control" name="harga_lapang" value="<?= htmlspecialchars($r['harga_lapang']) ?>"></div>
      <div class="col-md-3"><label class="form-label small fw-semibold">Harga / Jam</label><input type="number" step="0.01" min="0" class="form-control" name="harga_per_jam" value="<?= htmlspecialchars($r['harga_per_jam']) ?>"></div>
      <div class="col-md-3"><label class="form-label small fw-semibold">Harga Tiket</label><input type="number" step="0.01" min="0" class="form-control" name="harga_tiket" value="<?= htmlspecialchars($r['harga_tiket'] ?? 0) ?>"></div>
      <div class="col-md-3"><label class="form-label small fw-semibold">Harga Parkir</label><input type="number" step="0.01" min="0" class="form-control" name="harga_parkir" value="<?= htmlspecialchars($r['harga_parkir'] ?? 0) ?>"></div>
      <div class="col-md-8"><label class="form-label small fw-semibold">Catatan</label><textarea class="form-control" name="catatan" rows="2"><?= htmlspecialchars($r['catatan'] ?? '') ?></textarea></div>
      <div class="col-md-4 d-flex align-items-end"><div class="form-check">
        <?php $tampilE = ($r['tampil_booking']==='t' || $r['tampil_booking']===true || $r['tampil_booking']==='1'); ?>
        <input class="form-check-input" type="checkbox" name="tampil_booking" id="editTampil<?= (int)$r['id'] ?>" value="1" <?= $tampilE?'checked':'' ?>>
        <label class="form-check-label small fw-semibold" for="editTampil<?= (int)$r['id'] ?>"><i class="bi bi-calendar2-check text-primary"></i> Tampilkan di Booking</label>
      </div></div>

      <!-- Revisi 20 Juni 2026 R3 — Panel Hiking/Camping (edit) -->
      <div class="col-12 trail-panel" style="display:none">
        <div class="border rounded p-2 bg-success-subtle">
          <div class="fw-bold small mb-2"><i class="bi bi-tree text-success"></i> Rute Perjalanan (Hiking / Camping)</div>
          <div class="row g-2">
            <div class="col-md-6">
              <label class="form-label small fw-semibold"><i class="bi bi-cloud-upload"></i> Ganti file .GPX</label>
              <input type="file" name="gpx_file" class="form-control" accept=".gpx,application/gpx+xml">
              <?php if (!empty($r['gpx_path'])): ?>
                <div class="small mt-1">
                  <a href="<?= htmlspecialchars($r['gpx_path']) ?>" download><i class="bi bi-file-earmark-arrow-down"></i> Unduh GPX saat ini</a>
                  <label class="ms-3"><input type="checkbox" name="hapus_gpx" value="1"> Hapus rute saat ini</label>
                </div>
              <?php else: ?>
                <small class="text-muted d-block">Belum ada rute terupload.</small>
              <?php endif; ?>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold"><i class="bi bi-signpost-2"></i> Rute Tersimpan (run.php)</label>
              <select class="form-select" name="run_route_id">
                <option value="">— tidak memakai rute tersimpan —</option>
                <?php foreach($savedRoutes as $rr): ?>
                  <option value="<?= (int)$rr['id'] ?>" <?= ((string)($r['run_route_id'] ?? '')===(string)$rr['id'])?'selected':'' ?>>
                    <?= htmlspecialchars($rr['nama']) ?> · <?= round(((float)$rr['jarak_m'])/1000,2) ?> km
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label small fw-semibold"><i class="bi bi-p-square text-primary"></i> Tempat Parkir yang Disarankan</label>
              <textarea name="parkir_info" rows="2" class="form-control"><?= htmlspecialchars($r['parkir_info'] ?? '') ?></textarea>
            </div>
          </div>
        </div>
      </div>

      <div class="col-12 nontrail-section">
      <div class="row g-2">
      <div class="col-md-3"><label class="form-label small fw-semibold"><i class="bi bi-geo-alt-fill text-danger"></i> Latitude</label>
        <input class="form-control" name="lat" id="editLat<?= (int)$r['id'] ?>" value="<?= htmlspecialchars($r['lat'] ?? '') ?>" inputmode="decimal"></div>
      <div class="col-md-3"><label class="form-label small fw-semibold"><i class="bi bi-geo-alt-fill text-danger"></i> Longitude</label>
        <input class="form-control" name="lng" id="editLng<?= (int)$r['id'] ?>" value="<?= htmlspecialchars($r['lng'] ?? '') ?>" inputmode="decimal"></div>
      <div class="col-md-6"><label class="form-label small fw-semibold">Cari lokasi</label>
        <div class="input-group"><input class="form-control" id="editPlaceQuery<?= (int)$r['id'] ?>" placeholder="cth: GBK Senayan"><button type="button" class="btn btn-outline-primary" id="editSearchBtn<?= (int)$r['id'] ?>"><i class="bi bi-search"></i></button></div>
        <div id="editSearchResults<?= (int)$r['id'] ?>" class="list-group mt-1" style="max-height:120px;overflow:auto;"></div>
      </div>
      <div class="col-12"><label class="form-label small fw-semibold">Pin lokasi</label>
        <div id="editMap<?= (int)$r['id'] ?>" style="height:260px;border-radius:8px;border:1px solid #ddd;"></div>
      </div>
      </div>
      </div>
    </div>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button><button class="btn btn-primary"><i class="bi bi-save"></i> Simpan</button></div>
</form></div></div>
<?php endforeach; ?>

<?php include __DIR__.'/../includes/footer.php'; ?>

<script>
/* Revisi 20 Juni 2026 R3 — Toggle panel Hiking/Camping berdasar pilihan Jenis */
(function(){
  var TRAIL = (window.TRAIL_JENIS_IDS||[]).map(String);
  function applyForm(form){
    var sel = form.querySelector('select[name="jenis_id"]');
    if(!sel) return;
    var isTrail = TRAIL.indexOf(String(sel.value)) >= 0;
    form.querySelectorAll('.trail-panel').forEach(function(el){ el.style.display = isTrail ? '' : 'none'; });
    form.querySelectorAll('.nontrail-section').forEach(function(el){ el.style.display = isTrail ? 'none' : ''; });
    // Pastikan input lat/lng tidak dikirim saat trail
    form.querySelectorAll('.nontrail-section input[name="lat"], .nontrail-section input[name="lng"]').forEach(function(inp){
      inp.disabled = isTrail;
    });
  }
  document.querySelectorAll('form[data-trail-form="1"]').forEach(function(f){
    var sel = f.querySelector('select[name="jenis_id"]');
    if(sel){ sel.addEventListener('change', function(){ applyForm(f); }); }
    applyForm(f);
  });
})();
</script>
