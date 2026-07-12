<?php
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
send_security_headers(); require_login();
$u = current_user(); $uid = (int)$u['id'];
$pageTitle = 'Tracking Jalur / Rute';
$pageSkeleton = 'grid';

/* Gating Paket KOMUNITAS (paket Gratis dikunci). */
require_once __DIR__.'/includes/paket_helpers.php';
if (!isset($u) || !$u) { require_login(); $u = current_user(); }
paket_require_or_lock('komunitas', $u, 'Tracking Jalur / Rute',
    'Tracking Jalur & Eksplorasi Rute tersedia untuk paket Komunitas.');

// Foto profil user (opsional untuk ikon pelari)
$userRow = db_one("SELECT foto_url FROM users WHERE id=$1", [$uid]);
$userPhoto = trim((string)($userRow['foto_url'] ?? ''));
if ($userPhoto === '') $userPhoto = '/assets/img/avatar-default.png';

// Berat badan (dipakai untuk kalori MET). Kolom opsional — fallback 65 kg.
$wRow = @db_one("SELECT berat_kg FROM users WHERE id=$1", [$uid]);
$userWeight = (float)($wRow['berat_kg'] ?? 0); if ($userWeight <= 0) $userWeight = 65;

// Tabel Route Builder (idempotent)
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
$active  = db_one("SELECT * FROM run_sessions WHERE user_id=$1 AND status='aktif' ORDER BY id DESC LIMIT 1", [$uid]);
$savedRoutes = db_all("SELECT id, nama, jarak_m, elevasi_pref, surface_pref, is_public, created_at FROM run_routes WHERE user_id=$1 ORDER BY id DESC LIMIT 20", [$uid]);

include __DIR__.'/includes/header.php';
?>

