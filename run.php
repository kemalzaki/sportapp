<?php
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
send_security_headers(); require_login();
$u = current_user(); $uid = (int)$u['id'];
$pageTitle = 'Tracking Lari';

// Riwayat
$history = db_all("SELECT * FROM run_sessions WHERE user_id=$1 ORDER BY mulai_at DESC LIMIT 20", [$uid]);
$active = db_one("SELECT * FROM run_sessions WHERE user_id=$1 AND status='aktif' ORDER BY id DESC LIMIT 1", [$uid]);

include __DIR__.'/includes/header.php';
?>
<h4 class="mb-3"><i class="bi bi-stopwatch text-danger"></i> Tracking Lari Realtime</h4>

<div class="row g-3">
  <div class="col-md-7">
    <div class="card shadow-sm"><div class="card-body">
      <div id="runMap" style="height:380px;border-radius:10px;border:1px solid var(--bs-border-color,#e5e7eb)"></div>
      <div class="row text-center mt-3 g-2">
        <div class="col-4"><div class="small text-muted">Jarak</div><div class="fs-3 fw-bold" id="runDistance">0.00 km</div></div>
        <div class="col-4"><div class="small text-muted">Waktu</div><div class="fs-3 fw-bold" id="runTime">00:00</div></div>
        <div class="col-4"><div class="small text-muted">Pace</div><div class="fs-3 fw-bold" id="runPace">--'--"</div></div>
      </div>
      <div class="d-flex justify-content-center gap-2 mt-3 flex-wrap">
        <button id="btnStart" class="btn btn-success px-4"><i class="bi bi-play-fill"></i> Mulai</button>
        <button id="btnStop"  class="btn btn-danger  px-4" disabled><i class="bi bi-stop-fill"></i> Selesai</button>
        <button id="btnLocate" type="button" class="btn btn-outline-primary px-3" title="Posisikan peta ke lokasi Anda sekarang">
          <i class="bi bi-geo-alt-fill"></i> Posisikan Posisi Sekarang
        </button>
      </div>
      <div id="runStatus" class="small text-muted mt-2 text-center"></div>
    </div></div>
  </div>

  <div class="col-md-5">
    <div class="card shadow-sm"><div class="card-header"><i class="bi bi-clock-history"></i> Riwayat Lari</div>
    <div class="list-group list-group-flush">
      <?php if(!$history): ?><div class="p-3 small text-muted">Belum ada sesi lari.</div><?php endif; ?>
      <?php foreach($history as $h): ?>
        <div class="list-group-item" id="run-row-<?= (int)$h['id'] ?>">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <strong><?= round(((float)$h['jarak_m'])/1000, 2) ?> km</strong>
              <div class="small text-muted">
                Durasi: <?= gmdate('i:s', (int)$h['durasi_dtk']) ?> · Kalori: <?= (int)$h['kalori'] ?> ·
                <span class="badge bg-<?= $h['status']==='aktif'?'warning':'success' ?>-subtle text-<?= $h['status']==='aktif'?'warning':'success' ?>"><?= htmlspecialchars($h['status']) ?></span>
              </div>
            </div>
            <div class="d-flex flex-column align-items-end gap-1">
              <span class="small text-muted"><?= htmlspecialchars(date('d M H:i', strtotime($h['mulai_at']))) ?></span>
              <button type="button" class="btn btn-sm btn-link p-0 run-route-btn" data-id="<?= (int)$h['id'] ?>" title="Lihat riwayat rute">
                <i class="bi bi-map"></i> Lihat Rute
              </button>
              <button type="button" class="btn btn-sm btn-link text-danger p-0 run-del-btn" data-id="<?= (int)$h['id'] ?>" title="Hapus riwayat ini">
                <i class="bi bi-trash"></i> Hapus
              </button>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div></div>
  </div>
</div>

<!-- Modal Riwayat Rute -->
<div class="modal fade" id="routeModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-map text-danger"></i> Riwayat Rute Lari</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="routeInfo" class="small text-muted mb-2"></div>
        <div id="routeMap" style="height:380px;border-radius:10px;border:1px solid #e5e7eb"></div>
        <div id="routeEmpty" class="alert alert-info small mt-2 d-none mb-0">Tidak ada titik rute tersimpan untuk sesi ini.</div>
      </div>
    </div>
  </div>
