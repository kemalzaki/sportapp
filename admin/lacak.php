<?php
require __DIR__.'/../config/db.php';
require __DIR__.'/../includes/auth.php';
require __DIR__.'/../includes/helpers.php';
require_role('admin');
$pageTitle = 'Lacak HP Member';
$rows = db_all("SELECT u.id, u.nama, u.role, u.foto_url, u.nomor_wa, d.lat, d.lng, d.accuracy_m, d.device_label, d.updated_at
                FROM users u LEFT JOIN device_locations d ON d.user_id=u.id
                ORDER BY (d.updated_at IS NULL), d.updated_at DESC, u.nama");
include __DIR__.'/../includes/header.php';
?>
<h2 class="mb-2"><i class="bi bi-broadcast-pin text-danger"></i> Lacak HP Member</h2>
<p class="text-muted small">Posisi terakhir HP setiap user yang sudah menginstall &amp; login ke aplikasi (heartbeat tiap 2 menit selama browser terbuka). Berguna jika HP hilang atau lupa simpan.</p>
<div class="row g-3">
  <div class="col-lg-5">
    <div class="card shadow-sm"><div class="list-group list-group-flush" style="max-height:560px;overflow:auto">
    <?php foreach($rows as $r): $has = !empty($r['lat']); ?>
      <div class="list-group-item d-flex gap-2 align-items-start <?= $has?'':'opacity-50' ?>">
        <?= user_avatar($r['foto_url'] ?? null, $r['nama'], 32) ?>
        <div class="flex-grow-1">
          <div class="d-flex justify-content-between">
            <strong><?= htmlspecialchars($r['nama']) ?></strong>
            <span class="badge bg-<?= $r['role']==='admin'?'danger':'secondary' ?>-subtle text-<?= $r['role']==='admin'?'danger':'secondary' ?>"><?= htmlspecialchars($r['role']) ?></span>
          </div>
          <?php if($has): ?>
            <div class="small text-muted">Lat <?= number_format((float)$r['lat'],6) ?>, Lng <?= number_format((float)$r['lng'],6) ?> · akurasi <?= round((float)$r['accuracy_m']) ?> m</div>
            <div class="small text-muted">Update: <?= htmlspecialchars(date('d M Y H:i', strtotime($r['updated_at']))) ?></div>
            <div class="mt-1 d-flex gap-2 flex-wrap">
              <button class="btn btn-sm btn-outline-primary track-focus" data-lat="<?= $r['lat'] ?>" data-lng="<?= $r['lng'] ?>" data-nama="<?= htmlspecialchars($r['nama']) ?>"><i class="bi bi-bullseye"></i> Fokus di Peta</button>
              <a class="btn btn-sm btn-outline-success" target="_blank" href="https://www.google.com/maps?q=<?= $r['lat'] ?>,<?= $r['lng'] ?>"><i class="bi bi-map"></i> Buka di Google Maps</a>
              <?php if(!empty($r['nomor_wa'])): ?><a class="btn btn-sm btn-outline-success" target="_blank" href="https://wa.me/<?= preg_replace('/\D/','',$r['nomor_wa']) ?>"><i class="bi bi-whatsapp"></i></a><?php endif; ?>
            </div>
          <?php else: ?>
            <div class="small text-muted">Belum ada data lokasi (user belum membuka aplikasi setelah update ini, atau menolak izin GPS).</div>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
    </div></div>
  </div>
  <div class="col-lg-7">
    <div class="card shadow-sm"><div class="card-body p-2">
      <div id="lacakMap" style="height:560px;border-radius:8px"></div>
    </div></div>
  </div>
</div>

<!-- Revisi 19 Juni 2026 Part O #8 — Modal popup peta saat klik "Fokus di Peta" -->
<div class="modal fade" id="focusMapModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h6 class="modal-title"><i class="bi bi-bullseye text-primary"></i> <span id="fmTitle">Lokasi Member</span></h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-2">
        <div id="focusMap" style="height:60vh;border-radius:8px"></div>
        <div class="small text-muted mt-2" id="fmInfo"></div>
      </div>
    </div>
  </div>
</div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
var pts = <?= json_encode(array_values(array_map(function($r){
  return ['nama'=>$r['nama'],'lat'=>(float)$r['lat'],'lng'=>(float)$r['lng'],'when'=>$r['updated_at']];
}, array_filter($rows, fn($r)=>!empty($r['lat']))))) ?>;
var map = L.map('lacakMap').setView([-6.926263,107.717553], 13);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'&copy; OSM'}).addTo(map);
var markers = {};
pts.forEach(function(p){
  var m = L.marker([p.lat,p.lng]).addTo(map).bindPopup('<strong>'+p.nama+'</strong><br>'+p.when);
  markers[p.nama] = m;
});
if (pts.length) { var g = L.featureGroup(Object.values(markers)); map.fitBounds(g.getBounds().pad(0.2)); }

/* Revisi 19 Juni 2026 Part O #8 — popup peta saat klik Fokus */
var _fMap=null, _fMarker=null;
document.querySelectorAll('.track-focus').forEach(function(b){
  b.addEventListener('click', function(){
    var lat=parseFloat(this.dataset.lat), lng=parseFloat(this.dataset.lng), n=this.dataset.nama;
    map.setView([lat,lng],17); if(markers[n]) markers[n].openPopup();
    // Tampilkan popup peta detail
    document.getElementById('fmTitle').textContent = 'Lokasi: '+n;
    document.getElementById('fmInfo').textContent  = 'Lat '+lat.toFixed(6)+', Lng '+lng.toFixed(6);
    var el = document.getElementById('focusMapModal');
    var modal = new bootstrap.Modal(el); modal.show();
    el.addEventListener('shown.bs.modal', function once(){
      el.removeEventListener('shown.bs.modal', once);
      if (!_fMap){
        _fMap = L.map('focusMap');
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'&copy; OSM'}).addTo(_fMap);
      }
      _fMap.invalidateSize();
      _fMap.setView([lat,lng], 17);
      if (_fMarker){ _fMap.removeLayer(_fMarker); }
      _fMarker = L.marker([lat,lng]).addTo(_fMap).bindPopup('<strong>'+n+'</strong>').openPopup();
    });
  });
});
</script>
<?php include __DIR__.'/../includes/footer.php'; ?>
