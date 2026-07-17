<?php
/* =====================================================================
 * KawanKeringat — Tracking Jalur (REVISI R34 — Strava/Garmin-Style)
 * ---------------------------------------------------------------------
 * Perubahan besar dibanding R33:
 *  - Mode Tracking Fullscreen (header/nav/sidebar/search disembunyikan)
 *  - Peta fullscreen + auto-follow + rotasi map (heading GPS/Compass)
 *  - Floating metrics (blur + transparan) di atas peta
 *  - Floating control (Pause/Resume + Lock + Stop)
 *  - Swipe-to-Finish (anti salah pencet)
 *  - Lock Screen slide-to-unlock
 *  - Auto Dim setelah diam (GPS tetap jalan)
 *  - Keep Screen On (WakeLock + Capacitor KeepAwake bila native)
 *  - Background tracking (Capacitor background-geolocation)
 *  - Floating tracking bubble style Google Maps (Overlay APK)
 *  - Notification permanen saat background (Foreground Service)
 *  - Voice feedback tiap 500 m / 1 km (TTS)
 *  - Auto-pause bila diam
 *  - Fullscreen Finish screen (map besar + split + grafik pace/speed/elev)
 *  - Kode dipecah ke module JS (tracking, gps, map, ui, background, voice, save)
 * ===================================================================== */
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
send_security_headers(); require_login();
$u = current_user(); $uid = (int)$u['id'];
$pageTitle = 'Tracking Jalur / Rute';
$pageSkeleton = 'grid';

require_once __DIR__.'/includes/paket_helpers.php';
if (!isset($u) || !$u) { require_login(); $u = current_user(); }
paket_require_or_lock('komunitas', $u, 'Tracking Jalur / Rute',
    'Tracking Jalur & Eksplorasi Rute tersedia untuk paket Komunitas.');

$userRow = db_one("SELECT foto_url FROM users WHERE id=$1", [$uid]);
$userPhoto = trim((string)($userRow['foto_url'] ?? ''));
if ($userPhoto === '') $userPhoto = '/assets/img/avatar-default.png';

$wRow = @db_one("SELECT berat_kg FROM users WHERE id=$1", [$uid]);
$userWeight = (float)($wRow['berat_kg'] ?? 0); if ($userWeight <= 0) $userWeight = 65;

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

$history = db_all("SELECT * FROM run_sessions WHERE user_id=$1 ORDER BY mulai_at DESC LIMIT 20", [$uid]);
$active  = db_one("SELECT * FROM run_sessions WHERE user_id=$1 AND status='aktif' ORDER BY id DESC LIMIT 1", [$uid]);

include __DIR__.'/includes/header.php';
?>