</div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(function(){
  var csrf='<?= csrf_token() ?>';
  var sessionId = <?= $active ? (int)$active['id'] : 'null' ?>;
  var watchId=null, startedAt=null, timerInt=null;
  var totalM = 0, points = [];
  var map = L.map('runMap').setView([-6.2,106.816666], 13);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'&copy; OSM'}).addTo(map);
  var line = L.polyline([], {color:'#dc2626', weight:5}).addTo(map);
  var marker=null;

  function haversine(a,b){
    var R=6371000, toRad=Math.PI/180;
    var dLat=(b.lat-a.lat)*toRad, dLng=(b.lng-a.lng)*toRad;
    var s=Math.sin(dLat/2)**2 + Math.cos(a.lat*toRad)*Math.cos(b.lat*toRad)*Math.sin(dLng/2)**2;
    return 2*R*Math.asin(Math.sqrt(s));
  }
  function fmtTime(s){var m=Math.floor(s/60), ss=s%60; return String(m).padStart(2,'0')+':'+String(ss).padStart(2,'0');}
  function updateUI(){
    var km = totalM/1000;
    document.getElementById('runDistance').textContent = km.toFixed(2)+' km';
    var sec = startedAt ? Math.floor((Date.now()-startedAt)/1000) : 0;
    document.getElementById('runTime').textContent = fmtTime(sec);
    if (km>0.05) {
      var paceSec = sec/km;
      var pm=Math.floor(paceSec/60), ps=Math.floor(paceSec%60);
      document.getElementById('runPace').textContent = pm+"'"+String(ps).padStart(2,'0')+'"';
    }
  }
  function onPos(pos){
    var p={lat:pos.coords.latitude,lng:pos.coords.longitude,acc:pos.coords.accuracy,spd:pos.coords.speed};
    if (points.length) totalM += haversine(points[points.length-1], p);
    points.push(p);
    line.addLatLng([p.lat,p.lng]);
    if (!marker) marker = L.marker([p.lat,p.lng]).addTo(map);
    else marker.setLatLng([p.lat,p.lng]);
    map.setView([p.lat,p.lng], Math.max(map.getZoom(),16));
    document.getElementById('runStatus').textContent='GPS akurasi: '+Math.round(p.acc)+' m';
    // kirim ke server
    if (sessionId) {
      var fd=new FormData();
      fd.append('csrf',csrf); fd.append('_action','point'); fd.append('session_id',sessionId);
      fd.append('lat',p.lat); fd.append('lng',p.lng); fd.append('acc',p.acc); fd.append('spd',p.spd||'');
      fd.append('total_m', totalM);
      fetch('/api_run.php',{method:'POST',body:fd});
    }
    updateUI();
  }
  function onErr(e){ document.getElementById('runStatus').textContent='Error GPS: '+e.message; }

  document.getElementById('btnStart').addEventListener('click', function(){
    if (!navigator.geolocation){ alert('Browser tidak mendukung GPS'); return; }
    var fd=new FormData(); fd.append('csrf',csrf); fd.append('_action','start');
    fetch('/api_run.php',{method:'POST',body:fd}).then(r=>r.json()).then(function(d){
      if (!d.ok) { alert('Gagal mulai sesi'); return; }
      sessionId=d.id; startedAt=Date.now(); totalM=0; points=[]; line.setLatLngs([]);
      document.getElementById('btnStart').disabled=true;
      document.getElementById('btnStop').disabled=false;
      timerInt=setInterval(updateUI, 1000);
      watchId=navigator.geolocation.watchPosition(onPos,onErr,{enableHighAccuracy:true,maximumAge:1000,timeout:15000});
    });
  });
  document.getElementById('btnStop').addEventListener('click', function(){
    if (watchId) navigator.geolocation.clearWatch(watchId);
    clearInterval(timerInt);
    if (!sessionId){ return; }
    var dur = startedAt ? Math.floor((Date.now()-startedAt)/1000) : 0;
    var fd=new FormData(); fd.append('csrf',csrf); fd.append('_action','stop');
    fd.append('session_id',sessionId); fd.append('total_m',totalM); fd.append('durasi',dur);
    fetch('/api_run.php',{method:'POST',body:fd}).then(()=>location.reload());
  });
  // ===== Tombol "Posisikan Posisi Sekarang" (revisi 31 Mei 2026) =====
  // Memusatkan peta ke lokasi GPS terkini tanpa harus memulai sesi lari.
  var hereMarker = null;
  document.getElementById('btnLocate').addEventListener('click', function(){
    if (!navigator.geolocation){ alert('Browser tidak mendukung GPS'); return; }
    var btn = this; btn.disabled = true;
    var orig = btn.innerHTML;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Mencari posisi…';
    navigator.geolocation.getCurrentPosition(function(pos){
      var lat = pos.coords.latitude, lng = pos.coords.longitude;
      map.setView([lat, lng], 17);
      if (!hereMarker) {
        hereMarker = L.circleMarker([lat,lng], {radius:8, color:'#2563eb', fillColor:'#3b82f6', fillOpacity:.9, weight:2}).addTo(map);
        hereMarker.bindTooltip('Anda di sini', {permanent:false}).openTooltip();
      } else {
        hereMarker.setLatLng([lat,lng]);
      }
      document.getElementById('runStatus').textContent = 'Posisi sekarang: '+lat.toFixed(5)+', '+lng.toFixed(5)+' (akurasi '+Math.round(pos.coords.accuracy)+' m)';
      btn.disabled = false; btn.innerHTML = orig;
    }, function(err){
      alert('Gagal membaca posisi: '+err.message);
      btn.disabled = false; btn.innerHTML = orig;
    }, {enableHighAccuracy:true, timeout:15000, maximumAge:0});
  });
})();
document.addEventListener('click', function(ev){
  var b = ev.target.closest('.run-del-btn'); if(!b) return;
  if (!confirm('Hapus riwayat lari ini? Tindakan tidak dapat dibatalkan.')) return;
  var id = b.getAttribute('data-id');
  var fd = new FormData(); fd.append('csrf','<?= csrf_token() ?>'); fd.append('_action','delete'); fd.append('session_id', id);
  b.disabled = true;
  fetch('/api_run.php', {method:'POST', body:fd}).then(r=>r.json()).then(function(d){
    if (d && d.ok) { var row = document.getElementById('run-row-'+id); if (row) row.remove(); }
    else { alert('Gagal menghapus.'); b.disabled = false; }
  }).catch(function(){ alert('Gagal menghapus.'); b.disabled = false; });
});

