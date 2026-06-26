<?php
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
send_security_headers(); enforce_session_timeout();
$pageTitle = 'Daftar Tempat';
$pageSkeleton = 'grid';
$u = current_user();
$isAdmin = $u && $u['role']==='admin';

/* ====== Revisi 22 Juni 2026 R12 ======
 * - Pagination 9 kartu per halaman (3x3 grid) supaya tidak memanjang ke bawah.
 * - Filter pakai AJAX (fetch ?ajax=list=1) — tidak reload halaman.
 * ===================================== */
$q       = trim($_GET['q'] ?? '');
$fJenis  = (int)($_GET['jenis'] ?? 0);
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 9;
$ajax    = !empty($_GET['ajax_list']);

/* Revisi R19 — filter rentang kiloan KHUSUS Hiking.
 * Sumber distance: COALESCE(tempat.jarak_km, run_routes.jarak_m/1000).
 * Kolom tempat.jarak_km ditambah otomatis (idempotent) supaya admin bisa
 * mengisi langsung tanpa harus membuat run_route lebih dulu. */
@db_exec("ALTER TABLE tempat ADD COLUMN IF NOT EXISTS jarak_km NUMERIC(6,2) NULL");

/* Revisi R20 — auto-isi kolom jarak_km untuk tempat Hiking yg punya gpx_path
 * tetapi belum punya jarak_km. Sebelumnya kiloan hanya muncul di tempat_detail.php
 * karena dihitung via JS dari GPX. Di list kita hitung sekali di server lalu
 * cache ke tempat.jarak_km supaya badge "X.XX km" tampil di kartu. */
