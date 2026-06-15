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

<div class="card border-0 shadow-sm mb-3 border-start border-4 border-danger">
  <div class="card-body py-3">
    <h6 class="fw-bold mb-2"><i class="bi bi-info-circle text-danger"></i> Cara Penggunaan Tracking Jalur / Rute Realtime</h6>
    <ol class="small mb-2 ps-3">
      <li>Tekan <b class="text-success">Mulai</b> &mdash; izinkan akses GPS saat diminta. Browser otomatis
        merekam lintasan, jarak, durasi, dan pace Anda secara real-time di peta.</li>
      <li>Saat istirahat, tekan <b class="text-warning">Jeda</b>. Lanjutkan kembali dengan
        <b class="text-info">Lanjutkan</b>. Selesai berlari, tekan <b class="text-danger">Stop / Selesai</b>.</li>
      <li>Tombol <b>Posisi Sekarang</b> memusatkan peta ke lokasi GPS aktif &mdash; pakai bila peta tertinggal
        setelah HP sempat layar mati.</li>
      <li>Riwayat tiap sesi tersimpan di panel kanan: <b>Lihat Rute</b> menampilkan lintasan;
        <b>GPX</b>/<b>KML</b> bisa diimpor ke Google My Maps / Strava / Google Earth.</li>
      <li>Browser mengaktifkan <b>Wake Lock</b> agar layar tidak mati. Untuk tracking saat layar HP
        benar-benar mati, gunakan versi APK (Capacitor + background-geolocation).</li>
    </ol>
    <div class="alert alert-info small mb-0 py-2">
      <i class="bi bi-shield-check"></i>
      Filter anti-glitch otomatis: titik GPS dengan akurasi &gt;35&nbsp;m, lompatan &gt;150&nbsp;m, atau
      kecepatan &gt;36&nbsp;km/jam <i>diabaikan</i> agar rute tidak kacau.
    </div>
  </div>
