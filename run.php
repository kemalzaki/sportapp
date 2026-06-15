<?php
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
send_security_headers(); require_login();
$u = current_user(); $uid = (int)$u['id'];
$pageTitle = 'Tracking Jalur / Rute';

// ===== Revisi 15 Jun 2026: tabel untuk Route Builder (idempotent, aman dijalankan di local) =====
@db_exec("CREATE TABLE IF NOT EXISTS run_routes (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL,
    nama TEXT NOT NULL DEFAULT 'Rute',
    jarak_m DOUBLE PRECISION NOT NULL DEFAULT 0,
    elevasi_pref TEXT NOT NULL DEFAULT 'apa-saja',
    surface_pref TEXT NOT NULL DEFAULT 'apa-saja',
    geojson JSONB NOT NULL,
    is_public BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP NOT NULL DEFAULT now()
)");
@db_exec("CREATE INDEX IF NOT EXISTS run_routes_user_idx ON run_routes(user_id, created_at DESC)");

// Riwayat
$history = db_all("SELECT * FROM run_sessions WHERE user_id=$1 ORDER BY mulai_at DESC LIMIT 20", [$uid]);
$active = db_one("SELECT * FROM run_sessions WHERE user_id=$1 AND status='aktif' ORDER BY id DESC LIMIT 1", [$uid]);
$savedRoutes = db_all("SELECT id, nama, jarak_m, elevasi_pref, surface_pref, is_public, created_at FROM run_routes WHERE user_id=$1 ORDER BY id DESC LIMIT 20", [$uid]);