// ===== Lihat riwayat rute =====
var routeModal=null, routeMapObj=null;
document.addEventListener('click', function(ev){
  var b = ev.target.closest('.run-route-btn'); if(!b) return;
  var id = b.getAttribute('data-id');
  if (!routeModal) routeModal = new bootstrap.Modal(document.getElementById('routeModal'));
  document.getElementById('routeInfo').textContent = 'Memuat rute…';
  document.getElementById('routeEmpty').classList.add('d-none');
  routeModal.show();
  fetch('/api_run.php?route='+encodeURIComponent(id)).then(r=>r.json()).then(function(d){
    setTimeout(function(){
      if (!routeMapObj){
        routeMapObj = L.map('routeMap').setView([-6.2,106.816666], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'&copy; OSM'}).addTo(routeMapObj);
      }
      routeMapObj.invalidateSize();
      if (routeMapObj._routeLayer) { routeMapObj.removeLayer(routeMapObj._routeLayer); routeMapObj._routeLayer=null; }
      if (!d || !d.ok || !d.points || !d.points.length){
        document.getElementById('routeInfo').textContent = '';
        document.getElementById('routeEmpty').classList.remove('d-none');
        return;
      }
      var s = d.session || {};
      document.getElementById('routeInfo').innerHTML =
        '<strong>'+(((+s.jarak_m||0)/1000).toFixed(2))+' km</strong> · Durasi '+
        (function(t){t=+s.durasi_dtk||0;var m=Math.floor(t/60),ss=t%60;return (m<10?'0':'')+m+':'+(ss<10?'0':'')+ss;})()+
        ' · '+(+s.kalori||0)+' kkal';
      var grp = L.featureGroup();
      var poly = L.polyline(d.points, {color:'#dc2626', weight:5}).addTo(grp);
      L.marker(d.points[0]).bindTooltip('Start').addTo(grp);
      L.marker(d.points[d.points.length-1]).bindTooltip('Finish').addTo(grp);
      grp.addTo(routeMapObj); routeMapObj._routeLayer = grp;
      routeMapObj.fitBounds(poly.getBounds(), {padding:[20,20]});
    }, 200);
  }).catch(function(){ document.getElementById('routeInfo').textContent='Gagal memuat rute.'; });
});
</script>
<?php include __DIR__.'/includes/footer.php'; ?>
