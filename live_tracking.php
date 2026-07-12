<?php
/**
 * Revisi 15 Juni 2026 — Halaman "Live Tracking / Beacon"
 *
 * Pemilik akun memulai sesi → mendapatkan tautan publik yang bisa dikirim ke
 * keluarga / kontak darurat lewat WhatsApp / Telegram / SMS / email.
 * Browser pemilik akan mengirim titik GPS otomatis tiap 5 detik selama tab
 * dibiarkan terbuka. Penerima cukup buka tautan, peta langsung mengikuti.
 */
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
require __DIR__.'/includes/paket_helpers.php'; // Revisi 26 Juni 2026 #8 — gating PRO/KOMUNITAS
send_security_headers(); require_login();
$u = current_user(); $uid = (int)$u['id'];
$pageTitle = 'Live Tracking / Beacon';

/* Revisi — Gating Paket KOMUNITAS (sama seperti tempat_list.php).
   Paket Gratis dikunci. Paket PRO tetap boleh mengakses fitur Komunitas. */
paket_require_or_lock('komunitas', $u, 'Live Tracking / Beacon',
    'Berbagi lokasi real-time ke keluarga / kontak darurat tersedia untuk paket Komunitas.');

// Revisi 19 Juni 2026 — Ikon pelari memakai foto profil user
$userRow = db_one("SELECT foto_url FROM users WHERE id=$1", [$uid]);
$userPhoto = trim((string)($userRow['foto_url'] ?? ''));
if ($userPhoto === '') $userPhoto = '/assets/img/avatar-default.png';

// Auto-migrasi (lihat api_live_tracking.php) — dipanggil juga di sini supaya
// halaman aman dibuka pertama kali tanpa pernah hit API.
@db_exec("CREATE TABLE IF NOT EXISTS live_tracking_sessions (
    id BIGSERIAL PRIMARY KEY, user_id BIGINT NOT NULL,
    token VARCHAR(48) NOT NULL UNIQUE, judul TEXT NOT NULL DEFAULT 'Live Tracking',
    pesan TEXT, olahraga TEXT NOT NULL DEFAULT 'lari',
    started_at TIMESTAMP NOT NULL DEFAULT now(), ended_at TIMESTAMP,
    expires_at TIMESTAMP NOT NULL DEFAULT (now() + INTERVAL '12 hours'),
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    last_lat DOUBLE PRECISION, last_lng DOUBLE PRECISION, last_seen_at TIMESTAMP
)");
@db_exec("CREATE TABLE IF NOT EXISTS live_tracking_contacts (
    id BIGSERIAL PRIMARY KEY, user_id BIGINT NOT NULL,
    nama TEXT NOT NULL, nomor_wa TEXT, email TEXT, relasi TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT now()
)");

$mine     = db_all("SELECT * FROM live_tracking_sessions WHERE user_id=$1 ORDER BY id DESC LIMIT 10", [$uid]);
$contacts = db_all("SELECT * FROM live_tracking_contacts WHERE user_id=$1 ORDER BY id DESC", [$uid]);

