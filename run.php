<?php
/* =====================================================================
 * KawanKeringat — Tracking Jalur (REVISI R35 — Dashboard + Focus Mode)
 * ---------------------------------------------------------------------
 * Perubahan dibanding R34:
 *  - Mode fullscreen kini OPSIONAL. Halaman default = Dashboard Mode
 *    (statistik + mini map ~38 vh + tombol Mulai/Pause/Stop + split
 *    + riwayat). Header & bottom nav TETAP tampil.
 *  - Focus Mode (fullscreen) diaktifkan hanya lewat tombol floating ⛶
 *    di kanan atas peta. Perpindahan cukup toggle CSS class, TIDAK
 *    reload halaman, TIDAK destroy Leaflet, TIDAK reset timer/GPS.
 *  - Floating map controls: 📍 Lokasi · 🧭 Compass · ⛶ Fullscreen · ⚙️ Settings.
 *  - Preferensi terakhir disimpan di localStorage['kk_run_mode_v1'].
 *  - Palet identitas KawanKeringat (navy + electric blue + light blue).
 *
 * TIDAK diubah:
 *  - Logika GPS / tracking / penyimpanan / polyline / marker / voice /
 *    background service. Modul JS lain (gps/tracking/map/save/
 *    background/voice.js) apa adanya. Perubahan hanya di run.php + ui.js.
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

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<style>
/* ================================================================
   KawanKeringat Design Tokens (R35)
   ================================================================ */
.kk-run-page{
  --kk-navy:#081223; --kk-navy-2:#0d1a33;
  --kk-blue:#1E90FF; --kk-blue-2:#4FB0FF;
  --kk-light:#BFE0FF; --kk-white:#ffffff;
  --kk-glow-blue:0 6px 22px rgba(30,144,255,.35);
}

/* ================================================================
   DASHBOARD MODE (default)
   ================================================================ */
.kk-dash-wrap{max-width:960px;margin:0 auto;padding:12px 12px 90px;}
.kk-dash-title{display:flex;align-items:center;justify-content:space-between;
  gap:8px;flex-wrap:wrap;margin-bottom:10px;}
