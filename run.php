<?php
/* =====================================================================
 * KawanKeringat — Tracking Jalur (REVISI R35 — Dashboard + Focus Mode)
 * ---------------------------------------------------------------------
 * Perubahan dibanding R34:
 *  - Dashboard Mode (default) saat halaman dibuka:
 *      Statistik + Mini Map (30–40% tinggi layar) + tombol Mulai/Pause/Stop
 *      + Split per KM + Riwayat + panel lain tetap terlihat.
 *      Header & Bottom Nav tetap tampil. Bukan fullscreen.
 *  - Focus Mode (fullscreen) via tombol floating ⛶ pada peta:
 *      Peta jadi fullscreen, header/bottom nav disembunyikan, statistik
 *      berubah menjadi floating glass overlay, tombol Pause/Stop floating.
 *      Perpindahan mode hanya toggle CSS class (tanpa destroy Leaflet).
 *      Timer/GPS/Marker/Polyline TIDAK direset saat pindah mode.
 *  - Floating map controls (kanan atas peta): Follow, Compass,
 *      Fullscreen toggle, Settings. Bulat, hover / ripple / glow.
 *  - Identitas visual KawanKeringat:
 *      Dark Navy #081223, Electric Blue #1E90FF, Light Blue, White.
 *      Dashboard bg = gradasi Navy → Electric Blue → Light Blue.
 *      Tile Leaflet TIDAK diberi overlay gelap (tetap normal).
 *      Focus Mode = background dark navy, glassmorphism, polyline
 *      electric blue, marker branded, status GPS hijau, REC merah
 *      berkedip halus, tombol aktif glow biru.
 *  - Mode terakhir disimpan di localStorage → dipulihkan saat halaman
 *      dibuka ulang.
 *
 * KONSTRAIN:
 *  - gps.js / tracking.js / map.js / save.js / background.js / voice.js
 *    TIDAK diubah. Fungsi baru HANYA di ui.js.
 *  - Skema DB tidak berubah. Tidak ada migrasi SQL.
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
/* ============================================================
 * KawanKeringat — Design Tokens (R35)
 * ============================================================ */
:root{
  --kk-navy:#081223;
  --kk-navy-2:#0d1a33;
  --kk-blue:#1E90FF;
  --kk-blue-2:#4FB0FF;
  --kk-light:#BFE0FF;
  --kk-white:#ffffff;
  --kk-rec:#ef4444;
  --kk-ok:#22c55e;
  --kk-radius:18px;
  --kk-radius-sm:14px;
  /* Flat & premium — shadow tipis, tanpa glow besar */
  --kk-shadow-soft:0 1px 2px rgba(8,18,35,.06), 0 4px 12px rgba(8,18,35,.06);
  --kk-shadow-glass:0 6px 20px rgba(2,6,23,.28), inset 0 1px 0 rgba(255,255,255,.06);
  --kk-glow-blue:0 1px 2px rgba(30,144,255,.18);
  --kk-transition:all .2s ease;
  --kk-gradient-page:linear-gradient(160deg,#081223 0%,#0d2547 55%,#1E90FF 100%);
  --kk-gradient-panel:linear-gradient(180deg,#ffffff,#fafcff);
}

/* ============================================================
 * Dashboard Mode — background halaman + panel bergaya KK
 * (Peta Leaflet TIDAK ikut kena overlay gelap)
 * ============================================================ */
body.kk-run-page{
  background:var(--kk-gradient-page) fixed;
}
#kk-pretrack-shell{
  color:#e5efff;
  padding-bottom:24px;
}
#kk-pretrack-shell .kk-page-title{
  color:#fff;font-weight:800;letter-spacing:.01em;
  text-shadow:0 2px 12px rgba(8,18,35,.35);
}
#kk-pretrack-shell .kk-page-sub{color:rgba(230,240,255,.75);font-size:.85rem;}

/* Panel style (kartu) */
.kk-card{
  border:0;border-radius:var(--kk-radius);
  background:var(--kk-gradient-panel);
  box-shadow:var(--kk-shadow-soft);
  backdrop-filter:blur(8px);
  color:#0f172a;
}
.kk-card > .card-body,.kk-card > .body{padding:1.1rem 1.2rem;}
.kk-card h5,.kk-card h6{color:var(--kk-navy);font-weight:800;}