<!-- ================================================================
     STYLES — Strava-style tracking (Revisi R34)
     ================================================================ -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<style>
  /* ---- Kartu & tombol tampilan pra-tracking ---- */
  .strava-card{border:0;border-radius:18px;background:#fff;
    box-shadow:0 4px 18px rgba(15,23,42,.06), 0 1px 3px rgba(15,23,42,.04);}
  .strava-card .card-body{padding:1.1rem 1.2rem;}
  .stat-label{font-size:.72rem;letter-spacing:.08em;text-transform:uppercase;
    color:#64748b;font-weight:600;margin-bottom:.15rem;}
  .strava-btn{border-radius:14px;font-weight:700;padding:.85rem 1.1rem;
    letter-spacing:.02em;box-shadow:0 2px 6px rgba(15,23,42,.08);}
  .strava-btn.btn-start{background:#22c55e;border:0;color:#fff;font-size:1.1rem;padding:1rem 1.6rem;}
  .strava-btn.btn-start:hover{background:#16a34a;}
  .history-strava .list-group-item{border:0;border-bottom:1px solid #f1f5f9;padding:.85rem 1rem;}
  .settings-row .form-control,.settings-row .form-select{border-radius:12px;}

  /* ================================================================
     MODE TRACKING FULLSCREEN
     ================================================================ */
  body.kk-tracking-fullscreen{overflow:hidden !important;}
  /* Sembunyikan SEMUA UI eksternal: header, bottom nav, sidebar, search, dsb.
     Status bar Android tetap terlihat (kita tidak masuk immersive). */
  body.kk-tracking-fullscreen header,
  body.kk-tracking-fullscreen .app-header,
  body.kk-tracking-fullscreen .site-header,
  body.kk-tracking-fullscreen .navbar,
  body.kk-tracking-fullscreen nav.navbar,
  body.kk-tracking-fullscreen .top-nav,
  body.kk-tracking-fullscreen .bottom-nav,
  body.kk-tracking-fullscreen .bottomnav,
  body.kk-tracking-fullscreen .app-bottom-nav,
  body.kk-tracking-fullscreen .mobile-bottom-nav,
  body.kk-tracking-fullscreen .app-sidebar,
  body.kk-tracking-fullscreen .sidebar,
  body.kk-tracking-fullscreen aside,
  body.kk-tracking-fullscreen .search-bar,
  body.kk-tracking-fullscreen .global-search,
  body.kk-tracking-fullscreen input[type=search],
  body.kk-tracking-fullscreen .kk-hide-when-tracking,
  body.kk-tracking-fullscreen footer,
  body.kk-tracking-fullscreen .site-footer,
  body.kk-tracking-fullscreen #kk-pretrack-shell{
    display:none !important;
  }

  /* Container fullscreen */
  #kk-track-root{position:fixed;inset:0;z-index:9998;background:#0f172a;display:none;}
  body.kk-tracking-fullscreen #kk-track-root{display:block;}

  /* Peta fullscreen */
  #kk-map{position:absolute;inset:0;background:#0f172a;}
  #kk-map .leaflet-control-attribution{font-size:9px;opacity:.55;}

  /* Rotasi map — kita putar #map-rot (parent Leaflet container hooked lewat JS) */
  .kk-map-rot{transition:transform .35s cubic-bezier(.25,.9,.3,1);
    transform-origin:50% 50%;will-change:transform;}

  /* ================================================================
     FLOATING METRICS (blur, transparan, rounded)
     ================================================================ */
  .kk-metrics{position:absolute;left:12px;right:12px;top:calc(env(safe-area-inset-top,0px) + 10px);
    z-index:5;pointer-events:none;
    display:flex;flex-direction:column;gap:8px;}
  .kk-metric-primary{background:rgba(15,23,42,.55);backdrop-filter:blur(14px);
    -webkit-backdrop-filter:blur(14px);border:1px solid rgba(255,255,255,.08);
    border-radius:22px;padding:14px 18px;color:#fff;
    display:grid;grid-template-columns:1fr;gap:2px;text-align:center;
    box-shadow:0 10px 30px rgba(0,0,0,.35);}
  .kk-metric-primary .m-val{font-size:3.4rem;font-weight:900;line-height:1;
    font-variant-numeric:tabular-nums;letter-spacing:-.02em;}
  .kk-metric-primary .m-lbl{font-size:.72rem;text-transform:uppercase;
    letter-spacing:.14em;color:#cbd5e1;font-weight:700;}
  .kk-metric-primary .m-unit{font-size:1rem;color:#cbd5e1;font-weight:600;margin-left:.2rem;}
  .kk-metric-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;}
  .kk-metric-cell{background:rgba(15,23,42,.5);backdrop-filter:blur(12px);
    -webkit-backdrop-filter:blur(12px);border:1px solid rgba(255,255,255,.07);
    border-radius:16px;padding:10px 8px;color:#fff;text-align:center;}
  .kk-metric-cell .m-val{font-size:1.35rem;font-weight:800;line-height:1.05;
    font-variant-numeric:tabular-nums;}
  .kk-metric-cell .m-lbl{font-size:.62rem;text-transform:uppercase;letter-spacing:.12em;
    color:#94a3b8;font-weight:700;margin-top:2px;}

  /* Chip status kecil */
  .kk-chips{position:absolute;left:12px;top:calc(env(safe-area-inset-top,0px) + 4px);
    display:flex;gap:6px;z-index:6;}
  .kk-chip{background:rgba(15,23,42,.75);color:#fff;padding:5px 10px;border-radius:999px;
    font-size:.7rem;font-weight:700;letter-spacing:.04em;display:inline-flex;align-items:center;gap:5px;
    backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);}
  .kk-chip.status-ok{background:rgba(34,197,94,.85);}
  .kk-chip.status-warn{background:rgba(234,179,8,.9);color:#111;}
  .kk-chip.status-bad{background:rgba(239,68,68,.9);}

  /* Tombol recenter */
  .kk-recenter{position:absolute;right:14px;bottom:calc(240px + env(safe-area-inset-bottom,0px));
    z-index:6;background:#fff;color:#0f172a;border:0;border-radius:999px;
    padding:10px 14px;font-weight:800;font-size:.85rem;
    box-shadow:0 6px 20px rgba(0,0,0,.35);display:none;align-items:center;gap:6px;
    animation:kkPop .25s ease;}
  .kk-recenter.show{display:inline-flex;}
  @keyframes kkPop{from{transform:scale(.7);opacity:0}to{transform:scale(1);opacity:1}}

  /* ================================================================
     FLOATING CONTROL (Pause/Resume/Stop + Lock)
     ================================================================ */
  .kk-ctrl{position:absolute;left:0;right:0;bottom:0;z-index:7;
    padding:14px 16px calc(18px + env(safe-area-inset-bottom,0px));
    background:linear-gradient(to top,rgba(15,23,42,.85) 40%,rgba(15,23,42,0));
    display:flex;flex-direction:column;gap:12px;align-items:center;}
  .kk-ctrl-row{display:flex;align-items:center;justify-content:center;gap:18px;}
  .kk-fab{border:0;border-radius:50%;width:64px;height:64px;font-size:1.6rem;
    color:#fff;box-shadow:0 8px 22px rgba(0,0,0,.45);
    display:inline-flex;align-items:center;justify-content:center;
    transition:transform .12s ease, background .2s;}
  .kk-fab:active{transform:scale(.92);}
  .kk-fab.pause{background:#f59e0b;}
  .kk-fab.resume{background:#0ea5e9;}
  .kk-fab.lock{background:rgba(255,255,255,.15);width:52px;height:52px;font-size:1.2rem;
    border:1px solid rgba(255,255,255,.2);}
  .kk-fab.stop{background:#ef4444;width:82px;height:82px;font-size:2rem;
    box-shadow:0 10px 26px rgba(239,68,68,.55);}

  /* Swipe-to-finish track */
  .kk-swipe{position:relative;width:min(360px,90vw);height:58px;
    background:rgba(15,23,42,.65);border:1px solid rgba(255,255,255,.14);
    border-radius:999px;color:#fff;display:none;align-items:center;justify-content:center;
    font-weight:700;letter-spacing:.06em;font-size:.9rem;user-select:none;overflow:hidden;}
  .kk-swipe.show{display:flex;}
  .kk-swipe .sw-thumb{position:absolute;left:4px;top:4px;bottom:4px;width:50px;
    background:#ef4444;border-radius:999px;display:flex;align-items:center;justify-content:center;
    color:#fff;font-size:1.3rem;transition:transform .18s ease;box-shadow:0 4px 12px rgba(0,0,0,.35);}
  .kk-swipe .sw-thumb.dragging{transition:none;}
  .kk-swipe .sw-fill{position:absolute;left:0;top:0;bottom:0;
    background:linear-gradient(90deg,rgba(239,68,68,.5),rgba(239,68,68,.15));width:0;
    border-radius:999px;pointer-events:none;}
  .kk-swipe .sw-label{position:relative;z-index:1;padding-left:60px;}

  /* ================================================================
     LOCK SCREEN OVERLAY
     ================================================================ */
  .kk-lock{position:absolute;inset:0;z-index:20;background:rgba(2,6,23,.72);
    backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px);
    display:none;flex-direction:column;justify-content:flex-end;align-items:center;
    padding:0 20px calc(60px + env(safe-area-inset-bottom,0px));color:#fff;text-align:center;}
  .kk-lock.show{display:flex;}
  .kk-lock .lk-hero{margin-top:auto;margin-bottom:auto;}
  .kk-lock .lk-icon{font-size:3rem;opacity:.85;}
  .kk-lock .lk-metrics{margin-top:14px;font-size:1.05rem;color:#e2e8f0;
    font-variant-numeric:tabular-nums;font-weight:700;letter-spacing:.05em;}
  .kk-lock .lk-slide{position:relative;width:min(360px,90vw);height:64px;
    background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.18);
    border-radius:999px;display:flex;align-items:center;justify-content:center;
    font-weight:700;letter-spacing:.06em;user-select:none;overflow:hidden;}
  .kk-lock .lk-thumb{position:absolute;left:4px;top:4px;bottom:4px;width:56px;
    background:#fff;border-radius:999px;display:flex;align-items:center;justify-content:center;
    color:#0f172a;font-size:1.4rem;box-shadow:0 4px 12px rgba(0,0,0,.3);transition:transform .18s ease;}
  .kk-lock .lk-thumb.dragging{transition:none;}
  .kk-lock .lk-fill{position:absolute;left:0;top:0;bottom:0;width:0;
    background:linear-gradient(90deg,rgba(255,255,255,.22),rgba(255,255,255,.04));border-radius:999px;}
  .kk-lock .lk-txt{position:relative;z-index:1;color:#e2e8f0;padding-left:60px;}

  /* Auto-dim overlay (turunkan brightness sedikit) */
  .kk-dim{position:absolute;inset:0;background:#000;opacity:0;pointer-events:none;z-index:15;
    transition:opacity .8s ease;}
  .kk-dim.on{opacity:.38;}

  /* Splash saat memulai */
  .kk-countdown{position:absolute;inset:0;z-index:30;background:rgba(2,6,23,.85);
    display:none;align-items:center;justify-content:center;color:#fff;
    font-size:8rem;font-weight:900;font-variant-numeric:tabular-nums;
    text-shadow:0 6px 30px rgba(0,0,0,.5);}
  .kk-countdown.show{display:flex;animation:kkCd .9s ease;}
  @keyframes kkCd{from{transform:scale(1.6);opacity:.2}to{transform:scale(1);opacity:1}}

  /* ================================================================
     FINISH SCREEN
     ================================================================ */
  #kk-finish{position:fixed;inset:0;z-index:9999;background:#f8fafc;display:none;overflow-y:auto;}
  body.kk-finish-open{overflow:hidden;}
  body.kk-finish-open #kk-finish{display:block;}
  .kk-finish-hero{position:relative;height:44vh;min-height:280px;background:#0f172a;}
  #kk-finish-map{position:absolute;inset:0;}
  .kk-finish-back{position:absolute;top:calc(env(safe-area-inset-top,0px) + 10px);left:12px;z-index:5;
    background:rgba(15,23,42,.75);color:#fff;border:0;border-radius:999px;padding:8px 12px;font-weight:700;}
  .kk-finish-body{padding:16px;max-width:720px;margin:0 auto;}
  .kk-summary{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:14px;}
  .kk-summary .c{background:#fff;border-radius:16px;padding:14px 10px;text-align:center;
    box-shadow:0 4px 14px rgba(15,23,42,.06);}
  .kk-summary .c .v{font-size:1.6rem;font-weight:900;color:#0f172a;font-variant-numeric:tabular-nums;}
  .kk-summary .c .l{font-size:.7rem;letter-spacing:.1em;text-transform:uppercase;color:#64748b;font-weight:700;}
  .kk-chart-card{background:#fff;border-radius:16px;padding:14px;margin-bottom:12px;
    box-shadow:0 4px 14px rgba(15,23,42,.06);}
  .kk-chart-card h6{margin:0 0 8px;font-weight:800;color:#0f172a;}
  .kk-chart-card canvas{width:100%;height:120px;display:block;}
  .kk-split-row{display:flex;align-items:center;padding:8px 4px;border-bottom:1px solid #f1f5f9;font-size:.9rem;}
  .kk-split-row:last-child{border-bottom:0;}
  .kk-split-row .km{width:60px;font-weight:800;color:#334155;}
  .kk-split-row .bar{flex:1;height:8px;background:#e2e8f0;border-radius:999px;margin:0 12px;overflow:hidden;}
  .kk-split-row .bar > i{display:block;height:100%;background:#fb923c;border-radius:999px;}
  .kk-split-row .pace{font-variant-numeric:tabular-nums;font-weight:800;color:#0f172a;min-width:70px;text-align:right;}
  .kk-finish-cta{display:flex;gap:10px;margin-top:14px;}
  .kk-finish-cta .btn{flex:1;border-radius:14px;padding:14px;font-weight:800;}

  /* ================================================================
     Marker pelari
     ================================================================ */
  .kk-runner{width:26px;height:26px;border-radius:50%;background:#fc5200;
    border:3px solid #fff;box-shadow:0 0 0 3px rgba(252,82,0,.35),0 3px 10px rgba(0,0,0,.4);
    position:relative;}
  .kk-runner::after{content:"";position:absolute;left:50%;top:-12px;transform:translateX(-50%);
    width:0;height:0;border-left:6px solid transparent;border-right:6px solid transparent;
    border-bottom:10px solid #fc5200;filter:drop-shadow(0 -1px 0 #fff);}
  .leaflet-marker-icon.kk-runner-icon{transition:transform .9s linear;}

  @media (max-width:400px){
    .kk-metric-primary .m-val{font-size:2.8rem;}
    .kk-metric-cell .m-val{font-size:1.15rem;}
    .kk-fab.stop{width:72px;height:72px;font-size:1.7rem;}
    .kk-fab{width:56px;height:56px;font-size:1.4rem;}
  }
</style>

<!-- ================================================================
     SHELL PRA-TRACKING (kartu ringkas + tombol MULAI besar)
     ================================================================ -->
<div id="kk-pretrack-shell">
  <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <h4 class="mb-0"><i class="bi bi-stopwatch text-danger"></i> Tracking Realtime</h4>
    <a href="/explore.php" class="btn btn-sm btn-outline-primary d-md-none">
      <i class="bi bi-compass"></i> Eksplorasi Rute
    </a>
  </div>

  <div id="kk-bg-warn" class="alert alert-warning small d-none">
    <i class="bi bi-exclamation-triangle-fill"></i>
    Tracking background terbatas di browser. Untuk pengalaman penuh seperti Strava
    (GPS jalan saat layar mati, bubble melayang, notification permanen),
    gunakan <strong>APK KawanKeringat</strong> berbasis Capacitor.
  </div>

  <div class="strava-card mb-3">
    <div class="card-body py-3 settings-row">
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
          <label class="stat-label mb-1">Berat (kg)</label>
          <input id="weightInp" type="number" min="20" max="250" step="0.1"
            class="form-control form-control-sm" value="<?= htmlspecialchars((string)$userWeight) ?>">
        </div>
        <div class="col-6 col-md-3">
          <label class="stat-label mb-1">Voice Feedback</label>
          <select id="voiceSel" class="form-select form-select-sm">
            <option value="1000">Setiap 1 km</option>
            <option value="500">Setiap 500 m</option>
            <option value="0">Nonaktif</option>
          </select>
        </div>
        <div class="col-6 col-md-3">
          <label class="stat-label mb-1">Rotasi Map</label>
          <select id="rotSel" class="form-select form-select-sm">
            <option value="heading">Ikuti Arah</option>
            <option value="north">Utara di Atas</option>
          </select>
        </div>
      </div>
    </div>
  </div>

  <div class="text-center my-4">
    <button id="kk-btn-start" class="strava-btn btn-start">
      <i class="bi bi-play-fill"></i> MULAI TRACKING
    </button>
    <div class="text-muted small mt-2">
      Peta fullscreen, floating metrics, auto-follow &amp; rotasi arah
    </div>
  </div>

  <details class="strava-card history-strava mb-4" <?= $history ? 'open' : '' ?>>
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
              <div class="btn-group btn-group-sm">
                <a class="btn btn-link p-0 me-2" href="/api_run.php?export=<?= (int)$h['id'] ?>&fmt=gpx"><i class="bi bi-download"></i> GPX</a>
                <a class="btn btn-link p-0 me-2" href="/api_run.php?export=<?= (int)$h['id'] ?>&fmt=kml"><i class="bi bi-download"></i> KML</a>
                <a class="btn btn-link p-0" href="/api_run.php?export=<?= (int)$h['id'] ?>&fmt=geojson"><i class="bi bi-download"></i> GeoJSON</a>
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

<!-- ================================================================
     ROOT MODE TRACKING FULLSCREEN
     ================================================================ -->
<div id="kk-track-root" aria-hidden="true">
  <div id="kk-map"></div>

  <!-- Chips atas -->
  <div class="kk-chips">
    <span class="kk-chip" id="kk-gps-chip">🟡 GPS…</span>
    <span class="kk-chip" id="kk-mode-chip" style="display:none">▶ Rekaman</span>
    <span class="kk-chip" id="kk-auto-chip" style="display:none">Auto-Pause</span>
  </div>

  <!-- Floating metrics -->
  <div class="kk-metrics">
    <div class="kk-metric-primary">
      <div class="m-lbl">Distance</div>
      <div><span class="m-val" id="m-dist">0.00</span><span class="m-unit">km</span></div>
    </div>
    <div class="kk-metric-grid">
      <div class="kk-metric-cell"><div class="m-val" id="m-time">00:00</div><div class="m-lbl">Time</div></div>
      <div class="kk-metric-cell"><div class="m-val" id="m-pace">--'--"</div><div class="m-lbl">Pace</div></div>
      <div class="kk-metric-cell"><div class="m-val" id="m-speed">0.0</div><div class="m-lbl">km/h</div></div>
      <div class="kk-metric-cell"><div class="m-val" id="m-cal">0</div><div class="m-lbl">Cal</div></div>
      <div class="kk-metric-cell"><div class="m-val" id="m-elev">–</div><div class="m-lbl">Elev</div></div>
      <div class="kk-metric-cell"><div class="m-val" id="m-avgpace">--'--"</div><div class="m-lbl">Avg Pace</div></div>
    </div>
  </div>

  <!-- Recenter -->
  <button class="kk-recenter" id="kk-recenter">
    <i class="bi bi-crosshair"></i> Kembali ke Posisi Saya
  </button>

  <!-- Controls -->
  <div class="kk-ctrl">
    <div class="kk-swipe" id="kk-swipe">
      <div class="sw-fill"></div>
      <div class="sw-thumb"><i class="bi bi-arrow-right"></i></div>
      <div class="sw-label">Geser untuk selesai →</div>
    </div>
    <div class="kk-ctrl-row" id="kk-ctrl-row">
      <button class="kk-fab lock" id="kk-btn-lock" title="Kunci Layar"><i class="bi bi-lock-fill"></i></button>
      <button class="kk-fab pause" id="kk-btn-pause" title="Jeda"><i class="bi bi-pause-fill"></i></button>
      <button class="kk-fab stop" id="kk-btn-stop" title="Stop"><i class="bi bi-stop-fill"></i></button>
      <button class="kk-fab resume" id="kk-btn-resume" title="Lanjutkan" style="display:none"><i class="bi bi-play-fill"></i></button>
      <button class="kk-fab lock" id="kk-btn-mute" title="Voice"><i class="bi bi-volume-up-fill"></i></button>
    </div>
  </div>

  <!-- Lock screen -->
  <div class="kk-lock" id="kk-lock">
    <div class="lk-hero">
      <div class="lk-icon"><i class="bi bi-lock-fill"></i></div>
      <div style="font-weight:800;font-size:1.15rem;margin-top:8px;">Layar terkunci</div>
      <div class="lk-metrics" id="lk-metrics">0.00 km · 00:00</div>
    </div>
    <div class="lk-slide" id="kk-lock-slide">
      <div class="lk-fill"></div>
      <div class="lk-thumb"><i class="bi bi-unlock-fill"></i></div>
      <div class="lk-txt">Geser untuk membuka →</div>
    </div>
  </div>

  <!-- Auto-dim overlay -->
  <div class="kk-dim" id="kk-dim"></div>

  <!-- Countdown -->
  <div class="kk-countdown" id="kk-countdown">3</div>
</div>

<!-- ================================================================
     FINISH SCREEN
     ================================================================ -->
<div id="kk-finish" aria-hidden="true">
  <div class="kk-finish-hero">
    <div id="kk-finish-map"></div>
    <button class="kk-finish-back" id="kk-finish-back"><i class="bi bi-x-lg"></i> Tutup</button>
  </div>
  <div class="kk-finish-body">
    <h4 class="fw-bold mb-1">Kerja bagus! 🎉</h4>
    <div class="text-muted small mb-3" id="kk-finish-when"></div>
    <div class="kk-summary">
      <div class="c"><div class="v" id="f-dist">0.00</div><div class="l">Km</div></div>
      <div class="c"><div class="v" id="f-time">00:00</div><div class="l">Waktu</div></div>
      <div class="c"><div class="v" id="f-pace">--'--"</div><div class="l">Pace</div></div>
      <div class="c"><div class="v" id="f-speed">0.0</div><div class="l">Avg km/h</div></div>
      <div class="c"><div class="v" id="f-cal">0</div><div class="l">Kalori</div></div>
      <div class="c"><div class="v" id="f-elev">0</div><div class="l">Elev (m)</div></div>
    </div>

    <div class="kk-chart-card">
      <h6><i class="bi bi-flag-fill text-warning"></i> Split per Kilometer</h6>
      <div id="f-splits"><div class="text-muted small">-</div></div>
    </div>

    <div class="kk-chart-card"><h6>Grafik Pace (menit/km)</h6><canvas id="f-chart-pace"></canvas></div>
    <div class="kk-chart-card"><h6>Grafik Kecepatan (km/h)</h6><canvas id="f-chart-speed"></canvas></div>
    <div class="kk-chart-card"><h6>Grafik Elevasi (m)</h6><canvas id="f-chart-elev"></canvas></div>

    <div class="kk-finish-cta">
      <button class="btn btn-outline-secondary" id="f-btn-discard"><i class="bi bi-trash"></i> Buang</button>
      <a class="btn btn-danger" id="f-btn-review" href="/upload.php"><i class="bi bi-cloud-arrow-up"></i> Review &amp; Upload</a>
    </div>
  </div>
</div>

<!-- ================================================================
     KONFIG UNTUK MODUL JS
     ================================================================ -->
<script>
window.KK_RUN = {
  csrf: <?= json_encode(csrf_token()) ?>,
  sessionId: <?= $active ? (int)$active['id'] : 'null' ?>,
  userPhoto: <?= json_encode($userPhoto) ?>,
  weight: <?= json_encode((float)$userWeight) ?>,
  mapboxToken: 'pk.eyJ1IjoiYWRhbXNhc21pdGE1MzQiLCJhIjoiY21xZnRsbWxjMXZldDJ0cHlhN2Jycnd1dCJ9.2E00ey-sgX9jUmf5kIRoEA',
  mapboxAttr: '&copy; <a href="https://www.mapbox.com/about/maps/">Mapbox</a> &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
};
window.KK_RUN.mapboxTileUrl =
  'https://api.mapbox.com/styles/v1/mapbox/outdoors-v12/tiles/256/{z}/{x}/{y}@2x?access_token=' +
  window.KK_RUN.mapboxToken;
</script>

<!-- Modul JS (dipecah agar mudah dirawat) -->
<script src="/assets/js/run/save.js?v=r34"></script>
<script src="/assets/js/run/voice.js?v=r34"></script>
<script src="/assets/js/run/map.js?v=r34"></script>
<script src="/assets/js/run/gps.js?v=r34"></script>
<script src="/assets/js/run/background.js?v=r34"></script>
<script src="/assets/js/run/ui.js?v=r34"></script>
<script src="/assets/js/run/tracking.js?v=r34"></script>

<script>
/* ---- Hapus riwayat (tetap dari halaman shell) ---- */
document.addEventListener('click', function(ev){
  var b = ev.target.closest('.run-del-btn'); if(!b) return;
  if (!confirm('Hapus riwayat lari ini?')) return;
  var id = b.getAttribute('data-id');
  var fd = new FormData();
  fd.append('csrf', window.KK_RUN.csrf);
  fd.append('_action','delete'); fd.append('session_id', id);
  b.disabled = true;
  fetch('/api_run.php',{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(d){
    if (d && d.ok){ var row = document.getElementById('run-row-'+id); if (row) row.remove(); }
    else { alert('Gagal menghapus.'); b.disabled = false; }
  }).catch(function(){ alert('Gagal menghapus.'); b.disabled = false; });
});
</script>

<?php include __DIR__.'/includes/footer.php'; ?>
