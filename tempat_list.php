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

$where = []; $params = []; $i=1;
if ($q !== '') { $where[] = "(t.nama ILIKE \$$i OR t.alamat ILIKE \$$i)"; $params[]="%$q%"; $i++; }
if ($fJenis)   { $where[] = "t.jenis_id = \$$i"; $params[]=$fJenis; $i++; }
$wsql = $where ? ('WHERE '.implode(' AND ',$where)) : '';

$total     = (int) db_val("SELECT COUNT(*) FROM tempat t $wsql", $params);
$totalPage = max(1, (int)ceil($total / $perPage));
if ($page > $totalPage) $page = $totalPage;
$offset    = ($page-1) * $perPage;

$rows = db_all("SELECT t.*, jo.nama AS jenis_nama, u.nama AS pic_nama, u.foto_url AS pic_foto, u.nomor_wa AS pic_wa
                FROM tempat t LEFT JOIN jenis_olahraga jo ON jo.id=t.jenis_id
                LEFT JOIN users u ON u.id=t.pic_user_id $wsql
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
  $isTrail = in_array($jenisLower, ['hiking','camping'], true);
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
            <span class="pill <?= $isTrail?'text-success':'' ?>">
              <i class="bi <?= $isTrail?'bi-tree-fill':'bi-tags' ?>"></i> <?= htmlspecialchars($r['jenis_nama']) ?>
            </span>
            <?php if(!empty($r['gpx_path'])): ?>
              <span class="badge bg-success-subtle text-success-emphasis ms-1"><i class="bi bi-bezier2"></i> Rute GPX</span>
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
    <!-- Revisi 23 Juni 2026 — tombol Filter dihapus karena filter sudah otomatis via AJAX (change/Enter). -->
  </form>
</div></div>

<div id="tempatListWrap">
<?php tempat_render_cards($rows, $isAdmin, $page, $totalPage, $total); ?>
</div>

<!-- Leaflet (untuk peta rute di popup) -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>

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
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'© OpenStreetMap'}).addTo(_tmLeaflet);
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
    var p = page || 1;
    var url = '/tempat_list.php?ajax_list=1&q='+encodeURIComponent(q)+'&jenis='+encodeURIComponent(j)+'&page='+p;
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
          if (p>1) qs.set('page', p);
          history.replaceState(null, '', '/tempat_list.php'+(qs.toString()?('?'+qs.toString()):''));
        } catch(e){}
      })
      .catch(function(){ wrap.style.opacity='1'; })
      .finally(function(){ loading = false; });
  }
  form.addEventListener('submit', function(e){ e.preventDefault(); loadList(1); });
  ['fQ','fJenis'].forEach(function(id){
    var el = document.getElementById(id);
    if (!el) return;
    if (el.tagName === 'SELECT') el.addEventListener('change', function(){ loadList(1); });
    else el.addEventListener('keydown', function(e){ if(e.key==='Enter'){ e.preventDefault(); loadList(1); }});
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
</script>
<?php include __DIR__.'/includes/footer.php'; ?>