function _r20_haversine_km($lat1,$lon1,$lat2,$lon2){
  $R=6371.0; $dLat=deg2rad($lat2-$lat1); $dLon=deg2rad($lon2-$lon1);
  $a=sin($dLat/2)**2 + cos(deg2rad($lat1))*cos(deg2rad($lat2))*sin($dLon/2)**2;
  return 2*$R*asin(min(1,sqrt($a)));
}
function _r20_gpx_to_km($gpxPath){
  if (!$gpxPath) return null;
  $fs = __DIR__ . '/' . ltrim($gpxPath,'/');
  if (!is_file($fs)) return null;
  $xml = @simplexml_load_file($fs); if (!$xml) return null;
  $pts = [];
  foreach ($xml->xpath('//*[local-name()="trkpt"]') as $p) {
    $pts[] = [(float)$p['lat'], (float)$p['lon']];
  }
  if (!$pts) foreach ($xml->xpath('//*[local-name()="rtept"]') as $p) {
    $pts[] = [(float)$p['lat'], (float)$p['lon']];
  }
  if (count($pts)<2) return null;
  $km = 0.0;
  for ($i=1;$i<count($pts);$i++) $km += _r20_haversine_km($pts[$i-1][0],$pts[$i-1][1],$pts[$i][0],$pts[$i][1]);
  return round($km,2);
}
try {
  $pending = db_all("SELECT t.id, t.gpx_path FROM tempat t
                     LEFT JOIN jenis_olahraga jo ON jo.id=t.jenis_id
                     WHERE t.jarak_km IS NULL AND t.gpx_path IS NOT NULL AND t.gpx_path<>''
                       AND LOWER(jo.nama)='hiking'");
  foreach ($pending as $p) {
    $km = _r20_gpx_to_km($p['gpx_path']);
    if ($km !== null && $km > 0) {
      @db_exec("UPDATE tempat SET jarak_km=$1 WHERE id=$2", [$km, (int)$p['id']]);
    }
  }
} catch (Throwable $e) {}

$kmMin = isset($_GET['km_min']) && $_GET['km_min'] !== '' ? (float)$_GET['km_min'] : null;
$kmMax = isset($_GET['km_max']) && $_GET['km_max'] !== '' ? (float)$_GET['km_max'] : null;

/* Cari id Hiking sekali (case-insensitive) supaya filter rentang trek hanya
 * berlaku saat jenis Hiking dipilih, sesuai revisi. */
$hikingId = (int) db_val("SELECT id FROM jenis_olahraga WHERE LOWER(nama)='hiking' LIMIT 1");
$isHikingFilter = $hikingId && $fJenis === $hikingId;

$distExpr = "COALESCE(t.jarak_km, rr.jarak_m/1000.0, 0)";

$where = []; $params = []; $i=1;
if ($q !== '') { $where[] = "(t.nama ILIKE \$$i OR t.alamat ILIKE \$$i)"; $params[]="%$q%"; $i++; }
if ($fJenis)   { $where[] = "t.jenis_id = \$$i"; $params[]=$fJenis; $i++; }
/* Filter rentang trek hanya aktif ketika jenis Hiking dipilih + nilainya berlaku
 * untuk tempat yg punya kiloan (jarak_km atau run_route). */
if ($isHikingFilter && $kmMin !== null) { $where[] = "$distExpr >= \$$i"; $params[]=$kmMin; $i++; }
if ($isHikingFilter && $kmMax !== null) { $where[] = "$distExpr <= \$$i"; $params[]=$kmMax; $i++; }
$wsql = $where ? ('WHERE '.implode(' AND ',$where)) : '';

$total     = (int) db_val("SELECT COUNT(*) FROM tempat t LEFT JOIN run_routes rr ON rr.id=t.run_route_id $wsql", $params);
$totalPage = max(1, (int)ceil($total / $perPage));
if ($page > $totalPage) $page = $totalPage;
$offset    = ($page-1) * $perPage;

$rows = db_all("SELECT t.*, jo.nama AS jenis_nama, u.nama AS pic_nama, u.foto_url AS pic_foto, u.nomor_wa AS pic_wa,
                $distExpr AS distance_km
                FROM tempat t LEFT JOIN jenis_olahraga jo ON jo.id=t.jenis_id
                LEFT JOIN users u ON u.id=t.pic_user_id
                LEFT JOIN run_routes rr ON rr.id=t.run_route_id $wsql
                ORDER BY t.nama ASC
                LIMIT $perPage OFFSET $offset", $params);

$jenisList = db_all("SELECT id,nama FROM jenis_olahraga ORDER BY nama");

/* ----- Helper render kartu + pagination (dipakai oleh full page & ajax) ----- */
function tempat_render_cards($rows, $isAdmin, $page, $totalPage, $total){
?>
<div class="row g-3" id="tempatGrid">
<?php foreach($rows as $r):
  $maps = ($r['lat'] && $r['lng']) ? ('https://www.google.com/maps/search/?api=1&query='.$r['lat'].','.$r['lng']) : ('https://www.google.com/maps/search/?api=1&query='.urlencode($r['nama'].' '.($r['alamat']??'')));
  $picWa = preg_replace('/^0/','62', preg_replace('/\D+/','', $r['kontak_wa'] ?: ($r['pic_wa'] ?? '')));
  $jenisLower = mb_strtolower(trim((string)($r['jenis_nama'] ?? '')));
  $isHiking = ($jenisLower === 'hiking');
  $isTrail  = $isHiking; // alias — kiloan/peta GPX hanya untuk Hiking sesuai revisi
  $km = (float)($r['distance_km'] ?? 0);
  $popup = [
    'nama' => $r['nama'],
    'alamat' => $r['alamat'] ?? '',
    'jenis' => $r['jenis_nama'] ?? '',
    'status' => $r['status_booking'],
    'harga_lapang' => (float)$r['harga_lapang'],
    'harga_jam' => (float)$r['harga_per_jam'],
    'harga_tiket' => (float)($r['harga_tiket'] ?? 0),
    'harga_parkir' => (float)($r['harga_parkir'] ?? 0),
    'catatan' => $r['catatan'] ?? '',
    'pic_nama' => $r['pic_nama'] ?? '',
    'pic_foto' => $r['pic_foto'] ?? '',
    'kontak_wa' => $isAdmin ? ($r['kontak_wa'] ?? '') : '',
    'pic_wa_admin' => $isAdmin ? ($r['pic_wa'] ?? '') : '',
    'wa_link' => $picWa ? ('https://wa.me/'.$picWa) : '',
    'lat' => $r['lat'],
    'lng' => $r['lng'],
    'maps' => $maps,
    'is_admin' => $isAdmin,
    'is_trail' => $isTrail,
    'gpx_path' => $r['gpx_path'] ?? '',
    'id' => (int)$r['id'],
    'detail_url' => '/tempat_detail.php?id='.(int)$r['id'],
    'km' => $km,
  ];
?>
  <div class="col-md-6 col-lg-4">
    <div class="card shadow-sm h-100">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start gap-2">
          <h5 class="card-title mb-1"><?= htmlspecialchars($r['nama']) ?></h5>
          <?php $st=$r['status_booking']; $cls=$st==='tersedia'?'success':($st==='booked'?'warning':'secondary'); ?>
          <span class="badge bg-<?= $cls ?>"><?= htmlspecialchars($st) ?></span>
        </div>
        <?php if($r['jenis_nama']): ?>
          <div class="mb-2">
            <span class="pill <?= $isHiking?'text-success':'' ?>">
              <i class="bi <?= $isHiking?'bi-tree-fill':'bi-tags' ?>"></i> <?= htmlspecialchars($r['jenis_nama']) ?>
            </span>
            <?php if($isHiking && !empty($r['gpx_path'])): ?>
              <span class="badge bg-success-subtle text-success-emphasis ms-1"><i class="bi bi-bezier2"></i> Rute GPX</span>
            <?php endif; ?>
            <?php /* Revisi 28 Jun 2026 — Kiloan untuk Hiking selalu ditampilkan.
                     Jika kolom jarak_km sudah ada → tampil langsung.
                     Jika 0 tapi punya gpx_path → dihitung di client (sama seperti tempat_detail.php). */ ?>
            <?php if($isHiking && $km > 0): ?>
              <span class="badge bg-primary-subtle text-primary-emphasis ms-1 km-badge" title="Jarak rute (kiloan)">
                <i class="bi bi-signpost-split"></i> <?= number_format($km, 2, ',', '.') ?> km
              </span>
            <?php elseif($isHiking && !empty($r['gpx_path'])): ?>
              <span class="badge bg-primary-subtle text-primary-emphasis ms-1 km-badge js-km-from-gpx"
                    data-gpx="<?= htmlspecialchars($r['gpx_path']) ?>" title="Menghitung jarak rute…">
                <i class="bi bi-signpost-split"></i> <span class="km-val">menghitung…</span>
              </span>
            <?php elseif($isHiking): ?>
              <span class="badge bg-warning-subtle text-warning-emphasis ms-1 km-badge" title="Kiloan belum diisi">
                <i class="bi bi-signpost-split"></i> kiloan: —
              </span>
            <?php endif; ?>
          </div>
        <?php endif; ?>
        <p class="small text-muted mb-2"><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($r['alamat'] ?? '—') ?></p>
        <?php if($r['pic_nama']): ?><div class="small mb-2">PIC: <?= user_name_with_avatar($r['pic_foto']??null,$r['pic_nama'],false,22) ?></div><?php endif; ?>
        <div class="d-flex gap-2">
          <button type="button" class="btn btn-sm btn-outline-primary"
            onclick='showTempatDetail(<?= json_encode($popup, JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE) ?>)'>
            <i class="bi bi-info-circle"></i> Detail
          </button>
        </div>
      </div>
    </div>
  </div>
<?php endforeach; if(!$rows): ?><div class="col-12"><div class="alert alert-info mb-0">Tidak ada tempat sesuai filter.</div></div><?php endif; ?>
</div>

<?php if ($totalPage > 1): ?>
<nav class="mt-3" id="tempatPager"><ul class="pagination pagination-sm justify-content-center mb-1">
  <li class="page-item <?= $page<=1?'disabled':'' ?>"><a class="page-link" href="#" data-page="<?= max(1,$page-1) ?>">«</a></li>
  <?php
    $from = max(1, $page-3); $to = min($totalPage, $from+6); $from = max(1, $to-6);
    for ($p=$from; $p<=$to; $p++):
  ?>
    <li class="page-item <?= $p===$page?'active':'' ?>"><a class="page-link" href="#" data-page="<?= $p ?>"><?= $p ?></a></li>
  <?php endfor; ?>
  <li class="page-item <?= $page>=$totalPage?'disabled':'' ?>"><a class="page-link" href="#" data-page="<?= min($totalPage,$page+1) ?>">»</a></li>
</ul>
<div class="text-center small text-muted">Halaman <?= $page ?> dari <?= $totalPage ?> · <?= $total ?> tempat</div>
</nav>
<?php endif; ?>
<?php
}

if ($ajax) {
    // Render fragment saja
    tempat_render_cards($rows, $isAdmin, $page, $totalPage, $total);
    exit;
}

include __DIR__.'/includes/header.php';
?>
<h2 class="mb-3"><i class="bi bi-geo-alt-fill text-primary"></i> Daftar Tempat Olahraga</h2>
<p class="text-muted small">Tempat-tempat olahraga yang dikelola admin komunitas. Klik untuk melihat detail & arah lokasi.</p>

<div class="card shadow-sm mb-3"><div class="card-body">
  <!-- Revisi 22 Juni 2026 R12 — filter via AJAX (tidak reload halaman) -->
  <form class="row g-2" id="tempatFilterForm" onsubmit="return false">
    <div class="col-md-6"><input class="form-control form-control-sm" name="q" id="fQ" value="<?= htmlspecialchars($q) ?>" placeholder="🔍 Cari nama / alamat..."></div>
    <div class="col-md-6"><select class="form-select form-select-sm" name="jenis" id="fJenis">
      <option value="0">Semua Jenis</option>
      <?php foreach($jenisList as $jn): ?><option value="<?= (int)$jn['id'] ?>" <?= $fJenis===(int)$jn['id']?'selected':'' ?>><?= htmlspecialchars($jn['nama']) ?></option><?php endforeach; ?>
    </select></div>
    <!-- Revisi — Filter rentang kiloan HANYA muncul saat jenis Hiking dipilih -->
    <div class="col-md-12" id="fKmWrap" style="<?= $isHikingFilter?'':'display:none' ?>">
      <div class="d-flex align-items-center gap-2 flex-wrap">
        <span class="small text-success fw-semibold"><i class="bi bi-signpost-split"></i> Rentang Kiloan (Hiking):</span>
        <input type="number" min="0" step="0.1" class="form-control form-control-sm" style="max-width:120px" id="fKmMin" placeholder="min km" value="<?= $kmMin!==null?htmlspecialchars((string)$kmMin):'' ?>">
        <span class="small">s/d</span>
        <input type="number" min="0" step="0.1" class="form-control form-control-sm" style="max-width:120px" id="fKmMax" placeholder="max km" value="<?= $kmMax!==null?htmlspecialchars((string)$kmMax):'' ?>">
        <button type="button" class="btn btn-sm btn-outline-secondary" id="fKmReset"><i class="bi bi-x-circle"></i> Reset KM</button>
        <small class="text-muted">Filter ini hanya aktif untuk jenis Hiking dan tempat yang sudah punya kiloan.</small>
      </div>
    </div>
    <input type="hidden" id="fHikingId" value="<?= (int)$hikingId ?>">
    <!-- Revisi 23 Juni 2026 — tombol Filter dihapus karena filter sudah otomatis via AJAX (change/Enter). -->
  </form>
</div></div>

<div id="tempatListWrap">
<?php tempat_render_cards($rows, $isAdmin, $page, $totalPage, $total); ?>
</div>

<!-- Revisi 24 Juni 2026 — Peta di tempat_list.php sekarang memakai MapBox (sama dgn run.php).
     Rendering tetap memakai Leaflet sebagai layer engine, namun tile source memakai MapBox. -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script>
  window.MAPBOX_TOKEN_JS = 'pk.eyJ1IjoiYWRhbXNhc21pdGE1MzQiLCJhIjoiY21xZnRsbWxjMXZldDJ0cHlhN2Jycnd1dCJ9.2E00ey-sgX9jUmf5kIRoEA';
  window.MAPBOX_TILE_URL = 'https://api.mapbox.com/styles/v1/mapbox/outdoors-v12/tiles/256/{z}/{x}/{y}@2x?access_token=' + window.MAPBOX_TOKEN_JS;
  window.MAPBOX_ATTR = '&copy; <a href="https://www.mapbox.com/about/maps/">Mapbox</a> &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>';
</script>

<!-- Popup detail Tempat -->
<div class="modal fade" id="tempatModal" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-scrollable">
  <div class="modal-content">
    <div class="modal-header">
      <h5 class="modal-title"><i class="bi bi-geo-alt-fill text-primary"></i> <span id="tmNama">Tempat</span></h5>
      <button class="btn-close" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body">
      <div class="mb-2 small text-muted" id="tmAlamat"></div>
      <div class="mb-2" id="tmJenis"></div>

      <div id="tmMapWrap" class="mb-3 d-none">
        <div class="d-flex justify-content-between align-items-center mb-1">
          <strong class="small"><i class="bi bi-map text-success"></i> Peta Rute</strong>
          <span id="tmGpxBadge" class="badge bg-success-subtle text-success-emphasis d-none"><i class="bi bi-bezier2"></i> GPX</span>
        </div>
        <div id="tmMap" style="height:320px;width:100%;border-radius:8px;overflow:hidden;border:1px solid #dee2e6"></div>
      </div>

      <div class="row g-3">
        <div class="col-12">
          <table class="table table-sm mb-2">
            <tr><th>Status</th><td><span id="tmStatus" class="badge bg-info-subtle text-info"></span></td></tr>
            <tr><th>Harga Lapang</th><td id="tmHL"></td></tr>
            <tr><th>Harga / Jam</th><td id="tmHJ"></td></tr>
            <tr><th>Harga Tiket</th><td id="tmHT"></td></tr>
            <tr><th>Harga Parkir</th><td id="tmHP"></td></tr>
            <tr id="tmRowPIC" class="d-none"><th>PIC</th><td id="tmPIC"></td></tr>
          </table>
          <div id="tmCatatan" class="small text-muted" style="white-space:pre-wrap"></div>
          <div class="mt-2 d-flex flex-wrap gap-2">
            <a id="tmMaps" target="_blank" rel="noopener" class="btn btn-sm btn-primary d-none"><i class="bi bi-geo-alt-fill"></i> Lihat di Google Maps</a>
            <a id="tmWa" target="_blank" rel="noopener" class="btn btn-sm btn-outline-success d-none"><i class="bi bi-whatsapp"></i> Hubungi PIC</a>
            <a id="tmDetail" class="btn btn-sm btn-outline-info d-none"><i class="bi bi-info-circle"></i> Halaman Detail</a>
          </div>
          <div id="tmKoord" class="small text-muted mt-1"></div>
        </div>
      </div>
    </div>
  </div>
</div></div>

<script>
/* ===== Detail popup (sama seperti versi sebelumnya) ===== */
let _tmM = null, _tmLeaflet = null;
function _tmDestroyMap(){ if (_tmLeaflet){ try{_tmLeaflet.remove();}catch(e){} _tmLeaflet=null; } var el=document.getElementById('tmMap'); if(el) el.innerHTML=''; }
function _tmRenderMap(d){
  const wrap = document.getElementById('tmMapWrap');
  const hasCoord = d.lat && d.lng, hasGpx = !!d.gpx_path;
  if (!hasCoord && !hasGpx) { wrap.classList.add('d-none'); return; }
  wrap.classList.remove('d-none');
  document.getElementById('tmGpxBadge').classList.toggle('d-none', !hasGpx);
  if (typeof L === 'undefined') { setTimeout(()=>_tmRenderMap(d), 250); return; }
  _tmDestroyMap();
  const center = hasCoord ? [Number(d.lat), Number(d.lng)] : [-6.9,107.6];
  _tmLeaflet = L.map('tmMap').setView(center, hasCoord?15:12);
  L.tileLayer(window.MAPBOX_TILE_URL,{maxZoom:19,attribution:window.MAPBOX_ATTR}).addTo(_tmLeaflet);
  setTimeout(()=>{ if(_tmLeaflet) _tmLeaflet.invalidateSize(); }, 200);
  if (hasCoord) L.marker(center).addTo(_tmLeaflet).bindPopup('<b>'+(d.nama||'')+'</b>').openPopup();
  if (hasGpx) {
    fetch(d.gpx_path).then(r=>r.text()).then(xml=>{
      const doc = new DOMParser().parseFromString(xml,'application/xml');
      const trkpts = doc.getElementsByTagName('trkpt'); const pts = [];
      for (let i=0;i<trkpts.length;i++) pts.push(L.latLng(parseFloat(trkpts[i].getAttribute('lat')), parseFloat(trkpts[i].getAttribute('lon'))));
      if (pts.length && _tmLeaflet){
        const line = L.polyline(pts,{color:'#198754',weight:5,opacity:.85}).addTo(_tmLeaflet);
        L.marker(pts[0]).addTo(_tmLeaflet).bindPopup('Start');
        L.marker(pts[pts.length-1]).addTo(_tmLeaflet).bindPopup('Finish');
        _tmLeaflet.fitBounds(line.getBounds(),{padding:[20,20]});
      }
    }).catch(()=>{});
  }
}
function showTempatDetail(d){
  if(!_tmM) { _tmM = new bootstrap.Modal(document.getElementById('tempatModal')); document.getElementById('tempatModal').addEventListener('hidden.bs.modal', _tmDestroyMap); }
  const fmt = v => 'Rp '+ Number(v||0).toLocaleString('id-ID');
  document.getElementById('tmNama').textContent = d.nama || '';
  document.getElementById('tmAlamat').innerHTML = '<i class="bi bi-geo-alt"></i> ' + (d.alamat || '—');
  document.getElementById('tmJenis').innerHTML = d.jenis ? ('<span class="pill">'+d.jenis+'</span>') : '';
  document.getElementById('tmStatus').textContent = d.status || '';
  document.getElementById('tmHL').textContent = fmt(d.harga_lapang);
  document.getElementById('tmHJ').textContent = fmt(d.harga_jam);
  document.getElementById('tmHT').textContent = fmt(d.harga_tiket);
  document.getElementById('tmHP').textContent = fmt(d.harga_parkir);
  if (d.pic_nama) { document.getElementById('tmRowPIC').classList.remove('d-none'); document.getElementById('tmPIC').textContent = d.pic_nama; }
  else            { document.getElementById('tmRowPIC').classList.add('d-none'); }
  document.getElementById('tmCatatan').textContent = d.catatan || '';
  const wa = document.getElementById('tmWa');
  if (d.wa_link) { wa.href = d.wa_link; wa.classList.remove('d-none'); } else { wa.classList.add('d-none'); }
  const mapsBtn = document.getElementById('tmMaps');
  if (d.maps) { mapsBtn.href = d.maps; mapsBtn.classList.remove('d-none'); } else { mapsBtn.classList.add('d-none'); }
  const det = document.getElementById('tmDetail');
  if (d.detail_url) { det.href = d.detail_url; det.classList.remove('d-none'); } else { det.classList.add('d-none'); }
  const kd = document.getElementById('tmKoord');
  if (d.lat && d.lng) { kd.innerHTML = '<i class="bi bi-pin-map"></i> Koordinat: '+Number(d.lat).toFixed(6)+', '+Number(d.lng).toFixed(6); }
  else { kd.innerHTML = ''; }
  _tmM.show();
  setTimeout(()=>_tmRenderMap(d), 250);
}

/* ===== Revisi R12: AJAX filter + pagination ===== */
(function(){
  var form = document.getElementById('tempatFilterForm');
  var wrap = document.getElementById('tempatListWrap');
  if (!form || !wrap) return;
  var loading = false;

  function loadList(page){
    if (loading) return;
    loading = true;
    var q = document.getElementById('fQ').value.trim();
    var j = document.getElementById('fJenis').value;
    var kmin = document.getElementById('fKmMin').value;
    var kmax = document.getElementById('fKmMax').value;
    var p = page || 1;
    var url = '/tempat_list.php?ajax_list=1&q='+encodeURIComponent(q)+'&jenis='+encodeURIComponent(j)
              + '&km_min='+encodeURIComponent(kmin)+'&km_max='+encodeURIComponent(kmax)+'&page='+p;
    wrap.style.opacity = '0.5';
    fetch(url, {headers:{'X-Requested-With':'fetch'}})
      .then(function(r){ return r.text(); })
      .then(function(html){
        wrap.innerHTML = html;
        wrap.style.opacity = '1';
        // Update URL agar bisa di-bookmark / share
        try {
          var qs = new URLSearchParams();
          if (q) qs.set('q', q);
          if (j && j!=='0') qs.set('jenis', j);
          if (kmin) qs.set('km_min', kmin);
          if (kmax) qs.set('km_max', kmax);
          if (p>1) qs.set('page', p);
          history.replaceState(null, '', '/tempat_list.php'+(qs.toString()?('?'+qs.toString()):''));
        } catch(e){}
      })
      .catch(function(){ wrap.style.opacity='1'; })
      .finally(function(){ loading = false; });
  }
  form.addEventListener('submit', function(e){ e.preventDefault(); loadList(1); });

  /* Tampilkan/sembunyikan blok filter kiloan sesuai jenis terpilih */
  function syncKmVisibility(){
    var hid  = parseInt((document.getElementById('fHikingId')||{}).value || '0', 10);
    var jSel = document.getElementById('fJenis');
    var wrap = document.getElementById('fKmWrap');
    if (!wrap || !jSel) return;
    var show = hid && parseInt(jSel.value||'0',10) === hid;
    wrap.style.display = show ? '' : 'none';
    if (!show){
      // reset nilai jika filter disembunyikan supaya tidak ikut terkirim
      var a=document.getElementById('fKmMin'), b=document.getElementById('fKmMax');
      if (a) a.value=''; if (b) b.value='';
    }
  }
  syncKmVisibility();

  ['fQ','fJenis','fKmMin','fKmMax'].forEach(function(id){
    var el = document.getElementById(id);
    if (!el) return;
    if (el.tagName === 'SELECT') el.addEventListener('change', function(){ syncKmVisibility(); loadList(1); });
    else {
      el.addEventListener('keydown', function(e){ if(e.key==='Enter'){ e.preventDefault(); loadList(1); }});
      el.addEventListener('change', function(){ loadList(1); });
    }
  });
  var rb = document.getElementById('fKmReset');
  if (rb) rb.addEventListener('click', function(){
    document.getElementById('fKmMin').value='';
    document.getElementById('fKmMax').value='';
    loadList(1);
  });
  // Delegate pagination clicks
  wrap.addEventListener('click', function(e){
    var a = e.target.closest('a[data-page]');
    if (!a) return;
    e.preventDefault();
    var li = a.parentElement;
    if (li && li.classList.contains('disabled')) return;
    var p = parseInt(a.dataset.page||'1',10);
    loadList(p);
    window.scrollTo({top: wrap.offsetTop - 60, behavior:'smooth'});
  });
})();

/* ===== Revisi 28 Jun 2026 — Hitung kiloan dari GPX di kartu (parity dgn tempat_detail.php).
 * Untuk badge .js-km-from-gpx (Hiking, jarak_km belum diisi tapi gpx_path ada),
 * fetch file GPX lalu hitung total jarak via haversine. Cache di memori per URL
 * supaya GPX yang sama tidak diunduh berulang kali. */
(function(){
  var _gpxCache = {};
  function haversine(a, b){
    var R = 6371; var toRad = function(d){ return d*Math.PI/180; };
    var dLat = toRad(b[0]-a[0]), dLon = toRad(b[1]-a[1]);
    var s = Math.sin(dLat/2)*Math.sin(dLat/2)
          + Math.cos(toRad(a[0]))*Math.cos(toRad(b[0]))*Math.sin(dLon/2)*Math.sin(dLon/2);
    return 2*R*Math.asin(Math.min(1, Math.sqrt(s)));
  }
  function computeKmFromGpxText(xml){
    var doc = new DOMParser().parseFromString(xml,'application/xml');
    var nodes = doc.getElementsByTagName('trkpt');
    if (!nodes.length) nodes = doc.getElementsByTagName('rtept');
    var pts = [];
    for (var i=0;i<nodes.length;i++){
      var la = parseFloat(nodes[i].getAttribute('lat'));
      var lo = parseFloat(nodes[i].getAttribute('lon'));
      if (!isNaN(la) && !isNaN(lo)) pts.push([la,lo]);
    }
    if (pts.length < 2) return null;
    var km = 0;
    for (var j=1;j<pts.length;j++) km += haversine(pts[j-1], pts[j]);
    return km;
  }
  function fmtKm(km){ return km.toLocaleString('id-ID',{minimumFractionDigits:2,maximumFractionDigits:2}) + ' km'; }
  function hydrate(root){
    (root||document).querySelectorAll('.js-km-from-gpx').forEach(function(badge){
      if (badge.dataset.kmDone) return;
      badge.dataset.kmDone = '1';
      var url = badge.getAttribute('data-gpx'); if (!url) return;
      var done = function(km){
        var v = badge.querySelector('.km-val');
        if (km && km > 0){ if(v) v.textContent = fmtKm(km); badge.title = 'Jarak rute (kiloan)'; }
        else { badge.classList.remove('bg-primary-subtle','text-primary-emphasis');
               badge.classList.add('bg-warning-subtle','text-warning-emphasis');
               if(v) v.textContent = 'kiloan: —'; badge.title='Kiloan belum diisi'; }
      };
      if (_gpxCache[url] !== undefined){ done(_gpxCache[url]); return; }
      fetch(url).then(function(r){ return r.ok ? r.text() : Promise.reject(); })
        .then(function(xml){ var km = computeKmFromGpxText(xml); _gpxCache[url] = km; done(km); })
        .catch(function(){ _gpxCache[url] = null; done(null); });
    });
  }
  hydrate(document);
  /* Re-hydrate setiap kali fragment grid di-replace oleh AJAX filter/pagination. */
  var wrap = document.getElementById('tempatListWrap');
  if (wrap && 'MutationObserver' in window){
    new MutationObserver(function(){ hydrate(wrap); }).observe(wrap, {childList:true, subtree:true});
  }
})();
</script>
<?php include __DIR__.'/includes/footer.php'; ?>