include __DIR__.'/includes/header.php';
?>
<h4 class="mb-3"><i class="bi bi-stopwatch text-danger"></i> Tracking Jalur / Rute Realtime</h4>

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
        <button id="btnStart"  class="btn btn-success px-3"><i class="bi bi-play-fill"></i> Mulai</button>
        <button id="btnPause"  class="btn btn-warning px-3" disabled><i class="bi bi-pause-fill"></i> Jeda</button>
        <button id="btnResume" class="btn btn-info    px-3 d-none"><i class="bi bi-play-circle"></i> Lanjutkan</button>
        <button id="btnStop"   class="btn btn-danger  px-3" disabled><i class="bi bi-stop-circle-fill"></i> Stop / Selesai</button>
        <button id="btnLocate" type="button" class="btn btn-outline-primary px-3" title="Posisikan peta ke lokasi Anda sekarang">
          <i class="bi bi-geo-alt-fill"></i> Posisi Sekarang
        </button>
      </div>
      <div id="runStatus" class="small text-muted mt-2 text-center"></div>
      <div id="wakeStatus" class="small text-success mt-1 text-center"></div>
      <div class="small text-muted mt-2">
        <i class="bi bi-info-circle"></i>
        Untuk tracking saat HP layar mati / pindah halaman seperti Strava, install aplikasi versi
        APK (Capacitor) dan aktifkan plugin <code>@capacitor-community/background-geolocation</code>.
        Di browser biasa, JS dihentikan OS saat layar mati — gunakan tombol <strong>Posisi Sekarang</strong>
        setelah kembali untuk menyambungkan rute.
      </div>
    </div></div>
  </div>

  <div class="col-md-5">
    <div class="card shadow-sm"><div class="card-header d-flex justify-content-between align-items-center">
      <span><i class="bi bi-clock-history"></i> Riwayat Tracking</span>
      <small class="text-muted">Export: GPX / KML untuk Google Maps</small>
    </div>
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
              <div class="btn-group btn-group-sm" role="group" aria-label="Export">
                <a class="btn btn-link p-0 me-2" href="/api_run.php?export=<?= (int)$h['id'] ?>&fmt=gpx" title="Export GPX (import ke Google My Maps / Strava)"><i class="bi bi-download"></i> GPX</a>
                <a class="btn btn-link p-0 me-2" href="/api_run.php?export=<?= (int)$h['id'] ?>&fmt=kml" title="Export KML (Google Earth / Maps)"><i class="bi bi-download"></i> KML</a>
              </div>
              <button type="button" class="btn btn-sm btn-link text-danger p-0 run-del-btn" data-id="<?= (int)$h['id'] ?>" title="Hapus riwayat ini">
                <i class="bi bi-trash"></i> Hapus
              </button>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div></div>
    <div class="small text-muted mt-2">
      <i class="bi bi-info-circle"></i> File <strong>GPX</strong>/<strong>KML</strong> bisa diimpor ke
      <em>Google My Maps</em> (mymaps.google.com → Create new map → Import) atau <em>Strava</em>.
    </div>
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
  var watchId=null, startedAt=null, timerInt=null, pauseAt=null, pausedTotalMs=0, paused=false;
  var totalM = 0, points = [];
  // Revisi 3 Jun 2026: persist state ke localStorage agar saat halaman pindah/HP layar mati,
  // ketika user kembali, tracking otomatis lanjut (mirip Strava — sesi tidak ke-stop sendiri).
  var LS_KEY = 'hf_run_state_v1';
  function saveState(){
    try {
      localStorage.setItem(LS_KEY, JSON.stringify({
        sessionId: sessionId, startedAt: startedAt, totalM: totalM,
        pausedTotalMs: pausedTotalMs, paused: paused, points: points.slice(-500),
        savedAt: Date.now()
      }));
    } catch(e){}
  }
  function loadState(){
    try {
      var raw = localStorage.getItem(LS_KEY); if (!raw) return null;
      return JSON.parse(raw);
    } catch(e){ return null; }
  }
  function clearState(){ try { localStorage.removeItem(LS_KEY); } catch(e){} }
  var wakeLock = null, audioCtx = null, silentOsc = null;
  var map = L.map('runMap').setView([-6.2,106.816666], 13);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'&copy; OSM'}).addTo(map);
  var line = L.polyline([], {color:'#dc2626', weight:5}).addTo(map);
  var marker=null;
  var trackingNotif = null;

  // Revisi 3 Jun 2026: notifikasi persistent supaya user tahu tracking masih jalan
  // walau pindah halaman / HP layar mati. Saat tap notifikasi, balik ke /run.php.
  function notifyTrackingRunning(){
    try {
      if (!('Notification' in window)) return;
      if (Notification.permission === 'default') Notification.requestPermission();
      if (Notification.permission !== 'granted') return;
      if (navigator.serviceWorker && navigator.serviceWorker.ready) {
        navigator.serviceWorker.ready.then(function(reg){
          reg.showNotification('🏃 Tracking lari aktif', {
            body: 'GPS sedang merekam. Tap untuk kembali ke halaman tracking.',
            tag: 'hf-run-tracking', renotify: false, silent: true,
            requireInteraction: true,
            data: { url: '/run.php' },
            icon: '/assets/icon-192.png', badge: '/assets/icon-192.png'
          }).catch(function(){});
        });
      }
    } catch(e){}
  }
  function closeTrackingNotif(){
    try {
      if (navigator.serviceWorker && navigator.serviceWorker.ready) {
        navigator.serviceWorker.ready.then(function(reg){
          reg.getNotifications({ tag: 'hf-run-tracking' }).then(function(ns){
            ns.forEach(function(n){ n.close(); });
          });
        });
      }
    } catch(e){}
  }

  // Auto-resume kalau ada sesi aktif (di DB) + state tersimpan lokal.
  function autoResumeIfActive(){
    if (!sessionId) return;
    var st = loadState();
    if (!st || st.sessionId !== sessionId) {
      // ada sesi aktif di server tapi tidak ada state lokal — mulai dari nol tapi tetap lanjutkan sesi.
      startedAt = Date.now();
      totalM = 0; points = []; pausedTotalMs = 0; paused = false;
    } else {
      startedAt = st.startedAt || Date.now();
      totalM = +st.totalM || 0;
      points = Array.isArray(st.points) ? st.points : [];
      pausedTotalMs = +st.pausedTotalMs || 0;
      paused = !!st.paused;
      if (points.length) { line.setLatLngs(points); map.fitBounds(line.getBounds(), {padding:[20,20]}); }
    }
    document.getElementById('btnStart').disabled = true;
    document.getElementById('btnPause').disabled = false;
    document.getElementById('btnStop').disabled = false;
    document.getElementById('runStatus').textContent = '▶ Sesi aktif dilanjutkan otomatis (auto-resume).';
    timerInt = setInterval(updateUI, 1000);
    if (!paused) startWatch();
    acquireWakeLock();
    startSilentAudio();
    notifyTrackingRunning();
    updateUI();
  }

  // ===== Wake Lock + audio silent loop (revisi 31 Mei 2026) =====
  // Mencegah layar tidur & menjaga JS tetap berjalan saat HP mati / layar tertutup,
  // sehingga GPS tetap terekam dan rute tidak kacau saat dilanjutkan.
  async function acquireWakeLock(){
    try {
      if ('wakeLock' in navigator) {
        wakeLock = await navigator.wakeLock.request('screen');
        document.getElementById('wakeStatus').textContent = '🔒 Wake Lock aktif — layar dijaga tetap menyala';
        wakeLock.addEventListener('release', function(){
          document.getElementById('wakeStatus').textContent = '⚠️ Wake Lock terlepas';
        });
      } else {
        document.getElementById('wakeStatus').textContent = 'ℹ️ Browser tidak mendukung Wake Lock — sebaiknya jangan kunci layar';
      }
    } catch(e){ document.getElementById('wakeStatus').textContent = 'Wake Lock gagal: '+e.message; }
  }
  function releaseWakeLock(){ try{ if(wakeLock){ wakeLock.release(); wakeLock=null; } }catch(e){} }
  // Audio diam yang sangat pelan agar OS tidak men-suspend tab di background
  function startSilentAudio(){
    try {
      var AC = window.AudioContext || window.webkitAudioContext; if (!AC) return;
      audioCtx = new AC();
      silentOsc = audioCtx.createOscillator();
      var gain = audioCtx.createGain(); gain.gain.value = 0.0001; // hampir mute
      silentOsc.connect(gain).connect(audioCtx.destination);
      silentOsc.start();
    } catch(e){}
  }
  function stopSilentAudio(){ try{ if(silentOsc) silentOsc.stop(); if(audioCtx) audioCtx.close(); }catch(e){} silentOsc=null; audioCtx=null; }
  document.addEventListener('visibilitychange', async function(){
    if (document.visibilityState === 'visible' && watchId !== null && !paused) {
      // Re-acquire wake lock setelah kembali dari background
      await acquireWakeLock();
      // Paksa baca posisi sekali untuk menyambung rute dengan akurat (anti rute kacau)
      navigator.geolocation.getCurrentPosition(onPos, onErr, {enableHighAccuracy:true, timeout:15000, maximumAge:0});
      // Restart watch supaya stream GPS yang ter-throttle di background kembali fresh.
      stopWatch(); startWatch();
      // Flush titik yang menumpuk di buffer
      flushPointBuffer();
    }
  });

  function haversine(a,b){
    var R=6371000, toRad=Math.PI/180;
    var dLat=(b.lat-a.lat)*toRad, dLng=(b.lng-a.lng)*toRad;
    var s=Math.sin(dLat/2)**2 + Math.cos(a.lat*toRad)*Math.cos(b.lat*toRad)*Math.sin(dLng/2)**2;
    return 2*R*Math.asin(Math.sqrt(s));
  }
  function fmtTime(s){var m=Math.floor(s/60), ss=s%60; return String(m).padStart(2,'0')+':'+String(ss).padStart(2,'0');}
  function elapsedSec(){
    if (!startedAt) return 0;
    var now = paused ? pauseAt : Date.now();
    return Math.floor((now - startedAt - pausedTotalMs)/1000);
  }
  function updateUI(){
    var km = totalM/1000;
    document.getElementById('runDistance').textContent = km.toFixed(2)+' km';
    var sec = elapsedSec();
    document.getElementById('runTime').textContent = fmtTime(sec);
    if (km>0.05) {
      var paceSec = sec/km;
      var pm=Math.floor(paceSec/60), ps=Math.floor(paceSec%60);
      document.getElementById('runPace').textContent = pm+"'"+String(ps).padStart(2,'0')+'"';
    }
  }
  function onPos(pos){
    if (paused) return; // abaikan titik selama jeda
    var p={lat:pos.coords.latitude,lng:pos.coords.longitude,acc:pos.coords.accuracy,spd:pos.coords.speed};
    // Revisi 4 Jun 2026 — filter GPS lebih cerdas (anti rute kacau):
    // 1) Titik pertama: tolak jika akurasi >100m (tunggu fix yg lebih baik)
    // 2) Titik berikutnya: tolak jika akurasi >35m
    // 3) Tolak lompatan jarak >150m antar tick (glitch / pindah cell)
    // 4) Tolak kecepatan tidak masuk akal (>10 m/s = >36 km/jam utk lari)
    // 5) Minimum gerak 5m supaya berdiri diam tidak menambah jarak (drift GPS)
    var nowT = pos.timestamp || Date.now();
    if (points.length === 0) {
      if (p.acc && p.acc > 100) {
        document.getElementById('runStatus').textContent='Menunggu fix GPS akurat… ('+Math.round(p.acc)+' m)';
        return;
      }
    } else {
      if (p.acc && p.acc > 35) {
        document.getElementById('runStatus').textContent='GPS akurasi rendah ('+Math.round(p.acc)+' m) — titik diabaikan';
        return;
      }
      var last = points[points.length-1];
      var d = haversine(last, p);
      if (d > 150) {
        document.getElementById('runStatus').textContent='Lompatan tidak masuk akal ('+Math.round(d)+' m) — titik diabaikan';
        return;
      }
      var dtSec = last.t ? Math.max(1,(nowT-last.t)/1000) : 1;
      var speed = d / dtSec; // m/s
      if (speed > 10) {
        document.getElementById('runStatus').textContent='Kecepatan tidak realistis ('+speed.toFixed(1)+' m/s) — titik diabaikan';
        return;
      }
      if (d < 5) {
        // gerakan terlalu kecil — kemungkinan drift GPS saat diam. Update marker tanpa
        // menambah jarak/poin agar peta tetap responsif.
        if (marker) marker.setLatLng([p.lat,p.lng]);
        document.getElementById('runStatus').textContent='GPS stabil — drift '+d.toFixed(1)+' m diabaikan ('+Math.round(p.acc)+' m)';
        return;
      }
      totalM += d;
    }
    p.t = nowT;
    points.push(p);
    line.addLatLng([p.lat,p.lng]);
    if (!marker) marker = L.marker([p.lat,p.lng]).addTo(map);
    else marker.setLatLng([p.lat,p.lng]);
    map.setView([p.lat,p.lng], Math.max(map.getZoom(),16));
    document.getElementById('runStatus').textContent='GPS akurasi: '+Math.round(p.acc)+' m';
    if (sessionId) sendPointToServer(p);
    updateUI();
    saveState();
  }
  function onErr(e){ document.getElementById('runStatus').textContent='Error GPS: '+e.message; }

  // Revisi 4 Jun 2026: buffer titik yang gagal dikirim (offline / koneksi terputus),
  // lalu retry otomatis. Mencegah hilangnya data saat jaringan goyang.
  var pointBuffer = [];
  function sendPointToServer(p){
    var pl = {lat:p.lat,lng:p.lng,acc:p.acc,spd:p.spd||'',total_m:totalM};
    pointBuffer.push(pl);
    flushPointBuffer();
  }
  async function flushPointBuffer(){
    if (!sessionId || !pointBuffer.length) return;
    var batch = pointBuffer.slice(0, 20);
    for (var i=0; i<batch.length; i++) {
      var pl = batch[i];
      var fd=new FormData();
      fd.append('csrf',csrf); fd.append('_action','point'); fd.append('session_id',sessionId);
      fd.append('lat',pl.lat); fd.append('lng',pl.lng); fd.append('acc',pl.acc);
      fd.append('spd',pl.spd); fd.append('total_m',pl.total_m);
      try {
        var r = await fetch('/api_run.php',{method:'POST',body:fd, keepalive:true});
        if (!r.ok) return; // tinggalkan di buffer, coba lagi nanti
        pointBuffer.shift();
      } catch(e){ return; }
    }
  }
  setInterval(flushPointBuffer, 5000);

  function startWatch(){
    watchId=navigator.geolocation.watchPosition(onPos,onErr,{enableHighAccuracy:true,maximumAge:1000,timeout:20000});
  }
  function stopWatch(){ if (watchId!==null){ navigator.geolocation.clearWatch(watchId); watchId=null; } }

  document.getElementById('btnStart').addEventListener('click', async function(){
    if (!navigator.geolocation){ alert('Browser tidak mendukung GPS'); return; }
    var fd=new FormData(); fd.append('csrf',csrf); fd.append('_action','start');
    var r = await fetch('/api_run.php',{method:'POST',body:fd}); var d = await r.json();
    if (!d.ok) { alert('Gagal mulai sesi'); return; }
    sessionId=d.id; startedAt=Date.now(); totalM=0; points=[]; pausedTotalMs=0; paused=false; line.setLatLngs([]);
    document.getElementById('btnStart').disabled=true;
    document.getElementById('btnPause').disabled=false;
    document.getElementById('btnStop').disabled=false;
    timerInt=setInterval(updateUI, 1000);
    startWatch();
    await acquireWakeLock();
    startSilentAudio();
    saveState();
    notifyTrackingRunning();
  });

  document.getElementById('btnPause').addEventListener('click', function(){
    if (!sessionId || paused) return;
    paused = true; pauseAt = Date.now();
    stopWatch();
    document.getElementById('btnPause').classList.add('d-none');
    document.getElementById('btnResume').classList.remove('d-none');
    document.getElementById('runStatus').textContent='⏸ Tracking dijeda — tekan "Lanjutkan" untuk melanjutkan';
  });
  document.getElementById('btnResume').addEventListener('click', function(){
    if (!sessionId || !paused) return;
    pausedTotalMs += (Date.now() - pauseAt);
    paused = false; pauseAt = null;
    startWatch();
    document.getElementById('btnResume').classList.add('d-none');
    document.getElementById('btnPause').classList.remove('d-none');
    document.getElementById('runStatus').textContent='▶ Tracking dilanjutkan';
  });

  document.getElementById('btnStop').addEventListener('click', function(){
    if (!confirm('Selesaikan sesi lari sekarang?')) return;
    stopWatch();
    clearInterval(timerInt);
    releaseWakeLock(); stopSilentAudio();
    if (!sessionId){ return; }
    var dur = elapsedSec();
    var fd=new FormData(); fd.append('csrf',csrf); fd.append('_action','stop');
    fd.append('session_id',sessionId); fd.append('total_m',totalM); fd.append('durasi',dur);
    fetch('/api_run.php',{method:'POST',body:fd}).then(function(){ clearState(); closeTrackingNotif(); location.reload(); });
  });

  // ===== Posisi Sekarang =====
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
      } else { hereMarker.setLatLng([lat,lng]); }
      document.getElementById('runStatus').textContent = 'Posisi sekarang: '+lat.toFixed(5)+', '+lng.toFixed(5)+' (akurasi '+Math.round(pos.coords.accuracy)+' m)';
      btn.disabled = false; btn.innerHTML = orig;
    }, function(err){
      alert('Gagal membaca posisi: '+err.message);
      btn.disabled = false; btn.innerHTML = orig;
    }, {enableHighAccuracy:true, timeout:15000, maximumAge:0});
  });

  // Jalankan auto-resume setelah DOM siap
  autoResumeIfActive();
  // Daftar service worker untuk dukungan notifikasi background
  if ('serviceWorker' in navigator) { navigator.serviceWorker.register('/service-worker.js').catch(function(){}); }
})();

