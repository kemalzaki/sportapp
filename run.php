<?php
/* =====================================================================
 * KawanKeringat — Tracking Jalur (REVISI R40 — Refactor Bersih)
 * ---------------------------------------------------------------------
 * Prinsip refactor:
 *  - SATU source of truth untuk tombol tracking (#kk-btn-start /
 *    #kk-btn-pause / #kk-btn-resume / #kk-btn-stop). Tidak ada hidden
 *    button, tidak ada dispatchEvent(), tidak ada safeClickHidden(),
 *    tidak ada override function existing.
 *  - Dashboard Mode & Focus Mode HANYA berbeda CSS class di <body>.
 *    Tombol yang sama dipakai kedua mode. Leaflet TIDAK pernah di
 *    destroy / recreate — hanya map.invalidateSize().
 *  - Floating map controls (Follow / Compass / Fullscreen / Settings /
 *    Recenter) ditempatkan di luar #kk-map sehingga event Leaflet
 *    TIDAK menangkap klik. map.js juga memasang
 *    L.DomEvent.disableClickPropagation() sebagai safety net.
 *  - Focus Mode: statistik hadir sebagai floating glass card KECIL di
 *    atas layar (tidak fullscreen), tidak menutupi tombol map, tidak
 *    menghalangi klik.
 *  - HTML valid: hanya satu <body> (dari header.php), tidak ada
 *    duplicate id.
 *  - CSS: shadow ringan, border-radius 20px, glass effect seperlunya.
 *  - PostgreSQL: tidak ada perubahan schema. Tabel run_sessions +
 *    run_points + endpoint api_run.php tetap dipakai apa adanya.
 * ===================================================================== */
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
send_security_headers(); require_login();
$u = current_user(); $uid = (int)$u['id'];
$pageTitle = 'Rekam Jogging';
$pageSkeleton = 'grid';

require_once __DIR__.'/includes/paket_helpers.php';
if (!isset($u) || !$u) { require_login(); $u = current_user(); }
paket_require_or_lock('komunitas', $u, 'Rekam Jogging',
    'Rekam Jogging & Eksplorasi Rute tersedia untuk paket Komunitas.');

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
   Design tokens
   ================================================================ */
.kk-run-page{
  --kk-navy:#0b1220; --kk-blue:#1E90FF; --kk-blue-2:#4FB0FF;
  --kk-light:#BFE0FF; --kk-ink:#0f172a; --kk-muted:#64748b;
  --kk-line:#e2e8f0; --kk-bg-soft:#f8fafc;
  --kk-shadow-sm:0 2px 8px rgba(15,23,42,.06);
  --kk-shadow-md:0 6px 18px rgba(15,23,42,.08);
  --kk-radius:20px;
}

/* ================================================================
   Dashboard layout (default)
   ================================================================ */
.kk-dash-wrap{max-width:960px;margin:0 auto;padding:12px 12px 90px;}
.kk-dash-title{display:flex;align-items:center;justify-content:space-between;
  gap:8px;flex-wrap:wrap;margin-bottom:10px;}
.kk-dash-title h4{margin:0;font-weight:800;color:var(--kk-ink);}
.kk-card{background:#fff;border:0;border-radius:var(--kk-radius);
  box-shadow:var(--kk-shadow-md);padding:16px;margin-bottom:12px;}
.stat-label{font-size:.68rem;letter-spacing:.1em;text-transform:uppercase;
  color:var(--kk-muted);font-weight:700;margin-bottom:.15rem;}
.kk-primary-stat{text-align:center;padding:6px 0 4px;}
.kk-primary-stat .val{font-size:3.2rem;font-weight:900;line-height:1;
  font-variant-numeric:tabular-nums;color:var(--kk-ink);letter-spacing:-.02em;}
.kk-primary-stat .unit{font-size:1rem;color:var(--kk-muted);font-weight:700;margin-left:6px;}
.kk-primary-stat .lbl{font-size:.72rem;letter-spacing:.14em;text-transform:uppercase;
  color:var(--kk-muted);font-weight:700;margin-top:4px;}