include __DIR__.'/includes/header.php';
?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<!-- Revisi 19 Juni 2026 Part O — Musik latar dihapus. Hanya popup detail + safety banner. -->
<style>
  .lt-detail-popup{position:fixed;right:18px;top:84px;z-index:1050;width:280px;max-width:90vw;
    background:var(--bs-body-bg);border:1px solid var(--bs-border-color);border-radius:14px;
    box-shadow:0 16px 40px rgba(0,0,0,.18);transform:translateY(-8px);opacity:0;pointer-events:none;
    transition:opacity .18s,transform .18s}
  .lt-detail-popup.show{opacity:1;transform:none;pointer-events:auto}
  .lt-detail-popup .head{display:flex;align-items:center;gap:.5rem;padding:.6rem .8rem;border-bottom:1px solid var(--bs-border-color);
    background:linear-gradient(135deg,#fef2f2,#fff);border-radius:14px 14px 0 0}
  .lt-detail-popup .body{padding:.7rem .9rem;font-size:.85rem}
  .lt-detail-popup .stat{display:flex;justify-content:space-between;padding:.15rem 0}
  .lt-stat-pill{display:inline-flex;align-items:center;gap:.35rem;background:var(--bs-secondary-bg);
    border-radius:999px;padding:.15rem .55rem;font-size:.75rem;margin-right:.25rem}
  .lt-safety-banner{position:fixed;left:50%;transform:translateX(-50%);top:64px;z-index:1055;
    padding:.5rem .9rem;border-radius:999px;font-size:.85rem;display:none;
    box-shadow:0 6px 18px rgba(0,0,0,.18)}
  .lt-safety-banner.aman{background:#dcfce7;color:#166534;border:1px solid #86efac}
  .lt-safety-banner.waspada{background:#fef9c3;color:#854d0e;border:1px solid #facc15}
  .lt-safety-banner.darurat{background:#fee2e2;color:#991b1b;border:1px solid #ef4444;animation:lt-pulse 1s infinite}
  @keyframes lt-pulse{0%,100%{opacity:1}50%{opacity:.6}}
</style>

<!-- Tombol musik latar dihapus (Revisi 19 Juni 2026 Part O #4) -->

<!-- Popup detail melayang -->
<div id="ltDetail" class="lt-detail-popup" role="dialog" aria-label="Detail Live Tracking">
  <div class="head">
    <i class="bi bi-broadcast text-danger fs-5"></i>
    <strong class="me-auto">Detail Sesi</strong>
    <button type="button" class="btn-close btn-sm" id="ltDetailClose" aria-label="Tutup"></button>
  </div>
  <div class="body">
    <div class="stat"><span><i class="bi bi-clock-history text-primary"></i> Durasi</span><strong id="ltdDur">—</strong></div>
    <div class="stat"><span><i class="bi bi-rulers text-info"></i> Jarak</span><strong id="ltdDist">—</strong></div>
    <div class="stat"><span><i class="bi bi-speedometer2 text-warning"></i> Pace</span><strong id="ltdPace">—</strong></div>
    <div class="stat"><span><i class="bi bi-lightning-charge text-success"></i> Kecepatan</span><strong id="ltdSpd">—</strong></div>
    <div class="stat"><span><i class="bi bi-geo-alt text-danger"></i> Titik terkirim</span><strong id="ltdPts">0</strong></div>
    <div class="stat"><span><i class="bi bi-shield-check"></i> AI Safety</span><strong id="ltdSafe">—</strong></div>
    <hr class="my-2">
    <div class="d-flex gap-1 flex-wrap">
      <span class="lt-stat-pill"><i class="bi bi-broadcast text-danger"></i> Live</span>
      <span class="lt-stat-pill"><i class="bi bi-people text-primary"></i> <span id="ltdShared">tautan siap</span></span>
    </div>
  </div>
</div>
<div id="ltSafetyBanner" class="lt-safety-banner"></div>

<script>window.MAPBOX_TOKEN_JS = 'pk.eyJ1IjoiYWRhbXNhc21pdGE1MzQiLCJhIjoiY21xZnRsbWxjMXZldDJ0cHlhN2Jycnd1dCJ9.2E00ey-sgX9jUmf5kIRoEA';
window.MAPBOX_TILE_URL = 'https://api.mapbox.com/styles/v1/mapbox/outdoors-v12/tiles/256/{z}/{x}/{y}@2x?access_token=' + MAPBOX_TOKEN_JS;
window.MAPBOX_ATTR = '&copy; <a href="https://www.mapbox.com/about/maps/">Mapbox</a> &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>';
</script>


<h4 class="mb-1"><i class="bi bi-broadcast text-danger"></i> Live Tracking / Beacon</h4>
<p class="text-muted small mb-3">
  Bagikan posisi GPS Anda secara langsung kepada keluarga / kontak darurat
  selama berolahraga. Mereka cukup membuka tautan yang dikirim — tidak perlu
  install apa-apa.
</p>


<details class="card border-0 shadow-sm mb-3 border-start border-4 border-danger">
  <summary class="card-body py-2" style="cursor:pointer;list-style:revert">
    <strong><i class="bi bi-info-circle text-danger"></i> Cara Penggunaan Live Tracking / Beacon</strong>
    <span class="text-muted small">(klik untuk buka/tutup)</span>
  </summary>
  <div class="card-body py-3 pt-0">
    <ol class="small mb-2 ps-3">
      <li><b>Tambahkan kontak darurat</b> di panel <em>Kontak Darurat</em> (Nama, No. WA, relasi).
        Kontak ini hanya disimpan untuk Anda — bisa dibagikan ulang cepat.</li>
      <li>Isi <b>Judul sesi</b> (misal "Lari sore di GBK") dan <b>durasi berlaku tautan</b> (jam).
        Sesi otomatis kadaluarsa setelah durasi habis.</li>
      <li>Tekan <b>Mulai &amp; buat tautan</b>. Izinkan akses GPS saat browser meminta.
        Sebuah tautan publik akan dibuat (contoh: <code>/track_view.php?token=…</code>).</li>
      <li>Klik tombol <b class="text-success">WhatsApp</b>, <b class="text-info">Telegram</b>, atau
        <b>SMS</b> untuk mengirim tautan ke kontak darurat. Mereka <u>tidak perlu install apa-apa</u>,
        cukup buka tautan lewat browser.</li>
      <li><b>JANGAN tutup tab ini</b> selama berlari. Browser akan otomatis mengirim posisi GPS
        setiap ±5 detik. Status pengiriman terlihat di bawah tautan.</li>
      <li>Selesai berolahraga, klik <b class="text-danger">Hentikan</b>. Tautan akan berhenti
        menerima titik baru (penerima tetap bisa melihat lintasan terakhir).</li>
    </ol>
    <div class="alert alert-warning small mb-0 py-2">
      <i class="bi bi-exclamation-triangle"></i>
      Tips: pakai HP dengan layar tetap menyala (atau aktifkan <em>Wake Lock</em> di
      <code>/run.php</code>). Jika layar mati, browser akan menjeda pengiriman GPS sampai layar nyala lagi.
    </div>
  </div>
</details>
<div class="row g-3">
  <!-- KIRI: Kontrol sesi -->
  <div class="col-lg-5">
    <div class="card shadow-sm">
      <div class="card-body">
        <h6 class="fw-bold mb-3"><i class="bi bi-play-circle text-success"></i> Mulai Sesi Berbagi</h6>
        <form id="frmStart" data-ajax class="vstack gap-2">
          <input class="form-control form-control-sm" name="judul"    placeholder="Judul (mis. Lari sore taman)" value="Lari sore">
          <input type="hidden" name="olahraga" value="lari">
          <div class="form-control form-control-sm bg-body-secondary text-muted d-flex align-items-center" style="cursor:not-allowed">
            <i class="bi bi-lock-fill me-2"></i> Jenis aktivitas: <b class="ms-1">Lari</b>
            <span class="ms-auto badge bg-danger-subtle text-danger">khusus lari</span>
          </div>
          <input type="number" min="1" max="24" class="form-control form-control-sm" name="durasi_jam" value="6" title="Durasi berlaku tautan (jam)">
          <textarea class="form-control form-control-sm" name="pesan" rows="2" placeholder="Pesan untuk penerima (opsional)"></textarea>
          <button class="btn btn-danger"><i class="bi bi-broadcast"></i> Mulai &amp; buat tautan</button>
        </form>

        <hr>
        <div id="liveBox" class="d-none">
          <div class="alert alert-success py-2 small mb-2">
            <i class="bi bi-check2-circle"></i> Sesi aktif. Browser akan kirim
            posisi setiap ~5 detik. <b>Jangan tutup tab ini.</b>
          </div>
          <label class="form-label small mb-1">Tautan untuk dibagikan</label>
          <div class="input-group input-group-sm mb-2">
            <input id="shareUrl" class="form-control" readonly>
            <button class="btn btn-outline-secondary" type="button" id="btnCopy"><i class="bi bi-clipboard"></i></button>
          </div>
          <div class="d-flex flex-wrap gap-2">
            <a id="waShare" target="_blank" class="btn btn-success btn-sm"><i class="bi bi-whatsapp"></i> WhatsApp</a>
            <a id="tgShare" target="_blank" class="btn btn-info btn-sm text-white"><i class="bi bi-telegram"></i> Telegram</a>
            <a id="smsShare" class="btn btn-secondary btn-sm"><i class="bi bi-chat-dots"></i> SMS</a>
            <!-- Revisi 15 Juni 2026 — popup melayang (PiP) agar GPS tetap aktif saat pindah app/tab -->
            <button id="btnPipLive" type="button" class="btn btn-outline-info btn-sm"><i class="bi bi-pip"></i> Popup Peta</button>
            <button id="btnDetailLive" type="button" class="btn btn-outline-primary btn-sm"><i class="bi bi-window-stack"></i> Detail Popup</button>
            <button id="btnStop" class="btn btn-outline-danger btn-sm ms-auto"><i class="bi bi-stop-circle"></i> Hentikan</button>
          </div>
          <div id="liveStat" class="small text-muted mt-2"></div>
        </div>
      </div>
    </div>

    <!-- Kontak darurat -->
    <div class="card shadow-sm mt-3">
      <div class="card-body">
        <h6 class="fw-bold mb-3"><i class="bi bi-people text-primary"></i> Kontak Darurat</h6>
        <form id="frmContact" data-ajax class="row g-2 mb-2">
          <div class="col-6"><input class="form-control form-control-sm" name="nama" placeholder="Nama" required></div>
          <div class="col-6"><input class="form-control form-control-sm" name="nomor_wa" placeholder="No. WA (628…)"></div>
          <div class="col-6"><input class="form-control form-control-sm" name="email" placeholder="Email"></div>
          <div class="col-6"><input class="form-control form-control-sm" name="relasi" placeholder="Relasi (Istri, Ibu, …)"></div>
          <div class="col-12"><button class="btn btn-primary btn-sm w-100">+ Tambah Kontak</button></div>
        </form>
        <ul class="list-group list-group-flush small" id="lstContact">
          <?php foreach ($contacts as $c): ?>
            <li class="list-group-item d-flex justify-content-between align-items-center px-0">
              <div>
                <b><?= htmlspecialchars($c['nama']) ?></b>
                <?php if ($c['relasi']): ?><span class="text-muted">· <?= htmlspecialchars($c['relasi']) ?></span><?php endif; ?>
                <?php if ($c['nomor_wa']): ?><div class="text-muted">WA: <?= htmlspecialchars($c['nomor_wa']) ?></div><?php endif; ?>
              </div>
              <button class="btn btn-link text-danger btn-sm p-0" data-del="<?= (int)$c['id'] ?>"><i class="bi bi-trash"></i></button>
            </li>
          <?php endforeach; if (!$contacts): ?>
            <li class="list-group-item px-0 text-muted">Belum ada kontak.</li>
          <?php endif; ?>
        </ul>
      </div>
    </div>
  </div>

  <!-- KANAN: Peta + riwayat -->
  <div class="col-lg-7">
    <div class="card shadow-sm">
      <div class="card-body">
        <div id="liveMap" style="height:380px;border-radius:10px;border:1px solid var(--bs-border-color,#e5e7eb)"></div>
        <div class="small text-muted mt-2">
          <i class="bi bi-info-circle"></i>
          Peta menampilkan lintasan yang baru saja terkirim. Penerima tautan
          akan melihat tampilan serupa di halaman publik <code>/track_view.php</code>.
        </div>
      </div>
    </div>

    <!-- Revisi 16 Juni 2026: bagian "Sesi Sebelumnya" dihapus / dinonaktifkan -->
  </div>
</div>

<script src="/assets/js/kk-geo.js"></script>
<script>
const API = '/api_live_tracking.php';
const map = L.map('liveMap').setView([-6.2, 106.8], 12);
L.tileLayer(window.MAPBOX_TILE_URL,{maxZoom:19,attribution:window.MAPBOX_ATTR}).addTo(map);
let poly = L.polyline([], {color:'#dc2626', weight:5}).addTo(map);
let me   = null;
let state = { token:null, watchId:null, timer:null, pts:[] };

// ===== Revisi 16 Juni 2026: keep GPS aktif walau layar HP mati =====
let wakeLock = null, audioCtx = null, silentOsc = null;
async function acquireWakeLock(){
  try {
    if ('wakeLock' in navigator) {
      wakeLock = await navigator.wakeLock.request('screen');
      wakeLock.addEventListener('release', ()=>{ /* akan di-reacquire saat visible */ });
    }
  } catch(e){}
}
function releaseWakeLock(){ try{ if(wakeLock){ wakeLock.release(); wakeLock=null; } }catch(e){} }
function startSilentAudio(){
  try {
    const AC = window.AudioContext || window.webkitAudioContext; if(!AC) return;
    audioCtx = new AC();
    silentOsc = audioCtx.createOscillator();
    const gain = audioCtx.createGain(); gain.gain.value = 0.0001;
    silentOsc.connect(gain).connect(audioCtx.destination);
    silentOsc.start();
  } catch(e){}
}
function stopSilentAudio(){ try{ if(silentOsc) silentOsc.stop(); if(audioCtx) audioCtx.close(); }catch(e){} silentOsc=null; audioCtx=null; }
document.addEventListener('visibilitychange', async ()=>{
  if (document.visibilityState === 'visible' && state.token && state.watchId !== null) {
    await acquireWakeLock();
    // Restart watch supaya stream GPS yang ter-throttle di background kembali fresh.
    stopGeo();
    startGeo();
    flushBuffer();
  }
});

function setShareUI(url){
  document.getElementById('shareUrl').value = url;
  const txt = encodeURIComponent('Pantau lokasi saya secara langsung: '+url);
  document.getElementById('waShare').href  = 'https://wa.me/?text='+txt;
  document.getElementById('tgShare').href  = 'https://t.me/share/url?url='+encodeURIComponent(url)+'&text='+encodeURIComponent('Pantau lokasi saya secara langsung');
  document.getElementById('smsShare').href = 'sms:?&body='+txt;
}

document.getElementById('btnCopy').onclick = ()=>{
  const el=document.getElementById('shareUrl'); el.select(); document.execCommand('copy');
};

document.getElementById('frmStart').addEventListener('submit', async (e)=>{
  e.preventDefault();
  const btn = e.target.querySelector('button[type=submit], button:not([type])');
  if (btn) { btn.disabled = true; btn.dataset._html = btn.innerHTML; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Membuat tautan…'; }
  const fd = new FormData(e.target);
  fd.append('action','start');
  try {
    const r = await fetch(API+'?action=start',{method:'POST', body:fd, credentials:'same-origin'});
    const j = await r.json();
    if(!j.ok){ alert('Gagal: '+(j.err||'?')); return; }
    state.token = j.token;
    setShareUI(j.url);
    document.getElementById('liveBox').classList.remove('d-none');
    startGeo();
    await acquireWakeLock();
    startSilentAudio();
  } finally {
    if (btn) { btn.disabled = false; if (btn.dataset._html) btn.innerHTML = btn.dataset._html; }
  }
});

document.getElementById('btnStop').onclick = async ()=>{
  if(!state.token) return;
  stopGeo();
  releaseWakeLock();
  stopSilentAudio();
  const fd = new FormData(); fd.append('token', state.token);
  await fetch(API+'?action=stop',{method:'POST', body:fd, credentials:'same-origin'});
  document.getElementById('liveStat').textContent = 'Sesi dihentikan.';
};

// Revisi 11 Juli 2026 — memakai KKGeo (Capacitor BackgroundGeolocation di APK,
// navigator.geolocation di browser). API pushPoint tetap sama.
function startGeo(){
  if (!window.KKGeo){
    if(!('geolocation' in navigator)){ alert('Browser tidak mendukung GPS.'); return; }
    state.watchId = navigator.geolocation.watchPosition(pushPoint, err=>{
      document.getElementById('liveStat').textContent = 'GPS error: '+err.message;
    }, {enableHighAccuracy:true, maximumAge:2000, timeout:15000});
    return;
  }
  KKGeo.start(pushPoint, function(err){
    document.getElementById('liveStat').textContent = 'GPS error: '+(err && err.message || err);
  }, {
    backgroundTitle:   '🛰️ Live Tracking aktif',
    backgroundMessage: 'Berbagi lokasi ke kontak darurat…',
    distanceFilter: 3
  }).then(function(ok){
    state.watchId = ok ? 'kkgeo' : null;
    if (ok && KKGeo.isNative) KKGeo.notify('Live Tracking aktif', 'GPS berjalan di background.');
  });
}
function stopGeo(){
  if (window.KKGeo){ KKGeo.stop(); state.watchId = null; return; }
  if(state.watchId!=null && typeof state.watchId==='number') navigator.geolocation.clearWatch(state.watchId);
  state.watchId = null;
}
let lastSend = 0;
let sendBuffer = [];
async function flushBuffer(){
  while (sendBuffer.length){
    const item = sendBuffer[0];
    try {
      const r = await fetch(API+'?action=push',{method:'POST', body:item, keepalive:true});
      if (!r.ok) return;
      sendBuffer.shift();
    } catch(e){ return; }
  }
}
setInterval(flushBuffer, 5000);
function pushPoint(pos){
  const {latitude:lat, longitude:lng, accuracy, speed, heading} = pos.coords;
  state.pts.push([lat,lng]); poly.setLatLngs(state.pts);
  if(!me){ me = L.marker([lat,lng]).addTo(map); map.setView([lat,lng], 16); } else { me.setLatLng([lat,lng]); }
  const now = Date.now();
  if (now - lastSend < 4500) return;            // throttle ~5 detik
  lastSend = now;
  const fd = new FormData();
  fd.append('token', state.token);
  fd.append('lat', lat); fd.append('lng', lng);
  if(accuracy!=null) fd.append('accuracy', accuracy);
  if(speed!=null)    fd.append('speed', speed);
  if(heading!=null)  fd.append('heading', heading);
  sendBuffer.push(fd);
  flushBuffer().then(()=>{
    document.getElementById('liveStat').textContent =
      'Terkirim '+ new Date().toLocaleTimeString() + (sendBuffer.length?' (buffer: '+sendBuffer.length+')':'');
  });
}

/* ===== Revisi 15 Juni 2026 — Document Picture-in-Picture (peta melayang) ===== */
let pipWinLT = null;
document.getElementById('btnPipLive').addEventListener('click', async ()=>{
  if (!('documentPictureInPicture' in window)) {
    alert('Browser belum mendukung Document Picture-in-Picture. Gunakan Chrome/Edge terbaru di HTTPS.');
    return;
  }
  if (pipWinLT) { try { pipWinLT.close(); } catch(e){} pipWinLT = null; return; }
  try {
    pipWinLT = await window.documentPictureInPicture.requestWindow({ width: 340, height: 420 });
    [...document.styleSheets].forEach(ss=>{
      try {
        const rules = [...ss.cssRules].map(r=>r.cssText).join('');
        const s = pipWinLT.document.createElement('style'); s.textContent = rules;
        pipWinLT.document.head.appendChild(s);
      } catch(e){
        if (ss.href){ const l = pipWinLT.document.createElement('link'); l.rel='stylesheet'; l.href=ss.href; pipWinLT.document.head.appendChild(l); }
      }
    });
    const mh = document.getElementById('liveMap');
    const ph = document.createElement('div');
    ph.style.height = mh.style.height;
    ph.className = 'd-flex align-items-center justify-content-center text-muted border rounded bg-light';
    ph.innerHTML = '<div class="text-center small"><i class="bi bi-pip fs-2"></i><br>Peta sedang melayang.</div>';
    mh.parentNode.insertBefore(ph, mh);
    pipWinLT.document.body.style.margin='0';
    pipWinLT.document.body.appendChild(mh);
    mh.style.height='100%';
    setTimeout(()=>map.invalidateSize(), 80);
    pipWinLT.addEventListener('pagehide', ()=>{
      mh.style.height='380px';
      ph.parentNode.replaceChild(mh, ph);
      setTimeout(()=>map.invalidateSize(), 80);
      pipWinLT = null;
    });
  } catch(err){ alert('Tidak dapat membuka popup melayang: '+err.message); }
});


/* ===== Revisi 16 Juni 2026 — Detail popup, musik latar, ikon, AI Safety Monitoring ===== */
(function(){
  var startTs = null;
  var totalKm = 0;
  var lastPt  = null;     // [lat,lng,ts]
  var idleSinceTs = null; // ms — sejak kapan belum ada titik baru / speed=0
  var speedDrops = [];    // riwayat penurunan kecepatan signifikan
  var basePoint  = null;  // titik referensi rute (titik pertama)
  var lastSafety = 0;
  var safetyTimer = null;

  // Ikon kustom Leaflet — Revisi 19 Juni 2026: pakai foto profil user
  if (window.L && me === null) {
    var USER_PHOTO_URL = <?= json_encode($userPhoto) ?>;
    var runIcon = L.divIcon({
      className:'lt-run-icon',
      html:'<div style="width:36px;height:36px;border-radius:50%;border:3px solid #ef4444;'
          + 'box-shadow:0 4px 10px rgba(0,0,0,.3);background:#fff center/cover no-repeat;'
          + 'background-image:url('+JSON.stringify(USER_PHOTO_URL)+')"></div>',
      iconSize:[36,36], iconAnchor:[18,18]
    });
    // ganti factory marker saat pertama kali dibuat
    var _origAdd = L.marker;
    L.marker = function(latlng, opts){
      opts = opts || {};
      if (!opts.icon) opts.icon = runIcon;
      return _origAdd.call(L, latlng, opts);
    };
  }

  // Musik latar dihapus (Revisi 19 Juni 2026 Part O #4)
  var music = null, mBtn = null, musicOn = false;
  function setMusicLabel(){ /* no-op */ }

  // Aktifkan tombol musik begitu sesi aktif
  var oldStartGeo = window.startGeo;
  // Patch UI: aktifkan musik dan mulai timer setelah sesi mulai
  var formStart = document.getElementById('frmStart');
  if (formStart){
    formStart.addEventListener('submit', function(){
      setTimeout(function(){
        if (state.token){
          startTs = Date.now();
          openDetail();
          startSafetyLoop();
        }
      }, 600);
    });
  }
  var btnStop = document.getElementById('btnStop');
  if (btnStop) btnStop.addEventListener('click', function(){
    if (safetyTimer){ clearInterval(safetyTimer); safetyTimer=null; }
  });

  // Detail popup
  var det = document.getElementById('ltDetail');
  document.getElementById('btnDetailLive').addEventListener('click', function(){ det.classList.toggle('show'); });
  document.getElementById('ltDetailClose').addEventListener('click', function(){ det.classList.remove('show'); });
  function openDetail(){ det.classList.add('show'); }
  function fmtDur(ms){
    var s = Math.floor(ms/1000); var h=Math.floor(s/3600); var m=Math.floor((s%3600)/60); var ss=s%60;
    return (h>0?(h+'j '):'') + m+'m '+ss+'s';
  }
  function fmtPace(secPerKm){
    if (!isFinite(secPerKm) || secPerKm<=0) return '—';
    var m = Math.floor(secPerKm/60), s = Math.round(secPerKm%60);
    return m + "'" + (s<10?'0':'') + s + '"/km';
  }
  function haversineKm(a,b){
    var R=6371, dLat=(b[0]-a[0])*Math.PI/180, dLng=(b[1]-a[1])*Math.PI/180;
    var s=Math.sin(dLat/2)**2+Math.cos(a[0]*Math.PI/180)*Math.cos(b[0]*Math.PI/180)*Math.sin(dLng/2)**2;
    return 2*R*Math.asin(Math.sqrt(s));
  }

  // Setiap kali pushPoint dipanggil — update detail stats + AI signals.
  var _orig_pushPoint = window.pushPoint;
  window.pushPoint = function(pos){
    var lat = pos.coords.latitude, lng = pos.coords.longitude;
    var spd = pos.coords.speed; // m/s
    var now = Date.now();
    if (!basePoint) basePoint = [lat,lng];
    if (lastPt){
      var dKm = haversineKm([lastPt[0],lastPt[1]],[lat,lng]);
      if (dKm < 2) totalKm += dKm; // anti glitch jauh
      // deteksi penurunan kecepatan
      if (lastPt.spd != null && spd != null && lastPt.spd > 1.5 && spd < lastPt.spd*0.4) {
        speedDrops.push(now);
      }
    }
    lastPt = [lat,lng,now]; lastPt.spd = spd;
    if (spd == null || spd < 0.3) {
      if (!idleSinceTs) idleSinceTs = now;
    } else { idleSinceTs = null; }

    // update UI detail
    var dur = startTs ? (now-startTs) : 0;
    document.getElementById('ltdDur').textContent = startTs? fmtDur(dur) : '—';
    document.getElementById('ltdDist').textContent = totalKm.toFixed(2)+' km';
    var paceSec = (totalKm>0.05 && dur>0) ? (dur/1000)/totalKm : 0;
    document.getElementById('ltdPace').textContent = fmtPace(paceSec);
    document.getElementById('ltdSpd').textContent = (spd!=null? (spd*3.6).toFixed(1)+' km/j' : '—');
    document.getElementById('ltdPts').textContent = state.pts.length;
    var shared = document.getElementById('ltdShared');
    if (shared) shared.textContent = state.token? 'tautan aktif' : 'tautan siap';

    return _orig_pushPoint.apply(this, arguments);
  };

  // AI Safety loop — setiap 60 dtk kirim ringkasan ke Gemini
  function startSafetyLoop(){
    if (safetyTimer) clearInterval(safetyTimer);
    safetyTimer = setInterval(checkSafety, 60000);
    setTimeout(checkSafety, 12000);
  }
  async function checkSafety(){
    if (!state.token) return;
    var now = Date.now();
    if (now - lastSafety < 30000) return;
    lastSafety = now;
    var idleSec = idleSinceTs ? Math.round((now-idleSinceTs)/1000) : 0;
    var farKm = (lastPt && basePoint) ? haversineKm(basePoint,[lastPt[0],lastPt[1]]).toFixed(2) : 0;
    var drops = speedDrops.filter(function(t){return now-t<10*60*1000;}).length;
    var ctx = 'Kecepatan terakhir: '+ (lastPt && lastPt.spd!=null? (lastPt.spd*3.6).toFixed(1)+' km/j' : 'tidak ada') +
              '. Idle (tanpa gerak): '+ idleSec +' detik. ' +
              'Jarak dari titik awal: '+ farKm +' km. ' +
              'Jumlah penurunan kecepatan drastis 10 menit terakhir: '+ drops +'. ' +
              'Total titik GPS terkirim: '+ state.pts.length +'.';
    try {
      var fd = new FormData();
      fd.append('csrf', '<?= csrf_token() ?>');
      fd.append('task','safety');
      fd.append('ctx', ctx);
      var r = await fetch('/api_ai.php',{method:'POST', body:fd, credentials:'same-origin'});
      var j = await r.json();
      var d = j.data;
      var safeEl = document.getElementById('ltdSafe');
      var banner = document.getElementById('ltSafetyBanner');
      if (d && d.level){
        safeEl.textContent = d.level.toUpperCase();
        safeEl.className = d.level==='darurat'?'text-danger':(d.level==='waspada'?'text-warning':'text-success');
        if (d.level !== 'aman'){
          banner.className = 'lt-safety-banner '+d.level;
          banner.innerHTML = '<i class="bi bi-shield-exclamation"></i> <strong>'+d.level.toUpperCase()+':</strong> '+(d.alasan||'')+' <span class="ms-2 text-muted">'+(d.pesan||'')+'</span>';
          banner.style.display = 'block';
          if (d.level==='darurat' && 'vibrate' in navigator) navigator.vibrate([200,80,200,80,500]);
        } else {
          banner.style.display = 'none';
        }
      } else {
        safeEl.textContent = 'aman';
      }
    } catch(e){ /* silent */ }
  }
})();

/* ---------- Kontak darurat ---------- */
document.getElementById('frmContact').addEventListener('submit', async (e)=>{
  e.preventDefault();
  const fd = new FormData(e.target); fd.append('action','contact_add');
  const j = await (await fetch(API+'?action=contact_add',{method:'POST', body:fd, credentials:'same-origin'})).json();
  if(j.ok) location.reload(); else alert('Gagal: '+(j.err||'?'));
});
document.querySelectorAll('[data-del]').forEach(b=>{
  b.onclick = async ()=>{
    if(!confirm('Hapus kontak ini?')) return;
    const fd = new FormData(); fd.append('id', b.dataset.del);
    await fetch(API+'?action=contact_del',{method:'POST', body:fd, credentials:'same-origin'});
    location.reload();
  };
});
</script>

<?php include __DIR__.'/includes/bottom_nav.php'; include __DIR__.'/includes/footer.php'; ?>