// Hapus
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

// Lihat riwayat rute
var routeModal=null, routeMapObj=null;
document.addEventListener('click', function(ev){
  var b = ev.target.closest('.run-route-btn'); if(!b) return;
  var id = b.getAttribute('data-id');
  if (!routeModal) routeModal = new bootstrap.Modal(document.getElementById('routeModal'));
  fetch('/api_run.php?route='+id).then(r=>r.json()).then(function(d){
    if (!d.ok) { alert('Gagal memuat rute.'); return; }
    document.getElementById('routeEmpty').classList.toggle('d-none', d.points.length>0);
    document.getElementById('routeInfo').textContent =
      'Jarak: '+(Math.round(((+d.session.jarak_m)/1000)*100)/100)+' km · Durasi: '+
      String(Math.floor((+d.session.durasi_dtk)/60)).padStart(2,'0')+':'+String((+d.session.durasi_dtk)%60).padStart(2,'0')+
      ' · Kalori: '+(+d.session.kalori);
    routeModal.show();
    setTimeout(function(){
      if (!routeMapObj) {
        routeMapObj = L.map('routeMap').setView([-6.2,106.8],13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'&copy; OSM'}).addTo(routeMapObj);
      }
      routeMapObj.eachLayer(function(l){ if(l instanceof L.Polyline || l instanceof L.Marker) routeMapObj.removeLayer(l); });
      if (d.points.length) {
        var ln = L.polyline(d.points,{color:'#dc2626',weight:5}).addTo(routeMapObj);
        routeMapObj.fitBounds(ln.getBounds(),{padding:[20,20]});
        L.marker(d.points[0]).addTo(routeMapObj).bindTooltip('Mulai');
        L.marker(d.points[d.points.length-1]).addTo(routeMapObj).bindTooltip('Selesai');
      }
      routeMapObj.invalidateSize();
    }, 250);
  });
});
</script>