.kk-stat-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-top:10px;}
.kk-stat-cell{background:var(--kk-bg-soft);border-radius:14px;padding:10px 6px;text-align:center;}
.kk-stat-cell .val{font-size:1.15rem;font-weight:800;color:var(--kk-ink);font-variant-numeric:tabular-nums;}
.kk-stat-cell .lbl{font-size:.6rem;letter-spacing:.1em;text-transform:uppercase;
  color:var(--kk-muted);font-weight:700;margin-top:2px;}

.kk-chip{background:#0f172a;color:#fff;padding:5px 10px;border-radius:999px;
  font-size:.7rem;font-weight:700;letter-spacing:.04em;display:inline-flex;align-items:center;gap:5px;}
.kk-chip.status-ok{background:#22c55e;}
.kk-chip.status-warn{background:#eab308;color:#111;}
.kk-chip.status-bad{background:#ef4444;}
#d-mode-chip{background:#ef4444;color:#fff;animation:kkRecBlink 1.4s ease-in-out infinite;}
@keyframes kkRecBlink{0%,100%{opacity:1}50%{opacity:.6}}

/* Map wrapper (dashboard) */
.kk-mapwrap{position:relative;border-radius:var(--kk-radius);
  height:38vh;min-height:260px;background:#0f172a;
  box-shadow:var(--kk-shadow-md);overflow:hidden;}
#kk-map{position:absolute;inset:0;z-index:0;}
.kk-map-rot{transition:transform .35s cubic-bezier(.25,.9,.3,1);
  transform-origin:50% 50%;will-change:transform;}

/* Chips atas peta */
.kk-chips{position:absolute;left:10px;top:10px;display:flex;gap:6px;
  z-index:600;pointer-events:auto;}
.kk-chips .kk-chip{backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);
  background:rgba(15,23,42,.72);}
.kk-chips .kk-chip.status-ok{background:rgba(34,197,94,.9);}
.kk-chips .kk-chip.status-warn{background:rgba(234,179,8,.95);color:#111;}
.kk-chips .kk-chip.status-bad{background:rgba(239,68,68,.9);}

/* Floating map controls (kanan atas) — z-index di atas leaflet controls (1000) */
/* Dashboard mode: z-index rendah agar tidak menembus header/chips fixed
   di atas peta. Focus mode meng-override ke 1300 (lihat di bawah). */
.kk-mapfabs{position:absolute;right:10px;top:10px;z-index:500;
  display:flex;flex-direction:column;gap:8px;pointer-events:auto;}
.kk-mapfab{position:relative;width:44px;height:44px;border-radius:50%;
  background:#ffffff;color:var(--kk-ink);font-size:1.05rem;
  border:1px solid rgba(15,23,42,.08);
  display:inline-flex;align-items:center;justify-content:center;
  box-shadow:var(--kk-shadow-md);cursor:pointer;
  transition:transform .15s ease, background .2s;pointer-events:auto;}
.kk-mapfab:hover{transform:translateY(-1px);background:var(--kk-bg-soft);}
.kk-mapfab.active{background:var(--kk-blue);color:#fff;
  box-shadow:0 6px 16px rgba(30,144,255,.35);}
.kk-mapfab:active{transform:scale(.94);}

/* Leaflet default controls: sembunyikan zoom bawaan agar tidak menabrak fab */
#kk-map .leaflet-control-zoom{display:none;}
#kk-map .leaflet-top,#kk-map .leaflet-bottom{z-index:400;}
#kk-map .leaflet-control-attribution{z-index:400;}

/* Settings popover */
.kk-settings-pop{position:absolute;right:64px;top:60px;z-index:1300;
  background:#fff;border-radius:14px;padding:12px;min-width:200px;
  box-shadow:0 10px 24px rgba(15,23,42,.16);
  display:none;pointer-events:auto;}
.kk-settings-pop.show{display:block;}
.kk-settings-pop label{font-size:.7rem;letter-spacing:.08em;text-transform:uppercase;
  color:var(--kk-muted);font-weight:700;margin-bottom:4px;display:block;}
.kk-settings-pop select,.kk-settings-pop input{width:100%;border:1px solid var(--kk-line);
  border-radius:10px;padding:6px 8px;font-size:.85rem;margin-bottom:8px;}

/* Recenter pill */
.kk-recenter{position:absolute;right:14px;bottom:14px;z-index:1200;
  background:#fff;color:var(--kk-ink);border:0;border-radius:999px;
  padding:8px 12px;font-weight:700;font-size:.8rem;
  box-shadow:var(--kk-shadow-md);display:none;align-items:center;gap:6px;
  pointer-events:auto;cursor:pointer;}
.kk-recenter.show{display:inline-flex;}

/* Tombol utama tracking */
.kk-controls{display:flex;gap:10px;flex-wrap:wrap;justify-content:center;}
.kk-btn{border:0;border-radius:16px;padding:14px 20px;font-weight:800;
  letter-spacing:.02em;font-size:1rem;color:#fff;min-width:140px;
  display:inline-flex;align-items:center;justify-content:center;gap:8px;
  box-shadow:var(--kk-shadow-sm);transition:transform .15s ease;cursor:pointer;}
.kk-btn:hover{transform:translateY(-1px);}
.kk-btn.start{background:linear-gradient(135deg,#1E90FF,#4FB0FF);
  box-shadow:0 6px 18px rgba(30,144,255,.35);font-size:1.05rem;padding:14px 26px;}
.kk-btn.pause{background:#f59e0b;}
.kk-btn.resume{background:#0ea5e9;}
.kk-btn.stop{background:#ef4444;}
.kk-btn.loc{background:#0ea5e9;}

/* Split & history */
.kk-split-row{display:flex;align-items:center;padding:8px 4px;border-bottom:1px solid #f1f5f9;font-size:.9rem;}
.kk-split-row:last-child{border-bottom:0;}
.kk-split-row .km{width:60px;font-weight:800;color:#334155;}
.kk-split-row .bar{flex:1;height:8px;background:#e2e8f0;border-radius:999px;margin:0 12px;overflow:hidden;}
.kk-split-row .bar > i{display:block;height:100%;background:var(--kk-blue);border-radius:999px;}
.kk-split-row .pace{font-variant-numeric:tabular-nums;font-weight:800;color:var(--kk-ink);min-width:70px;text-align:right;}
.history-kk .list-group-item{border:0;border-bottom:1px solid #f1f5f9;padding:.85rem 1rem;}

/* ================================================================
   FOCUS MODE — HANYA CSS. Tidak ada HTML duplikat. Elemen yang sama
   dengan Dashboard (#kk-stats-card, #kk-map, tombol) dipindahkan
   via position:fixed sehingga seluruh binding JavaScript tetap
   berfungsi (tracking.js meng-update id d-dist/d-time/dst).
   ================================================================ */
body.kk-focus-mode{overflow:hidden;}
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
body.kk-focus-mode footer,
body.kk-focus-mode .site-footer,
/* Top-nav & bottom-nav proyek (gojek-top / gj-nav) — selector spesifik
   dengan html body agar menang atas !important di includes/header.php. */
body.kk-focus-mode .gt-top,
body.kk-focus-mode .gt-chips,
body.kk-focus-mode .gj-nav,
html body.kk-focus-mode header.gt-top,
html body.kk-focus-mode nav.gt-chips,
html body.kk-focus-mode nav.gj-nav,
body.kk-focus-mode .kk-hide-in-focus{
  display:none !important;
}
/* Sembunyikan judul + wrapper spacing dashboard tapi TIDAK card stats/map/controls */
body.kk-focus-mode .kk-dash-wrap{max-width:none;padding:0;margin:0;}

/* Peta jadi fullscreen — Leaflet TIDAK di destroy, hanya CSS */
body.kk-focus-mode .kk-mapwrap{
  position:fixed !important;inset:0 !important;
  height:100vh !important;width:100vw !important;
  border-radius:0 !important;z-index:900;box-shadow:none;
}
body.kk-focus-mode .kk-mapwrap.kk-card,
body.kk-focus-mode .kk-card:has(> #kk-mapwrap){
  position:fixed !important;inset:0 !important;padding:0 !important;
  margin:0 !important;border-radius:0 !important;z-index:900;
}

/* ---- Card Statistik Dashboard dipindahkan ke atas peta ----
   Layer order (Focus Mode):
     Map (900) < Stats card (1100) < Chips/Fabs/Settings (1300+)
                                    < Controls card Pause/Selesai (1400)
   Stats card di-inset dari kanan (right:64px) supaya tidak menutupi
   kolom floating controls (fabs). pointer-events:none pada card,
   sehingga bila terjadi overlap tetap tidak memblokir klik peta/fab. */
body.kk-focus-mode #kk-stats-card{
  position:fixed !important;
  top:auto !important;
  bottom:calc(110px + env(safe-area-inset-bottom,0px));
  left:10px; right:10px; width:auto; max-width:none; margin:0 !important;
  z-index:1100; height:auto;
  padding:6px 10px 7px !important;
  background:rgba(11,18,32,.72) !important;
  color:#fff !important;
  border:1px solid rgba(191,224,255,.16);
  border-radius:12px !important;
  box-shadow:0 6px 18px rgba(0,0,0,.30) !important;
  backdrop-filter:blur(14px) saturate(140%);
  -webkit-backdrop-filter:blur(14px) saturate(140%);
  pointer-events:none;
}
body.kk-focus-mode #kk-stats-card .stat-label,
body.kk-focus-mode #kk-stats-card .kk-primary-stat .unit,
body.kk-focus-mode #kk-stats-card .kk-primary-stat .lbl,
body.kk-focus-mode #kk-stats-card .kk-stat-cell .lbl{
  color:var(--kk-light) !important;
}
body.kk-focus-mode #kk-stats-card > .d-flex{margin-bottom:1px !important;}
body.kk-focus-mode #kk-stats-card .stat-label{display:none;}
body.kk-focus-mode #kk-stats-card .kk-primary-stat{padding:0;}
body.kk-focus-mode #kk-stats-card .kk-primary-stat .val{
  font-size:1.15rem;color:#fff;line-height:1;
}
body.kk-focus-mode #kk-stats-card .kk-primary-stat .unit{
  font-size:.62rem;margin-left:3px;
}
body.kk-focus-mode #kk-stats-card .kk-primary-stat .lbl{
  font-size:.5rem;letter-spacing:.1em;margin-top:0;
}
body.kk-focus-mode #kk-stats-card .kk-stat-grid{
  grid-template-columns:repeat(6,1fr);gap:3px;margin-top:3px;
}
body.kk-focus-mode #kk-stats-card .kk-stat-cell{
  background:rgba(255,255,255,.07);border-radius:6px;padding:2px 1px;
}
body.kk-focus-mode #kk-stats-card .kk-stat-cell .val{
  color:#fff;font-size:.7rem;line-height:1.05;
}
body.kk-focus-mode #kk-stats-card .kk-stat-cell .lbl{
  font-size:.46rem;letter-spacing:.04em;margin-top:0;
}

/* Kontrol tracking mengambang di bawah saat Focus (paling atas) */
body.kk-focus-mode .kk-controls-card{
  position:fixed;left:0;right:0;bottom:0;z-index:1400;
  background:linear-gradient(to top,rgba(11,18,32,.92) 30%,rgba(11,18,32,0));
  border-radius:0;box-shadow:none;
  padding:14px 16px calc(20px + env(safe-area-inset-bottom,0px));
  margin:0;
}
body.kk-focus-mode .kk-controls-card .kk-controls-hint{color:#cbd5e1;}
body.kk-focus-mode .kk-controls-card .kk-btn.loc{display:none;} /* tombol lokasi hanya dashboard */

/* Floating map controls SELALU di atas panel statistik saat focus */
body.kk-focus-mode .kk-mapfabs{z-index:1300;}
body.kk-focus-mode .kk-chips{z-index:1300;}
body.kk-focus-mode .kk-settings-pop{z-index:1450;}
body.kk-focus-mode .kk-recenter{z-index:1300;bottom:190px;}

/* Small screens: statistik lebih ringkas lagi */
@media (max-width:380px){
  body.kk-focus-mode #kk-stats-card{padding:5px 8px 6px !important;}
  body.kk-focus-mode #kk-stats-card .kk-primary-stat .val{font-size:1rem;}
  body.kk-focus-mode #kk-stats-card .kk-stat-cell .val{font-size:.64rem;}
}

/* Marker pelari (biru KK) */
.kk-runner{width:26px;height:26px;border-radius:50%;background:#1E90FF;
  border:3px solid #fff;box-shadow:0 0 0 3px rgba(30,144,255,.35),0 3px 10px rgba(0,0,0,.4);position:relative;}
.kk-runner::after{content:"";position:absolute;left:50%;top:-12px;transform:translateX(-50%);
  width:0;height:0;border-left:6px solid transparent;border-right:6px solid transparent;
  border-bottom:10px solid #1E90FF;filter:drop-shadow(0 -1px 0 #fff);}
.leaflet-marker-icon.kk-runner-icon{transition:transform .9s linear;}
.leaflet-overlay-pane path.leaflet-interactive{stroke:#1E90FF;}

/* Marker "Lokasi Saya Sekarang" */
.kk-mylocation-icon{background:transparent!important;border:0!important;}
.kk-mylocation-dot{position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);
  width:16px;height:16px;border-radius:50%;background:#1E90FF;
  border:3px solid #fff;box-shadow:0 0 0 2px rgba(30,144,255,.35),0 2px 8px rgba(0,0,0,.35);z-index:2;}
.kk-mylocation-pulse{position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);
  width:22px;height:22px;border-radius:50%;background:rgba(30,144,255,.35);
  animation:kkMyLocPulse 1.8s ease-out infinite;z-index:1;}
@keyframes kkMyLocPulse{
  0%{transform:translate(-50%,-50%) scale(.6);opacity:.9;}
  100%{transform:translate(-50%,-50%) scale(2.6);opacity:0;}
}

/* Countdown */
.kk-countdown{position:fixed;inset:0;z-index:2000;background:rgba(2,6,23,.85);
  display:none;align-items:center;justify-content:center;color:#fff;
  font-size:8rem;font-weight:900;font-variant-numeric:tabular-nums;
  text-shadow:0 6px 30px rgba(0,0,0,.5);}
.kk-countdown.show{display:flex;animation:kkCd .9s ease;}
@keyframes kkCd{from{transform:scale(1.6);opacity:.2}to{transform:scale(1);opacity:1}}

/* Finish screen */
#kk-finish{position:fixed;inset:0;z-index:2100;display:none;overflow-y:auto;
  background:linear-gradient(160deg,#050a17 0%,#081223 45%,#0d2a5a 100%);color:#e2e8f0;}
body.kk-finish-open{overflow:hidden;}
body.kk-finish-open #kk-finish{display:block;}
.kk-finish-hero{position:relative;height:40vh;min-height:260px;overflow:hidden;background:#081223;}
#kk-finish-map{position:absolute;inset:0;}
.kk-finish-back{position:absolute;top:calc(env(safe-area-inset-top,0px) + 10px);left:12px;z-index:6;
  background:rgba(255,255,255,.14);color:#fff;border:1px solid rgba(191,224,255,.28);
  border-radius:999px;padding:8px 12px;font-weight:700;cursor:pointer;
  backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px);}
.kk-finish-body{padding:20px 16px 40px;max-width:760px;margin:0 auto;}
.kk-finish-body h4{color:#fff;letter-spacing:-.01em;}
.kk-finish-body .text-muted{color:#94a3b8 !important;}
.kk-summary{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:14px;}
.kk-summary .c{background:rgba(30,144,255,.10);border:1px solid rgba(191,224,255,.16);
  border-radius:16px;padding:14px 10px;text-align:center;}
.kk-summary .c .v{font-size:1.6rem;font-weight:900;color:#fff;font-variant-numeric:tabular-nums;}
.kk-summary .c .l{font-size:.7rem;letter-spacing:.1em;text-transform:uppercase;color:var(--kk-light);font-weight:800;}
.kk-chart-card{background:rgba(13,26,51,.7);border:1px solid rgba(191,224,255,.14);
  border-radius:14px;padding:14px;margin-bottom:12px;color:#e2e8f0;}
.kk-chart-card h6{margin:0 0 8px;font-weight:800;color:#fff;}
.kk-chart-card canvas{width:100%;height:120px;display:block;}
.kk-chart-card .kk-split-row{border-bottom-color:rgba(191,224,255,.10);}
.kk-chart-card .kk-split-row .km{color:var(--kk-light);}
.kk-chart-card .kk-split-row .pace{color:#fff;}
.kk-chart-card .kk-split-row .bar{background:rgba(191,224,255,.14);}
.kk-chart-card .kk-split-row .bar > i{background:linear-gradient(90deg,#1E90FF,#4FB0FF);}
.kk-finish-cta{display:flex;gap:10px;margin-top:14px;}
.kk-finish-cta .btn{flex:1;border-radius:14px;padding:14px;font-weight:800;}
.kk-finish-cta .btn-primary{background:linear-gradient(135deg,#1E90FF,#4FB0FF);border:0;}
.kk-finish-cta .btn-outline-secondary{background:rgba(255,255,255,.06);color:#e2e8f0;
  border:1px solid rgba(191,224,255,.24);}

@media (max-width:400px){
  .kk-primary-stat .val{font-size:2.6rem;}
  body.kk-focus-mode #kk-stats-card .kk-primary-stat .val{font-size:1.1rem;}
}
</style>

<!-- Body class + skeleton -->
<script>document.body.classList.add('kk-run-page','kk-dashboard-mode');</script>

<!-- ================================================================
     DASHBOARD SHELL (default). Urutan:
     Statistik → Map → Control Tracking → Split → Riwayat
     ================================================================ -->
<div class="kk-dash-wrap">
  <div class="kk-dash-title kk-hide-in-focus">
    <h4><i class="bi bi-stopwatch text-primary"></i> Rekam Jogging</h4>
  </div>

  <div id="kk-bg-warn" class="alert alert-warning small d-none kk-hide-in-focus">
    <i class="bi bi-exclamation-triangle-fill"></i>
    Tracking background terbatas di browser. Untuk pengalaman penuh (GPS jalan
    saat layar mati, bubble melayang, notification permanen), gunakan
    <strong>APK KawanKeringat</strong>.
  </div>

  <!-- Panel Statistik — SATU card, dipakai Dashboard & Focus Mode.
       Di Focus Mode card ini dipindahkan via CSS position:fixed.
       SEMUA id (d-*) tetap sama, sehingga tracking.js/ui.js
       tidak perlu tahu mode apa yang sedang aktif. -->
  <div class="kk-card" id="kk-stats-card">
    <div class="d-flex justify-content-between align-items-start mb-2">
      <span class="stat-label">Sesi Berjalan</span>
      <span class="kk-chip" id="d-mode-chip" style="display:none">▶ REC</span>
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

  <!-- Map (dipakai kedua mode — Leaflet TIDAK pernah di-destroy) -->
  <div class="kk-card" style="padding:6px;">
    <div class="kk-mapwrap" id="kk-mapwrap">
      <div id="kk-map"></div>

      <!-- Chips atas (kiri) -->
      <div class="kk-chips">
        <span class="kk-chip" id="kk-gps-chip">🟡 GPS…</span>
        <span class="kk-chip" id="kk-mode-chip" style="display:none">▶ Rekaman</span>
        <span class="kk-chip" id="kk-auto-chip" status="hidden" style="display:none">Auto-Pause</span>
      </div>

      <!-- Floating Map Controls (kanan atas) — di LUAR #kk-map -->
      <div class="kk-mapfabs" id="kk-mapfabs">
        <button type="button" class="kk-mapfab" id="kk-fab-location" title="Follow My Location" aria-label="Follow My Location"><i class="bi bi-cursor-fill"></i></button>
        <button type="button" class="kk-mapfab" id="kk-fab-compass"  title="Compass"            aria-label="Toggle Compass"><i class="bi bi-compass"></i></button>
        <button type="button" class="kk-mapfab" id="kk-fab-fullscreen" title="Fullscreen"       aria-label="Toggle Fullscreen"><i class="bi bi-arrows-fullscreen"></i></button>
        <button type="button" class="kk-mapfab" id="kk-fab-settings" title="Settings"           aria-label="Tracking Settings"><i class="bi bi-gear-fill"></i></button>
      </div>

      <!-- Settings popover -->
      <div class="kk-settings-pop" id="kk-settings-pop">
        <label>Jenis Olahraga</label>
        <select id="sportSel">
          <!-- Revisi R43 Juli 2026 — hanya Jogging (business logic tracking tetap: value="jog"). -->
          <option value="jog" selected>Jogging</option>
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
          value="<?= htmlspecialchars((string)$userWeight) ?>">
      </div>

      <!-- Recenter pill -->
      <button type="button" class="kk-recenter" id="kk-recenter">
        <i class="bi bi-crosshair"></i> Kembali ke Posisi Saya
      </button>
    </div>
  </div>

  <!-- Kontrol Tracking (SATU set tombol, dipakai kedua mode) -->
  <div class="kk-card kk-controls-card">
    <div class="kk-controls">
      <button type="button" class="kk-btn start"  id="kk-btn-start"><i class="bi bi-play-fill"></i> Mulai</button>
      <button type="button" class="kk-btn pause"  id="kk-btn-pause"  style="display:none"><i class="bi bi-pause-fill"></i> Jeda</button>
      <button type="button" class="kk-btn resume" id="kk-btn-resume" style="display:none"><i class="bi bi-play-fill"></i> Lanjut</button>
      <button type="button" class="kk-btn stop"   id="kk-btn-stop"   style="display:none"><i class="bi bi-stop-fill"></i> Selesai</button>
      <button type="button" class="kk-btn loc"    id="kk-btn-mylocation"><i class="bi bi-geo-alt-fill"></i> Lokasi Saya Sekarang</button>
    </div>
    <div class="text-center text-muted small mt-2 kk-controls-hint kk-hide-in-focus">
      Tekan <i class="bi bi-arrows-fullscreen"></i> pada peta untuk masuk mode Fullscreen kapan saja.
    </div>
  </div>

  <!-- Split per KM -->
  <div class="kk-card kk-hide-in-focus">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <strong><i class="bi bi-flag-fill text-warning"></i> Split per Kilometer</strong>
    </div>
    <div id="d-splits"><div class="text-muted small">Belum ada split.</div></div>
  </div>

  <!-- Riwayat -->
  <details class="kk-card history-kk kk-hide-in-focus" <?= $history ? 'open' : '' ?>>
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

<!-- Catatan: TIDAK ada HTML floating stats terpisah untuk Focus Mode.
     Card statistik #kk-stats-card di atas dipindahkan via CSS. -->


<!-- Countdown -->
<div class="kk-countdown" id="kk-countdown">3</div>

<!-- ================================================================
     FINISH SCREEN
     ================================================================ -->
<div id="kk-finish" aria-hidden="true">
  <div class="kk-finish-hero">
    <div id="kk-finish-map"></div>
    <button type="button" class="kk-finish-back" id="kk-finish-back"><i class="bi bi-x-lg"></i> Tutup</button>
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
      <button type="button" class="btn btn-outline-secondary" id="f-btn-discard"><i class="bi bi-trash"></i> Buang</button>
      <a class="btn btn-primary" id="f-btn-review" href="/upload.php"><i class="bi bi-cloud-arrow-up"></i> Review &amp; Upload</a>
    </div>
  </div>
</div>

<!-- ================================================================
     Konfigurasi untuk modul JS
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
</script>

<!-- Urutan modul: save → voice → map → gps → background → ui → tracking -->
<script src="/assets/js/run/save.js?v=r52"></script>
<script src="/assets/js/run/voice.js?v=r49"></script>
<script src="/assets/js/run/map.js?v=r49"></script>
<script src="/assets/js/run/gps.js?v=r49"></script>
<script src="/assets/js/run/background.js?v=r49"></script>
<script src="/assets/js/run/ui.js?v=r52"></script>
<script src="/assets/js/run/tracking.js?v=r50"></script>

<!-- Hapus riwayat -->
<script>
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
