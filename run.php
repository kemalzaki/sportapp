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
  <a href="/explore.php" class="btn btn-sm btn-outline-primary d-md-none">
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


<?php include __DIR__.'/includes/footer.php'; ?>