<!-- ================================================================== -->
<!-- ====== Revisi 15 Jun 2026: Eksplorasi Rute & Peta Canggih ======== -->
<!-- ================================================================== -->
<hr class="my-4">
<h4 class="mb-3"><i class="bi bi-compass text-primary"></i> Eksplorasi Rute &amp; Peta Canggih</h4>

<ul class="nav nav-tabs" id="advTab" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link active" id="tab-builder-btn" data-bs-toggle="tab" data-bs-target="#tab-builder" type="button"><i class="bi bi-magic"></i> Route Builder</button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="tab-heatmap-btn" data-bs-toggle="tab" data-bs-target="#tab-heatmap" type="button"><i class="bi bi-fire"></i> Heatmaps</button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="tab-offline-btn" data-bs-toggle="tab" data-bs-target="#tab-offline" type="button"><i class="bi bi-cloud-download"></i> Peta Offline</button>
  </li>
</ul>

<div class="tab-content border border-top-0 rounded-bottom p-3 bg-body">
  <!-- ============ TAB 1: ROUTE BUILDER ============ -->
  <div class="tab-pane fade show active" id="tab-builder" role="tabpanel">
    <div class="row g-3">
      <div class="col-md-4">
        <label class="form-label small">Titik mulai</label>
        <div class="input-group input-group-sm mb-2">
          <input id="rbStart" class="form-control" placeholder="lat,lng atau kosongkan = lokasi sekarang">
          <button class="btn btn-outline-secondary" id="rbUseMe" type="button" title="Gunakan lokasi saya"><i class="bi bi-geo-alt"></i></button>
        </div>

        <label class="form-label small">Target jarak (km)</label>
        <input type="number" id="rbDist" class="form-control form-control-sm mb-2" min="1" max="42" step="0.5" value="5">

        <label class="form-label small">Preferensi elevasi</label>
        <select id="rbElev" class="form-select form-select-sm mb-2">
          <option value="apa-saja">Apa saja</option>
          <option value="datar">Datar (loop kota)</option>
          <option value="berbukit">Berbukit / tanjakan</option>
        </select>

        <label class="form-label small">Jenis jalan</label>
        <select id="rbSurface" class="form-select form-select-sm mb-2">
          <option value="apa-saja">Apa saja</option>
          <option value="aspal">Aspal (jalan raya/kompleks)</option>
          <option value="tanah">Tanah / trail</option>
          <option value="campuran">Campuran</option>
        </select>

        <label class="form-label small">Tipe rute</label>
        <select id="rbShape" class="form-select form-select-sm mb-2">
          <option value="loop">Loop (kembali ke titik mulai)</option>
          <option value="out">Pulang-pergi (out &amp; back)</option>
        </select>

        <div class="d-grid gap-2">
          <button id="rbGen" class="btn btn-primary btn-sm"><i class="bi bi-magic"></i> Generate Rute</button>
          <div class="input-group input-group-sm">
            <input id="rbName" class="form-control" placeholder="Nama rute" value="Rute Baru">
            <div class="input-group-text bg-body">
              <input class="form-check-input mt-0 me-1" type="checkbox" id="rbPublic"><label for="rbPublic" class="small mb-0">Publik</label>
            </div>
          </div>
          <button id="rbSave" class="btn btn-success btn-sm" disabled><i class="bi bi-save"></i> Simpan Rute</button>
          <button id="rbExport" class="btn btn-outline-secondary btn-sm" disabled><i class="bi bi-download"></i> Export GPX</button>
        </div>
        <div id="rbInfo" class="small text-muted mt-2"></div>
      </div>
      <div class="col-md-8">
        <div id="builderMap" style="height:420px;border-radius:10px;border:1px solid var(--bs-border-color,#e5e7eb)"></div>
        <div class="mt-2">
          <strong class="small">Rute tersimpan:</strong>
          <div class="list-group list-group-flush small mt-1" id="rbSavedList">
            <?php if(!$savedRoutes): ?><div class="text-muted px-2">Belum ada rute tersimpan.</div><?php endif; ?>
            <?php foreach($savedRoutes as $r): ?>
              <div class="list-group-item d-flex justify-content-between align-items-center py-1" data-rid="<?= (int)$r['id'] ?>">
                <span>
                  <i class="bi bi-signpost-2 text-primary"></i>
                  <strong><?= htmlspecialchars($r['nama']) ?></strong>
                  · <?= round(((float)$r['jarak_m'])/1000,2) ?> km
                  · <span class="badge bg-light text-dark border"><?= htmlspecialchars($r['surface_pref']) ?></span>
                  · <span class="badge bg-light text-dark border"><?= htmlspecialchars($r['elevasi_pref']) ?></span>
                  <?php if($r['is_public']==='t' || $r['is_public']===true || $r['is_public']==='1'): ?>
                    <span class="badge bg-info-subtle text-info">publik</span>
                  <?php endif; ?>
                </span>
                <span>
                  <button class="btn btn-link btn-sm p-0 me-2 rb-load" data-rid="<?= (int)$r['id'] ?>"><i class="bi bi-eye"></i></button>
                  <button class="btn btn-link btn-sm p-0 text-danger rb-del" data-rid="<?= (int)$r['id'] ?>"><i class="bi bi-trash"></i></button>
                </span>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ============ TAB 2: HEATMAPS ============ -->
  <div class="tab-pane fade" id="tab-heatmap" role="tabpanel">
    <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
      <div class="btn-group btn-group-sm" role="group">
        <input type="radio" class="btn-check" name="hmMode" id="hmPribadi" value="pribadi" checked>
        <label class="btn btn-outline-primary" for="hmPribadi"><i class="bi bi-person"></i> Pribadi</label>
        <input type="radio" class="btn-check" name="hmMode" id="hmPublik" value="publik">
        <label class="btn btn-outline-primary" for="hmPublik"><i class="bi bi-people"></i> Publik (komunitas)</label>
        <input type="radio" class="btn-check" name="hmMode" id="hmNight" value="night">
        <label class="btn btn-outline-primary" for="hmNight"><i class="bi bi-moon-stars"></i> Night Heatmap</label>
      </div>
      <button id="hmReload" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-clockwise"></i> Muat ulang</button>
      <span id="hmInfo" class="small text-muted"></span>
    </div>
    <div id="heatMap" style="height:440px;border-radius:10px;border:1px solid var(--bs-border-color,#e5e7eb)"></div>
    <div class="small text-muted mt-2">
      <i class="bi bi-info-circle"></i> Heatmap dihitung dari semua titik GPS yang tersimpan di <code>run_points</code>.
      Mode <strong>Night</strong> hanya menampilkan titik antara pukul 18:00–05:00 — berguna untuk memilih jalur yang sering dilalui komunitas saat malam.
    </div>
  </div>

  <!-- ============ TAB 3: PETA OFFLINE ============ -->
  <div class="tab-pane fade" id="tab-offline" role="tabpanel">
    <div class="row g-3">
      <div class="col-md-4">
        <label class="form-label small">Pilih rute yang akan di-cache</label>
        <select id="offRouteSel" class="form-select form-select-sm mb-2">
          <option value="">— Pilih rute tersimpan —</option>
          <?php foreach($savedRoutes as $r): ?>
            <option value="<?= (int)$r['id'] ?>"><?= htmlspecialchars($r['nama']) ?> (<?= round(((float)$r['jarak_m'])/1000,2) ?> km)</option>
          <?php endforeach; ?>
        </select>
        <label class="form-label small">Atau pakai riwayat sesi</label>
        <select id="offSessSel" class="form-select form-select-sm mb-2">
          <option value="">— Pilih riwayat lari —</option>
          <?php foreach($history as $h): ?>
            <option value="<?= (int)$h['id'] ?>"><?= date('d M H:i', strtotime($h['mulai_at'])) ?> · <?= round(((float)$h['jarak_m'])/1000,2) ?> km</option>
          <?php endforeach; ?>
        </select>
        <label class="form-label small">Level zoom yang di-cache</label>
        <select id="offZoom" class="form-select form-select-sm mb-2">
          <option value="13">13 (area luas, ~kota)</option>
          <option value="14" selected>14 (kecamatan)</option>
          <option value="15">15 (kelurahan)</option>
          <option value="16">16 (detail jalan)</option>
        </select>
        <div class="d-grid gap-2">
          <button id="offDownload" class="btn btn-primary btn-sm" disabled><i class="bi bi-cloud-download"></i> Unduh Peta Offline</button>
          <button id="offClear" class="btn btn-outline-danger btn-sm"><i class="bi bi-x-circle"></i> Hapus Cache Offline</button>
        </div>
        <div id="offProg" class="small text-muted mt-2"></div>
      </div>
      <div class="col-md-8">
        <div id="offMap" style="height:420px;border-radius:10px;border:1px solid var(--bs-border-color,#e5e7eb)"></div>
        <div class="small text-muted mt-2">
          <i class="bi bi-info-circle"></i> Tile peta akan disimpan di <code>CacheStorage</code> browser. Saat sinyal hilang, peta yang
          sudah di-cache tetap terlihat (trail running / naik gunung). Hapus cache untuk membebaskan ruang.
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://unpkg.com/leaflet.heat@0.2.0/dist/leaflet-heat.js"></script>
<script>
(function(){
  var CSRF = '<?= csrf_token() ?>';
  var OSRM = 'https://router.project-osrm.org/route/v1/foot/';
  var TILE = 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
  var CACHE_NAME = 'hf-tiles-v1';

  // ===== Service worker untuk mem-serve tile dari cache saat offline =====
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/service-worker.js').catch(function(){});
  }

  // ===== Helper: rute loop sintetis berbasis bearing acak + snap ke jalan via OSRM =====
  function destPoint(lat,lng,distM,bearingDeg){
    var R=6371000, br=bearingDeg*Math.PI/180, la=lat*Math.PI/180, lo=lng*Math.PI/180;
    var dr=distM/R;
    var la2=Math.asin(Math.sin(la)*Math.cos(dr)+Math.cos(la)*Math.sin(dr)*Math.cos(br));
    var lo2=lo+Math.atan2(Math.sin(br)*Math.sin(dr)*Math.cos(la), Math.cos(dr)-Math.sin(la)*Math.sin(la2));
    return {lat:la2*180/Math.PI, lng:lo2*180/Math.PI};
  }

  // ====================== ROUTE BUILDER ======================
  var bMap = null, bLine = null, bMarkers = [], bCurrentRoute = null;
  function ensureBuilderMap(center){
    if (bMap) return bMap;
    bMap = L.map('builderMap').setView(center||[-6.2,106.816666],14);
    L.tileLayer(TILE,{maxZoom:19,attribution:'&copy; OSM'}).addTo(bMap);
    return bMap;
  }
  document.getElementById('tab-builder-btn').addEventListener('shown.bs.tab', function(){
    ensureBuilderMap(); setTimeout(function(){ bMap.invalidateSize(); },100);
  });
  document.getElementById('rbUseMe').addEventListener('click', function(){
    if (!navigator.geolocation) return;
    navigator.geolocation.getCurrentPosition(function(p){
      document.getElementById('rbStart').value = p.coords.latitude.toFixed(6)+','+p.coords.longitude.toFixed(6);
      ensureBuilderMap([p.coords.latitude,p.coords.longitude]).setView([p.coords.latitude,p.coords.longitude],15);
    });
  });

  function clearBuilder(){
    if (bLine){ bMap.removeLayer(bLine); bLine=null; }
    bMarkers.forEach(function(m){ bMap.removeLayer(m); }); bMarkers=[];
  }

  async function buildRoute(){
    var info = document.getElementById('rbInfo');
    var st = document.getElementById('rbStart').value.trim();
    var lat,lng;
    if (st && st.indexOf(',')>0){ var p=st.split(','); lat=parseFloat(p[0]); lng=parseFloat(p[1]); }
    if (!lat || !lng){
      info.textContent = 'Mengambil lokasi GPS...';
      try {
        var pos = await new Promise(function(res,rej){ navigator.geolocation.getCurrentPosition(res,rej,{enableHighAccuracy:true,timeout:10000}); });
        lat = pos.coords.latitude; lng = pos.coords.longitude;
      } catch(e){ info.textContent = 'Tidak bisa mengambil lokasi. Isi titik mulai manual (lat,lng).'; return; }
    }
    ensureBuilderMap([lat,lng]).setView([lat,lng],14);
    clearBuilder();

    var distKm = parseFloat(document.getElementById('rbDist').value)||5;
    var shape = document.getElementById('rbShape').value;
    var elev = document.getElementById('rbElev').value;
    var totalM = distKm*1000;

    // Strategi:
    //  - "out" : pilih bearing acak, lompat 0.5*total, lalu kembali.
    //  - "loop": tiga waypoint berbentuk segitiga membentuk loop ~ totalM.
    //  - elev "berbukit": gunakan bearing yang menjauh dari pusat kota (heuristik: pilih bearing acak — OSRM tidak tahu elevasi gratis).
    var waypoints = [[lng,lat]];
    var baseBearing = Math.random()*360;
    if (shape === 'out') {
      var dp = destPoint(lat,lng,totalM*0.5,baseBearing);
      waypoints.push([dp.lng, dp.lat]); waypoints.push([lng,lat]);
    } else {
      // segitiga sama sisi → keliling 3*L, jadi L = totalM/3, jarak titik dari start ~ L*0.577
      var sideRadius = (totalM/3)*0.6;
      var b1 = baseBearing, b2 = baseBearing+120, b3 = baseBearing+240;
      var w1 = destPoint(lat,lng,sideRadius,b1);
      var w2 = destPoint(lat,lng,sideRadius,b2);
      var w3 = destPoint(lat,lng,sideRadius,b3);
      waypoints.push([w1.lng,w1.lat]); waypoints.push([w2.lng,w2.lat]); waypoints.push([w3.lng,w3.lat]); waypoints.push([lng,lat]);
    }

    info.textContent = 'Menghubungi OSRM untuk men-snap rute ke jalan...';
    var url = OSRM + waypoints.map(function(w){return w[0]+','+w[1];}).join(';') + '?overview=full&geometries=geojson';
    try {
      var r = await fetch(url);
      var d = await r.json();
      if (!d.routes || !d.routes.length) throw new Error('OSRM tidak mengembalikan rute');
      var coords = d.routes[0].geometry.coordinates.map(function(c){return [c[1],c[0]];}); // [lat,lng]
      var realM  = d.routes[0].distance;
      bLine = L.polyline(coords,{color:'#2563eb',weight:5}).addTo(bMap);
      bMap.fitBounds(bLine.getBounds(),{padding:[20,20]});
      bMarkers.push(L.marker(coords[0]).addTo(bMap).bindTooltip('Mulai'));
      bMarkers.push(L.marker(coords[coords.length-1]).addTo(bMap).bindTooltip('Selesai'));
      bCurrentRoute = { coords: coords, jarak_m: realM };
      info.innerHTML = '✓ Rute dibuat: <strong>'+(realM/1000).toFixed(2)+' km</strong> '+
        '(target '+distKm.toFixed(2)+' km) · preferensi: '+elev+' / '+document.getElementById('rbSurface').value+
        '. <em>Catatan: OSRM publik tidak membedakan aspal/tanah — preferensi disimpan sebagai metadata.</em>';
      document.getElementById('rbSave').disabled = false;
      document.getElementById('rbExport').disabled = false;
    } catch(e){
      info.textContent = 'Gagal generate rute: '+e.message+'. Coba ulangi atau pakai jarak yang lebih kecil.';
    }
  }
  document.getElementById('rbGen').addEventListener('click', buildRoute);

  document.getElementById('rbSave').addEventListener('click', async function(){
    if (!bCurrentRoute) return;
    var fd = new FormData();
    fd.append('csrf', CSRF);
    fd.append('_action','route_save');
    fd.append('nama', document.getElementById('rbName').value || 'Rute');
    fd.append('jarak_m', bCurrentRoute.jarak_m);
    fd.append('elevasi_pref', document.getElementById('rbElev').value);
    fd.append('surface_pref', document.getElementById('rbSurface').value);
    fd.append('is_public', document.getElementById('rbPublic').checked ? '1':'0');
    fd.append('coords', JSON.stringify(bCurrentRoute.coords));
    var r = await fetch('/api_run.php',{method:'POST',body:fd}); var d = await r.json();
    if (d.ok){ alert('Rute tersimpan. ID #'+d.id); location.reload(); } else alert('Gagal: '+(d.err||''));
  });

  document.getElementById('rbExport').addEventListener('click', function(){
    if (!bCurrentRoute) return;
    var gpx = '<' + '?xml version="1.0" encoding="UTF-8"?>\n<gpx version="1.1" creator="SportApp" xmlns="http://www.topografix.com/GPX/1/1">\n<trk><name>'+(document.getElementById('rbName').value||'Rute')+'</name><trkseg>\n';
    bCurrentRoute.coords.forEach(function(c){ gpx+='<trkpt lat="'+c[0]+'" lon="'+c[1]+'"></trkpt>\n'; });
    gpx += '</trkseg></trk></gpx>';
    var blob = new Blob([gpx], {type:'application/gpx+xml'});
    var a = document.createElement('a'); a.href=URL.createObjectURL(blob);
    a.download=(document.getElementById('rbName').value||'rute')+'.gpx'; a.click();
  });

  document.addEventListener('click', async function(ev){
    var del = ev.target.closest('.rb-del');
    if (del){
      if (!confirm('Hapus rute ini?')) return;
      var fd = new FormData(); fd.append('csrf',CSRF); fd.append('_action','route_delete'); fd.append('id',del.dataset.rid);
      var r = await fetch('/api_run.php',{method:'POST',body:fd}); var d=await r.json();
      if (d.ok) location.reload();
      return;
    }
    var ld = ev.target.closest('.rb-load');
    if (ld){
      var r = await fetch('/api_run.php?route_load='+ld.dataset.rid); var d=await r.json();
      if (!d.ok) return alert('Gagal');
      ensureBuilderMap();
      clearBuilder();
      bLine = L.polyline(d.coords,{color:'#16a34a',weight:5}).addTo(bMap);
      bMap.fitBounds(bLine.getBounds(),{padding:[20,20]});
      bCurrentRoute = { coords: d.coords, jarak_m: d.jarak_m };
      document.getElementById('rbInfo').textContent = '✓ Memuat rute tersimpan: '+(d.jarak_m/1000).toFixed(2)+' km';
      document.getElementById('rbExport').disabled = false;
    }
  });

  // ====================== HEATMAPS ======================
  var hMap = null, hLayer = null;
  function ensureHeatMap(){
    if (hMap) return hMap;
    hMap = L.map('heatMap').setView([-6.2,106.816666],12);
    L.tileLayer(TILE,{maxZoom:19,attribution:'&copy; OSM'}).addTo(hMap);
    return hMap;
  }
  document.getElementById('tab-heatmap-btn').addEventListener('shown.bs.tab', function(){
    ensureHeatMap(); setTimeout(function(){ hMap.invalidateSize(); loadHeat(); },100);
  });
  async function loadHeat(){
    var mode = document.querySelector('input[name=hmMode]:checked').value;
    document.getElementById('hmInfo').textContent = 'Memuat titik heatmap ('+mode+')...';
    var r = await fetch('/api_run.php?heatmap='+mode); var d=await r.json();
    if (!d.ok){ document.getElementById('hmInfo').textContent='Gagal memuat.'; return; }
    if (hLayer){ hMap.removeLayer(hLayer); hLayer=null; }
    if (!d.points.length){ document.getElementById('hmInfo').textContent='Belum ada titik untuk mode ini.'; return; }
    hLayer = L.heatLayer(d.points, {radius:18, blur:22, maxZoom:17,
      gradient: mode==='night' ? {0.2:'#1e3a8a',0.4:'#3b82f6',0.7:'#fbbf24',1.0:'#fde047'} : undefined
    }).addTo(hMap);
    var b = L.latLngBounds(d.points.map(function(p){return [p[0],p[1]];}));
    hMap.fitBounds(b,{padding:[20,20]});
    document.getElementById('hmInfo').textContent = '✓ '+d.points.length+' titik dimuat ('+mode+').';
  }
  document.getElementById('hmReload').addEventListener('click', loadHeat);
  document.querySelectorAll('input[name=hmMode]').forEach(function(el){ el.addEventListener('change', loadHeat); });

  // ====================== PETA OFFLINE ======================
  var oMap = null, oLine = null, oCoords = null;
  function ensureOffMap(center){
    if (oMap) return oMap;
    oMap = L.map('offMap').setView(center||[-6.2,106.816666],13);
    L.tileLayer(TILE,{maxZoom:19,attribution:'&copy; OSM (cache)'}).addTo(oMap);
    return oMap;
  }
  document.getElementById('tab-offline-btn').addEventListener('shown.bs.tab', function(){
    ensureOffMap(); setTimeout(function(){ oMap.invalidateSize(); },100);
  });

  async function loadCoordsForOffline(){
    var rid = document.getElementById('offRouteSel').value;
    var sid = document.getElementById('offSessSel').value;
    if (!rid && !sid){ oCoords=null; document.getElementById('offDownload').disabled=true; return; }
    var url = rid ? '/api_run.php?route_load='+rid : '/api_run.php?route='+sid;
    var r = await fetch(url); var d = await r.json();
    if (!d.ok) return;
    oCoords = rid ? d.coords : d.points;
    ensureOffMap();
    if (oLine){ oMap.removeLayer(oLine); }
    oLine = L.polyline(oCoords,{color:'#7c3aed',weight:5}).addTo(oMap);
    oMap.fitBounds(oLine.getBounds(),{padding:[20,20]});
    document.getElementById('offDownload').disabled = !oCoords || !oCoords.length;
  }
  document.getElementById('offRouteSel').addEventListener('change', function(){ document.getElementById('offSessSel').value=''; loadCoordsForOffline(); });
  document.getElementById('offSessSel').addEventListener('change', function(){ document.getElementById('offRouteSel').value=''; loadCoordsForOffline(); });

  function lng2tile(lng,z){ return Math.floor((lng+180)/360*Math.pow(2,z)); }
  function lat2tile(lat,z){ return Math.floor((1-Math.log(Math.tan(lat*Math.PI/180)+1/Math.cos(lat*Math.PI/180))/Math.PI)/2*Math.pow(2,z)); }

  document.getElementById('offDownload').addEventListener('click', async function(){
    if (!oCoords || !oCoords.length) return;
    if (!('caches' in window)){ alert('Browser tidak mendukung CacheStorage.'); return; }
    var z = parseInt(document.getElementById('offZoom').value,10);
    var minLat=999,maxLat=-999,minLng=999,maxLng=-999;
    oCoords.forEach(function(p){ if(p[0]<minLat)minLat=p[0]; if(p[0]>maxLat)maxLat=p[0]; if(p[1]<minLng)minLng=p[1]; if(p[1]>maxLng)maxLng=p[1]; });
    // bbox + padding
    var pad=0.005; minLat-=pad; maxLat+=pad; minLng-=pad; maxLng+=pad;
    var x1=lng2tile(minLng,z), x2=lng2tile(maxLng,z);
    var y1=lat2tile(maxLat,z), y2=lat2tile(minLat,z);
    var total = (Math.abs(x2-x1)+1)*(Math.abs(y2-y1)+1);
    if (total > 800 && !confirm('Akan men-download '+total+' tile. Lanjutkan?')) return;

    var cache = await caches.open(CACHE_NAME);
    var done = 0, fail=0;
    var prog = document.getElementById('offProg');
    var subs=['a','b','c'];
    for (var x=Math.min(x1,x2); x<=Math.max(x1,x2); x++){
      for (var y=Math.min(y1,y2); y<=Math.max(y1,y2); y++){
        var s = subs[(x+y)%3];
        var u = 'https://'+s+'.tile.openstreetmap.org/'+z+'/'+x+'/'+y+'.png';
        try {
          var resp = await fetch(u, {mode:'cors'});
          if (resp.ok) await cache.put(u, resp.clone()); else fail++;
        } catch(e){ fail++; }
        done++;
        if (done%10===0) prog.textContent = 'Mengunduh '+done+'/'+total+' tile (gagal: '+fail+')...';
      }
    }
    prog.textContent = '✓ Selesai: '+done+' tile di-cache di zoom '+z+' (gagal: '+fail+'). Peta dapat dibuka offline saat di area ini.';
  });

  document.getElementById('offClear').addEventListener('click', async function(){
    if (!('caches' in window)) return;
    await caches.delete(CACHE_NAME);
    document.getElementById('offProg').textContent = 'Cache tile peta dihapus.';
  });

})();
</script>

<?php include __DIR__.'/includes/footer.php'; ?>