.kk-dash-title h4{margin:0;font-weight:800;color:#0f172a;}
.kk-card{background:#fff;border:0;border-radius:20px;
  box-shadow:0 6px 22px rgba(15,23,42,.06),0 1px 3px rgba(15,23,42,.04);
  padding:16px;margin-bottom:12px;transition:all .3s ease;}
.stat-label{font-size:.68rem;letter-spacing:.1em;text-transform:uppercase;
  color:#64748b;font-weight:700;margin-bottom:.15rem;}
.kk-primary-stat{text-align:center;padding:6px 0 4px;}
.kk-primary-stat .val{font-size:3.2rem;font-weight:900;line-height:1;
  font-variant-numeric:tabular-nums;color:#0f172a;letter-spacing:-.02em;}
.kk-primary-stat .unit{font-size:1rem;color:#64748b;font-weight:700;margin-left:6px;}
.kk-primary-stat .lbl{font-size:.72rem;letter-spacing:.14em;text-transform:uppercase;
  color:#64748b;font-weight:700;margin-top:4px;}
.kk-stat-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-top:10px;}
.kk-stat-cell{background:#f8fafc;border-radius:14px;padding:10px 6px;text-align:center;}
.kk-stat-cell .val{font-size:1.15rem;font-weight:800;color:#0f172a;font-variant-numeric:tabular-nums;}
.kk-stat-cell .lbl{font-size:.6rem;letter-spacing:.1em;text-transform:uppercase;
  color:#64748b;font-weight:700;margin-top:2px;}

.kk-chip{background:#0f172a;color:#fff;padding:5px 10px;border-radius:999px;
  font-size:.7rem;font-weight:700;letter-spacing:.04em;display:inline-flex;align-items:center;gap:5px;}
.kk-chip.status-ok{background:#22c55e;}
.kk-chip.status-warn{background:#eab308;color:#111;}
.kk-chip.status-bad{background:#ef4444;}
#d-mode-chip{background:#ef4444;color:#fff;animation:kkRecBlink 1.4s ease-in-out infinite;}
@keyframes kkRecBlink{0%,100%{opacity:1}50%{opacity:.6}}

/* Mini map card di Dashboard Mode */
.kk-mapwrap{position:relative;border-radius:20px;overflow:hidden;
  height:38vh;min-height:260px;background:#0f172a;
  box-shadow:0 6px 22px rgba(15,23,42,.08);transition:all .3s ease;}
#kk-map{position:absolute;inset:0;}
.kk-map-rot{transition:transform .35s cubic-bezier(.25,.9,.3,1);
  transform-origin:50% 50%;will-change:transform;}

/* Chips atas peta */
.kk-chips{position:absolute;left:10px;top:10px;display:flex;gap:6px;z-index:6;}
.kk-chips .kk-chip{backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);
  background:rgba(15,23,42,.75);}
.kk-chips .kk-chip.status-ok{background:rgba(34,197,94,.9);}
.kk-chips .kk-chip.status-warn{background:rgba(234,179,8,.95);color:#111;}
.kk-chips .kk-chip.status-bad{background:rgba(239,68,68,.9);}

/* Floating map controls (kanan atas) */
.kk-mapfabs{position:absolute;right:10px;top:10px;z-index:7;
  display:flex;flex-direction:column;gap:8px;}
.kk-mapfab{width:44px;height:44px;border-radius:50%;border:0;
  background:rgba(255,255,255,.92);backdrop-filter:blur(8px);
  -webkit-backdrop-filter:blur(8px);color:#0f172a;font-size:1.05rem;
  display:inline-flex;align-items:center;justify-content:center;
  box-shadow:0 4px 14px rgba(15,23,42,.18);cursor:pointer;
  transition:transform .2s ease, box-shadow .25s ease, background .25s;}
.kk-mapfab:hover{transform:translateY(-2px);
  box-shadow:0 8px 20px rgba(15,23,42,.24);}
.kk-mapfab.active{background:#1E90FF;color:#fff;box-shadow:0 6px 22px rgba(30,144,255,.45);}
.kk-mapfab.kk-ripple{animation:kkRipple .32s ease;}
@keyframes kkRipple{0%{transform:scale(1)}45%{transform:scale(.9)}100%{transform:scale(1)}}

/* Settings popover */
.kk-settings-pop{position:absolute;right:64px;top:60px;z-index:9;
  background:#fff;border-radius:14px;padding:12px;min-width:200px;
  box-shadow:0 12px 30px rgba(15,23,42,.18);
  display:none;transform:translateY(-4px);opacity:0;transition:all .2s ease;}
.kk-settings-pop.show{display:block;transform:translateY(0);opacity:1;}
.kk-settings-pop label{font-size:.7rem;letter-spacing:.08em;text-transform:uppercase;
  color:#64748b;font-weight:700;margin-bottom:4px;display:block;}
.kk-settings-pop select{width:100%;border:1px solid #e2e8f0;border-radius:10px;
  padding:6px 8px;font-size:.85rem;margin-bottom:8px;}

/* Recenter pill (only muncul saat user pan map) */
.kk-recenter{position:absolute;right:14px;bottom:14px;z-index:6;
  background:#fff;color:#0f172a;border:0;border-radius:999px;
  padding:8px 12px;font-weight:700;font-size:.8rem;
  box-shadow:0 6px 18px rgba(0,0,0,.25);display:none;align-items:center;gap:6px;}
.kk-recenter.show{display:inline-flex;}

/* Tombol utama Dashboard */
.kk-dash-controls{display:flex;gap:10px;flex-wrap:wrap;justify-content:center;margin-top:12px;}
.kk-dash-btn{border:0;border-radius:16px;padding:14px 20px;font-weight:800;
  letter-spacing:.02em;font-size:1rem;color:#fff;min-width:140px;
  display:inline-flex;align-items:center;justify-content:center;gap:8px;
  box-shadow:0 4px 14px rgba(15,23,42,.12);transition:all .25s ease;cursor:pointer;}
.kk-dash-btn:hover{transform:translateY(-1px);}
.kk-dash-btn.start{background:linear-gradient(135deg,#1E90FF,#4FB0FF);
  box-shadow:var(--kk-glow-blue);font-size:1.05rem;padding:14px 26px;}
.kk-dash-btn.pause{background:#f59e0b;}
.kk-dash-btn.resume{background:#0ea5e9;}
.kk-dash-btn.stop{background:#ef4444;}

/* Split & history */
.kk-split-row{display:flex;align-items:center;padding:8px 4px;border-bottom:1px solid #f1f5f9;font-size:.9rem;}
.kk-split-row:last-child{border-bottom:0;}
.kk-split-row .km{width:60px;font-weight:800;color:#334155;}
.kk-split-row .bar{flex:1;height:8px;background:#e2e8f0;border-radius:999px;margin:0 12px;overflow:hidden;}
.kk-split-row .bar > i{display:block;height:100%;background:#1E90FF;border-radius:999px;}
.kk-split-row .pace{font-variant-numeric:tabular-nums;font-weight:800;color:#0f172a;min-width:70px;text-align:right;}
.history-kk .list-group-item{border:0;border-bottom:1px solid #f1f5f9;padding:.85rem 1rem;}
.settings-row .form-control,.settings-row .form-select{border-radius:12px;}

/* ================================================================
   FOCUS MODE (aktif hanya via tombol ⛶)
   ================================================================ */
body.kk-focus-mode{overflow:hidden !important;}
body.kk-focus-mode header,
body.kk-focus-mode .app-header,
body.kk-focus-mode .site-header,
body.kk-focus-mode .navbar,
body.kk-focus-mode nav.navbar,
body.kk-focus-mode .top-nav,
body.kk-focus-mode .bottom-nav,
body.kk-focus-mode .bottomnav,
body.kk-focus-mode .app-bottom-nav,
body.kk-focus-mode .mobile-bottom-nav,
body.kk-focus-mode .app-sidebar,
body.kk-focus-mode .sidebar,
body.kk-focus-mode aside,
body.kk-focus-mode .search-bar,
body.kk-focus-mode .global-search,
body.kk-focus-mode input[type=search],
body.kk-focus-mode .kk-hide-when-tracking,
body.kk-focus-mode footer,
body.kk-focus-mode .site-footer,
body.kk-focus-mode .kk-hide-in-focus{
  display:none !important;
}

/* Wrapper dashboard tetap ada agar layout tak flicker,
   tapi seluruhnya tidak terlihat karena mapwrap dinaikkan */
body.kk-focus-mode .kk-hide-in-focus{display:none !important;}

/* Peta di Focus Mode = fullscreen (Leaflet TIDAK di-destroy) */
body.kk-focus-mode .kk-mapwrap{
  position:fixed !important;inset:0 !important;
  height:100vh !important;width:100vw !important;
  border-radius:0 !important;z-index:9998;
  transition:all .3s ease;
}

/* Floating overlay metrics (glass) */
.kk-focus-overlay{display:none;}
body.kk-focus-mode .kk-focus-overlay{
  display:flex;flex-direction:column;gap:8px;
  position:fixed;left:12px;right:12px;
  top:calc(env(safe-area-inset-top,0px) + 60px);
  z-index:10000;pointer-events:none;
  animation:kkFadeIn .3s ease;
}
@keyframes kkFadeIn{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:none}}
.kk-metric-primary{background:rgba(8,18,35,.55);backdrop-filter:blur(18px) saturate(140%);
  -webkit-backdrop-filter:blur(18px) saturate(140%);
  border:1px solid rgba(191,224,255,.22);border-radius:22px;padding:14px 18px;color:#fff;
  text-align:center;box-shadow:0 10px 30px rgba(0,0,0,.35);}
.kk-metric-primary .m-val{font-size:3.2rem;font-weight:900;line-height:1;
  font-variant-numeric:tabular-nums;letter-spacing:-.02em;}
.kk-metric-primary .m-unit{font-size:1rem;color:#BFE0FF;font-weight:600;margin-left:.2rem;}
.kk-metric-primary .m-lbl{font-size:.7rem;text-transform:uppercase;letter-spacing:.14em;
  color:#BFE0FF;font-weight:700;}
.kk-metric-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;}
.kk-metric-cell{background:rgba(8,18,35,.5);backdrop-filter:blur(16px) saturate(140%);
  -webkit-backdrop-filter:blur(16px) saturate(140%);
  border:1px solid rgba(191,224,255,.16);border-radius:16px;padding:10px 8px;color:#fff;text-align:center;}
.kk-metric-cell .m-val{font-size:1.3rem;font-weight:800;line-height:1.05;font-variant-numeric:tabular-nums;}
.kk-metric-cell .m-lbl{font-size:.6rem;text-transform:uppercase;letter-spacing:.12em;
  color:#BFE0FF;font-weight:700;margin-top:2px;}

/* Floating chips di Focus Mode (kiri atas) */
body.kk-focus-mode .kk-focus-chips{
  position:fixed;left:12px;top:calc(env(safe-area-inset-top,0px) + 12px);
  z-index:10001;display:flex;gap:6px;pointer-events:none;
}
.kk-focus-chips{display:none;}

/* Tombol exit fullscreen (pojok kanan atas) */
.kk-exit-focus{display:none;}
body.kk-focus-mode .kk-exit-focus{
  display:inline-flex;align-items:center;gap:6px;
  position:fixed;right:12px;top:calc(env(safe-area-inset-top,0px) + 12px);
  z-index:10002;background:rgba(8,18,35,.7);color:#fff;
  border:1px solid rgba(191,224,255,.22);border-radius:999px;
  padding:8px 14px;font-weight:700;font-size:.85rem;cursor:pointer;
  backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px);
}

/* Floating control bar bawah (pause/stop/lock/mute) */
.kk-ctrl{display:none;}
body.kk-focus-mode .kk-ctrl{
  display:flex;flex-direction:column;gap:12px;align-items:center;
  position:fixed;left:0;right:0;bottom:0;z-index:10001;
  padding:14px 16px calc(18px + env(safe-area-inset-bottom,0px));
  background:linear-gradient(to top,rgba(8,18,35,.85) 40%,rgba(8,18,35,0));
  pointer-events:none;
}
body.kk-focus-mode .kk-ctrl > *{pointer-events:auto;}
.kk-ctrl-row{display:flex;align-items:center;justify-content:center;gap:18px;}
.kk-fab{border:0;border-radius:50%;width:64px;height:64px;font-size:1.6rem;
  color:#fff;box-shadow:0 8px 22px rgba(0,0,0,.45);
  display:inline-flex;align-items:center;justify-content:center;
  transition:transform .12s ease, background .2s;cursor:pointer;}
.kk-fab:active{transform:scale(.92);}
.kk-fab.pause{background:#f59e0b;}
.kk-fab.resume{background:#0ea5e9;}
.kk-fab.lock{background:rgba(255,255,255,.15);width:52px;height:52px;font-size:1.2rem;
  border:1px solid rgba(255,255,255,.22);}
.kk-fab.stop{background:#ef4444;width:82px;height:82px;font-size:2rem;
  box-shadow:0 10px 26px rgba(239,68,68,.55);}

/* Swipe-to-finish */
.kk-swipe{position:relative;width:min(360px,90vw);height:58px;
  background:rgba(8,18,35,.65);border:1px solid rgba(191,224,255,.18);
  border-radius:999px;color:#fff;display:none;align-items:center;justify-content:center;
  font-weight:700;letter-spacing:.06em;font-size:.9rem;user-select:none;overflow:hidden;}
.kk-swipe.show{display:flex;}
.kk-swipe .sw-thumb{position:absolute;left:4px;top:4px;bottom:4px;width:50px;
  background:#ef4444;border-radius:999px;display:flex;align-items:center;justify-content:center;
  color:#fff;font-size:1.3rem;transition:transform .18s ease;box-shadow:0 4px 12px rgba(0,0,0,.35);}
.kk-swipe .sw-thumb.dragging{transition:none;}
.kk-swipe .sw-fill{position:absolute;left:0;top:0;bottom:0;
  background:linear-gradient(90deg,rgba(239,68,68,.5),rgba(239,68,68,.15));width:0;border-radius:999px;pointer-events:none;}
.kk-swipe .sw-label{position:relative;z-index:1;padding-left:60px;}

/* Lock screen */
.kk-lock{position:fixed;inset:0;z-index:10005;background:rgba(2,6,23,.72);
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

/* Auto-dim (focus only) */
.kk-dim{position:fixed;inset:0;background:#000;opacity:0;pointer-events:none;z-index:10004;
  transition:opacity .8s ease;display:none;}
body.kk-focus-mode .kk-dim{display:block;}
.kk-dim.on{opacity:.38;}

/* Countdown */
.kk-countdown{position:fixed;inset:0;z-index:10010;background:rgba(2,6,23,.85);
  display:none;align-items:center;justify-content:center;color:#fff;
  font-size:8rem;font-weight:900;font-variant-numeric:tabular-nums;
  text-shadow:0 6px 30px rgba(0,0,0,.5);}
.kk-countdown.show{display:flex;animation:kkCd .9s ease;}
@keyframes kkCd{from{transform:scale(1.6);opacity:.2}to{transform:scale(1);opacity:1}}

/* Marker pelari (biru KK) */
.kk-runner{width:26px;height:26px;border-radius:50%;background:#1E90FF;
  border:3px solid #fff;box-shadow:0 0 0 3px rgba(30,144,255,.35),0 3px 10px rgba(0,0,0,.4);position:relative;}
.kk-runner::after{content:"";position:absolute;left:50%;top:-12px;transform:translateX(-50%);
  width:0;height:0;border-left:6px solid transparent;border-right:6px solid transparent;
  border-bottom:10px solid #1E90FF;filter:drop-shadow(0 -1px 0 #fff);}
.leaflet-marker-icon.kk-runner-icon{transition:transform .9s linear;}
.leaflet-overlay-pane path.leaflet-interactive{stroke:#1E90FF;}

/* Finish screen */
#kk-finish{position:fixed;inset:0;z-index:10020;background:#f8fafc;display:none;overflow-y:auto;}
body.kk-finish-open{overflow:hidden;}
body.kk-finish-open #kk-finish{display:block;}
.kk-finish-hero{position:relative;height:40vh;min-height:260px;background:#081223;}
#kk-finish-map{position:absolute;inset:0;}
.kk-finish-back{position:absolute;top:calc(env(safe-area-inset-top,0px) + 10px);left:12px;z-index:5;
  background:rgba(8,18,35,.75);color:#fff;border:0;border-radius:999px;padding:8px 12px;font-weight:700;cursor:pointer;}
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
.kk-finish-cta{display:flex;gap:10px;margin-top:14px;}
.kk-finish-cta .btn{flex:1;border-radius:14px;padding:14px;font-weight:800;}

@media (max-width:400px){
  .kk-metric-primary .m-val{font-size:2.6rem;}
  .kk-metric-cell .m-val{font-size:1.1rem;}
  .kk-fab.stop{width:72px;height:72px;font-size:1.7rem;}
  .kk-fab{width:56px;height:56px;font-size:1.4rem;}
  .kk-primary-stat .val{font-size:2.6rem;}
}
</style>

<body class="kk-run-page kk-dashboard-mode"></body>
<script>document.body.classList.add('kk-run-page','kk-dashboard-mode');</script>

<!-- ================================================================
     DASHBOARD SHELL (default)
     ================================================================ -->
<div class="kk-dash-wrap kk-hide-in-focus">
  <div class="kk-dash-title">
    <h4><i class="bi bi-stopwatch text-primary"></i> Tracking Jalur</h4>
  </div>

  <div id="kk-bg-warn" class="alert alert-warning small d-none">
    <i class="bi bi-exclamation-triangle-fill"></i>
    Tracking background terbatas di browser. Untuk pengalaman penuh (GPS jalan
    saat layar mati, bubble melayang, notification permanen), gunakan
    <strong>APK KawanKeringat</strong>.
  </div>

  <!-- Panel Statistik -->
  <div class="kk-card">
    <div class="d-flex justify-content-between align-items-start mb-2">
      <span class="stat-label">Sesi Berjalan</span>
      <span class="kk-chip" id="d-mode-chip" style="display:none">▶ Rekaman</span>
    </div>
    <div class="kk-primary-stat">
      <div><span class="val" id="d-dist">0.00</span><span class="unit">km</span></div>
      <div class="lbl">Distance</div>
    </div>
    <div class="kk-stat-grid">
      <div class="kk-stat-cell"><div class="val" id="d-time">00:00</div><div class="lbl">Time</div></div>
      <div class="kk-stat-cell"><div class="val" id="d-pace">--'--"</div><div class="lbl">Pace</div></div>
      <div class="kk-stat-cell"><div class="val" id="d-speed">0.0</div><div class="lbl">km/h</div></div>
      <div class="kk-stat-cell"><div class="val" id="d-cal">0</div><div class="lbl">Cal</div></div>
      <div class="kk-stat-cell"><div class="val" id="d-elev">–</div><div class="lbl">Elev</div></div>
      <div class="kk-stat-cell"><div class="val" id="d-avgpace">--'--"</div><div class="lbl">Avg Pace</div></div>
    </div>
  </div>

  <!-- Mini Map (juga digunakan Focus Mode — jangan destroy/recreate) -->
  <div class="kk-card" style="padding:6px;">
    <div class="kk-mapwrap" id="kk-mapwrap">
      <div id="kk-map"></div>

      <!-- Chips atas -->
      <div class="kk-chips">
        <span class="kk-chip" id="kk-gps-chip">🟡 GPS…</span>
        <span class="kk-chip" id="kk-mode-chip" style="display:none">▶ Rekaman</span>
        <span class="kk-chip" id="kk-auto-chip" style="display:none">Auto-Pause</span>
      </div>

      <!-- Floating Map Controls -->
      <div class="kk-mapfabs">
        <button class="kk-mapfab" id="kk-fab-location" title="Follow My Location" aria-label="Follow My Location"><i class="bi bi-cursor-fill"></i></button>
        <button class="kk-mapfab" id="kk-fab-compass" title="Compass" aria-label="Toggle Compass"><i class="bi bi-compass"></i></button>
        <button class="kk-mapfab" id="kk-fab-fullscreen" title="Fullscreen" aria-label="Toggle Fullscreen"><i class="bi bi-arrows-fullscreen"></i></button>
        <button class="kk-mapfab" id="kk-fab-settings" title="Settings" aria-label="Tracking Settings"><i class="bi bi-gear-fill"></i></button>
      </div>

      <!-- Settings popover -->
      <div class="kk-settings-pop" id="kk-settings-pop">
        <label>Jenis Olahraga</label>
        <select id="sportSel">
          <option value="run">Lari</option>
          <option value="jog">Jogging</option>
          <option value="walk">Jalan</option>
          <option value="bike">Sepeda</option>
        </select>
        <label>Voice Feedback</label>
        <select id="voiceSel">
          <option value="1000">Setiap 1 km</option>
          <option value="500">Setiap 500 m</option>
          <option value="0">Nonaktif</option>
        </select>
        <label>Rotasi Map</label>
        <select id="rotSel">
          <option value="heading">Ikuti Arah</option>
          <option value="north">Utara di Atas</option>
        </select>
        <label>Berat (kg)</label>
        <input id="weightInp" type="number" min="20" max="250" step="0.1"
          class="form-control form-control-sm" value="<?= htmlspecialchars((string)$userWeight) ?>">
      </div>

      <!-- Recenter pill -->
      <button class="kk-recenter" id="kk-recenter">
        <i class="bi bi-crosshair"></i> Kembali ke Posisi Saya
      </button>
    </div>
  </div>

  <!-- Tombol utama -->
  <div class="kk-card">
    <div class="kk-dash-controls">
      <button class="kk-dash-btn start" id="kk-dash-btn-start"><i class="bi bi-play-fill"></i> Mulai</button>
      <button class="kk-dash-btn pause" id="kk-dash-btn-pause" style="display:none"><i class="bi bi-pause-fill"></i> Jeda</button>
      <button class="kk-dash-btn resume" id="kk-dash-btn-resume" style="display:none"><i class="bi bi-play-fill"></i> Lanjut</button>
      <button class="kk-dash-btn stop" id="kk-dash-btn-stop"><i class="bi bi-stop-fill"></i> Selesai</button>
      <button class="kk-dash-btn" id="kk-dash-btn-mylocation" style="background:#0ea5e9;"><i class="bi bi-geo-alt-fill"></i> Lokasi Saya Sekarang</button>
    </div>
    <div class="text-center text-muted small mt-2">
      Tekan <i class="bi bi-arrows-fullscreen"></i> pada peta untuk masuk mode Fullscreen kapan saja.
    </div>
  </div>

  <!-- Split per KM -->
  <div class="kk-card">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <strong><i class="bi bi-flag-fill text-warning"></i> Split per Kilometer</strong>
    </div>
    <div id="d-splits"><div class="text-muted small">Belum ada split.</div></div>
  </div>

  <!-- Riwayat -->
  <details class="kk-card history-kk" <?= $history ? 'open' : '' ?>>
    <summary class="d-flex justify-content-between align-items-center" style="cursor:pointer;list-style:revert">
      <span><i class="bi bi-clock-history"></i> Riwayat Tracking</span>
      <small class="text-muted">GPX · KML · GeoJSON</small>
    </summary>
    <div class="list-group list-group-flush mt-2">
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
     FOCUS MODE OVERLAY (glass) — hanya tampil saat body.kk-focus-mode
     ================================================================ -->
<button class="kk-exit-focus" id="kk-exit-focus" aria-label="Exit Fullscreen">
  <i class="bi bi-x-lg"></i> Exit Fullscreen
</button>

<div class="kk-focus-chips">
  <!-- Chip GPS di focus dipinjam dari kk-chips (fixed via CSS). Tidak duplikat DOM. -->
</div>

<div class="kk-focus-overlay">
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

<!-- Floating control bar (Focus Mode) -->
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

<!-- Tombol Start "asli" tersembunyi — dipicu oleh tombol Dashboard -->
<button id="kk-btn-start" style="display:none" aria-hidden="true"></button>

<!-- Lock screen overlay -->
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

<!-- Auto-dim overlay (focus only) -->
<div class="kk-dim" id="kk-dim"></div>

<!-- Countdown -->
<div class="kk-countdown" id="kk-countdown">3</div>

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
      <a class="btn btn-primary" id="f-btn-review" href="/upload.php"><i class="bi bi-cloud-arrow-up"></i> Review &amp; Upload</a>
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
  mapboxAttr: '&copy; <a href="https://www.mapbox.com/about/maps/">Mapbox</a> &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
  polylineColor: '#1E90FF'
};
window.KK_RUN.mapboxTileUrl =
  'https://api.mapbox.com/styles/v1/mapbox/outdoors-v12/tiles/256/{z}/{x}/{y}@2x?access_token=' +
  window.KK_RUN.mapboxToken;
document.body.classList.add('kk-run-page');
</script>

<!-- Modul JS (urutan penting: save → voice → map → gps → background → ui → tracking) -->
<script src="/assets/js/run/save.js?v=r35"></script>
<script src="/assets/js/run/voice.js?v=r35"></script>
<script src="/assets/js/run/map.js?v=r35"></script>
<script src="/assets/js/run/gps.js?v=r35"></script>
<script src="/assets/js/run/background.js?v=r35"></script>
<script src="/assets/js/run/ui.js?v=r35"></script>
<script src="/assets/js/run/tracking.js?v=r35"></script>

<script>
/* ---- Sinkronkan tombol Pause/Resume Dashboard dengan tombol Focus asli ---- */
document.addEventListener('DOMContentLoaded', function(){
  var focusPause  = document.getElementById('kk-btn-pause');
  var focusResume = document.getElementById('kk-btn-resume');
  var dashPause   = document.getElementById('kk-dash-btn-pause');
  var dashResume  = document.getElementById('kk-dash-btn-resume');
  var dashStart   = document.getElementById('kk-dash-btn-start');
  var dashStop    = document.getElementById('kk-dash-btn-stop');
  var dashLoc     = document.getElementById('kk-dash-btn-mylocation');

  /* ---- FIX: Tombol Mulai tidak jalan ----
   * Penyebab: `forward()` di ui.js meneruskan klik ke tombol tersembunyi
   * `#kk-btn-start`. Pada beberapa kasus (event handler belum ter-attach saat
   * DOMContentLoaded urut, atau tombol tersembunyi tidak menerima click
   * sintetis di sebagian browser), rantai ini gagal diam-diam.
   * Solusi: wiring langsung + fallback trigger tombol tersembunyi. */
  function safeClickHidden(id){
    var t = document.getElementById(id);
    if (!t) return false;
    try { t.click(); return true; } catch(e){ return false; }
  }
  if (dashStart) {
    dashStart.addEventListener('click', function(ev){
      ev.preventDefault();
      // 1) trigger tombol tersembunyi (jalur lama)
      safeClickHidden('kk-btn-start');
      // 2) fallback: dispatch event bubbling agar handler yang terpasang
      //    di listener document juga menerima
      var t = document.getElementById('kk-btn-start');
      if (t) t.dispatchEvent(new MouseEvent('click', {bubbles:true, cancelable:true}));
    });
  }
  if (dashPause)  dashPause.addEventListener('click',  function(){ safeClickHidden('kk-btn-pause'); });
  if (dashResume) dashResume.addEventListener('click', function(){ safeClickHidden('kk-btn-resume'); });
  if (dashStop)   dashStop.addEventListener('click',   function(){ safeClickHidden('kk-btn-stop'); });

  /* ---- Tombol "Lokasi Saya Sekarang" ----
   * Ambil GPS sekali & pusatkan peta. Bekerja meski sesi belum dimulai. */
  if (dashLoc) {
    dashLoc.addEventListener('click', function(){
      if (!navigator.geolocation){ alert('Browser tidak mendukung GPS'); return; }
      var orig = dashLoc.innerHTML;
      dashLoc.disabled = true;
      dashLoc.innerHTML = '<i class="bi bi-hourglass-split"></i> Mencari lokasi…';
      navigator.geolocation.getCurrentPosition(function(pos){
        var p = { lat: pos.coords.latitude, lng: pos.coords.longitude, acc: pos.coords.accuracy };
        try {
          if (window.KKTracking && window.KKTracking.state){
            window.KKTracking.state.lastFix = p;
          }
          if (window.KKMap && typeof KKMap.recenter === 'function'){
            KKMap.recenter(p);
          } else if (window.KKMap && KKMap._map){
            KKMap._map.setView([p.lat, p.lng], 16);
          }
        } catch(e){}
        dashLoc.disabled = false;
        dashLoc.innerHTML = orig;
      }, function(err){
        dashLoc.disabled = false;
        dashLoc.innerHTML = orig;
        alert('Gagal mendapatkan lokasi: ' + (err && err.message ? err.message : 'unknown'));
      }, { enableHighAccuracy:true, timeout:15000, maximumAge:0 });
    });
  }

  function sync(){
    var isPaused = focusPause && focusPause.style.display === 'none' && focusResume && focusResume.style.display !== 'none';
    var running  = !!(window.KKTracking && window.KKTracking.state && window.KKTracking.state.sessionId);
    if (dashStart)  dashStart.style.display  = running ? 'none' : '';
    if (dashPause)  dashPause.style.display  = (running && !isPaused) ? '' : 'none';
    if (dashResume) dashResume.style.display = (running && isPaused) ? '' : 'none';
  }
  // Poll sederhana — kompatibel tanpa mengubah tracking.js
  setInterval(sync, 500);
  sync();
});

/* ---- Hapus riwayat ---- */
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