<!-- ============================================================ -->
<!-- REVISI R33 (Strava-style Tracking) — Nov 2026                -->
<!-- ============================================================ -->
<style>
  .strava-card{border:0;border-radius:18px;background:#fff;
    box-shadow:0 4px 18px rgba(15,23,42,.06), 0 1px 3px rgba(15,23,42,.04);}
  .strava-card .card-body{padding:1.1rem 1.2rem;}
  .stat-block{padding:.35rem .25rem;}
  .stat-label{font-size:.72rem;letter-spacing:.08em;text-transform:uppercase;
    color:#64748b;font-weight:600;margin-bottom:.15rem;}
  .stat-value{font-weight:800;color:#0f172a;line-height:1;
    font-variant-numeric:tabular-nums;font-feature-settings:"tnum";}
  .stat-primary .stat-value{font-size:2.6rem;}
  .stat-secondary .stat-value{font-size:1.35rem;}
  .stat-mini .stat-value{font-size:1.05rem;font-weight:700;color:#334155;}
  .stat-sub{font-size:.7rem;color:#94a3b8;margin-top:.1rem;}
  .strava-btn{border-radius:14px;font-weight:700;padding:.85rem 1.1rem;
    letter-spacing:.02em;box-shadow:0 2px 6px rgba(15,23,42,.08);}
  .strava-btn-lg{font-size:1.05rem;padding:1rem 1.3rem;border-radius:16px;}
  .strava-btn.btn-start{background:#22c55e;border:0;color:#fff;}
  .strava-btn.btn-start:hover{background:#16a34a;}
  .strava-btn.btn-pause{background:#f59e0b;border:0;color:#fff;}
  .strava-btn.btn-resume{background:#0ea5e9;border:0;color:#fff;}
  .strava-btn.btn-stop{background:#ef4444;border:0;color:#fff;}
  .map-wrap{position:relative;border-radius:18px;overflow:hidden;
    box-shadow:0 4px 18px rgba(15,23,42,.08);}
  #runMap{height:min(58vh,560px);min-height:340px;width:100%;}
  .map-overlay{position:absolute;left:12px;right:12px;top:12px;z-index:500;
    display:grid;grid-template-columns:repeat(3,1fr);gap:.55rem;
    background:rgba(255,255,255,.94);backdrop-filter:blur(6px);
    padding:.7rem .85rem;border-radius:16px;
    box-shadow:0 4px 18px rgba(15,23,42,.12);}
  .map-overlay .stat-label{font-size:.65rem;}
  .map-overlay .stat-value{font-size:1.15rem;font-weight:800;color:#0f172a;}
  .gps-chip{position:absolute;left:12px;bottom:12px;z-index:500;
    background:rgba(15,23,42,.85);color:#fff;padding:.4rem .7rem;
    border-radius:999px;font-size:.78rem;font-weight:600;
    display:inline-flex;align-items:center;gap:.4rem;
    box-shadow:0 2px 10px rgba(0,0,0,.2);}
  .recenter-btn{position:absolute;right:12px;bottom:12px;z-index:500;
    background:#fff;border:0;border-radius:999px;padding:.55rem .9rem;
    font-weight:700;font-size:.85rem;color:#0f172a;
    box-shadow:0 4px 14px rgba(0,0,0,.18);display:none;
    align-items:center;gap:.35rem;}
  .recenter-btn.show{display:inline-flex;}
  /* Smooth Strava-like marker interpolation between GPS fixes */
  .leaflet-marker-icon.kk-runner-icon{transition:transform .9s linear;}
  .split-item{display:flex;justify-content:space-between;align-items:center;
    padding:.55rem .8rem;border-bottom:1px solid #f1f5f9;font-size:.9rem;}
  .split-item:last-child{border-bottom:0;}
  .split-km{font-weight:700;color:#334155;}
  .split-pace{font-variant-numeric:tabular-nums;font-weight:700;color:#0f172a;}
  .split-bar{flex:1;height:6px;background:#e2e8f0;border-radius:999px;
    margin:0 .8rem;overflow:hidden;}
  .split-bar > i{display:block;height:100%;background:#fb923c;border-radius:999px;}
  .bg-warn{background:#fef3c7;border-radius:14px;padding:.7rem .9rem;
    font-size:.85rem;color:#92400e;border:1px solid #fde68a;}
  .settings-row .form-control,.settings-row .form-select{border-radius:12px;}
  .history-strava .list-group-item{border:0;border-bottom:1px solid #f1f5f9;padding:.85rem 1rem;}
  .history-strava .list-group-item:last-child{border-bottom:0;}
  @media (max-width:576px){
    .stat-primary .stat-value{font-size:2.1rem;}
    #runMap{height:52vh;}
    .map-overlay{grid-template-columns:repeat(3,1fr);padding:.55rem .6rem;}
    .map-overlay .stat-value{font-size:1rem;}
  }
</style>

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <h4 class="mb-0"><i class="bi bi-stopwatch text-danger"></i> Tracking Realtime</h4>
  <a href="#eksplorasi" class="btn btn-sm btn-outline-primary d-md-none">
    <i class="bi bi-compass"></i> Eksplorasi Rute &amp; Peta Canggih
  </a>
</div>

<!-- Warning kalau bukan native APK -->
<div id="bgWarn" class="bg-warn mb-3 d-none">
  <i class="bi bi-exclamation-triangle-fill"></i>
  <strong>Tracking background tidak didukung browser.</strong>
  Jika layar HP dikunci atau aplikasi diminimize, GPS bisa berhenti.
  Untuk hasil setara Strava, gunakan <strong>APK KawanKeringat</strong>
  (Capacitor + <code>@capacitor-community/background-geolocation</code>).
</div>

<!-- Panduan singkat (spoiler) -->
<details class="strava-card mb-3">
  <summary class="card-body py-2" style="cursor:pointer;list-style:revert">
    <strong><i class="bi bi-info-circle text-danger"></i> Cara Penggunaan Tracking</strong>
    <span class="text-muted small">(klik buka/tutup)</span>
  </summary>
  <div class="card-body py-3 pt-0">
    <ol class="small mb-2 ps-3">
      <li>Tekan <b class="text-success">Mulai</b> — izinkan akses GPS. Marker &amp; polyline bergerak otomatis mengikuti Anda.</li>
      <li>Peta auto-follow posisi Anda. Kalau Anda geser peta manual, muncul tombol <b>Kembali ke Posisi Saya</b>.</li>
      <li><b>Auto-pause</b> aktif setelah diam &gt;10 dtk; lanjut otomatis saat mulai bergerak lagi.</li>
      <li>Filter GPS: titik dengan akurasi &gt;30 m, perpindahan &lt;3 m, atau kecepatan tak masuk akal diabaikan.</li>
      <li>Selesai berlari, tekan <b class="text-danger">Stop</b> — track disimpan lengkap dengan pace, split KM &amp; kalori (MET).</li>
      <li>Untuk background tracking saat layar mati / app diminimize, gunakan versi <b>APK KawanKeringat</b>.</li>
    </ol>
  </div>
</details>

<!-- Setting berat & jenis olahraga (untuk MET) -->
<div class="strava-card mb-3">
  <div class="card-body py-2 settings-row">
    <div class="row g-2 align-items-center">
      <div class="col-6 col-md-3">
        <label class="stat-label mb-1">Jenis Olahraga</label>
        <select id="sportSel" class="form-select form-select-sm">
          <option value="run">Lari</option>
          <option value="jog">Jogging</option>
          <option value="walk">Jalan</option>
          <option value="bike">Sepeda</option>
        </select>
      </div>
      <div class="col-6 col-md-3">
        <label class="stat-label mb-1">Berat Badan (kg)</label>
        <input id="weightInp" type="number" min="20" max="250" step="0.1"
          class="form-control form-control-sm" value="<?= htmlspecialchars((string)$userWeight) ?>">
      </div>
      <div class="col-12 col-md-6 text-md-end small text-muted">
        <i class="bi bi-fire text-danger"></i> Kalori dihitung memakai rumus <b>MET × berat × jam</b>
        (MET otomatis dari jenis olahraga &amp; kecepatan).
      </div>
    </div>
  </div>
</div>

<div class="row g-3">
  <!-- ===== KOLOM KIRI: Map + Stats + Controls ===== -->
  <div class="col-lg-8">
    <div class="map-wrap mb-3">
      <div id="runMap"></div>
      <!-- Floating info overlay -->
      <div class="map-overlay">
        <div class="stat-block text-center">
          <div class="stat-label">Distance</div>
          <div class="stat-value" id="ovDist">0.00 <small style="font-size:.7em">km</small></div>
        </div>
        <div class="stat-block text-center">
          <div class="stat-label">Time</div>
          <div class="stat-value" id="ovTime">00:00</div>
        </div>
        <div class="stat-block text-center">
          <div class="stat-label">Pace</div>
          <div class="stat-value" id="ovPace">--'--"</div>
        </div>
        <div class="stat-block text-center">
          <div class="stat-label">Speed</div>
          <div class="stat-value" id="ovSpd">0.0 <small style="font-size:.7em">km/h</small></div>
        </div>
        <div class="stat-block text-center">
          <div class="stat-label">Avg Pace</div>
          <div class="stat-value" id="ovAvgPace">--'--"</div>
        </div>
        <div class="stat-block text-center">
          <div class="stat-label">Elev</div>
          <div class="stat-value" id="ovElev">–</div>
        </div>
      </div>
      <div class="gps-chip" id="gpsChip">🟡 Menunggu GPS</div>
      <button type="button" class="recenter-btn" id="btnRecenter">
        <i class="bi bi-crosshair"></i> Kembali ke Posisi Saya
      </button>
    </div>

    <!-- Stat besar di bawah map (Strava-style) -->
    <div class="strava-card mb-3">
      <div class="card-body">
        <div class="row text-center g-2">
          <div class="col-4 stat-block stat-primary">
            <div class="stat-label">Distance</div>
            <div class="stat-value" id="bigDist">0.00</div>
            <div class="stat-sub">km</div>
          </div>
          <div class="col-4 stat-block stat-primary">
            <div class="stat-label">Time</div>
            <div class="stat-value" id="bigTime">00:00</div>
            <div class="stat-sub" id="bigTimeH">&nbsp;</div>
          </div>
          <div class="col-4 stat-block stat-primary">
            <div class="stat-label">Pace</div>
            <div class="stat-value" id="bigPace">--'--"</div>
            <div class="stat-sub">/km</div>
          </div>
        </div>
        <hr class="my-3">
        <div class="row text-center g-2">
          <div class="col-6 col-md-3 stat-block stat-secondary">
            <div class="stat-label">Avg Speed</div>
            <div class="stat-value" id="avgSpd">0.0</div>
            <div class="stat-sub">km/h</div>
          </div>
          <div class="col-6 col-md-3 stat-block stat-secondary">
            <div class="stat-label">Current Speed</div>
            <div class="stat-value" id="curSpd">0.0</div>
            <div class="stat-sub">km/h</div>
          </div>
          <div class="col-6 col-md-3 stat-block stat-secondary">
            <div class="stat-label">Elevation</div>
            <div class="stat-value" id="elevVal">–</div>
            <div class="stat-sub">m</div>
          </div>
          <div class="col-6 col-md-3 stat-block stat-secondary">
            <div class="stat-label">Calories</div>
            <div class="stat-value" id="calVal">0</div>
            <div class="stat-sub">kkal</div>
          </div>
        </div>
        <hr class="my-3">
        <div class="row text-center g-2 mb-2">
          <div class="col-6 stat-block stat-mini">
            <div class="stat-label">GPS Status</div>
            <div class="stat-value" id="gpsStatus">🟡 Menunggu</div>
          </div>
          <div class="col-6 stat-block stat-mini">
            <div class="stat-label">GPS Accuracy</div>
            <div class="stat-value" id="gpsAcc">– m</div>
          </div>
        </div>
        <div id="runStatus" class="small text-muted text-center mt-2"></div>
        <div id="wakeStatus" class="small text-success text-center"></div>

        <div class="d-flex justify-content-center gap-2 mt-3 flex-wrap">
          <button id="btnStart"  class="strava-btn strava-btn-lg btn-start"><i class="bi bi-play-fill"></i> Mulai</button>
          <button id="btnPause"  class="strava-btn strava-btn-lg btn-pause" disabled><i class="bi bi-pause-fill"></i> Jeda</button>
          <button id="btnResume" class="strava-btn strava-btn-lg btn-resume d-none"><i class="bi bi-play-circle"></i> Lanjutkan</button>
          <button id="btnStop"   class="strava-btn strava-btn-lg btn-stop" disabled><i class="bi bi-stop-circle-fill"></i> Stop / Selesai</button>
        </div>
      </div>
    </div>

    <!-- Split KM -->
    <div class="strava-card mb-3">
      <div class="card-body">
        <h6 class="mb-2"><i class="bi bi-flag-fill text-warning"></i> Split per Kilometer</h6>
        <div id="splitList">
          <div class="text-muted small text-center py-2">Split akan muncul otomatis setiap 1 km.</div>
        </div>
      </div>
    </div>
  </div>

  <!-- ===== KOLOM KANAN: Riwayat ===== -->
  <div class="col-lg-4">
    <details class="strava-card history-strava" open>
      <summary class="card-body py-2 d-flex justify-content-between align-items-center" style="cursor:pointer;list-style:revert">
        <span><i class="bi bi-clock-history"></i> Riwayat Tracking</span>
        <small class="text-muted">GPX · KML · GeoJSON</small>
      </summary>
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
                <button type="button" class="btn btn-sm btn-link p-0 run-route-btn" data-id="<?= (int)$h['id'] ?>">
                  <i class="bi bi-map"></i> Lihat Rute
                </button>
                <div class="btn-group btn-group-sm">
                  <a class="btn btn-link p-0 me-2" href="/api_run.php?export=<?= (int)$h['id'] ?>&fmt=gpx"><i class="bi bi-download"></i> GPX</a>
                  <a class="btn btn-link p-0 me-2" href="/api_run.php?export=<?= (int)$h['id'] ?>&fmt=kml"><i class="bi bi-download"></i> KML</a>
                  <a class="btn btn-link p-0 me-2" href="/api_run.php?export=<?= (int)$h['id'] ?>&fmt=geojson"><i class="bi bi-download"></i> GeoJSON</a>
                </div>
                <button type="button" class="btn btn-sm btn-link text-danger p-0 run-del-btn" data-id="<?= (int)$h['id'] ?>">
                  <i class="bi bi-trash"></i> Hapus
                </button>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </details>
  </div>
</div>

<!-- Modal Riwayat Rute -->
<div class="modal fade" id="routeModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content" style="border-radius:18px;">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-map text-danger"></i> Riwayat Rute</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="routeInfo" class="small text-muted mb-2"></div>
        <div id="routeMap" style="height:380px;border-radius:14px;border:1px solid #e5e7eb"></div>
        <div id="routeEmpty" class="alert alert-info small mt-2 d-none mb-0">Tidak ada titik rute tersimpan untuk sesi ini.</div>
      </div>
    </div>
  </div>
</div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
window.MAPBOX_TOKEN_JS = 'pk.eyJ1IjoiYWRhbXNhc21pdGE1MzQiLCJhIjoiY21xZnRsbWxjMXZldDJ0cHlhN2Jycnd1dCJ9.2E00ey-sgX9jUmf5kIRoEA';
window.MAPBOX_TILE_URL = 'https://api.mapbox.com/styles/v1/mapbox/outdoors-v12/tiles/256/{z}/{x}/{y}@2x?access_token=' + MAPBOX_TOKEN_JS;
window.MAPBOX_ATTR = '&copy; <a href="https://www.mapbox.com/about/maps/">Mapbox</a> &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>';
</script>

<script>
/* =====================================================================
 * KawanKeringat — Strava-style Tracking (Revisi R33)
 * Modul terisolasi; hanya menyentuh elemen di section Tracking.
 * ===================================================================== */
(function(){
  'use strict';
  var csrf = '<?= csrf_token() ?>';
  var sessionId = <?= $active ? (int)$active['id'] : 'null' ?>;

  // ---- State ----
  var state = {
    startedAt: null, timerInt: null,
    pauseAt: null, pausedTotalMs: 0, paused: false,
    autoPaused: false, lastMoveAt: 0,
    totalM: 0, points: [], // {lat,lng,t,acc,spd,elev}
    kmSplits: [], // {km, sec, pace}
    curSpeed: 0, curElev: null, lastElev: null,
    userPanning: false,
    followUser: true,
    calories: 0
  };
  var LS_KEY = 'kk_run_state_v2';

  // Sport MET table
  var METS = {
    walk: 3.5, jog: 7.0, run: 9.8, bike: 8.0
  };

  // ---- Persistence ----
  function saveState(){
    try {
      localStorage.setItem(LS_KEY, JSON.stringify({
        sessionId: sessionId, startedAt: state.startedAt,
        totalM: state.totalM, pausedTotalMs: state.pausedTotalMs,
        paused: state.paused, points: state.points.slice(-1500),
        kmSplits: state.kmSplits, calories: state.calories,
        sport: document.getElementById('sportSel').value,
        weight: +document.getElementById('weightInp').value || 65,
        savedAt: Date.now()
      }));
    } catch(e){}
  }
  function loadState(){
    try { return JSON.parse(localStorage.getItem(LS_KEY) || 'null'); }
    catch(e){ return null; }
  }
  function clearState(){ try { localStorage.removeItem(LS_KEY); } catch(e){} }

  // ---- Map ----
  var map = L.map('runMap', { zoomControl: true }).setView([-6.2, 106.816666], 14);
  L.tileLayer(window.MAPBOX_TILE_URL, { maxZoom: 19, attribution: window.MAPBOX_ATTR }).addTo(map);

  // Multi-segment polyline (untuk auto-reconnect)
  var segments = [];
  function newSegment(){
    var poly = L.polyline([], { color:'#fc5200', weight:6, opacity:.95, lineCap:'round', lineJoin:'round' }).addTo(map);
    segments.push({ poly: poly, pts: [] });
    return segments[segments.length-1];
  }
  var seg = newSegment();
  var marker = null;
  var accCircle = null;

  function makeRunnerIcon(){
    return L.divIcon({
      className: 'kk-runner-icon',
      html: '<div style="width:22px;height:22px;border-radius:50%;background:#fc5200;'
          + 'border:3px solid #fff;box-shadow:0 0 0 3px rgba(252,82,0,.35),0 2px 6px rgba(0,0,0,.3);"></div>',
      iconSize: [22,22], iconAnchor: [11,11]
    });
  }

  // Deteksi user manual pan → matikan auto-follow
  map.on('dragstart', function(){
    state.userPanning = true;
    state.followUser = false;
    document.getElementById('btnRecenter').classList.add('show');
  });
  document.getElementById('btnRecenter').addEventListener('click', function(){
    state.followUser = true; state.userPanning = false;
    this.classList.remove('show');
    if (state.points.length){
      var p = state.points[state.points.length-1];
      map.setView([p.lat, p.lng], Math.max(map.getZoom(), 16), { animate:true });
    }
  });

  // ---- Utils ----
  function haversine(a,b){
    var R=6371000, toRad=Math.PI/180;
    var dLat=(b.lat-a.lat)*toRad, dLng=(b.lng-a.lng)*toRad;
    var s=Math.sin(dLat/2)**2 + Math.cos(a.lat*toRad)*Math.cos(b.lat*toRad)*Math.sin(dLng/2)**2;
    return 2*R*Math.asin(Math.sqrt(s));
  }
  function fmtTime(s){
    s = Math.max(0, s|0);
    var h = Math.floor(s/3600), m = Math.floor((s%3600)/60), ss = s%60;
    if (h) return h+':'+String(m).padStart(2,'0')+':'+String(ss).padStart(2,'0');
    return String(m).padStart(2,'0')+':'+String(ss).padStart(2,'0');
  }
  function fmtPace(secPerKm){
    if (!isFinite(secPerKm) || secPerKm <= 0 || secPerKm > 60*60) return "--'--\"";
    var m = Math.floor(secPerKm/60), s = Math.floor(secPerKm%60);
    return m+"'"+String(s).padStart(2,'0')+'"';
  }
  function elapsedSec(){
    if (!state.startedAt) return 0;
    var now = state.paused ? state.pauseAt : Date.now();
    return Math.floor((now - state.startedAt - state.pausedTotalMs)/1000);
  }

  // GPS accuracy classifier
  function gpsClass(acc){
    if (acc == null) return { emoji:'🟡', label:'Menunggu GPS', color:'#eab308' };
    if (acc < 5)   return { emoji:'🟢', label:'Sangat Akurat (±'+Math.round(acc)+'m)', color:'#22c55e' };
    if (acc < 10)  return { emoji:'🟢', label:'GPS Baik (±'+Math.round(acc)+'m)', color:'#22c55e' };
    if (acc < 20)  return { emoji:'🟡', label:'GPS Sedang (±'+Math.round(acc)+'m)', color:'#eab308' };
    return { emoji:'🔴', label:'GPS Buruk (±'+Math.round(acc)+'m)', color:'#ef4444' };
  }
  function setGpsChip(acc, lost){
    var el = document.getElementById('gpsChip');
    var st = document.getElementById('gpsStatus');
    var ac = document.getElementById('gpsAcc');
    if (lost){
      el.innerHTML = '🔴 GPS Hilang';
      st.textContent = '🔴 GPS Hilang';
      ac.textContent = '– m';
      return;
    }
    var g = gpsClass(acc);
    el.innerHTML = g.emoji+' '+g.label;
    st.textContent = g.emoji+' '+g.label.split(' (')[0];
    ac.textContent = acc==null?'– m':(Math.round(acc)+' m');
  }

  // MET-based calories
  function computeCalories(){
    var mets = METS[document.getElementById('sportSel').value] || 7;
    var w = +document.getElementById('weightInp').value || 65;
    // Adjust MET by current speed (m/s)
    var sMs = state.curSpeed;
    if (sMs > 0){
      var kmh = sMs*3.6;
      if (kmh < 4)      mets = Math.max(2.5, mets*0.5);
      else if (kmh < 6) mets = Math.max(3.5, mets*0.7);
      else if (kmh < 8) mets = mets*0.85;
      else if (kmh > 12) mets = mets*1.15;
    }
    var hours = elapsedSec()/3600;
    state.calories = Math.max(0, Math.round(mets * w * hours));
    return state.calories;
  }

  // Moving-avg pace (last N points)
  function movingPace(){
    var pts = state.points;
    if (pts.length < 2) return null;
    var slice = pts.slice(-30); // ~30 titik terakhir
    var dist = 0;
    for (var i=1; i<slice.length; i++) dist += haversine(slice[i-1], slice[i]);
    var t = (slice[slice.length-1].t - slice[0].t)/1000;
    if (dist < 20 || t <= 0) return null;
    return t / (dist/1000);
  }
  function avgPace(){
    var t = elapsedSec();
    if (state.totalM < 50 || t <= 0) return null;
    return t / (state.totalM/1000);
  }

  // ---- UI refresh ----
  function updateUI(){
    var km = state.totalM/1000;
    var t = elapsedSec();
    document.getElementById('bigDist').textContent = km.toFixed(2);
    document.getElementById('ovDist').innerHTML = km.toFixed(2)+' <small style="font-size:.7em">km</small>';
    document.getElementById('bigTime').textContent = fmtTime(t);
    document.getElementById('ovTime').textContent = fmtTime(t);

    var mp = movingPace();
    var pStr = fmtPace(mp);
    document.getElementById('bigPace').textContent = pStr;
    document.getElementById('ovPace').textContent = pStr;

    var ap = avgPace();
    document.getElementById('ovAvgPace').textContent = fmtPace(ap);

    var cs = state.curSpeed*3.6;
    document.getElementById('curSpd').textContent = cs.toFixed(1);
    document.getElementById('ovSpd').innerHTML = cs.toFixed(1)+' <small style="font-size:.7em">km/h</small>';
    var avgKmh = (state.totalM/1000) / (Math.max(1,t)/3600);
    document.getElementById('avgSpd').textContent = isFinite(avgKmh)?avgKmh.toFixed(1):'0.0';

    var el = state.curElev;
    document.getElementById('elevVal').textContent = (el==null)?'–':Math.round(el);
    document.getElementById('ovElev').textContent = (el==null)?'–':(Math.round(el)+' m');

    document.getElementById('calVal').textContent = computeCalories();
  }

  // ---- Split KM ----
  function checkSplit(){
    var kmDone = Math.floor(state.totalM/1000);
    while (state.kmSplits.length < kmDone){
      var idx = state.kmSplits.length; // 0-based km number to finish (idx+1)
      // Estimasi waktu split: cari titik pertama yg jaraknya >= (idx+1)*1000
      var target = (idx+1)*1000;
      var accM = 0, tStart = state.points.length ? state.points[0].t : state.startedAt;
      var tEnd = null;
      for (var i=1; i<state.points.length; i++){
        accM += haversine(state.points[i-1], state.points[i]);
        if (accM >= target){ tEnd = state.points[i].t; break; }
      }
      if (!tEnd) tEnd = Date.now();
      var prevT = idx===0 ? state.startedAt : (state.kmSplits[idx-1]._absEnd);
      var splitSec = Math.max(1, Math.round((tEnd - prevT)/1000));
      state.kmSplits.push({ km: idx+1, sec: splitSec, _absEnd: tEnd });
    }
    renderSplits();
  }
  function renderSplits(){
    var host = document.getElementById('splitList');
    if (!state.kmSplits.length){
      host.innerHTML = '<div class="text-muted small text-center py-2">Split akan muncul otomatis setiap 1 km.</div>';
      return;
    }
    var maxSec = Math.max.apply(null, state.kmSplits.map(function(s){return s.sec;}));
    host.innerHTML = state.kmSplits.map(function(s){
      var w = Math.min(100, Math.round(s.sec/maxSec*100));
      return '<div class="split-item">'
        + '<div class="split-km">KM '+s.km+'</div>'
        + '<div class="split-bar"><i style="width:'+w+'%"></i></div>'
        + '<div class="split-pace">'+fmtTime(s.sec)+'</div>'
        + '</div>';
    }).join('');
  }

  // ---- Wake Lock ----
  var wakeLock = null;
  async function acquireWakeLock(){
    try {
      if ('wakeLock' in navigator){
        wakeLock = await navigator.wakeLock.request('screen');
        document.getElementById('wakeStatus').textContent = '🔒 Wake Lock aktif — layar dijaga tetap menyala';
        wakeLock.addEventListener('release', function(){
          document.getElementById('wakeStatus').textContent = '⚠️ Wake Lock terlepas';
        });
      } else {
        document.getElementById('wakeStatus').textContent = 'ℹ️ Browser tidak mendukung Wake Lock';
      }
    } catch(e){ document.getElementById('wakeStatus').textContent = 'Wake Lock gagal: '+e.message; }
  }
  function releaseWakeLock(){ try{ if(wakeLock){ wakeLock.release(); wakeLock=null; } }catch(e){} }

  // ---- Capacitor Background Geolocation (opsional saat APK) ----
  var isNative = !!(window.Capacitor && window.Capacitor.isNativePlatform && window.Capacitor.isNativePlatform());
  var bgWatcherId = null;
  async function startBackgroundGeoloc(){
    if (!isNative) return false;
    try {
      // Plugin @capacitor-community/background-geolocation
      var BG = (window.Capacitor && window.Capacitor.Plugins && window.Capacitor.Plugins.BackgroundGeolocation) || null;
      if (!BG) {
        console.warn('[BG] plugin belum diinstall di APK — fallback ke watchPosition biasa.');
        return false;
      }
      bgWatcherId = await BG.addWatcher({
        backgroundMessage: 'KawanKeringat sedang merekam GPS…',
        backgroundTitle: '🏃 Tracking aktif',
        requestPermissions: true,
        stale: false,
        distanceFilter: 3
      }, function(location, error){
        if (error){
          setGpsChip(null, true);
          return;
        }
        handlePosition({
          coords:{ latitude: location.latitude, longitude: location.longitude,
                   accuracy: location.accuracy, speed: location.speed, altitude: location.altitude },
          timestamp: location.time || Date.now()
        });
      });
      document.getElementById('wakeStatus').textContent = '📡 Background Geolocation aktif (APK) — GPS terus berjalan walau layar mati';
      return true;
    } catch(e){ console.warn('[BG] gagal:', e); return false; }
  }
  async function stopBackgroundGeoloc(){
    try {
      var BG = (window.Capacitor && window.Capacitor.Plugins && window.Capacitor.Plugins.BackgroundGeolocation) || null;
      if (BG && bgWatcherId) await BG.removeWatcher({ id: bgWatcherId });
    } catch(e){}
    bgWatcherId = null;
  }

  // ---- watchPosition (browser + fallback) ----
  var watchId = null;
  function startWatch(){
    if (watchId !== null) return;
    watchId = navigator.geolocation.watchPosition(handlePosition, function(e){
      setGpsChip(null, true);
      document.getElementById('runStatus').textContent = 'Error GPS: '+e.message;
    }, { enableHighAccuracy:true, maximumAge:0, timeout:10000 });
  }
  function stopWatch(){
    if (watchId !== null){ navigator.geolocation.clearWatch(watchId); watchId=null; }
  }

  // ---- Adaptive throttling ----
  var lastAcceptedAt = 0;
  function adaptiveMinInterval(){
    var kmh = state.curSpeed*3.6;
    if (kmh < 1)      return 5000; // diam
    if (kmh < 6)      return 2000; // jalan
    return 1000;                   // lari
  }

  // ---- Handle position ----
  function handlePosition(pos){
    if (state.paused && !state.autoPaused) return;

    var nowT = pos.timestamp || Date.now();
    var acc = pos.coords.accuracy;
    var p = {
      lat: pos.coords.latitude, lng: pos.coords.longitude,
      acc: acc, spd: pos.coords.speed, elev: pos.coords.altitude,
      t: nowT
    };

    // Live accuracy chip
    setGpsChip(acc, false);
    if (accCircle){ accCircle.setLatLng([p.lat,p.lng]).setRadius(acc||10); }
    else { accCircle = L.circle([p.lat,p.lng], { radius: acc||10, color:'#fc5200', weight:1, opacity:.5, fillOpacity:.1 }).addTo(map); }

    // First point: allow up to 100 m accuracy
    if (state.points.length === 0){
      if (acc && acc > 100){
        document.getElementById('runStatus').textContent = 'Menunggu fix GPS akurat… (±'+Math.round(acc)+' m)';
        return;
      }
      _acceptFirst(p); return;
    }

    // Subsequent filters
    if (acc != null && acc > 30){
      document.getElementById('runStatus').textContent = 'Akurasi rendah (±'+Math.round(acc)+' m) — titik diabaikan';
      return;
    }

    var last = state.points[state.points.length-1];
    var d = haversine(last, p);
    var dt = Math.max(0.001, (nowT - last.t)/1000);
    var speed = d/dt;

    // Adaptive throttle by time
    if (nowT - lastAcceptedAt < adaptiveMinInterval() && d < 30){
      // update marker halus tanpa menyimpan
      if (marker) marker.setLatLng([p.lat, p.lng]);
      return;
    }

    // Segment break (GPS lost, screen off, etc.)
    if (d > 150 || dt > 25){
      document.getElementById('runStatus').textContent = 'Gap GPS ('+Math.round(d)+' m / '+Math.round(dt)+' dtk) — segmen baru, jarak tidak ditambah.';
      seg = newSegment();
      state.points.push(p);
      seg.poly.addLatLng([p.lat,p.lng]); seg.pts.push([p.lat,p.lng]);
      _placeMarker(p);
      lastAcceptedAt = nowT;
      state.lastMoveAt = nowT; // jangan langsung auto-pause tepat setelah reconnect
      // Auto-resume kalau sempat auto-paused saat GPS hilang
      if (state.autoPaused){
        state.pausedTotalMs += (nowT - state.pauseAt);
        state.autoPaused = false; state.paused = false; state.pauseAt = null;
      }
      afterPoint(p, /*sendDist*/false);
      return;
    }

    // Filters
    if (speed > 12){ // >43 km/h — implausible
      document.getElementById('runStatus').textContent = 'Kecepatan tak realistis ('+(speed*3.6).toFixed(1)+' km/h) — diabaikan';
      return;
    }
    if (d < 3){
      // Diam / drift — jangan tambah jarak, tetap update marker
      if (marker) marker.setLatLng([p.lat,p.lng]);
      // Auto-pause detect (Strava-like: >6 dtk tanpa gerakan berarti)
      if (!state.autoPaused && !state.paused && state.lastMoveAt && (nowT - state.lastMoveAt) > 6000){
        state.autoPaused = true; state.paused = true; state.pauseAt = nowT;
        document.getElementById('runStatus').textContent = '⏸ Auto-pause — mulai bergerak untuk melanjutkan';
      }
      return;
    }

    // Auto-resume from auto-pause
    if (state.autoPaused){
      state.pausedTotalMs += (nowT - state.pauseAt);
      state.autoPaused = false; state.paused = false; state.pauseAt = null;
      document.getElementById('runStatus').textContent = '▶ Auto-resume — Anda kembali bergerak';
    }

    // Smoothing: EMA on curSpeed
    state.curSpeed = state.curSpeed*0.7 + (pos.coords.speed && pos.coords.speed>=0 ? pos.coords.speed : speed)*0.3;
    if (p.elev != null){ state.curElev = state.curElev==null ? p.elev : (state.curElev*0.7 + p.elev*0.3); }
    state.lastMoveAt = nowT;

    state.totalM += d;
    state.points.push(p);
    seg.poly.addLatLng([p.lat,p.lng]); seg.pts.push([p.lat,p.lng]);
    _placeMarker(p);
    lastAcceptedAt = nowT;
    afterPoint(p, true);
  }

  function _acceptFirst(p){
    state.points.push(p);
    seg.poly.addLatLng([p.lat,p.lng]); seg.pts.push([p.lat,p.lng]);
    _placeMarker(p, true);
    state.curSpeed = 0;
    state.curElev = p.elev != null ? p.elev : null;
    state.lastMoveAt = p.t;
    lastAcceptedAt = p.t;
    afterPoint(p, true);
    document.getElementById('runStatus').textContent = 'GPS siap. Mulai bergerak — track akan tercatat otomatis.';
  }
  function _placeMarker(p, firstFix){
    if (!marker){ marker = L.marker([p.lat,p.lng], { icon: makeRunnerIcon() }).addTo(map); }
    else marker.setLatLng([p.lat,p.lng]);
    if (state.followUser){
      if (firstFix){
        map.setView([p.lat,p.lng], Math.max(map.getZoom(), 16), { animate:true });
      } else {
        // Halus & tidak reset zoom (Strava behavior)
        map.panTo([p.lat,p.lng], { animate:true, duration:0.9, easeLinearity:0.5 });
      }
    }
  }
  function afterPoint(p, sendDist){
    checkSplit();
    if (sessionId){ sendPointToServer(p); }
    updateUI(); saveState();
  }

  // ---- Server sync (buffered) ----
  var pointBuffer = [];
  function sendPointToServer(p){
    pointBuffer.push({ lat:p.lat, lng:p.lng, acc:p.acc, spd:p.spd||'', total_m:state.totalM });
    flushPointBuffer();
  }
  async function flushPointBuffer(){
    if (!sessionId || !pointBuffer.length) return;
    while (pointBuffer.length){
      var pl = pointBuffer[0];
      var fd = new FormData();
      fd.append('csrf',csrf); fd.append('_action','point'); fd.append('session_id',sessionId);
      fd.append('lat',pl.lat); fd.append('lng',pl.lng); fd.append('acc',pl.acc);
      fd.append('spd',pl.spd); fd.append('total_m',pl.total_m);
      try {
        var r = await fetch('/api_run.php', { method:'POST', body:fd, keepalive:true });
        if (!r.ok) return;
        pointBuffer.shift();
      } catch(e){ return; }
    }
  }
  setInterval(flushPointBuffer, 5000);

  // ---- Buttons ----
  document.getElementById('btnStart').addEventListener('click', async function(){
    if (!navigator.geolocation){ alert('Browser tidak mendukung GPS'); return; }
    var fd = new FormData(); fd.append('csrf',csrf); fd.append('_action','start');
    var r = await fetch('/api_run.php',{method:'POST',body:fd}); var d = await r.json();
    if (!d.ok){ alert('Gagal mulai sesi'); return; }
    sessionId = d.id;
    state.startedAt = Date.now();
    state.totalM = 0; state.points = []; state.kmSplits = [];
    state.pausedTotalMs = 0; state.paused = false; state.autoPaused = false;
    state.curSpeed = 0; state.curElev = null; state.lastMoveAt = 0;
    // reset polyline
    segments.forEach(function(s){ map.removeLayer(s.poly); });
    segments = []; seg = newSegment();
    if (marker){ map.removeLayer(marker); marker = null; }

    document.getElementById('btnStart').disabled = true;
    document.getElementById('btnPause').disabled = false;
    document.getElementById('btnStop').disabled = false;
    document.getElementById('runStatus').textContent = '▶ Mencari GPS…';
    state.timerInt = setInterval(function(){ updateUI(); checkSplit(); }, 1000);

    var bgOk = await startBackgroundGeoloc();
    if (!bgOk) startWatch();
    acquireWakeLock();
    saveState();
    updateUI();
  });

  document.getElementById('btnPause').addEventListener('click', function(){
    if (!sessionId || state.paused) return;
    state.paused = true; state.pauseAt = Date.now();
    document.getElementById('btnPause').classList.add('d-none');
    document.getElementById('btnResume').classList.remove('d-none');
    document.getElementById('runStatus').textContent = '⏸ Tracking dijeda';
    saveState();
  });
  document.getElementById('btnResume').addEventListener('click', function(){
    if (!sessionId || !state.paused) return;
    state.pausedTotalMs += (Date.now() - state.pauseAt);
    state.paused = false; state.pauseAt = null; state.autoPaused = false;
    document.getElementById('btnResume').classList.add('d-none');
    document.getElementById('btnPause').classList.remove('d-none');
    document.getElementById('runStatus').textContent = '▶ Tracking dilanjutkan';
    saveState();
  });

  document.getElementById('btnStop').addEventListener('click', async function(){
    if (!confirm('Selesaikan sesi lari sekarang?')) return;
    stopWatch(); await stopBackgroundGeoloc();
    clearInterval(state.timerInt);
    releaseWakeLock();
    if (!sessionId){ return; }
    var dur = elapsedSec();
    var fd = new FormData();
    fd.append('csrf',csrf); fd.append('_action','stop');
    fd.append('session_id',sessionId);
    fd.append('total_m',state.totalM); fd.append('durasi',dur);
    await fetch('/api_run.php',{method:'POST',body:fd});
    clearState();
    location.reload();
  });

  // ---- Auto-resume kalau ada sesi aktif ----
  function autoResumeIfActive(){
    if (!sessionId) return;
    var st = loadState();
    if (st && st.sessionId === sessionId){
      state.startedAt = st.startedAt || Date.now();
      state.totalM = +st.totalM || 0;
      state.points = Array.isArray(st.points) ? st.points : [];
      state.pausedTotalMs = +st.pausedTotalMs || 0;
      state.paused = !!st.paused;
      state.kmSplits = Array.isArray(st.kmSplits) ? st.kmSplits : [];
      if (st.sport) document.getElementById('sportSel').value = st.sport;
      if (st.weight) document.getElementById('weightInp').value = st.weight;
      if (state.points.length){
        state.points.forEach(function(p){ seg.poly.addLatLng([p.lat,p.lng]); seg.pts.push([p.lat,p.lng]); });
        var last = state.points[state.points.length-1];
        marker = L.marker([last.lat,last.lng], { icon: makeRunnerIcon() }).addTo(map);
        map.setView([last.lat,last.lng], 16);
      }
    } else {
      state.startedAt = Date.now();
    }
    document.getElementById('btnStart').disabled = true;
    document.getElementById('btnPause').disabled = false;
    document.getElementById('btnStop').disabled = false;
    document.getElementById('runStatus').textContent = '▶ Sesi aktif dilanjutkan otomatis';
    state.timerInt = setInterval(function(){ updateUI(); checkSplit(); }, 1000);
    startBackgroundGeoloc().then(function(ok){ if (!ok) startWatch(); });
    acquireWakeLock();
    updateUI(); renderSplits();
  }

  // ---- Re-fresh GPS saat balik dari background ----
  document.addEventListener('visibilitychange', async function(){
    if (document.visibilityState === 'visible' && (watchId !== null || bgWatcherId != null)){
      if (!isNative){
        stopWatch(); startWatch();
      }
      await acquireWakeLock();
    }
  });

  // ---- Show background warning kalau bukan native ----
  if (!isNative){ document.getElementById('bgWarn').classList.remove('d-none'); }

  // ---- Init ----
  autoResumeIfActive();
  if ('serviceWorker' in navigator){ navigator.serviceWorker.register('/service-worker.js').catch(function(){}); }
})();

// ---- Hapus riwayat ----
document.addEventListener('click', function(ev){
  var b = ev.target.closest('.run-del-btn'); if(!b) return;
  if (!confirm('Hapus riwayat lari ini? Tindakan tidak dapat dibatalkan.')) return;
  var id = b.getAttribute('data-id');
  var fd = new FormData(); fd.append('csrf','<?= csrf_token() ?>'); fd.append('_action','delete'); fd.append('session_id', id);
  b.disabled = true;
  fetch('/api_run.php',{method:'POST',body:fd}).then(r=>r.json()).then(function(d){
    if (d && d.ok){ var row = document.getElementById('run-row-'+id); if (row) row.remove(); }
    else { alert('Gagal menghapus.'); b.disabled = false; }
  }).catch(function(){ alert('Gagal menghapus.'); b.disabled = false; });
});

// ---- Lihat riwayat rute ----
var routeModal=null, routeMapObj=null;
document.addEventListener('click', function(ev){
  var b = ev.target.closest('.run-route-btn'); if(!b) return;
  var id = b.getAttribute('data-id');
  if (!routeModal) routeModal = new bootstrap.Modal(document.getElementById('routeModal'));
  fetch('/api_run.php?route='+id).then(r=>r.json()).then(function(d){
    if (!d.ok){ alert('Gagal memuat rute.'); return; }
    document.getElementById('routeEmpty').classList.toggle('d-none', d.points.length>0);
    document.getElementById('routeInfo').textContent =
      'Jarak: '+(Math.round(((+d.session.jarak_m)/1000)*100)/100)+' km · Durasi: '+
      String(Math.floor((+d.session.durasi_dtk)/60)).padStart(2,'0')+':'+String((+d.session.durasi_dtk)%60).padStart(2,'0')+
      ' · Kalori: '+(+d.session.kalori);
    routeModal.show();
    setTimeout(function(){
      if (!routeMapObj){
        routeMapObj = L.map('routeMap').setView([-6.2,106.8],13);
        L.tileLayer(window.MAPBOX_TILE_URL,{maxZoom:19,attribution:window.MAPBOX_ATTR}).addTo(routeMapObj);
      }
      routeMapObj.eachLayer(function(l){ if(l instanceof L.Polyline || l instanceof L.Marker) routeMapObj.removeLayer(l); });
      if (d.points.length){
        var ln = L.polyline(d.points,{color:'#fc5200',weight:5}).addTo(routeMapObj);
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
<details id="eksplorasi" class="mb-3">
  <summary style="cursor:pointer;list-style:revert">
    <h4 class="d-inline-block mb-0"><i class="bi bi-compass text-primary"></i> Eksplorasi Rute &amp; Peta Canggih</h4>
    <span class="text-muted small ms-1">(klik untuk buka/tutup)</span>
  </summary>

<!-- Revisi 22 Juni 2026 R12 — Panel info dibungkus <details> (spoiler) -->
<details class="card border-0 shadow-sm mb-3 border-start border-4 border-primary">
  <summary class="card-body py-2" style="cursor:pointer;list-style:revert">
    <strong><i class="bi bi-info-circle text-primary"></i> Cara Penggunaan Eksplorasi Rute &amp; Peta Canggih</strong>
    <span class="text-muted small">(klik untuk buka/tutup)</span>
  </summary>
  <div class="card-body py-3 pt-0">
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
</details>

<ul class="nav nav-tabs" id="advTab" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link active" id="tab-builder-btn" data-bs-toggle="tab" data-bs-target="#tab-builder" type="button"><i class="bi bi-magic"></i> Route Builder</button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="tab-heatmap-btn" data-bs-toggle="tab" data-bs-target="#tab-heatmap" type="button"><i class="bi bi-fire"></i> Heatmaps</button>
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
          <label class="btn btn-outline-success" for="rbModeAI"><i class="bi bi-robot"></i> Buat Rute oleh AI</label>
        </div>

        <!-- ===== Panel AI Import Rute — Revisi 19 Juni 2026: HANYA via prompt teks ===== -->
        <div id="rbAIPanel" class="border rounded p-2 mb-2 bg-success-subtle" style="display:none">
          <div class="small fw-bold mb-1"><i class="bi bi-robot text-success"></i> Buat Rute oleh AI</div>
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


        <div id="rbManualBox" class="d-none alert alert-info small py-2 mb-2">
          <b><i class="bi bi-hand-index"></i> Mode Manual</b> &mdash; klik peta untuk menambahkan titik (mulai, transit, finish).
          <div class="d-flex flex-wrap gap-1 mt-2">
            <button type="button" id="rbManSnap"  class="btn btn-sm btn-primary"><i class="bi bi-magnet"></i> Snap ke jalan</button>
            <button type="button" id="rbManUndo"  class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-counterclockwise"></i> Hapus titik terakhir</button>
            <button type="button" id="rbManClear" class="btn btn-sm btn-outline-danger"><i class="bi bi-x-circle"></i> Reset</button>
          </div>
          <div class="small text-muted mt-1" id="rbManInfo">0 titik dipilih.</div>
        </div>

        <label class="form-label small">Titik mulai <span class="badge bg-success-subtle text-success">Revisi #3</span></label>
        <div class="input-group input-group-sm mb-1">
          <input id="rbStart" class="form-control" placeholder="lat,lng atau kosongkan = lokasi sekarang">
          <button class="btn btn-outline-secondary" id="rbUseMe" type="button" title="Gunakan lokasi saya"><i class="bi bi-geo-alt"></i></button>
          <button class="btn btn-outline-primary" id="rbPickOnMap" type="button" title="Klik di peta untuk pilih titik mulai"><i class="bi bi-hand-index-thumb"></i> Pilih di Peta</button>
        </div>
        <div class="input-group input-group-sm mb-2">
          <input id="rbAddrSearch" class="form-control" placeholder="atau cari alamat/landmark (cth: GBK Senayan)">
          <button class="btn btn-outline-success" id="rbAddrGo" type="button" title="Cari & jadikan titik mulai"><i class="bi bi-search"></i></button>
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
          <option value="loop">Loop / Melingkar (rute lingkaran kembali ke start)</option>
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

        <!-- Revisi Juli 2026 — Import Rute dari Gambar dibungkus spoiler agar tidak memanjang -->
        <details class="card border-warning-subtle mt-3">
          <summary class="card-header py-2 bg-warning-subtle" style="cursor:pointer;list-style:revert">
            <strong class="small"><i class="bi bi-image text-warning"></i> Import Rute dari Gambar (screenshot Strava)</strong>
            <span class="small text-muted ms-1">— klik untuk buka/tutup</span>
          </summary>
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
        </details>

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
                  <button class="btn btn-link btn-sm p-0 me-2 rb-load" data-rid="<?= (int)$r['id'] ?>" title="Lihat"><i class="bi bi-eye"></i></button>
                  <button class="btn btn-link btn-sm p-0 me-2 rb-edit"
                    data-rid="<?= (int)$r['id'] ?>"
                    data-nama="<?= htmlspecialchars($r['nama']) ?>"
                    data-elev="<?= htmlspecialchars($r['elevasi_pref']) ?>"
                    data-surf="<?= htmlspecialchars($r['surface_pref']) ?>"
                    data-pub="<?= ($r['is_public']==='t'||$r['is_public']===true||$r['is_public']==='1')?'1':'0' ?>"
                    title="Edit"><i class="bi bi-pencil"></i></button>
                  <button class="btn btn-link btn-sm p-0 text-danger rb-del" data-rid="<?= (int)$r['id'] ?>" title="Hapus"><i class="bi bi-trash"></i></button>
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

  <!-- Tab Peta Offline dihapus (Revisi Juli 2026) -->
  <div id="tab-offline" class="d-none" role="tabpanel" aria-hidden="true">
    <select id="offRouteSel" class="d-none"></select>
    <select id="offSessSel" class="d-none"></select>
    <select id="offZoom" class="d-none"><option value="14" selected>14</option></select>
    <button id="offDownload" type="button" class="d-none" disabled></button>
    <button id="offClear" type="button" class="d-none"></button>
    <div id="offProg" class="d-none"></div>
    <div id="offMap" class="d-none"></div>
  </div>
</div>
</details>
<script>
// Auto-buka spoiler Eksplorasi jika hash #eksplorasi
(function(){
  var d = document.getElementById('eksplorasi');
  function openIfHash(){ if (d && location.hash === '#eksplorasi') { d.open = true; d.scrollIntoView({behavior:'smooth'}); } }
  window.addEventListener('hashchange', openIfHash); openIfHash();
})();
</script>

<script src="https://unpkg.com/leaflet.heat@0.2.0/dist/leaflet-heat.js"></script>
<script>
(function(){
  var CSRF = '<?= csrf_token() ?>';
  var OSRM = 'https://router.project-osrm.org/route/v1/foot/';
  var TILE = window.MAPBOX_TILE_URL;
  var CACHE_NAME = 'hf-tiles-v1';
  /* Revisi 19 Juni 2026 — Ikon pelari pakai foto profil (Leaflet divIcon). */
  var USER_PHOTO_URL = <?= json_encode($userPhoto) ?>;
  function makeRunnerIcon(){
    return L.divIcon({
      className: 'run-user-icon',
      html: '<div style="width:40px;height:40px;border-radius:50%;border:3px solid #3b82f6;'
          + 'box-shadow:0 4px 10px rgba(0,0,0,.3);background:#fff center/cover no-repeat;'
          + 'background-image:url('+JSON.stringify(USER_PHOTO_URL)+')"></div>',
      iconSize:[40,40], iconAnchor:[20,20]
    });
  }

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

  // ============================================================
  // Revisi 18 Juni 2026 — Turn Markers + Street View Popup
  // Setiap belokan tajam (>= ~40°) di polyline rute diberi marker oranye
  // yang bisa diklik → popup dengan tombol Google Street View, Mapillary,
  // dan thumbnail OpenStreetMap pada titik tersebut.
  // ============================================================
  var bTurnMarkers = [];
  function clearTurnMarkers(){
    bTurnMarkers.forEach(function(m){ try{ bMap.removeLayer(m); }catch(e){} });
    bTurnMarkers = [];
  }
  function _bearing(a,b){
    var la1=a[0]*Math.PI/180, la2=b[0]*Math.PI/180;
    var dLo=(b[1]-a[1])*Math.PI/180;
    var y=Math.sin(dLo)*Math.cos(la2);
    var x=Math.cos(la1)*Math.sin(la2)-Math.sin(la1)*Math.cos(la2)*Math.cos(dLo);
    return (Math.atan2(y,x)*180/Math.PI+360)%360;
  }
  function _hav(a,b){
    var R=6371000, la1=a[0]*Math.PI/180, la2=b[0]*Math.PI/180;
    var dLa=(b[0]-a[0])*Math.PI/180, dLo=(b[1]-a[1])*Math.PI/180;
    var s=Math.sin(dLa/2)**2+Math.cos(la1)*Math.cos(la2)*Math.sin(dLo/2)**2;
    return 2*R*Math.asin(Math.sqrt(s));
  }
  function detectTurns(coords, minAngle, minGapM){
    minAngle = minAngle || 40; minGapM = minGapM || 80;
    var turns=[]; var lastIdx=-1;
    for (var i=1; i<coords.length-1; i++){
      var b1=_bearing(coords[i-1],coords[i]);
      var b2=_bearing(coords[i],coords[i+1]);
      var diff=Math.abs(((b2-b1)+540)%360-180); // 0..180
      if (diff>=minAngle){
        if (lastIdx<0 || _hav(coords[lastIdx],coords[i])>=minGapM){
          turns.push({idx:i, latlng:coords[i], angle:Math.round(diff), heading:Math.round(b2)});
          lastIdx=i;
        }
      }
    }
    return turns;
  }
  function turnPopupHtml(t){
    var lat=t.latlng[0].toFixed(6), lng=t.latlng[1].toFixed(6);
    var gsv = 'https://www.google.com/maps?q=&layer=c&cbll='+lat+','+lng+'&cbp=11,'+t.heading+',0,0,0';
    var gmap= 'https://www.google.com/maps/@?api=1&map_action=pano&viewpoint='+lat+','+lng+'&heading='+t.heading;
    var mpl = 'https://www.mapillary.com/app/?lat='+lat+'&lng='+lng+'&z=18&focus=photo';
    // OSM static tile preview (tile.openstreetmap.org)
    var z=17;
    var n=Math.pow(2,z);
    var xt=Math.floor((t.latlng[1]+180)/360*n);
    var ylat=t.latlng[0]*Math.PI/180;
    var yt=Math.floor((1-Math.log(Math.tan(ylat)+1/Math.cos(ylat))/Math.PI)/2*n);
    var osmTile='https://tile.openstreetmap.org/'+z+'/'+xt+'/'+yt+'.png';
    return ''+
      '<div style="min-width:230px">'+
      '<div style="font-weight:600;margin-bottom:.25rem">'+
        '<i class="bi bi-signpost-split text-warning"></i> Belokan ~'+t.angle+'° (arah '+t.heading+'°)'+
      '</div>'+
      '<img src="'+osmTile+'" alt="peta" style="width:100%;height:120px;object-fit:cover;border-radius:6px;border:1px solid #ddd" onerror="this.style.display=\'none\'">'+
      '<div class="small text-muted mt-1">'+lat+', '+lng+'</div>'+
      '<div class="d-grid gap-1 mt-2">'+
        '<a class="btn btn-sm btn-primary" target="_blank" rel="noopener" href="'+gsv+'"><i class="bi bi-geo-alt"></i> Google Street View</a>'+
        '<a class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener" href="'+gmap+'"><i class="bi bi-google"></i> Buka di Google Maps</a>'+
        '<a class="btn btn-sm btn-outline-warning" target="_blank" rel="noopener" href="'+mpl+'"><i class="bi bi-camera"></i> Mapillary (foto jalan)</a>'+
      '</div>'+
      '<div class="small text-muted mt-1" style="font-size:.7rem">Foto jalan diambil dari layanan publik Street View / Mapillary.</div>'+
      '</div>';
  }
  function addTurnMarkers(coords){
    if (!bMap || !coords || coords.length<3) return;
    clearTurnMarkers();
    var turns = detectTurns(coords, 40, 80);
    turns.forEach(function(t){
      var ic = L.divIcon({
        className:'rb-turn-icon',
        html:'<div title="Belokan — klik untuk lihat foto" style="background:#f59e0b;border:2px solid #fff;border-radius:50%;width:16px;height:16px;box-shadow:0 0 0 1px #b45309,0 1px 4px rgba(0,0,0,.4);cursor:pointer"></div>',
        iconSize:[16,16], iconAnchor:[8,8]
      });
      var m = L.marker(t.latlng,{icon:ic, zIndexOffset:500}).addTo(bMap);
      m.bindPopup(turnPopupHtml(t), {maxWidth:280});
      bTurnMarkers.push(m);
    });
    if (turns.length){
      var info = document.getElementById('rbInfo');
      if (info){
        var extra = document.createElement('div');
        extra.className='small text-muted mt-1';
        extra.innerHTML='<i class="bi bi-signpost-split text-warning"></i> '+turns.length+' belokan terdeteksi — klik marker oranye di peta untuk lihat foto Street View.';
        info.appendChild(extra);
      }
    }
  }
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
    clearTurnMarkers();
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
      // Revisi 18 Juni 2026 — Loop = LINGKARAN (circular).
      // Sebar 8 waypoint mengelilingi pusat lingkaran sehingga OSRM
      // membentuk rute melingkar (bukan segitiga seperti versi lama).
      // Pusat lingkaran digeser ke depan (searah bearing) sejauh jari-jari,
      // dan jari-jari ≈ totalM/(2π) agar keliling ≈ jarak target.
      var R = totalM / (2 * Math.PI);
      var center = destPoint(lat, lng, R, bearing);
      var nPts = 8;
      // Mulai dari titik mulai → kelilingi → kembali ke titik mulai.
      for (var i = 1; i <= nPts; i++){
        var ang = bearing + 180 + (i * (360 / nPts));
        var p = destPoint(center.lat, center.lng, R, ang);
        waypoints.push([p.lng, p.lat]);
      }
      waypoints.push([lng, lat]); // tutup loop kembali ke start
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
    bMap.fitBounds(bLine.getBounds(),{padding:[20,20]}); addTurnMarkers(bLine.getLatLngs().map(function(p){return [p.lat,p.lng];}));
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
        fd.append('csrf', CSRF); // Revisi 16 Juni 2026 — perbaiki "csrf is not defined" (scope IIFE)
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
        bMap.fitBounds(bLine.getBounds(), {padding:[40,40]}); addTurnMarkers(bLine.getLatLngs().map(function(p){return [p.lat,p.lng];}));
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
      stat.innerHTML = '<span class="spinner-border spinner-border-sm"></span> AI sedang menyusun rute…';
      btnAIPrompt.disabled = true;
      try {
        var fd = new FormData();
        fd.append('csrf', CSRF); // Revisi 16 Juni 2026 — perbaiki "csrf is not defined" (scope IIFE)
        fd.append('task', 'ai_route_prompt');
        fd.append('prompt', prompt);
        var r = await fetch('/api_ai.php', { method:'POST', body: fd, credentials:'same-origin' });
        var d = await r.json();
        if (!d.ok) { stat.textContent = 'Gagal: '+(d.err||'?'); return; }
        if (!d.coords || d.coords.length < 2) { stat.textContent = 'Hasil rute terlalu sedikit.'; return; }
        ensureBuilderMap();
        if (bLine) { bMap.removeLayer(bLine); }
        bLine = L.polyline(d.coords, {color:'#16a34a', weight:5}).addTo(bMap);
        bMap.fitBounds(bLine.getBounds(), {padding:[40,40]}); addTurnMarkers(bLine.getLatLngs().map(function(p){return [p.lat,p.lng];}));
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
      bMap.fitBounds(bLine.getBounds(),{padding:[20,20]}); addTurnMarkers(bLine.getLatLngs().map(function(p){return [p.lat,p.lng];}));
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
      // Revisi 19 Juni 2026 Part R — tampilkan spinner kecil di tombol mata saat memuat
      var icon = ld.querySelector('i');
      var origCls = icon ? icon.className : '';
      if (icon){ icon.className = ''; icon.innerHTML = ''; }
      var prevHtml = ld.innerHTML;
      ld.innerHTML = '<span class="spinner-border spinner-border-sm" style="width:.85rem;height:.85rem"></span>';
      ld.disabled = true;
      try {
        var r = await fetch('/api_run.php?route_load='+ld.dataset.rid);
        var d = await r.json();
        if (!d.ok){ alert('Gagal memuat rute'); return; }
        // Revisi 19 Juni 2026 Part O (#1) — tampilkan rute dalam popup map
        showRouteInPopup(d);
        try {
          ensureBuilderMap(); clearBuilder();
          bLine = L.polyline(d.coords,{color:'#16a34a',weight:5}).addTo(bMap);
          bMap.fitBounds(bLine.getBounds(),{padding:[20,20]});
          addTurnMarkers(bLine.getLatLngs().map(function(p){return [p.lat,p.lng];}));
          bCurrentRoute = { coords: d.coords, jarak_m: d.jarak_m };
          document.getElementById('rbInfo').textContent = '✓ Memuat rute tersimpan: '+(d.jarak_m/1000).toFixed(2)+' km. Lihat detail lengkapnya di peta pembuatan rute.';
          document.getElementById('rbExport').disabled = false;
        } catch(_){}
      } catch(e){
        alert('Gagal memuat rute: '+(e.message||e));
      } finally {
        ld.innerHTML = prevHtml; ld.disabled = false;
      }
      return;
    }
    // Revisi 17 Juni 2026 Part I (#4) — Edit rute tersimpan
    var ed = ev.target.closest('.rb-edit');
    if (ed){
      document.getElementById('reId').value   = ed.dataset.rid;
      document.getElementById('reNama').value = ed.dataset.nama || '';
      document.getElementById('reElev').value = ed.dataset.elev || 'apa-saja';
      document.getElementById('reSurf').value = ed.dataset.surf || 'apa-saja';
      document.getElementById('rePub').checked = (ed.dataset.pub === '1');
      var m = new bootstrap.Modal(document.getElementById('routeEditModal'));
      m.show();
    }
  });

  // Submit modal edit rute — Revisi 17 Juni 2026 Part J
  // Fix: pakai handler robust + tombol fallback agar "Simpan Perubahan" pasti tersimpan.
  async function submitRouteEdit(){
    var btn = document.querySelector('#routeEditForm button[type=submit]');
    var orig = btn ? btn.innerHTML : '';
    if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Menyimpan…'; }
    try {
      var fd = new FormData();
      fd.append('csrf', CSRF);
      fd.append('_action', 'route_update');
      fd.append('id',           document.getElementById('reId').value);
      fd.append('nama',         document.getElementById('reNama').value);
      fd.append('elevasi_pref', document.getElementById('reElev').value);
      fd.append('surface_pref', document.getElementById('reSurf').value);
      fd.append('is_public',    document.getElementById('rePub').checked ? '1':'0');
      var r = await fetch('/api_run.php', { method:'POST', body: fd, credentials:'same-origin' });
      var txt = await r.text();
      var d; try { d = JSON.parse(txt); } catch(e){ throw new Error('Respon bukan JSON: '+txt.substring(0,200)); }
      if (d.ok) { location.reload(); return; }
      throw new Error(d.err || 'Server menolak update.');
    } catch (err) {
      alert('Gagal update rute: ' + (err && err.message ? err.message : err));
      if (btn) { btn.disabled = false; btn.innerHTML = orig; }
    }
  }
  var feForm = document.getElementById('routeEditForm');
  if (feForm) {
    feForm.addEventListener('submit', function(e){ e.preventDefault(); e.stopPropagation(); submitRouteEdit(); });
    // Fallback: kalau event submit terhalang sesuatu, tombol langsung memanggil handler.
    var feBtn = feForm.querySelector('button[type=submit]');
    if (feBtn) feBtn.addEventListener('click', function(e){
      // Jika form valid, biarkan submit native fire (akan ditangkap di atas);
      // Jika tidak valid (mis. nama kosong), browser akan munculkan tooltip native.
      if (!feForm.checkValidity()) { return; }
      e.preventDefault();
      submitRouteEdit();
    });
  }

  // ====================== Revisi #3: Pilih titik mulai (klik peta / cari alamat) ======================
  var pickMode = false, pickHandler = null;
  document.getElementById('rbPickOnMap').addEventListener('click', function(){
    ensureBuilderMap();
    pickMode = !pickMode;
    this.classList.toggle('btn-primary', pickMode);
    this.classList.toggle('btn-outline-primary', !pickMode);
    var info = document.getElementById('rbInfo');
    if (pickMode){
      info.textContent = 'Klik di peta untuk menetapkan titik mulai...';
      if (!pickHandler) pickHandler = function(e){
        document.getElementById('rbStart').value = e.latlng.lat.toFixed(6)+','+e.latlng.lng.toFixed(6);
        setStartMarker(e.latlng.lat, e.latlng.lng);
        info.textContent = '✓ Titik mulai diset dari klik peta: '+e.latlng.lat.toFixed(5)+', '+e.latlng.lng.toFixed(5);
        pickMode = false;
        document.getElementById('rbPickOnMap').classList.remove('btn-primary');
        document.getElementById('rbPickOnMap').classList.add('btn-outline-primary');
        bMap.off('click', pickHandler);
      };
      bMap.on('click', pickHandler);
    } else {
      if (pickHandler) bMap.off('click', pickHandler);
      info.textContent = 'Mode pilih titik dimatikan.';
    }
  });
  document.getElementById('rbAddrGo').addEventListener('click', async function(){
    var q = (document.getElementById('rbAddrSearch').value || '').trim();
    var info = document.getElementById('rbInfo');
    if (!q) { info.textContent='Tulis alamat/landmark dulu.'; return; }
    info.textContent = 'Mencari alamat "'+q+'"...';
    try {
      var url = 'https://nominatim.openstreetmap.org/search?format=json&limit=1&countrycodes=id&q='+encodeURIComponent(q);
      var r = await fetch(url, {headers:{'Accept-Language':'id,en'}});
      var arr = await r.json();
      if (!arr || !arr.length){ info.textContent='Alamat tidak ditemukan. Coba lebih spesifik atau sertakan kota.'; return; }
      var lat=parseFloat(arr[0].lat), lng=parseFloat(arr[0].lon);
      document.getElementById('rbStart').value = lat.toFixed(6)+','+lng.toFixed(6);
      setStartMarker(lat,lng);
      info.textContent = '✓ Titik mulai diset ke "'+(arr[0].display_name||q)+'" ('+lat.toFixed(5)+', '+lng.toFixed(5)+')';
    } catch(e){ info.textContent='Error: '+e.message; }
  });
  document.getElementById('rbAddrSearch').addEventListener('keydown', function(e){
    if (e.key === 'Enter'){ e.preventDefault(); document.getElementById('rbAddrGo').click(); }
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
  // Tab Peta Offline dihapus — listener dinonaktifkan
  var _tabOffBtn = document.getElementById('tab-offline-btn');
  if (_tabOffBtn) _tabOffBtn.addEventListener('shown.bs.tab', function(){
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


<!-- Revisi 17 Juni 2026 Part I (#4) — Modal Edit Rute Tersimpan -->
<div class="modal fade" id="routeEditModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form id="routeEditForm" class="modal-content">
  <div class="modal-header"><h5 class="modal-title"><i class="bi bi-pencil-square text-primary"></i> Edit Rute Tersimpan</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body row g-2">
    <input type="hidden" id="reId">
    <div class="col-12"><label class="form-label small">Nama rute</label><input id="reNama" class="form-control form-control-sm" required></div>
    <div class="col-6"><label class="form-label small">Preferensi elevasi</label>
      <select id="reElev" class="form-select form-select-sm">
        <option value="apa-saja">Apa saja</option><option value="datar">Datar</option><option value="berbukit">Berbukit</option>
      </select></div>
    <div class="col-6"><label class="form-label small">Jenis jalan</label>
      <select id="reSurf" class="form-select form-select-sm">
        <option value="apa-saja">Apa saja</option><option value="aspal">Aspal</option><option value="tanah">Tanah</option><option value="campuran">Campuran</option>
      </select></div>
    <div class="col-12 form-check ms-2"><input class="form-check-input" type="checkbox" id="rePub"><label class="form-check-label small" for="rePub">Publik (terlihat komunitas)</label></div>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-save"></i> Simpan Perubahan</button></div>
</form></div></div>

<!-- Revisi 19 Juni 2026 Part O (#1) — Modal popup peta untuk Lihat Rute Tersimpan -->
<div class="modal fade" id="routeViewModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h6 class="modal-title"><i class="bi bi-map text-primary"></i> <span id="rvTitle">Lihat Rute</span></h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-2">
        <div id="rvMap" style="height:60vh;border-radius:8px"></div>
        <div class="small text-muted mt-2" id="rvInfo"></div>
      </div>
    </div>
  </div>
</div>
<script>
/* Revisi 19 Juni 2026 Part O #1 — popup peta untuk Lihat Rute Tersimpan */
var _rvMap=null, _rvLine=null;
function showRouteInPopup(d){
  var el = document.getElementById('routeViewModal'); if(!el || typeof L==='undefined') return;
  document.getElementById('rvTitle').textContent = 'Lihat Rute: '+(d.nama||'') + ' · '+ ((d.jarak_m||0)/1000).toFixed(2)+' km';
  document.getElementById('rvInfo').innerHTML = (d.coords||[]).length + ' titik koordinat. ' +
    '<span class="text-info"><i class="bi bi-info-circle"></i> Lihat detail lengkapnya di peta pembuatan rute.</span>';
  var m = new bootstrap.Modal(el); m.show();
  el.addEventListener('shown.bs.modal', function once(){
    el.removeEventListener('shown.bs.modal', once);
    if (!_rvMap){
      _rvMap = L.map('rvMap');
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'&copy; OSM'}).addTo(_rvMap);
    } else if (_rvLine){ _rvMap.removeLayer(_rvLine); _rvLine=null; }
    _rvMap.invalidateSize();
    _rvLine = L.polyline(d.coords||[], {color:'#dc2626', weight:5}).addTo(_rvMap);
    if ((d.coords||[]).length) _rvMap.fitBounds(_rvLine.getBounds(),{padding:[20,20]});
  });
}

/* Revisi 19 Juni 2026 Part O #2 — Pasang handler form Edit Rute SETELAH modal ada di DOM.
   Sebelumnya skrip di tengah halaman tidak menemukan #routeEditForm (modal di bawah)
   sehingga submit fallback ke GET default → perubahan tidak tersimpan. */
(function(){
  function bind(){
    var feForm = document.getElementById('routeEditForm');
    if (!feForm || feForm.dataset.bound) return;
    feForm.dataset.bound = '1';
    async function doSave(){
      var btn = feForm.querySelector('button[type=submit]');
      var orig = btn ? btn.innerHTML : '';
      if (btn){ btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Menyimpan…'; }
      try {
        var fd = new FormData();
        fd.append('csrf', (typeof CSRF!=='undefined'?CSRF:(document.querySelector('input[name=csrf]')?.value||'')));
        fd.append('_action', 'route_update');
        fd.append('id',           document.getElementById('reId').value);
        fd.append('nama',         document.getElementById('reNama').value);
        fd.append('elevasi_pref', document.getElementById('reElev').value);
        fd.append('surface_pref', document.getElementById('reSurf').value);
        fd.append('is_public',    document.getElementById('rePub').checked ? '1':'0');
        var r = await fetch('/api_run.php', { method:'POST', body: fd, credentials:'same-origin' });
        var txt = await r.text();
        var d; try { d = JSON.parse(txt); } catch(e){ throw new Error('Respon bukan JSON: '+txt.substring(0,200)); }
        if (d.ok) { location.reload(); return; }
        throw new Error(d.err || 'Server menolak update.');
      } catch (err) {
        alert('Gagal update rute: ' + (err && err.message ? err.message : err));
        if (btn){ btn.disabled = false; btn.innerHTML = orig; }
      }
    }
    feForm.addEventListener('submit', function(e){ e.preventDefault(); e.stopPropagation(); doSave(); });
  }
  if (document.readyState === 'complete' || document.readyState === 'interactive') bind();
  else document.addEventListener('DOMContentLoaded', bind);
  window.addEventListener('load', bind);
})();
</script>

<?php include __DIR__.'/includes/footer.php'; ?>