</div>

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
        <!-- Revisi 15 Juni 2026 — Popup melayang (Document Picture-in-Picture) agar GPS tetap aktif saat berpindah app/tab di HP -->
        <button id="btnPip" type="button" class="btn btn-outline-info px-3" title="Tampilkan peta sebagai jendela melayang (PiP) seperti Google Maps">
          <i class="bi bi-pip"></i> Popup Melayang
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
<script>window.MAPBOX_TOKEN_JS = 'pk.eyJ1IjoiYWRhbXNhc21pdGE1MzQiLCJhIjoiY21xZnRsbWxjMXZldDJ0cHlhN2Jycnd1dCJ9.2E00ey-sgX9jUmf5kIRoEA';
window.MAPBOX_TILE_URL = 'https://api.mapbox.com/styles/v1/mapbox/outdoors-v12/tiles/256/{z}/{x}/{y}@2x?access_token=' + MAPBOX_TOKEN_JS;
window.MAPBOX_ATTR = '&copy; <a href="https://www.mapbox.com/about/maps/">Mapbox</a> &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>';
</script>

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
  L.tileLayer(window.MAPBOX_TILE_URL,{maxZoom:19,attribution:window.MAPBOX_ATTR}).addTo(map);
  // Revisi 15 Juni 2026 — multi-segment polyline. Setiap kali ada gap besar (layar mati lalu nyala),
  // segmen baru dibuat sehingga TIDAK ada garis lurus dari titik mati ke titik nyala.
  var segments = [];           // array of {poly:L.polyline, pts:[[lat,lng],...] }
  function newSegment(){
    var poly = L.polyline([], {color:'#dc2626', weight:5}).addTo(map);
    segments.push({ poly: poly, pts: [] });
    return segments[segments.length-1];
  }
  var seg = newSegment();
  // Alias `line` untuk kompatibilitas dengan kode lama yang masih memanggil line.addLatLng / setLatLngs.
  var line = {
    addLatLng: function(ll){ seg.poly.addLatLng(ll); seg.pts.push(ll); },
    setLatLngs: function(arr){
      segments.forEach(function(s){ map.removeLayer(s.poly); });
      segments = []; seg = newSegment();
      if (arr && arr.length) { arr.forEach(function(ll){ seg.poly.addLatLng(ll); seg.pts.push(ll); }); }
    }
  };
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
      var dtSec = last.t ? Math.max(1,(nowT-last.t)/1000) : 1;
      var speed = d / dtSec; // m/s
      // Revisi 15 Juni 2026 — anti garis-lurus saat layar mati:
      // jika gap waktu > 25 dtk ATAU jarak > 150 m, anggap "pen-lift":
      // tutup segmen saat ini, mulai segmen baru, JANGAN tambah jarak (anti garis lurus
      // dari titik mati layar ke titik nyala layar).
      if (d > 150 || dtSec > 25) {
        document.getElementById('runStatus').textContent =
          'Gap GPS terdeteksi ('+Math.round(d)+' m / '+Math.round(dtSec)+' dtk) — segmen baru dimulai, jarak tidak ditambah.';
        seg = newSegment();
        // catat titik baru sebagai awal segmen, tanpa menambah jarak
        p.t = nowT;
        points.push(p);
        seg.poly.addLatLng([p.lat,p.lng]); seg.pts.push([p.lat,p.lng]);
        if (!marker) marker = L.marker([p.lat,p.lng]).addTo(map);
        else marker.setLatLng([p.lat,p.lng]);
        map.setView([p.lat,p.lng], Math.max(map.getZoom(),16));
        if (sessionId) sendPointToServer(p);
        updateUI(); saveState();
        return;
      }
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

  // ===== Revisi 15 Juni 2026 — Document Picture-in-Picture (peta melayang) =====
  // Saat user pindah ke app/tab lain di HP, jendela melayang ini tetap menampilkan
  // peta + status, sehingga browser tidak men-throttle JavaScript & GPS tetap aktif.
  // Mengikuti pola Google Maps PiP. Memerlukan Chromium 116+/HTTPS.
  var pipWin = null;
  document.getElementById('btnPip').addEventListener('click', async function(){
    if (!('documentPictureInPicture' in window)) {
      alert('Browser Anda belum mendukung Document Picture-in-Picture.\nGunakan Chrome/Edge versi terbaru di Android/Desktop, lalu jalankan dari HTTPS.');
      return;
    }
    if (pipWin) { try { pipWin.close(); } catch(e){} pipWin = null; return; }
    try {
      pipWin = await window.documentPictureInPicture.requestWindow({ width: 340, height: 420 });
      // Salin stylesheet ke jendela PiP
      [...document.styleSheets].forEach(function(ss){
        try {
          var rules = [...ss.cssRules].map(function(r){return r.cssText;}).join('');
          var s = pipWin.document.createElement('style'); s.textContent = rules;
          pipWin.document.head.appendChild(s);
        } catch(e) {
          if (ss.href) {
            var l = pipWin.document.createElement('link');
            l.rel='stylesheet'; l.href=ss.href; pipWin.document.head.appendChild(l);
          }
        }
      });
      // Pindahkan elemen peta ke PiP window
      var mapHost = document.getElementById('runMap');
      var placeholder = document.createElement('div');
      placeholder.id = 'runMapPiPPlaceholder';
      placeholder.style.height = mapHost.style.height;
      placeholder.className = 'd-flex align-items-center justify-content-center text-muted border rounded bg-light';
      placeholder.innerHTML = '<div class="text-center small"><i class="bi bi-pip fs-2"></i><br>Peta sedang melayang. Tutup popup untuk mengembalikan.</div>';
      mapHost.parentNode.insertBefore(placeholder, mapHost);
      pipWin.document.body.style.margin = '0';
      pipWin.document.body.appendChild(mapHost);
      mapHost.style.height = '100%';
      setTimeout(function(){ map.invalidateSize(); }, 80);

      pipWin.addEventListener('pagehide', function(){
        // Kembalikan peta ke halaman utama
        mapHost.style.height = '380px';
        placeholder.parentNode.replaceChild(mapHost, placeholder);
        setTimeout(function(){ map.invalidateSize(); }, 80);
        pipWin = null;
      });
    } catch(err) {
      alert('Tidak dapat membuka popup melayang: '+err.message);
    }
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
        L.tileLayer(window.MAPBOX_TILE_URL,{maxZoom:19,attribution:window.MAPBOX_ATTR}).addTo(routeMapObj);
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

<div class="card border-0 shadow-sm mb-3 border-start border-4 border-primary">
  <div class="card-body py-3">
    <h6 class="fw-bold mb-2"><i class="bi bi-info-circle text-primary"></i> Cara Penggunaan Eksplorasi Rute &amp; Peta Canggih</h6>
    <ul class="small mb-2 ps-3">
      <li><b>Route Builder &middot; Auto Generate</b>: isi titik mulai (atau klik <i class="bi bi-geo-alt"></i> untuk lokasi sekarang),
        target jarak, preferensi elevasi/jalan/tipe rute, lalu tekan <b>Generate Rute</b>.
        Sistem akan men-<i>scale</i> hasil hingga jaraknya mendekati target dan mencoba beberapa kandidat
        bearing untuk mencocokkan preferensi elevasi/permukaan.</li>
      <li><b>Route Builder &middot; Buat Sendiri</b>: pilih mode <b>Manual</b>, lalu klik titik-titik di peta
        untuk menyusun rute Anda sendiri. Tekan <b>Snap ke jalan</b> untuk menempelkan ke jaringan jalan,
        atau <b>Hapus titik terakhir</b> untuk koreksi. Beri nama dan simpan.</li>
      <li><b>Heatmaps</b>: visualisasi titik GPS yang sering Anda lewati (Pribadi), populer di komunitas
        (Publik), atau khusus malam (Night) &mdash; cocok untuk memilih jalur aman saat lari malam.</li>
      <li><b>Peta Offline</b>: pilih rute / riwayat &amp; level zoom, lalu unduh tile peta. Berguna saat trail
        running / naik gunung tanpa sinyal. Hapus cache kapan saja untuk membebaskan ruang.</li>
    </ul>
    <div class="alert alert-warning small mb-0 py-2">
      <i class="bi bi-info-circle"></i>
      Server OSRM publik bersifat <i>best-effort</i> dan rate-limited. Bila gagal, ulangi atau pakai jarak
      lebih kecil. Preferensi elevasi/permukaan diolah dengan API gratis (Open-Elevation/Overpass) &mdash; hasilnya
      heuristik, bukan jaminan.
    </div>
  </div>
</div>

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
        <div class="btn-group btn-group-sm w-100 mb-2" role="group" aria-label="Mode Route Builder">
          <input type="radio" class="btn-check" name="rbMode" id="rbModeAuto" value="auto" checked>
          <label class="btn btn-outline-primary" for="rbModeAuto"><i class="bi bi-magic"></i> Auto Generate</label>
          <input type="radio" class="btn-check" name="rbMode" id="rbModeManual" value="manual">
          <label class="btn btn-outline-primary" for="rbModeManual"><i class="bi bi-pencil-square"></i> Buat Sendiri</label>
          <!-- Revisi 15 Juni 2026: Mode AI — import rute dari gambar peta tanpa input titik manual -->
          <input type="radio" class="btn-check" name="rbMode" id="rbModeAI" value="ai">
          <label class="btn btn-outline-success" for="rbModeAI"><i class="bi bi-robot"></i> AI Import dari Gambar</label>
        </div>

        <!-- ===== Panel AI Import Rute (Revisi 16 Juni 2026: Gemini 2.5 Flash) ===== -->
        <div id="rbAIPanel" class="border rounded p-2 mb-2 bg-success-subtle" style="display:none">
          <ul class="nav nav-pills nav-fill mb-2" role="tablist">
            <li class="nav-item"><button class="nav-link active py-1 px-2 small" data-bs-toggle="tab" data-bs-target="#aiTabPrompt" type="button"><i class="bi bi-chat-text"></i> Prompt Teks</button></li>
            <li class="nav-item"><button class="nav-link py-1 px-2 small" data-bs-toggle="tab" data-bs-target="#aiTabImage" type="button"><i class="bi bi-image"></i> Dari Gambar</button></li>
          </ul>
          <div class="tab-content">
            <!-- Prompt teks → Gemini → daftar tempat → Geocode -->
            <div class="tab-pane fade show active" id="aiTabPrompt">
              <div class="small fw-bold mb-1"><i class="bi bi-robot text-success"></i> Buat Rute dari Prompt (AI Gemini)</div>
              <div class="small text-muted mb-2">
                Tulis kebutuhan Anda &mdash; AI akan menyusun daftar landmark/jalan lalu mengubahnya menjadi rute lari.
                Contoh: <em>"Buatkan rute lari 5 km yang aman dan minim tanjakan di Bandung"</em>.
              </div>
              <textarea id="aiPromptText" class="form-control form-control-sm mb-2" rows="3"
                placeholder="cth: Buatkan rute lari 5 km yang aman dan minim tanjakan di Bandung"></textarea>
              <button id="btnAIPrompt" type="button" class="btn btn-success btn-sm w-100">
                <i class="bi bi-stars"></i> Hasilkan Rute dengan AI
              </button>
              <div id="aiPromptStat" class="small text-muted mt-1"></div>
            </div>
            <!-- Gambar / screenshot -->
            <div class="tab-pane fade" id="aiTabImage">
              <div class="small fw-bold mb-1"><i class="bi bi-image text-success"></i> Import Rute dari Gambar (AI Gemini Vision)</div>
              <div class="small text-muted mb-2">Unggah screenshot peta yang sudah berisi rute (Google Maps / Strava / dll). AI akan
                mendeteksi <em>landmark berurutan</em> lalu di-geocode otomatis.</div>
              <input id="aiRouteImg" type="file" accept="image/*" class="form-control form-control-sm mb-2">
              <input id="aiRouteHint" type="text" class="form-control form-control-sm mb-2"
                     placeholder="Petunjuk area (opsional), cth: 'GOR Senayan Jakarta'">
              <button id="btnAIRoute" type="button" class="btn btn-success btn-sm w-100">
                <i class="bi bi-stars"></i> Identifikasi &amp; Geocode dengan AI
              </button>
              <div id="aiRouteStat" class="small text-muted mt-1"></div>
            </div>
          </div>
        </div>

        <div id="rbManualBox" class="d-none alert alert-info small py-2 mb-2">
          <b><i class="bi bi-hand-index"></i> Mode Manual</b> &mdash; klik peta untuk menambahkan titik (mulai, transit, finish).
          <div class="d-flex flex-wrap gap-1 mt-2">
            <button type="button" id="rbManSnap"  class="btn btn-sm btn-primary"><i class="bi bi-magnet"></i> Snap ke jalan</button>
            <button type="button" id="rbManUndo"  class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-counterclockwise"></i> Hapus titik terakhir</button>
            <button type="button" id="rbManClear" class="btn btn-sm btn-outline-danger"><i class="bi bi-x-circle"></i> Reset</button>
          </div>
          <div class="small text-muted mt-1" id="rbManInfo">0 titik dipilih.</div>
        </div>

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

        <!-- Revisi 16 Juni 2026 (#2): Import rute dari screenshot Strava / gambar -->
        <div class="card border-warning-subtle mt-3">
          <div class="card-header py-2 bg-warning-subtle">
            <strong class="small"><i class="bi bi-image text-warning"></i> Import Rute dari Gambar (screenshot Strava)</strong>
            <span class="small text-muted ms-1">— ubah screenshot peta jadi rute pada peta interaktif</span>
          </div>
          <div class="card-body small">
            <ol class="ps-3 mb-2">
              <li>Upload screenshot lari (Strava / app sejenis) yang memperlihatkan garis rute berwarna.</li>
              <li>Klik <b>2 titik kalibrasi</b> pada gambar (mis. titik mulai &amp; titik finish).</li>
              <li>Klik <b>2 titik yang sama</b> pada peta di atas untuk mengikat koordinat lat/lng.</li>
              <li>Tekan <b>Ekstrak Rute</b>. Sistem akan mendeteksi piksel berwarna garis rute dan
                memetakannya ke peta secara otomatis (transformasi affine sederhana).</li>
            </ol>
            <div class="row g-2 align-items-end">
              <div class="col-md-4">
                <label class="form-label small mb-1">File gambar</label>
                <input type="file" id="imgRouteFile" accept="image/*" class="form-control form-control-sm">
              </div>
              <div class="col-md-3">
                <label class="form-label small mb-1">Warna garis rute</label>
                <select id="imgRouteColor" class="form-select form-select-sm">
                  <option value="strava">Oranye / merah (Strava)</option>
                  <option value="blue">Biru</option>
                  <option value="green">Hijau</option>
                  <option value="purple">Ungu / magenta</option>
                  <option value="auto">Auto (titik kalibrasi)</option>
                </select>
              </div>
              <div class="col-md-3">
                <label class="form-label small mb-1">Toleransi warna</label>
                <input type="range" id="imgRouteTol" min="20" max="120" value="60" class="form-range">
              </div>
              <div class="col-md-2 d-grid">
                <button id="imgRouteExtract" type="button" class="btn btn-warning btn-sm" disabled><i class="bi bi-magic"></i> Ekstrak Rute</button>
              </div>
            </div>
            <div class="row g-2 mt-2">
              <div class="col-md-7">
                <div class="position-relative" style="border:1px solid var(--bs-border-color,#e5e7eb);border-radius:8px;overflow:hidden;background:#f8fafc;min-height:160px">
                  <canvas id="imgRouteCanvas" style="display:block;max-width:100%;cursor:crosshair"></canvas>
                </div>
                <div class="small text-muted mt-1" id="imgRouteImgInfo">Belum ada gambar. Titik gambar dipilih: <b>0/2</b>.</div>
              </div>
              <div class="col-md-5">
                <div class="alert alert-info py-2 mb-2 small">
                  <b>Cara kalibrasi:</b><br>
                  1. Klik 2 titik pada <em>gambar</em> (kiri).<br>
                  2. Klik 2 titik pada <em>peta builder</em> (atas) — tombol kalibrasi peta di bawah harus aktif.
                </div>
                <button id="imgRouteMapMode" type="button" class="btn btn-outline-primary btn-sm w-100 mb-1">
                  <i class="bi bi-bullseye"></i> Mode pilih titik peta: <span id="imgRouteMapState">off</span>
                </button>
                <div class="small text-muted">Titik peta dipilih: <b id="imgRouteMapCount">0</b>/2.</div>
                <button id="imgRouteReset" type="button" class="btn btn-outline-secondary btn-sm w-100 mt-2">
                  <i class="bi bi-arrow-counterclockwise"></i> Reset kalibrasi
                </button>
                <div id="imgRouteResult" class="small mt-2"></div>
              </div>
            </div>
          </div>
        </div>

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
  var TILE = window.MAPBOX_TILE_URL;
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
  var bStartMarker = null; // Revisi 16 Juni 2026: marker titik mulai (lokasi sekarang)
  function ensureBuilderMap(center){
    if (bMap) return bMap;
    bMap = L.map('builderMap').setView(center||[-6.2,106.816666],14);
    L.tileLayer(TILE,{maxZoom:19,attribution:'&copy; OSM'}).addTo(bMap);
    return bMap;
  }
  document.getElementById('tab-builder-btn').addEventListener('shown.bs.tab', function(){
    ensureBuilderMap(); setTimeout(function(){ bMap.invalidateSize(); },100);
  });
  // Revisi 16 Juni 2026 (#1): klik "Lokasi sekarang" pada Route Builder menampilkan
  // titik & simbol di peta (marker biru dengan label "Mulai (Anda)").
  function setStartMarker(lat,lng){
    ensureBuilderMap([lat,lng]);
    var startIcon = L.divIcon({
      className:'rb-start-icon',
      html:'<div style="background:#16a34a;border:3px solid #fff;border-radius:50%;width:18px;height:18px;box-shadow:0 0 0 2px #16a34a,0 2px 8px rgba(0,0,0,.35)"></div>',
      iconSize:[18,18], iconAnchor:[9,9]
    });
    if (bStartMarker){ bMap.removeLayer(bStartMarker); bStartMarker=null; }
    bStartMarker = L.marker([lat,lng], {icon:startIcon, zIndexOffset:1000})
      .addTo(bMap)
      .bindTooltip('<i class="bi bi-geo-alt-fill"></i> Mulai (Anda)', {permanent:true, direction:'top', offset:[0,-6], className:'rb-start-tip'})
      .openTooltip();
    bMap.setView([lat,lng], Math.max(bMap.getZoom(),15));
  }
  document.getElementById('rbUseMe').addEventListener('click', function(){
    if (!navigator.geolocation) { alert('Browser tidak mendukung GPS'); return; }
    var btn = this; var orig = btn.innerHTML;
    btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    navigator.geolocation.getCurrentPosition(function(p){
      var lat = p.coords.latitude, lng = p.coords.longitude;
      document.getElementById('rbStart').value = lat.toFixed(6)+','+lng.toFixed(6);
      setStartMarker(lat,lng);
      var info = document.getElementById('rbInfo');
      if (info) info.textContent = '📍 Titik mulai diset ke lokasi Anda: '+lat.toFixed(5)+', '+lng.toFixed(5)+' (akurasi '+Math.round(p.coords.accuracy)+' m)';
      btn.disabled = false; btn.innerHTML = orig;
    }, function(err){
      alert('Gagal membaca lokasi: '+err.message);
      btn.disabled = false; btn.innerHTML = orig;
    }, {enableHighAccuracy:true, timeout:15000, maximumAge:0});
  });

  function clearBuilder(){
    if (bLine){ bMap.removeLayer(bLine); bLine=null; }
    bMarkers.forEach(function(m){ bMap.removeLayer(m); }); bMarkers=[];
  }

  // --- Helpers (preferensi elev/surface via API publik gratis, best-effort) ---
  async function scoreElevation(coords){
    // Sampel 12 titik sepanjang rute → Open-Elevation
    try {
      var n = coords.length; if (n<2) return 0;
      var sample = [];
      for (var i=0;i<12;i++){ sample.push(coords[Math.floor(i*(n-1)/11)]); }
      var locs = sample.map(function(c){return c[0]+','+c[1];}).join('|');
      var r = await fetch('https://api.open-elevation.com/api/v1/lookup?locations='+locs);
      if (!r.ok) return null;
      var d = await r.json();
      var els = (d.results||[]).map(function(x){return x.elevation;});
      var gain=0; for (var i=1;i<els.length;i++){ var dv=els[i]-els[i-1]; if (dv>0) gain+=dv; }
      return gain; // total ascent in meters
    } catch(e){ return null; }
  }
  async function scoreSurface(coords, surfPref){
    if (surfPref==='apa-saja') return 1;
    try {
      var lats = coords.map(function(c){return c[0];}), lngs = coords.map(function(c){return c[1];});
      var minLat=Math.min.apply(null,lats), maxLat=Math.max.apply(null,lats);
      var minLng=Math.min.apply(null,lngs), maxLng=Math.max.apply(null,lngs);
      var pad=0.002;
      var bbox=(minLat-pad)+','+(minLng-pad)+','+(maxLat+pad)+','+(maxLng+pad);
      var q='[out:json][timeout:10];way["highway"]["surface"]('+bbox+');out tags 200;';
      var r = await fetch('https://overpass-api.de/api/interpreter',{method:'POST',body:q});
      if (!r.ok) return 0;
      var d = await r.json();
      var match=0,total=0;
      (d.elements||[]).forEach(function(w){
        var sf=(w.tags&&w.tags.surface)||''; total++;
        if (surfPref==='aspal'   && /asphalt|paved|concrete/i.test(sf)) match++;
        if (surfPref==='tanah'   && /ground|dirt|unpaved|gravel|earth|grass|sand/i.test(sf)) match++;
        if (surfPref==='campuran'&& sf) match++;
      });
      return total ? match/total : 0;
    } catch(e){ return 0; }
  }

  async function osrmRoute(waypoints){
    var url = OSRM + waypoints.map(function(w){return w[0]+','+w[1];}).join(';') + '?overview=full&geometries=geojson';
    var r = await fetch(url); if (!r.ok) throw new Error('OSRM '+r.status);
    var d = await r.json();
    if (!d.routes || !d.routes.length) throw new Error('OSRM kosong');
    return { coords: d.routes[0].geometry.coordinates.map(function(c){return [c[1],c[0]];}), m: d.routes[0].distance };
  }

  function buildWaypoints(lat,lng,totalM,shape,bearing){
    var waypoints=[[lng,lat]];
    if (shape==='out'){
      var dp = destPoint(lat,lng,totalM*0.5,bearing);
      waypoints.push([dp.lng,dp.lat]); waypoints.push([lng,lat]);
    } else {
      var sideRadius=(totalM/3)*0.6;
      var w1=destPoint(lat,lng,sideRadius,bearing);
      var w2=destPoint(lat,lng,sideRadius,bearing+120);
      var w3=destPoint(lat,lng,sideRadius,bearing+240);
      waypoints.push([w1.lng,w1.lat]);
      waypoints.push([w2.lng,w2.lat]);
      waypoints.push([w3.lng,w3.lat]);
      waypoints.push([lng,lat]);
    }
    return waypoints;
  }

  async function generateOne(lat,lng,totalM,shape,bearing){
    // Iterative scaling: target → real OSRM distance, koreksi 3x max.
    var scale = 1.0, last=null;
    for (var i=0;i<3;i++){
      var wps = buildWaypoints(lat,lng,totalM*scale,shape,bearing);
      var res = await osrmRoute(wps);
      last = res;
      var err = (res.m - totalM)/totalM;
      if (Math.abs(err) < 0.07) break;          // < 7% selisih → cukup
      scale = scale * (totalM/res.m);
      if (scale<0.3||scale>3) break;
    }
    return last;
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
    var shape  = document.getElementById('rbShape').value;
    var elev   = document.getElementById('rbElev').value;
    var surf   = document.getElementById('rbSurface').value;
    var totalM = distKm*1000;

    info.textContent = 'Membuat 4 kandidat rute & menilai preferensi (bisa 5–15 detik)...';
    var bearings=[Math.random()*360, Math.random()*360, Math.random()*360, Math.random()*360];
    var candidates=[];
    for (var i=0;i<bearings.length;i++){
      try {
        var r = await generateOne(lat,lng,totalM,shape,bearings[i]);
        candidates.push(r);
      } catch(e){ /* skip */ }
    }
    if (!candidates.length){ info.textContent='Gagal generate rute (OSRM publik mungkin sibuk). Coba lagi.'; return; }

    // Skor: kombinasi (a) kedekatan jarak ke target, (b) elev pref, (c) surface pref
    info.textContent = 'Menilai kandidat (elevasi & permukaan)...';
    var scored = [];
    for (var i=0;i<candidates.length;i++){
      var c = candidates[i];
      var distScore = 1 - Math.min(1, Math.abs(c.m-totalM)/totalM); // 1 = persis
      var elevGain = null, surfScore = null;
      if (elev !== 'apa-saja') elevGain = await scoreElevation(c.coords);
      if (surf !== 'apa-saja') surfScore = await scoreSurface(c.coords, surf);
      c.elevGain = elevGain; c.surfScore = surfScore;
      // normalisasi elev (0..1) → 0 (datar) sampai 100m+ (berbukit)
      var elevNorm = elevGain==null ? 0.5 : Math.min(1, elevGain/100);
      var elevFit  = elev==='berbukit' ? elevNorm : elev==='datar' ? (1-elevNorm) : 0.5;
      var surfFit  = surfScore==null ? 0.5 : surfScore;
      // bobot: jarak 55%, elev 25%, surface 20%
      c.score = distScore*0.55 + elevFit*0.25 + surfFit*0.20;
      scored.push(c);
    }
    scored.sort(function(a,b){return b.score-a.score;});
    var best = scored[0];

    bLine = L.polyline(best.coords,{color:'#2563eb',weight:5}).addTo(bMap);
    bMap.fitBounds(bLine.getBounds(),{padding:[20,20]});
    bMarkers.push(L.marker(best.coords[0]).addTo(bMap).bindTooltip('Mulai'));
    bMarkers.push(L.marker(best.coords[best.coords.length-1]).addTo(bMap).bindTooltip('Selesai'));
    bCurrentRoute = { coords: best.coords, jarak_m: best.m };
    var elevTxt  = best.elevGain==null ? '—' : Math.round(best.elevGain)+' m ascent';
    var surfTxt  = best.surfScore==null ? '—' : Math.round(best.surfScore*100)+'% '+surf;
    info.innerHTML = '✓ Rute terpilih: <strong>'+(best.m/1000).toFixed(2)+' km</strong> '+
      '(target '+distKm.toFixed(2)+' km · selisih '+Math.abs(best.m-totalM).toFixed(0)+' m) · '+
      'Elev: '+elevTxt+' · Surface: '+surfTxt+
      ' <span class="badge bg-light text-dark border">'+candidates.length+' kandidat dinilai</span>';
    document.getElementById('rbSave').disabled = false;
    document.getElementById('rbExport').disabled = false;
  }
  document.getElementById('rbGen').addEventListener('click', function(){
    if (document.querySelector('input[name=rbMode]:checked').value==='manual'){
      alert('Mode Manual aktif — klik peta untuk menambah titik, lalu tekan "Snap ke jalan".'); return;
    }
    buildRoute();
  });

  // ====================== MANUAL ROUTE MODE ======================
  var manPts = [], manMarkers = [], manLine = null;
  function manRefresh(){
    if (manLine){ bMap.removeLayer(manLine); manLine=null; }
    if (manPts.length>=2){
      manLine = L.polyline(manPts,{color:'#f59e0b',weight:4,dashArray:'6,6'}).addTo(bMap);
    }
    document.getElementById('rbManInfo').textContent = manPts.length+' titik dipilih.';
  }
  function manReset(){
    // Revisi 16 Juni 2026 (#3): Reset di mode "Buat Sendiri" harus mengosongkan
    // peta sepenuhnya — termasuk rute hasil snap/auto, marker mulai, dan info.
    manPts=[]; manMarkers.forEach(function(m){bMap.removeLayer(m);}); manMarkers=[];
    if (manLine){ bMap.removeLayer(manLine); manLine=null; }
    clearBuilder(); // hapus bLine + bMarkers (rute aktif)
    if (bStartMarker){ bMap.removeLayer(bStartMarker); bStartMarker=null; }
    bCurrentRoute = null;
    var rs = document.getElementById('rbSave');   if (rs) rs.disabled = true;
    var re = document.getElementById('rbExport'); if (re) re.disabled = true;
    var ri = document.getElementById('rbInfo');   if (ri) ri.textContent = '';
    document.getElementById('rbManInfo').textContent='0 titik dipilih. Peta dikosongkan.';
  }
  function manClickHandler(e){
    var ll = e.latlng;
    manPts.push([ll.lat, ll.lng]);
    var idx = manPts.length;
    var mk = L.marker([ll.lat,ll.lng]).addTo(bMap).bindTooltip(String(idx));
    manMarkers.push(mk);
    manRefresh();
  }
  document.querySelectorAll('input[name=rbMode]').forEach(function(el){
    el.addEventListener('change', function(){
      var manual = document.getElementById('rbModeManual').checked;
      var ai     = document.getElementById('rbModeAI') && document.getElementById('rbModeAI').checked;
      document.getElementById('rbManualBox').classList.toggle('d-none', !manual);
      var aiPanel = document.getElementById('rbAIPanel'); if (aiPanel) aiPanel.style.display = ai ? '' : 'none';
      ensureBuilderMap();
      if (manual){
        bMap.on('click', manClickHandler);
        document.getElementById('rbGen').disabled = true;
      } else {
        bMap.off('click', manClickHandler);
        manReset();
        document.getElementById('rbGen').disabled = !!ai; // di mode AI, tombol Auto-Generate tidak relevan
      }
    });
  });

  // ===== Revisi 15 Juni 2026 — Handler AI Import Rute dari Gambar =====
  var btnAIRoute = document.getElementById('btnAIRoute');
  if (btnAIRoute) {
    btnAIRoute.addEventListener('click', async function(){
      var f = document.getElementById('aiRouteImg').files[0];
      var hint = document.getElementById('aiRouteHint').value.trim();
      var stat = document.getElementById('aiRouteStat');
      if (!f) { stat.textContent = 'Pilih gambar dulu.'; return; }
      stat.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Mengirim ke AI…';
      btnAIRoute.disabled = true;
      try {
        var fd = new FormData();
        fd.append('csrf', csrf);
        fd.append('_action', 'ai_route_from_image');
        fd.append('hint', hint);
        fd.append('image', f);
        var r = await fetch('/api_run.php', { method:'POST', body: fd });
        var d = await r.json();
        if (!d.ok) { stat.textContent = 'Gagal: '+(d.err||'tidak diketahui'); btnAIRoute.disabled=false; return; }
        if (!d.coords || d.coords.length < 2) { stat.textContent = 'AI tidak menemukan titik rute yang cukup.'; btnAIRoute.disabled=false; return; }
        ensureBuilderMap();
        if (bLine) { bMap.removeLayer(bLine); }
        bLine = L.polyline(d.coords, {color:'#16a34a', weight:5}).addTo(bMap);
        bMap.fitBounds(bLine.getBounds(), {padding:[40,40]});
        // hitung jarak
        var km = 0;
        for (var i=1;i<d.coords.length;i++){
          var a = d.coords[i-1], b = d.coords[i];
          var R=6371, dLat=(b[0]-a[0])*Math.PI/180, dLng=(b[1]-a[1])*Math.PI/180;
          var s = Math.sin(dLat/2)**2 + Math.cos(a[0]*Math.PI/180)*Math.cos(b[0]*Math.PI/180)*Math.sin(dLng/2)**2;
          km += 2*R*Math.asin(Math.sqrt(s));
        }
        stat.innerHTML = 'Berhasil! '+d.coords.length+' titik · ~'+km.toFixed(2)+' km. '+(d.note?'<br><em>'+d.note+'</em>':'');
        // simpan ke field untuk disimpan via tombol "Simpan Rute" yang sudah ada
        window._aiRouteCoords = d.coords;
      } catch(e) { stat.textContent = 'Error: '+e.message; }
      btnAIRoute.disabled = false;
    });
  }

  // ===== Revisi 16 Juni 2026 — Handler AI Route dari Prompt Teks (Gemini) =====
  var btnAIPrompt = document.getElementById('btnAIPrompt');
  if (btnAIPrompt) {
    btnAIPrompt.addEventListener('click', async function(){
      var prompt = (document.getElementById('aiPromptText').value || '').trim();
      var stat = document.getElementById('aiPromptStat');
      if (!prompt) { stat.textContent = 'Tulis prompt dulu (cth: "Buatkan rute lari 5 km di Bandung").'; return; }
      stat.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Gemini sedang menyusun rute…';
      btnAIPrompt.disabled = true;
      try {
        var fd = new FormData();
        fd.append('csrf', csrf);
        fd.append('task', 'ai_route_prompt');
        fd.append('prompt', prompt);
        var r = await fetch('/api_ai.php', { method:'POST', body: fd, credentials:'same-origin' });
        var d = await r.json();
        if (!d.ok) { stat.textContent = 'Gagal: '+(d.err||'?'); return; }
        if (!d.coords || d.coords.length < 2) { stat.textContent = 'Hasil rute terlalu sedikit.'; return; }
        ensureBuilderMap();
        if (bLine) { bMap.removeLayer(bLine); }
        bLine = L.polyline(d.coords, {color:'#16a34a', weight:5}).addTo(bMap);
        bMap.fitBounds(bLine.getBounds(), {padding:[40,40]});
        var km = 0;
        for (var i=1;i<d.coords.length;i++){
          var a=d.coords[i-1], b=d.coords[i];
          var R=6371, dLat=(b[0]-a[0])*Math.PI/180, dLng=(b[1]-a[1])*Math.PI/180;
          var s=Math.sin(dLat/2)**2+Math.cos(a[0]*Math.PI/180)*Math.cos(b[0]*Math.PI/180)*Math.sin(dLng/2)**2;
          km += 2*R*Math.asin(Math.sqrt(s));
        }
        var places = (d.places||[]).map(function(p){return '• '+p;}).join('<br>');
        stat.innerHTML = '<strong>Berhasil!</strong> '+d.coords.length+' titik · ~'+km.toFixed(2)+' km'
                       + (d.note?'<br><em>'+d.note+'</em>':'')
                       + (places?'<details class="mt-1"><summary>Landmark</summary>'+places+'</details>':'');
        window._aiRouteCoords = d.coords;
        bCurrentRoute = { coords: d.coords, jarak_m: km*1000 };
        var sv = document.getElementById('rbSave'); if (sv) sv.disabled = false;
        var ex = document.getElementById('rbExport'); if (ex) ex.disabled = false;
      } catch(e){ stat.textContent = 'Error: '+e.message; }
      btnAIPrompt.disabled = false;
    });
  }

  document.getElementById('rbManUndo').addEventListener('click', function(){
    if (!manPts.length) return;
    manPts.pop();
    var mk = manMarkers.pop(); if (mk) bMap.removeLayer(mk);
    manRefresh();
  });
  document.getElementById('rbManClear').addEventListener('click', manReset);
  document.getElementById('rbManSnap').addEventListener('click', async function(){
    if (manPts.length<2){ alert('Minimal 2 titik (mulai & finish).'); return; }
    var info = document.getElementById('rbInfo');
    info.textContent = 'Snap '+manPts.length+' titik ke jalan via OSRM...';
    try {
      var wps = manPts.map(function(p){return [p[1],p[0]];});
      var res = await osrmRoute(wps);
      clearBuilder();
      bLine = L.polyline(res.coords,{color:'#16a34a',weight:5}).addTo(bMap);
      bMap.fitBounds(bLine.getBounds(),{padding:[20,20]});
      bMarkers.push(L.marker(res.coords[0]).addTo(bMap).bindTooltip('Mulai'));
      bMarkers.push(L.marker(res.coords[res.coords.length-1]).addTo(bMap).bindTooltip('Selesai'));
      bCurrentRoute = { coords: res.coords, jarak_m: res.m };
      info.innerHTML = '✓ Rute manual ter-snap: <strong>'+(res.m/1000).toFixed(2)+' km</strong> dari '+manPts.length+' waypoint.';
      document.getElementById('rbSave').disabled = false;
      document.getElementById('rbExport').disabled = false;
    } catch(e){
      info.textContent = 'Gagal snap: '+e.message;
    }
  });


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
  // Revisi 16 Juni 2026 (#4): live tracking lokasi sekarang + legend +
  // heatmap dipertebal + garis tipis menampilkan rute padat (urut titik).
  var hMap = null, hLayer = null, hMeMarker = null, hMeWatch = null, hMeAcc = null;
  var hLegend = null, hPolyLayer = null;
  function ensureHeatMap(){
    if (hMap) return hMap;
    hMap = L.map('heatMap').setView([-6.2,106.816666],12);
    L.tileLayer(TILE,{maxZoom:19,attribution:'&copy; OSM'}).addTo(hMap);
    hLegend = L.control({position:'bottomright'});
    hLegend.onAdd = function(){
      var div = L.DomUtil.create('div','info legend');
      div.style.cssText = 'background:#fff;padding:8px 10px;border-radius:8px;box-shadow:0 1px 6px rgba(0,0,0,.2);font-size:11px;line-height:1.45;max-width:240px';
      div.innerHTML =
        '<b>Keterangan Heatmap</b><br>'+
        '<span style="display:inline-block;width:30px;height:8px;background:linear-gradient(90deg,#3b82f6,#22c55e,#eab308,#ef4444);border-radius:4px;vertical-align:middle"></span> '+
        'kepadatan titik GPS (rendah → tinggi)<br>'+
        '<span style="display:inline-block;width:24px;height:3px;background:#dc2626;border-radius:2px;vertical-align:middle"></span> '+
        '<b>Garis heatmap</b> = jalur padat<br>'+
        '<span style="display:inline-block;width:10px;height:10px;background:#3b82f6;border:2px solid #fff;border-radius:50%;box-shadow:0 0 0 2px #2563eb;vertical-align:middle"></span> '+
        'lokasi Anda sekarang (live)<br>'+
        '<span class="text-muted">Sumber data: tabel <code>run_points</code> — titik GPS sesi lari Anda (Pribadi) atau seluruh komunitas (Publik). Mode <b>Night</b> hanya titik pukul 18:00–05:00.</span>';
      return div;
    };
    hLegend.addTo(hMap);
    return hMap;
  }
  document.getElementById('tab-heatmap-btn').addEventListener('shown.bs.tab', function(){
    ensureHeatMap(); setTimeout(function(){ hMap.invalidateSize(); loadHeat(); startHeatLive(); },100);
  });
  function startHeatLive(){
    if (!navigator.geolocation || hMeWatch !== null) return;
    hMeWatch = navigator.geolocation.watchPosition(function(p){
      var lat=p.coords.latitude, lng=p.coords.longitude, acc=p.coords.accuracy||0;
      if (!hMeMarker){
        hMeMarker = L.circleMarker([lat,lng], {radius:8, color:'#2563eb', fillColor:'#3b82f6', fillOpacity:.95, weight:3}).addTo(hMap);
        hMeMarker.bindTooltip('Anda di sini (live)', {permanent:false});
        hMeAcc    = L.circle([lat,lng], {radius:acc, color:'#3b82f6', weight:1, fillOpacity:.08}).addTo(hMap);
        hMap.setView([lat,lng], Math.max(hMap.getZoom(),14));
      } else {
        hMeMarker.setLatLng([lat,lng]);
        if (hMeAcc) hMeAcc.setLatLng([lat,lng]).setRadius(acc);
      }
      var el = document.getElementById('hmInfo');
      el.textContent = el.textContent.replace(/\s*\|\s*📍.*$/,'') + '  |  📍 Live: '+lat.toFixed(5)+','+lng.toFixed(5)+' (±'+Math.round(acc)+' m)';
    }, function(){}, {enableHighAccuracy:true, maximumAge:2000, timeout:15000});
  }
  async function loadHeat(){
    var mode = document.querySelector('input[name=hmMode]:checked').value;
    document.getElementById('hmInfo').textContent = 'Memuat titik heatmap ('+mode+') dari run_points...';
    var r = await fetch('/api_run.php?heatmap='+mode); var d=await r.json();
    if (!d.ok){ document.getElementById('hmInfo').textContent='Gagal memuat.'; return; }
    if (hLayer){ hMap.removeLayer(hLayer); hLayer=null; }
    if (hPolyLayer){ hMap.removeLayer(hPolyLayer); hPolyLayer=null; }
    if (!d.points.length){ document.getElementById('hmInfo').textContent='Belum ada titik untuk mode '+mode+' (sumber: tabel run_points).'; return; }
    // Heatmap dipertebal: radius 28, blur 18, minOpacity 0.35 + gradien penuh.
    hLayer = L.heatLayer(d.points, {radius:28, blur:18, maxZoom:17, minOpacity:0.35,
      gradient: mode==='night'
        ? {0.2:'#1e3a8a',0.4:'#3b82f6',0.7:'#fbbf24',1.0:'#fde047'}
        : {0.2:'#3b82f6',0.4:'#22c55e',0.7:'#eab308',1.0:'#ef4444'}
    }).addTo(hMap);
    // Garis tipis dashed yang melewati semua titik — jadi penanda visual "garis heatmap".
    try {
      var coords = d.points.map(function(p){ return [p[0],p[1]]; });
      hPolyLayer = L.polyline(coords, {color:'#dc2626', weight:2, opacity:0.5, dashArray:'4,4'}).addTo(hMap);
    } catch(e){}
    var b = L.latLngBounds(d.points.map(function(p){return [p[0],p[1]];}));
    hMap.fitBounds(b,{padding:[20,20]});
    document.getElementById('hmInfo').textContent = '✓ '+d.points.length+' titik dimuat ('+mode+') · Sumber data: tabel run_points.';
  }
  document.getElementById('hmReload').addEventListener('click', function(){ loadHeat(); startHeatLive(); });
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

  // ====================== IMPORT RUTE DARI GAMBAR (Revisi 16 Juni 2026 #2) ======================
  // Pipeline: upload gambar → tampilkan di canvas → user klik 2 titik kalibrasi pada gambar →
  // user klik 2 titik pada peta builder → kita lakukan affine transform sederhana
  // (pixel(x,y) → lat,lng) berdasarkan 2 pasang titik (asumsi peta tidak miring/rotasi),
  // lalu deteksi piksel berwarna garis rute (Strava oranye/merah, dll.) → bangun polyline
  // dengan menelusuri komponen warna terbesar (sederhana: ambil semua piksel yg cocok →
  // sortir mengikuti urutan dekat-dengan-titik-mulai → simplifikasi Douglas-Peucker).
  (function(){
    var fileInput   = document.getElementById('imgRouteFile');
    var canvas      = document.getElementById('imgRouteCanvas');
    var ctx         = canvas.getContext('2d', { willReadFrequently:true });
    var colorSel    = document.getElementById('imgRouteColor');
    var tolInp      = document.getElementById('imgRouteTol');
    var btnExtract  = document.getElementById('imgRouteExtract');
    var btnMapMode  = document.getElementById('imgRouteMapMode');
    var btnReset    = document.getElementById('imgRouteReset');
    var spanMapState= document.getElementById('imgRouteMapState');
    var spanMapCnt  = document.getElementById('imgRouteMapCount');
    var spanImgInfo = document.getElementById('imgRouteImgInfo');
    var divResult   = document.getElementById('imgRouteResult');
    if (!fileInput) return;

    var imgEl = null;
    var imgPts = []; // {x,y} di koordinat piksel ASLI gambar
    var mapPts = []; // {lat,lng}
    var mapMode = false;
    var mapClickHandler = null;
    var imgRouteLine = null;
    var imgMarkers = [];

    function refreshState(){
      spanImgInfo.innerHTML = (imgEl?'Gambar: '+imgEl.naturalWidth+'×'+imgEl.naturalHeight+' px':'Belum ada gambar')+'. Titik gambar dipilih: <b>'+imgPts.length+'/2</b>.';
      spanMapCnt.textContent = mapPts.length;
      btnExtract.disabled = !(imgEl && imgPts.length===2 && mapPts.length===2);
    }
    function redrawCanvas(){
      if (!imgEl) return;
      var maxW = canvas.parentElement.clientWidth - 4; if (maxW<200) maxW=200;
      var scale = Math.min(1, maxW/imgEl.naturalWidth);
      canvas.width  = Math.round(imgEl.naturalWidth*scale);
      canvas.height = Math.round(imgEl.naturalHeight*scale);
      canvas._scale = scale;
      ctx.drawImage(imgEl, 0, 0, canvas.width, canvas.height);
      // gambar titik kalibrasi
      imgPts.forEach(function(p, i){
        var x = p.x*scale, y = p.y*scale;
        ctx.beginPath(); ctx.arc(x,y,7,0,Math.PI*2);
        ctx.fillStyle = i===0 ? '#16a34a' : '#dc2626';
        ctx.strokeStyle = '#fff'; ctx.lineWidth = 2; ctx.fill(); ctx.stroke();
        ctx.fillStyle='#000'; ctx.font='bold 11px sans-serif';
        ctx.fillText(String(i+1), x+9, y-9);
      });
    }
    canvas.addEventListener('click', function(ev){
      if (!imgEl) return;
      if (imgPts.length>=2){ imgPts = []; }
      var rect = canvas.getBoundingClientRect();
      var cx = ev.clientX-rect.left, cy = ev.clientY-rect.top;
      var s = canvas._scale || 1;
      imgPts.push({ x: cx/s, y: cy/s });
      redrawCanvas(); refreshState();
    });
    fileInput.addEventListener('change', function(){
      var f = fileInput.files && fileInput.files[0]; if (!f) return;
      var url = URL.createObjectURL(f);
      var im = new Image();
      im.onload = function(){ imgEl = im; imgPts=[]; redrawCanvas(); refreshState(); };
      im.src = url;
    });

    btnMapMode.addEventListener('click', function(){
      ensureBuilderMap();
      mapMode = !mapMode;
      spanMapState.textContent = mapMode ? 'on (klik peta)' : 'off';
      btnMapMode.classList.toggle('btn-primary', mapMode);
      btnMapMode.classList.toggle('btn-outline-primary', !mapMode);
      if (mapMode){
        if (!mapClickHandler){
          mapClickHandler = function(e){
            if (mapPts.length>=2){
              // reset markers
              imgMarkers.forEach(function(m){ bMap.removeLayer(m); }); imgMarkers = [];
              mapPts = [];
            }
            mapPts.push({ lat: e.latlng.lat, lng: e.latlng.lng });
            var idx = mapPts.length;
            var mk = L.circleMarker([e.latlng.lat, e.latlng.lng], {
              radius:8, color: idx===1 ? '#16a34a' : '#dc2626', fillColor:'#fff', fillOpacity:1, weight:3
            }).addTo(bMap).bindTooltip('Kalibrasi #'+idx, {permanent:true, direction:'top'});
            imgMarkers.push(mk);
            refreshState();
          };
        }
        bMap.on('click', mapClickHandler);
      } else if (mapClickHandler){
        bMap.off('click', mapClickHandler);
      }
    });

    btnReset.addEventListener('click', function(){
      imgPts = []; mapPts = [];
      imgMarkers.forEach(function(m){ if (bMap) bMap.removeLayer(m); }); imgMarkers = [];
      if (imgRouteLine && bMap){ bMap.removeLayer(imgRouteLine); imgRouteLine = null; }
      redrawCanvas(); refreshState();
      divResult.textContent = 'Kalibrasi direset.';
    });

    function colorMatch(r,g,b, preset, tol){
      // tol skala 20..120
      if (preset==='strava')  return (r>180 && g<140 && b<120) || (r>200 && g>80 && g<170 && b<100);
      if (preset==='blue')    return (b>150 && r<140 && g<170);
      if (preset==='green')   return (g>150 && r<160 && b<140);
      if (preset==='purple')  return (r>120 && b>120 && g<140);
      // auto: gunakan warna rata-rata di sekitar 2 titik kalibrasi
      var ref = colorMatch._ref; if (!ref) return false;
      var dr=r-ref[0], dg=g-ref[1], db=b-ref[2];
      return (dr*dr+dg*dg+db*db) < tol*tol;
    }

    btnExtract.addEventListener('click', async function(){
      if (!(imgEl && imgPts.length===2 && mapPts.length===2)){
        alert('Lengkapi 2 titik gambar + 2 titik peta dulu.'); return;
      }
      divResult.textContent = 'Memproses piksel gambar...';
      // Hitung warna referensi (auto) dari rata-rata 5x5 di sekitar titik kalibrasi.
      var off = document.createElement('canvas');
      off.width = imgEl.naturalWidth; off.height = imgEl.naturalHeight;
      var octx = off.getContext('2d', { willReadFrequently:true });
      octx.drawImage(imgEl, 0, 0);
      var data;
      try { data = octx.getImageData(0,0,off.width,off.height); }
      catch(e){ divResult.textContent='Gagal baca pixel (CORS?). Coba gambar lokal.'; return; }
      var d = data.data, w=off.width, h=off.height;
      function avgAt(px,py){
        var rs=0,gs=0,bs=0,n=0;
        for (var yy=Math.max(0,py-2); yy<=Math.min(h-1,py+2); yy++){
          for (var xx=Math.max(0,px-2); xx<=Math.min(w-1,px+2); xx++){
            var i=(yy*w+xx)*4; rs+=d[i]; gs+=d[i+1]; bs+=d[i+2]; n++;
          }
        }
        return [rs/n,gs/n,bs/n];
      }
      var a = avgAt(Math.round(imgPts[0].x), Math.round(imgPts[0].y));
      var b = avgAt(Math.round(imgPts[1].x), Math.round(imgPts[1].y));
      colorMatch._ref = [(a[0]+b[0])/2, (a[1]+b[1])/2, (a[2]+b[2])/2];
      var preset = colorSel.value, tol = +tolInp.value;

      // Step 1: kumpulkan piksel rute
      var pts = []; // {x,y}
      var step = Math.max(1, Math.round(Math.min(w,h)/600)); // sampling supaya cepat
      for (var y=0; y<h; y+=step){
        for (var x=0; x<w; x+=step){
          var i=(y*w+x)*4;
          if (colorMatch(d[i],d[i+1],d[i+2], preset, tol)) pts.push({x:x,y:y});
        }
      }
      if (pts.length < 5){
        divResult.textContent = 'Gagal: hanya '+pts.length+' piksel cocok. Coba ganti warna garis / naikkan toleransi.';
        return;
      }
      // Step 2: urutkan piksel mengikuti rute (greedy nearest-neighbor mulai dari titik kalibrasi #1)
      var start = imgPts[0];
      var ordered = []; var used = new Uint8Array(pts.length);
      var cur = { x: start.x, y: start.y };
      // ambil terdekat ke start sebagai awal
      var bestI = -1, bestD = Infinity;
      for (var k=0; k<pts.length; k++){
        var dx=pts[k].x-cur.x, dy=pts[k].y-cur.y; var dd=dx*dx+dy*dy;
        if (dd<bestD){ bestD=dd; bestI=k; }
      }
      ordered.push(pts[bestI]); used[bestI]=1; cur=pts[bestI];
      var MAX_NN_JUMP = Math.pow(Math.min(w,h)*0.05, 2); // toleransi loncatan
      while (true){
        var ni=-1, nd=Infinity;
        for (var k=0; k<pts.length; k++){
          if (used[k]) continue;
          var dx=pts[k].x-cur.x, dy=pts[k].y-cur.y; var dd=dx*dx+dy*dy;
          if (dd<nd){ nd=dd; ni=k; }
        }
        if (ni<0 || nd>MAX_NN_JUMP) break;
        ordered.push(pts[ni]); used[ni]=1; cur=pts[ni];
      }
      // Step 3: simplifikasi (Douglas–Peucker sederhana via langkah dec)
      var dec = Math.max(1, Math.floor(ordered.length/300));
      var simpl = []; for (var k=0; k<ordered.length; k+=dec) simpl.push(ordered[k]);
      if (simpl[simpl.length-1] !== ordered[ordered.length-1]) simpl.push(ordered[ordered.length-1]);

      // Step 4: affine pixel→lat/lng dari 2 pasang titik (asumsi tanpa rotasi)
      var p1 = imgPts[0], p2 = imgPts[1];
      var m1 = mapPts[0], m2 = mapPts[1];
      var dpx = p2.x - p1.x, dpy = p2.y - p1.y;
      var dml = m2.lng - m1.lng, dma = m2.lat - m1.lat;
      // ratio per pixel
      function px2ll(p){
        var tx = dpx===0 ? 0 : (p.x - p1.x)/dpx;
        var ty = dpy===0 ? 0 : (p.y - p1.y)/dpy;
        // gunakan rata2 dari kedua sumbu (peta umumnya tidak miring)
        var lng = m1.lng + (dpx===0 ? 0 : tx*dml);
        var lat = m1.lat + (dpy===0 ? 0 : ty*dma);
        return [lat, lng];
      }
      var coords = simpl.map(px2ll);
      // Tampilkan polyline di builder map
      ensureBuilderMap();
      if (imgRouteLine){ bMap.removeLayer(imgRouteLine); }
      imgRouteLine = L.polyline(coords, {color:'#f59e0b', weight:5, opacity:0.95}).addTo(bMap);
      bMap.fitBounds(imgRouteLine.getBounds(), {padding:[20,20]});
      // hitung jarak total (haversine)
      function hav(a,b){
        var R=6371000, toRad=Math.PI/180;
        var dLat=(b[0]-a[0])*toRad, dLng=(b[1]-a[1])*toRad;
        var s=Math.sin(dLat/2)**2 + Math.cos(a[0]*toRad)*Math.cos(b[0]*toRad)*Math.sin(dLng/2)**2;
        return 2*R*Math.asin(Math.sqrt(s));
      }
      var totalM = 0; for (var k=1; k<coords.length; k++) totalM += hav(coords[k-1], coords[k]);
      bCurrentRoute = { coords: coords, jarak_m: totalM };
      document.getElementById('rbSave').disabled = false;
      document.getElementById('rbExport').disabled = false;
      divResult.innerHTML = '✓ Rute diekstrak: <strong>'+coords.length+' titik</strong>, panjang ~'+(totalM/1000).toFixed(2)+' km. '+
        'Anda bisa <em>Simpan Rute</em> atau <em>Export GPX</em> pada panel kiri.';
    });

    refreshState();
  })();

})();
</script>

<?php include __DIR__.'/includes/footer.php'; ?>

