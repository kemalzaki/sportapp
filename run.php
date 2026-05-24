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
      <div class="d-flex justify-content-center gap-2 mt-3">
        <button id="btnStart" class="btn btn-success px-4"><i class="bi bi-play-fill"></i> Mulai</button>
        <button id="btnStop"  class="btn btn-danger  px-4" disabled><i class="bi bi-stop-fill"></i> Selesai</button>
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
              <div class="d-flex gap-2">
                <button type="button" class="btn btn-sm btn-link text-primary p-0 run-route-btn" data-id="<?= (int)$h['id'] ?>" title="Lihat jalur di peta">
                  <i class="bi bi-map"></i> Lihat Jalur
                </button>
                <button type="button" class="btn btn-sm btn-link text-danger p-0 run-del-btn" data-id="<?= (int)$h['id'] ?>" title="Hapus riwayat ini">
                  <i class="bi bi-trash"></i> Hapus
                </button>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div></div>
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

// === Lihat Jalur (riwayat → polyline di peta) ===
(function(){
  var modalEl=null, routeMap=null, routeLine=null;
  function ensureModal(){
    if (modalEl) return;
    var html = '<div class="modal fade" id="runRouteModal" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content">'
      + '<div class="modal-header"><h6 class="modal-title"><i class="bi bi-map"></i> Jalur Lari</h6><button class="btn-close" data-bs-dismiss="modal"></button></div>'
      + '<div class="modal-body"><div id="runRouteMap" style="height:60vh;border-radius:8px"></div><div id="runRouteInfo" class="small text-muted mt-2"></div></div>'
      + '</div></div></div>';
    document.body.insertAdjacentHTML('beforeend', html);
    modalEl = new bootstrap.Modal(document.getElementById('runRouteModal'));
    document.getElementById('runRouteModal').addEventListener('shown.bs.modal', function(){
      if (routeMap) routeMap.invalidateSize();
    });
  }
  document.addEventListener('click', function(ev){
    var b = ev.target.closest('.run-route-btn'); if(!b) return;
    var id = b.getAttribute('data-id');
    ensureModal();
    document.getElementById('runRouteInfo').textContent = 'Memuat jalur…';
    modalEl.show();
    fetch('/api_run.php?route='+encodeURIComponent(id)).then(r=>r.json()).then(function(d){
      if (!d || !d.ok) { document.getElementById('runRouteInfo').textContent='Gagal memuat jalur.'; return; }
      var pts = (d.points||[]).map(function(p){return [parseFloat(p.lat), parseFloat(p.lng)];});
      var mapEl = document.getElementById('runRouteMap');
      mapEl.innerHTML='';
      routeMap = L.map(mapEl).setView(pts[0]||[-6.2,106.816666], 15);
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'&copy; OSM'}).addTo(routeMap);
      if (!pts.length) {
        document.getElementById('runRouteInfo').textContent='Tidak ada titik GPS pada sesi ini.';
        return;
      }
      routeLine = L.polyline(pts, {color:'#dc2626', weight:5}).addTo(routeMap);
      L.marker(pts[0]).bindPopup('Start').addTo(routeMap);
      L.marker(pts[pts.length-1]).bindPopup('Finish').addTo(routeMap);
      routeMap.fitBounds(routeLine.getBounds(), {padding:[20,20]});
      document.getElementById('runRouteInfo').innerHTML =
        '<strong>'+(d.jarak_km||'0.00')+' km</strong> · Durasi '+(d.durasi||'00:00')+' · '+pts.length+' titik GPS';
      setTimeout(function(){ routeMap.invalidateSize(); }, 200);
    }).catch(function(){ document.getElementById('runRouteInfo').textContent='Gagal memuat jalur.'; });
  });
})();
</script>
<?php include __DIR__.'/includes/footer.php'; ?>