.kk-stat-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;}
@media (max-width:640px){.kk-stat-grid{grid-template-columns:repeat(2,1fr);}}
.kk-stat{
  border-radius:var(--kk-radius-sm);
  background:linear-gradient(180deg,#ffffff,#f1f7ff);
  border:1px solid rgba(30,144,255,.14);
  padding:12px 14px;text-align:center;
  transition:var(--kk-transition);
}
.kk-stat:hover{transform:translateY(-2px);box-shadow:0 8px 20px rgba(30,144,255,.18);}
.kk-stat .v{font-size:1.55rem;font-weight:900;color:var(--kk-navy);font-variant-numeric:tabular-nums;line-height:1;}
.kk-stat .l{font-size:.68rem;letter-spacing:.12em;text-transform:uppercase;color:#64748b;font-weight:700;margin-top:6px;}

.stat-label{font-size:.72rem;letter-spacing:.08em;text-transform:uppercase;color:#64748b;font-weight:600;margin-bottom:.15rem;}

/* Tombol utama Mulai/Pause/Stop — flat, proporsional */
.kk-btn{
  border:0;border-radius:14px;
  padding:0 1.2rem;height:48px;font-weight:700;letter-spacing:.01em;
  transition:var(--kk-transition);color:#fff;font-size:.95rem;
  display:inline-flex;align-items:center;justify-content:center;gap:.5rem;
  box-shadow:0 1px 2px rgba(8,18,35,.08);
}
.kk-btn:hover{transform:translateY(-1px);box-shadow:0 3px 8px rgba(8,18,35,.12);}
.kk-btn-start{background:var(--kk-blue);height:52px;padding:0 1.6rem;font-size:1rem;}
.kk-btn-start:hover{background:var(--kk-blue-2);}
.kk-btn-pause{background:#f59e0b;}
.kk-btn-resume{background:var(--kk-blue);}
.kk-btn-stop{background:#ef4444;}
.kk-btn-ghost{background:rgba(30,144,255,.10);color:var(--kk-navy);box-shadow:none;}
.kk-btn-ghost:hover{background:rgba(30,144,255,.16);}

/* Settings row inputs */
.settings-row .form-control,.settings-row .form-select{border-radius:12px;border:1px solid rgba(30,144,255,.2);}
.settings-row .form-control:focus,.settings-row .form-select:focus{border-color:var(--kk-blue);box-shadow:0 0 0 3px rgba(30,144,255,.18);}

/* Riwayat rows */
.history-kk .list-group-item{border:0;border-bottom:1px solid #eef4fb;padding:.85rem 1rem;background:transparent;}

/* ============================================================
 * Track root — inline (Dashboard) vs fixed (Focus)
 * ============================================================ */
#kk-track-root{position:relative;border-radius:var(--kk-radius);overflow:hidden;
  background:#0f172a;box-shadow:var(--kk-shadow-soft);}
/* Dashboard: peta setinggi 38vh */
body:not(.kk-focus-mode) #kk-track-root{height:38vh;min-height:280px;max-height:520px;}
body:not(.kk-focus-mode) #kk-map{position:absolute;inset:0;}

/* Focus Mode — fullscreen */
body.kk-focus-mode{overflow:hidden !important;background:var(--kk-navy);}
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
body.kk-focus-mode #kk-dash-panels{
  display:none !important;
}
body.kk-focus-mode #kk-track-root{
  position:fixed !important;inset:0 !important;z-index:9998;
  height:100vh !important;max-height:none !important;
  border-radius:0;box-shadow:none;
}
body.kk-focus-mode #kk-map{position:absolute;inset:0;}

/* Rotasi map */
.kk-map-rot{transition:transform .35s cubic-bezier(.25,.9,.3,1);
  transform-origin:50% 50%;will-change:transform;}

/* ============================================================
 * FLOATING MAP CONTROLS (kanan atas peta)
 * — hadir di Dashboard maupun Focus
 * ============================================================ */
.kk-mapfab-stack{position:absolute;right:12px;top:12px;z-index:600;
  display:flex;flex-direction:column;gap:10px;}
.kk-mapfab{
  --sz:48px;
  width:var(--sz);height:var(--sz);border-radius:50%;
  border:1px solid rgba(8,18,35,.08);
  background:#ffffff;color:var(--kk-navy);
  box-shadow:0 1px 3px rgba(8,18,35,.12), 0 2px 6px rgba(8,18,35,.06);
  display:inline-flex;align-items:center;justify-content:center;
  font-size:1.05rem;position:relative;overflow:hidden;
  transition:var(--kk-transition);cursor:pointer;
}
.kk-mapfab:hover{transform:translateY(-1px);color:var(--kk-blue);
  box-shadow:0 3px 10px rgba(8,18,35,.16);}
.kk-mapfab.active{background:var(--kk-blue);color:#fff;border-color:transparent;}
.kk-mapfab .ripple{position:absolute;border-radius:50%;transform:scale(0);
  background:rgba(30,144,255,.25);animation:kkRipple .5s ease-out;pointer-events:none;}
@keyframes kkRipple{to{transform:scale(4);opacity:0;}}
body.kk-focus-mode .kk-mapfab{background:rgba(15,23,42,.55);color:#fff;
  border-color:rgba(255,255,255,.16);
  backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px);}
body.kk-focus-mode .kk-mapfab:hover{color:var(--kk-blue-2);}
body.kk-focus-mode .kk-mapfab.active{color:#fff;}

/* Settings popover */
.kk-settings-pop{position:absolute;right:66px;top:12px;z-index:601;
  min-width:240px;background:#fff;border-radius:16px;padding:12px;
  box-shadow:0 20px 40px rgba(8,18,35,.32);display:none;}
.kk-settings-pop.show{display:block;animation:kkFade .2s ease;}
@keyframes kkFade{from{opacity:0;transform:translateY(-4px);}to{opacity:1;}}
body.kk-focus-mode .kk-settings-pop{background:rgba(15,23,42,.9);color:#e5efff;
  backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px);}
body.kk-focus-mode .kk-settings-pop .form-select{background:rgba(255,255,255,.08);color:#fff;border-color:rgba(255,255,255,.15);}

/* ============================================================
 * FLOATING METRICS (Focus Mode) — glassmorphism KK
 * ============================================================ */
.kk-metrics{position:absolute;left:12px;right:12px;top:calc(env(safe-area-inset-top,0px) + 10px);
  z-index:5;pointer-events:none;
  display:none;flex-direction:column;gap:8px;}
body.kk-focus-mode .kk-metrics{display:flex;}

.kk-metric-primary{
  background:linear-gradient(180deg,rgba(8,18,35,.55),rgba(8,18,35,.35));
  backdrop-filter:blur(18px) saturate(140%);
  -webkit-backdrop-filter:blur(18px) saturate(140%);
  border:1px solid rgba(191,224,255,.22);
  border-radius:24px;padding:16px 20px;color:#fff;text-align:center;
  box-shadow:var(--kk-shadow-glass);
}
.kk-metric-primary .m-val{font-size:3.4rem;font-weight:900;line-height:1;
  font-variant-numeric:tabular-nums;letter-spacing:-.02em;
  background:linear-gradient(180deg,#fff,#BFE0FF);
  -webkit-background-clip:text;background-clip:text;color:transparent;}
.kk-metric-primary .m-lbl{font-size:.72rem;text-transform:uppercase;
  letter-spacing:.16em;color:#BFE0FF;font-weight:700;}
.kk-metric-primary .m-unit{font-size:1rem;color:#BFE0FF;font-weight:600;margin-left:.2rem;}
.kk-metric-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;}
.kk-metric-cell{
  background:linear-gradient(180deg,rgba(8,18,35,.5),rgba(8,18,35,.3));
  backdrop-filter:blur(14px) saturate(140%);
  -webkit-backdrop-filter:blur(14px) saturate(140%);
  border:1px solid rgba(191,224,255,.18);
  border-radius:18px;padding:10px 8px;color:#fff;text-align:center;
}
.kk-metric-cell .m-val{font-size:1.35rem;font-weight:800;line-height:1.05;
  font-variant-numeric:tabular-nums;}
.kk-metric-cell .m-lbl{font-size:.62rem;text-transform:uppercase;letter-spacing:.12em;
  color:#a9c8ea;font-weight:700;margin-top:2px;}

/* Chip status */
.kk-chips{position:absolute;left:12px;top:calc(env(safe-area-inset-top,0px) + 4px);
  display:flex;gap:6px;z-index:6;}
.kk-chip{background:rgba(8,18,35,.75);color:#fff;padding:5px 10px;border-radius:999px;
  font-size:.7rem;font-weight:700;letter-spacing:.04em;display:inline-flex;align-items:center;gap:5px;
  backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);
  border:1px solid rgba(191,224,255,.18);}
.kk-chip.status-ok{background:rgba(34,197,94,.9);border-color:transparent;}
.kk-chip.status-warn{background:rgba(234,179,8,.9);color:#111;border-color:transparent;}
.kk-chip.status-bad{background:rgba(239,68,68,.9);border-color:transparent;}
.kk-chip.rec{background:rgba(239,68,68,.92);border-color:transparent;animation:kkRecBlink 1.4s ease-in-out infinite;}
@keyframes kkRecBlink{0%,100%{box-shadow:0 0 0 0 rgba(239,68,68,.6);}50%{box-shadow:0 0 0 8px rgba(239,68,68,0);}}

/* Recenter (kembali ke posisi saya) */
.kk-recenter{position:absolute;right:14px;bottom:calc(240px + env(safe-area-inset-bottom,0px));
  z-index:6;background:#fff;color:var(--kk-navy);border:0;border-radius:999px;
  padding:10px 14px;font-weight:800;font-size:.85rem;
  box-shadow:0 6px 20px rgba(8,18,35,.35);display:none;align-items:center;gap:6px;
  animation:kkPop .25s ease;}
.kk-recenter.show{display:inline-flex;}
body:not(.kk-focus-mode) .kk-recenter{bottom:14px;}
@keyframes kkPop{from{transform:scale(.7);opacity:0}to{transform:scale(1);opacity:1}}

/* ============================================================
 * FLOATING CONTROLS (Focus mode) — bottom pause/stop/lock/mute
 * ============================================================ */
.kk-ctrl{position:absolute;left:0;right:0;bottom:0;z-index:7;
  padding:14px 16px calc(18px + env(safe-area-inset-bottom,0px));
  background:linear-gradient(to top,rgba(8,18,35,.85) 40%,rgba(8,18,35,0));
  display:none;flex-direction:column;gap:12px;align-items:center;}
body.kk-focus-mode .kk-ctrl{display:flex;}
.kk-ctrl-row{display:flex;align-items:center;justify-content:center;gap:18px;}
.kk-fab{border:0;border-radius:16px;min-width:56px;height:52px;padding:0 18px;font-size:1.1rem;
  color:#fff;box-shadow:0 2px 6px rgba(0,0,0,.25);
  display:inline-flex;align-items:center;justify-content:center;
  transition:var(--kk-transition);}
.kk-fab:active{transform:scale(.96);}
.kk-fab.pause{background:#f59e0b;}
.kk-fab.resume{background:var(--kk-blue);}
.kk-fab.lock{background:rgba(255,255,255,.14);min-width:48px;height:48px;font-size:1rem;
  border:1px solid rgba(255,255,255,.18);}
.kk-fab.stop{background:#ef4444;min-width:72px;height:52px;font-size:1.2rem;
  box-shadow:0 2px 8px rgba(239,68,68,.35);}

/* Dashboard-mode inline controls (Mulai/Pause/Stop di panel bawah peta) */
.kk-dash-controls{display:flex;gap:10px;flex-wrap:wrap;justify-content:center;margin-top:12px;}
.kk-dash-controls .kk-btn{min-width:130px;}

/* Swipe-to-finish */
.kk-swipe{position:relative;width:min(360px,90vw);height:58px;
  background:rgba(8,18,35,.65);border:1px solid rgba(191,224,255,.22);
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

/* Lock screen */
.kk-lock{position:absolute;inset:0;z-index:20;background:rgba(2,6,23,.72);
  backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);
  display:none;flex-direction:column;justify-content:flex-end;align-items:center;
  padding:0 20px calc(60px + env(safe-area-inset-bottom,0px));color:#fff;text-align:center;}
.kk-lock.show{display:flex;}
.kk-lock .lk-hero{margin-top:auto;margin-bottom:auto;}
.kk-lock .lk-icon{font-size:3rem;opacity:.85;color:var(--kk-blue-2);}
.kk-lock .lk-metrics{margin-top:14px;font-size:1.05rem;color:#e2e8f0;
  font-variant-numeric:tabular-nums;font-weight:700;letter-spacing:.05em;}
.kk-lock .lk-slide{position:relative;width:min(360px,90vw);height:64px;
  background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.18);
  border-radius:999px;display:flex;align-items:center;justify-content:center;
  font-weight:700;letter-spacing:.06em;user-select:none;overflow:hidden;}
.kk-lock .lk-thumb{position:absolute;left:4px;top:4px;bottom:4px;width:56px;
  background:#fff;border-radius:999px;display:flex;align-items:center;justify-content:center;
  color:var(--kk-navy);font-size:1.4rem;box-shadow:0 4px 12px rgba(0,0,0,.3);transition:transform .18s ease;}
.kk-lock .lk-thumb.dragging{transition:none;}
.kk-lock .lk-fill{position:absolute;left:0;top:0;bottom:0;width:0;
  background:linear-gradient(90deg,rgba(30,144,255,.35),rgba(30,144,255,.05));border-radius:999px;}
.kk-lock .lk-txt{position:relative;z-index:1;color:#e2e8f0;padding-left:60px;}

.kk-dim{position:absolute;inset:0;background:#000;opacity:0;pointer-events:none;z-index:15;
  transition:opacity .8s ease;}
.kk-dim.on{opacity:.38;}

.kk-countdown{position:absolute;inset:0;z-index:30;background:rgba(2,6,23,.85);
  display:none;align-items:center;justify-content:center;color:#fff;
  font-size:8rem;font-weight:900;font-variant-numeric:tabular-nums;
  text-shadow:0 6px 30px rgba(0,0,0,.5);}
.kk-countdown.show{display:flex;animation:kkCd .9s ease;}
@keyframes kkCd{from{transform:scale(1.6);opacity:.2}to{transform:scale(1);opacity:1}}

/* ============================================================
 * DASHBOARD live metrics (di bawah peta, saat tracking + dashboard)
 * ============================================================ */
#kk-dash-live{display:none;}
body.kk-tracking-active:not(.kk-focus-mode) #kk-dash-live{display:block;}
body.kk-tracking-active #kk-dash-pretrack{display:none;}

/* ============================================================
 * FINISH SCREEN — konsisten dengan Activity Summary KawanKeringat
 * ============================================================ */
#kk-finish{position:fixed;inset:0;z-index:9999;background:linear-gradient(180deg,#f5faff,#e8f2ff);display:none;overflow-y:auto;}
body.kk-finish-open{overflow:hidden;}
body.kk-finish-open #kk-finish{display:block;}
.kk-finish-hero{position:relative;height:44vh;min-height:280px;background:var(--kk-navy);}
#kk-finish-map{position:absolute;inset:0;}
.kk-finish-back{position:absolute;top:calc(env(safe-area-inset-top,0px) + 10px);left:12px;z-index:5;
  background:rgba(8,18,35,.75);color:#fff;border:0;border-radius:999px;padding:8px 12px;font-weight:700;
  backdrop-filter:blur(8px);}
.kk-finish-body{padding:16px;max-width:720px;margin:0 auto;}
.kk-summary{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:14px;}
.kk-summary .c{background:#fff;border-radius:var(--kk-radius-sm);padding:14px 10px;text-align:center;
  box-shadow:0 4px 14px rgba(8,18,35,.06);border:1px solid rgba(30,144,255,.1);}
.kk-summary .c .v{font-size:1.6rem;font-weight:900;color:var(--kk-navy);font-variant-numeric:tabular-nums;}
.kk-summary .c .l{font-size:.7rem;letter-spacing:.1em;text-transform:uppercase;color:#64748b;font-weight:700;}
.kk-chart-card{background:#fff;border-radius:var(--kk-radius-sm);padding:14px;margin-bottom:12px;
  box-shadow:0 4px 14px rgba(8,18,35,.06);border:1px solid rgba(30,144,255,.08);}
.kk-chart-card h6{margin:0 0 8px;font-weight:800;color:var(--kk-navy);}
.kk-chart-card canvas{width:100%;height:120px;display:block;}
.kk-split-row{display:flex;align-items:center;padding:8px 4px;border-bottom:1px solid #eef4fb;font-size:.9rem;}
.kk-split-row:last-child{border-bottom:0;}
.kk-split-row .km{width:60px;font-weight:800;color:var(--kk-navy);}
.kk-split-row .bar{flex:1;height:8px;background:#e2ecf7;border-radius:999px;margin:0 12px;overflow:hidden;}
.kk-split-row .bar > i{display:block;height:100%;background:linear-gradient(90deg,var(--kk-blue),var(--kk-blue-2));border-radius:999px;}
.kk-split-row .pace{font-variant-numeric:tabular-nums;font-weight:800;color:var(--kk-navy);min-width:70px;text-align:right;}
.kk-finish-cta{display:flex;gap:10px;margin-top:14px;}
.kk-finish-cta .btn{flex:1;border-radius:var(--kk-radius-sm);padding:14px;font-weight:800;}

/* Marker pelari — branding KK (Electric Blue) */
.kk-runner{width:26px;height:26px;border-radius:50%;background:var(--kk-blue);
  border:3px solid #fff;box-shadow:0 0 0 3px rgba(30,144,255,.35),0 3px 10px rgba(0,0,0,.4);
  position:relative;}
.kk-runner::after{content:"";position:absolute;left:50%;top:-12px;transform:translateX(-50%);
  width:0;height:0;border-left:6px solid transparent;border-right:6px solid transparent;
  border-bottom:10px solid var(--kk-blue);filter:drop-shadow(0 -1px 0 #fff);}
.leaflet-marker-icon.kk-runner-icon{transition:transform .9s linear;}
/* Polyline dibuat electric blue via option di map.js/tracking.js kalau ada;
   kalau tidak, path SVG default overridden lewat CSS berikut: */
.leaflet-overlay-pane path.leaflet-interactive{stroke:var(--kk-blue);}

@media (max-width:400px){
  .kk-metric-primary .m-val{font-size:2.8rem;}
  .kk-metric-cell .m-val{font-size:1.15rem;}
  .kk-fab.stop{min-width:64px;height:48px;font-size:1.05rem;}
  .kk-fab{height:48px;font-size:1rem;}
}
</style>

<script>document.body && document.body.classList.add('kk-run-page');</script>

<!-- ================================================================
     PRE-TRACK SHELL (Dashboard Mode header)
     ================================================================ -->
<div id="kk-pretrack-shell">
  <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
      <h4 class="mb-0 kk-page-title"><i class="bi bi-stopwatch"></i> Tracking Jalur</h4>
      <div class="kk-page-sub">KawanKeringat · AI Sport &amp; Healthy Lifestyle</div>
    </div>
    <a href="/explore.php" class="kk-btn kk-btn-ghost">
      <i class="bi bi-compass"></i> Eksplorasi Rute
    </a>
  </div>

  <div id="kk-bg-warn" class="alert alert-warning small d-none">
    <i class="bi bi-exclamation-triangle-fill"></i>
    Tracking background terbatas di browser. Untuk pengalaman penuh (GPS jalan
    saat layar mati, bubble melayang, notification permanen), gunakan
    <strong>APK KawanKeringat</strong>.
  </div>

  <!-- Panel Statistik (selalu tampil di Dashboard Mode) -->
  <div class="kk-card mb-3">
    <div class="card-body">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h6 class="mb-0"><i class="bi bi-graph-up-arrow" style="color:var(--kk-blue)"></i> Statistik</h6>
        <small class="text-muted" id="kk-dash-status">Siap</small>
      </div>
      <div class="kk-stat-grid">
        <div class="kk-stat"><div class="v" id="d-dist">0.00</div><div class="l">Km</div></div>
        <div class="kk-stat"><div class="v" id="d-time">00:00</div><div class="l">Durasi</div></div>
        <div class="kk-stat"><div class="v" id="d-pace">--'--"</div><div class="l">Pace</div></div>
        <div class="kk-stat"><div class="v" id="d-speed">0.0</div><div class="l">Km/h</div></div>
        <div class="kk-stat"><div class="v" id="d-cal">0</div><div class="l">Kalori</div></div>
        <div class="kk-stat"><div class="v" id="d-elev">–</div><div class="l">Elev (m)</div></div>
        <div class="kk-stat"><div class="v" id="d-avgpace">--'--"</div><div class="l">Avg Pace</div></div>
        <div class="kk-stat"><div class="v" id="d-gps">–</div><div class="l">Akurasi GPS</div></div>
      </div>
    </div>
  </div>

  <!-- Mini Map (Dashboard) — track root container -->
  <div id="kk-track-root" aria-hidden="false">
    <div id="kk-map"></div>

    <!-- Chips atas -->
    <div class="kk-chips">
      <span class="kk-chip" id="kk-gps-chip">🟡 GPS…</span>
      <span class="kk-chip rec" id="kk-mode-chip" style="display:none">● REC</span>
      <span class="kk-chip" id="kk-auto-chip" style="display:none">Auto-Pause</span>
    </div>

    <!-- Floating map controls (kanan atas peta) -->
    <div class="kk-mapfab-stack">
      <button class="kk-mapfab" id="kk-fab-follow" title="Ikuti Posisi Saya" aria-label="Follow My Location">
        <i class="bi bi-geo-alt-fill"></i>
      </button>
      <button class="kk-mapfab" id="kk-fab-compass" title="Compass / Utara di Atas" aria-label="Compass">
        <i class="bi bi-compass"></i>
      </button>
      <button class="kk-mapfab" id="kk-fab-fullscreen" title="Fullscreen (Focus Mode)" aria-label="Fullscreen">
        <i class="bi bi-arrows-fullscreen"></i>
      </button>
      <button class="kk-mapfab" id="kk-fab-settings" title="Pengaturan" aria-label="Settings">
        <i class="bi bi-gear-fill"></i>
      </button>
    </div>

    <!-- Settings popover -->
    <div class="kk-settings-pop" id="kk-settings-pop">
      <div class="mb-2">
        <label class="stat-label">Rotasi Map</label>
        <select id="rotSel" class="form-select form-select-sm">
          <option value="heading">Ikuti Arah</option>
          <option value="north">Utara di Atas</option>
        </select>
      </div>
      <div class="mb-2">
        <label class="stat-label">Voice Feedback</label>
        <select id="voiceSel" class="form-select form-select-sm">
          <option value="1000">Setiap 1 km</option>
          <option value="500">Setiap 500 m</option>
          <option value="0">Nonaktif</option>
        </select>
      </div>
      <div>
        <label class="stat-label">Jenis Olahraga</label>
        <select id="sportSel" class="form-select form-select-sm">
          <option value="run">Lari</option>
          <option value="jog">Jogging</option>
          <option value="walk">Jalan</option>
          <option value="bike">Sepeda</option>
        </select>
      </div>
    </div>

    <!-- Floating metrics (Focus Mode only, disembunyikan di Dashboard) -->
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

    <!-- Controls (Focus Mode) -->
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

    <!-- Exit fullscreen (hanya tampil saat Focus Mode) -->
    <button class="kk-mapfab" id="kk-fab-exit-focus"
            style="position:absolute;left:12px;bottom:calc(150px + env(safe-area-inset-bottom,0px));z-index:6;display:none;"
            title="Keluar Focus Mode" aria-label="Exit Fullscreen">
      <i class="bi bi-fullscreen-exit"></i>
    </button>

    <div class="kk-dim" id="kk-dim"></div>
    <div class="kk-countdown" id="kk-countdown">3</div>
  </div>

  <!-- ============================================================
       DASHBOARD PANELS — Mulai/Pause/Stop, split, riwayat, dsb.
       Panel ini disembunyikan otomatis saat Focus Mode aktif.
       ============================================================ -->
  <div id="kk-dash-panels">

    <!-- Pre-track controls (sebelum sesi dimulai) -->
    <div id="kk-dash-pretrack" class="kk-card mt-3">
      <div class="card-body">
        <div class="row g-2 align-items-center settings-row">
          <div class="col-6 col-md-3">
            <label class="stat-label mb-1">Berat (kg)</label>
            <input id="weightInp" type="number" min="20" max="250" step="0.1"
              class="form-control form-control-sm" value="<?= htmlspecialchars((string)$userWeight) ?>">
          </div>
          <div class="col-12 col-md-9 text-md-end">
            <button id="kk-btn-start" class="kk-btn kk-btn-start">
              <i class="bi bi-play-fill"></i> MULAI TRACKING
            </button>
          </div>
        </div>
        <div class="text-center small mt-2" style="color:#64748b;">
          Mode saat tracking dapat diubah kapan saja via tombol <i class="bi bi-arrows-fullscreen"></i> pada peta.
        </div>
      </div>
    </div>

    <!-- Live controls (saat sesi berjalan, Dashboard Mode) -->
    <div id="kk-dash-live" class="kk-card mt-3">
      <div class="card-body">
        <div class="kk-dash-controls">
          <button class="kk-btn kk-btn-pause" id="kk-dash-btn-pause"><i class="bi bi-pause-fill"></i> Pause</button>
          <button class="kk-btn kk-btn-resume" id="kk-dash-btn-resume" style="display:none"><i class="bi bi-play-fill"></i> Lanjutkan</button>
          <button class="kk-btn kk-btn-stop" id="kk-dash-btn-stop"><i class="bi bi-stop-fill"></i> Stop</button>
          <button class="kk-btn kk-btn-ghost" id="kk-dash-btn-focus"><i class="bi bi-arrows-fullscreen"></i> Focus Mode</button>
        </div>
      </div>
    </div>

    <!-- Split per KM (Dashboard) -->
    <div class="kk-card mt-3">
      <div class="card-body">
        <h6 class="mb-2"><i class="bi bi-flag-fill" style="color:var(--kk-blue)"></i> Split per Kilometer</h6>
        <div id="d-splits"><div class="text-muted small">Belum ada split. Mulai tracking untuk melihat progres per km.</div></div>
      </div>
    </div>

    <!-- Riwayat -->
    <details class="kk-card history-kk mt-3" <?= $history ? 'open' : '' ?>>
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
</div>

<!-- ================================================================
     FINISH SCREEN (Activity Summary style)
     ================================================================ -->
<div id="kk-finish" aria-hidden="true">
  <div class="kk-finish-hero">
    <div id="kk-finish-map"></div>
    <button class="kk-finish-back" id="kk-finish-back"><i class="bi bi-x-lg"></i> Tutup</button>
  </div>
  <div class="kk-finish-body">
    <h4 class="fw-bold mb-1" style="color:var(--kk-navy);">Kerja bagus! 🎉</h4>
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
      <h6><i class="bi bi-flag-fill" style="color:var(--kk-blue)"></i> Split per Kilometer</h6>
      <div id="f-splits"><div class="text-muted small">-</div></div>
    </div>

    <div class="kk-chart-card"><h6>Grafik Pace (menit/km)</h6><canvas id="f-chart-pace"></canvas></div>
    <div class="kk-chart-card"><h6>Grafik Kecepatan (km/h)</h6><canvas id="f-chart-speed"></canvas></div>
    <div class="kk-chart-card"><h6>Grafik Elevasi (m)</h6><canvas id="f-chart-elev"></canvas></div>

    <div class="kk-finish-cta">
      <button class="btn btn-outline-secondary" id="f-btn-discard"><i class="bi bi-trash"></i> Buang</button>
      <a class="btn kk-btn kk-btn-start" id="f-btn-review" href="/upload.php"><i class="bi bi-cloud-arrow-up"></i> Review &amp; Upload</a>
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

<!-- Modul JS — TIDAK berubah kecuali ui.js -->
<script src="/assets/js/run/save.js?v=r35"></script>
<script src="/assets/js/run/voice.js?v=r35"></script>
<script src="/assets/js/run/map.js?v=r35"></script>
<script src="/assets/js/run/gps.js?v=r35"></script>
<script src="/assets/js/run/background.js?v=r35"></script>
<script src="/assets/js/run/ui.js?v=r35"></script>
<script src="/assets/js/run/tracking.js?v=r35"></script>

<script>
/* ---- Inisialisasi Dashboard Mode segera setelah halaman siap ---- */
document.addEventListener('DOMContentLoaded', function(){
  if (window.KKUI && typeof window.KKUI.initDashboardMode === 'function'){
    window.KKUI.initDashboardMode();
  }
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
