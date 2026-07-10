<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/notifications.php';
require_once __DIR__ . '/theme_user.php';
require_once __DIR__ . '/migrations_v7.php';
require_once __DIR__ . '/migrations_v8.php';
require_once __DIR__ . '/migrations_v9.php';
require_once __DIR__ . '/paket_helpers.php';
require_once __DIR__ . '/scope.php'; // Revisi R7 #6 (Juli 2026)

/* ============================================================
 * Revisi 30 Jun 2026 — Lock badge untuk menu Pro / Komunitas.
 * Mapping fitur ke paket yang dibutuhkan, dan helper render
 * ikon gembok + label di samping nama menu pada drawer / navbar.
 * ============================================================ */
if (!function_exists('nav_feature_paket_map')) {
    function nav_feature_paket_map(): array {
        // pages => required paket(s)
        return [
            // Komunitas-only (Jogging Progress)
            'monitoring.php'          => ['komunitas'],
            'live_tracking.php'       => ['komunitas'],
            'flyover.php'             => ['komunitas'],
            // Revisi R6 (Juli 2026) — badge Paket Komunitas untuk Riwayat dihapus.
            // 'riwayat.php'             => ['komunitas'],
            // Revisi Juli 2026 — Tracking Jalur (run.php) dipindah ke KOMUNITAS
            'run.php'                 => ['komunitas'],
            // Tempat → Komunitas
            'tempat.php'              => ['komunitas'],
            'tempat_list.php'         => ['komunitas'],
            'islami.php'              => ['komunitas'],
            // Pro + Komunitas
            'kalori_badminton.php'    => ['pro'],
            'kalori_renang.php'       => ['pro'],
            'kalori_pingpong.php'     => ['pro'],
            'kalori_futsal.php'       => ['pro'],
            'kalori_mingguan.php'     => ['pro'],
            'iptv.php'                => ['pro'],
            'toko_olahraga.php'       => ['pro'],
            'artikel_olahraga.php'    => ['pro'],
            'cedera_olahraga.php'     => ['pro'],
            'lacak_faskes.php'        => ['pro'],
            'survival.php'            => ['pro'],
            'kalkulator.php'          => ['pro'],
            'kalkulator_jantung.php'  => ['pro'],
            'kalkulator_kesehatan.php'=> ['pro'],
            'gaya_hidup.php'          => ['pro'],
            // Pro-only — Paket Anak & Lansia
            'paket_anak_2_4.php'      => ['pro'],
            'paket_anak_4_6.php'      => ['pro'],
            'paket_anak_7_9.php'      => ['pro'],
            'paket_anak_10_12.php'    => ['pro'],
            'paket_lansia_55_69.php'  => ['pro'],
            'paket_lansia_70.php'     => ['pro'],
            // Revisi Juli 2026 R10 — Paket Perokok (Jogging)
            'paket_perokok_jogging.php' => ['pro'],
            // Revisi Juli 2026 R11 — Pro-only badge (removed Komunitas)
            'kalistenik.php'          => ['pro'],
            'kesehatan.php'           => ['pro'],
        ];
    }
}
if (!function_exists('nav_lock_badge_for')) {
    /** Revisi Juli 2026 — Aturan tampil badge:
     *   - GRATIS   : tampilkan semua badge (Pro & Komunitas)
     *   - PRO      : hanya tampilkan badge KOMUNITAS
     *   - KOMUNITAS: tidak tampilkan badge apa pun (fitur full)
     */
    function nav_lock_badge_for(string $page): string {
        $page = ltrim($page, '/');
        $page = preg_replace('/[?#].*$/', '', $page);
        $map = nav_feature_paket_map();
        if (!isset($map[$page])) return '';
        $req = $map[$page];

        $curPk = 'gratis';
        if (function_exists('paket_user')) {
            try { $curPk = paket_user(function_exists('current_user') ? current_user() : null); } catch (Throwable $e) {}
        }
        if ($curPk === 'komunitas') return '';
        // Revisi Juli 2026 — user PRO: sembunyikan badge Komunitas untuk fitur
        // yang juga tersedia untuk paket Pro (mis. Pro+Komunitas). Hanya
        // fitur yang MURNI Komunitas (tanpa 'pro' di $req) yang masih di-badge.
        if ($curPk === 'pro') {
            if (in_array('pro', $req, true)) return '';
            $req = array_values(array_intersect($req, ['komunitas']));
        }
        if (!$req) return '';

        $defs = [
            'pro'       => ['Pro',       'bg-warning-subtle text-warning-emphasis border border-warning-subtle'],
            'komunitas' => ['Komunitas', 'bg-success-subtle text-success-emphasis border border-success-subtle'],
        ];
        $out = '';
        foreach ($req as $r) {
            if (!isset($defs[$r])) continue;
            [$lab,$cls] = $defs[$r];
            $out .= ' <span class="badge rounded-pill '.$cls.' ms-1 nav-lock-badge" title="Khusus paket '.$lab.'">'
                  . '<i class="bi bi-lock-fill"></i> '.$lab.'</span>';
        }
        return $out;
    }
}

send_security_headers(); enforce_session_timeout();
/* Revisi 29 Juni 2026 — GLOBAL AUTH GUARD.
 * Semua halaman yang meng-include header.php otomatis butuh login.
 * Pengecualian: file publik di bawah ini tidak boleh memicu require_login (mereka
 * memang tidak meng-include header.php, tapi kita whitelist juga untuk aman). */
$__pub_pages = ['login.php','register.php','logout.php','splash.php','onboarding.php','manifest.php','health.php','strava_webhook.php','track_view.php','tes.php'];
$__cur_page  = basename($_SERVER['SCRIPT_NAME'] ?? '');
if (!in_array($__cur_page, $__pub_pages, true)) { require_login(); }
$u = current_user();
if ($u) touch_online();
$navFoto = null; $nUnread = 0; $darkMode = 0;
if ($u) {
  $_uf = db_one("SELECT foto_url, COALESCE(dark_mode,0) AS dark_mode FROM users WHERE id=$1", [(int)$u['id']]);
  $navFoto = $_uf['foto_url'] ?? null;
  $darkMode = (int)($_uf['dark_mode'] ?? 0);
  $nUnread = unread_notif_count((int)$u['id']);
}

// Revisi 18 Juni 2026 — auto-default $pageSkeleton per halaman bila belum diset.
// Skeleton sesuai bentuk konten dominan tiap halaman. Halaman yang sudah
// mendefinisikan $pageSkeleton sendiri tidak ditimpa.
if (empty($pageSkeleton)) {
    $_base = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $_skelMap = [
        'index.php'=>'feed', 'feed_islami.php'=>'feed', 'islami.php'=>'feed',
        'artikel_olahraga.php'=>'feed', 'artikel_sunnah.php'=>'feed',
        'berita.php'=>'feed', 'hidup_sehat.php'=>'feed', 'gaya_hidup.php'=>'feed',
        'cedera_olahraga.php'=>'feed', 'doa.php'=>'list', 'dzikir.php'=>'list',
        'hadist.php'=>'list', 'quran.php'=>'list', 'quran_surah.php'=>'list',
        'jadwal_sholat.php'=>'jadwal', 'event.php'=>'jadwal', 'calendar.php'=>'jadwal',
        'kalender_hijriyah.php'=>'jadwal',
        'kalori_mingguan.php'=>'table', 'kalori_badminton.php'=>'table',
        'kalori_futsal.php'=>'table', 'kalori_renang.php'=>'table',
        'kalori_pingpong.php'=>'table',
        'leaderboard_islami.php'=>'table', 'statistik_islami.php'=>'profile',
        'profile.php'=>'profile', 'user.php'=>'profile',
        'dm.php'=>'chat', 'dm_floating.php'=>'chat',
        'tempat.php'=>'grid', 'tempat_list.php'=>'grid', 'tempat_detail.php'=>'grid',
        'buku.php'=>'grid', 'jajanan.php'=>'grid', 'beasiswa.php'=>'grid',
        'kajian.php'=>'grid', 'iptv.php'=>'grid',
        'run.php'=>'grid', 'riwayat.php'=>'table', 'upload.php'=>'grid',
        'challenge.php'=>'forum', 'doa_antar_member.php'=>'forum',
        'bookmark.php'=>'list', 'search.php'=>'list', 'hashtag.php'=>'feed',
        'monitoring.php'=>'table', 'export.php'=>'table',
        'sejarah_nabi.php'=>'list', 'shalat_tatacara.php'=>'list',
        'shalat_rawatib.php'=>'list', 'shalat_sunnah.php'=>'list',
        'rukun_islam.php'=>'list', 'catatan_hafalan.php'=>'list',
        'kesehatan.php'=>'feed', 'kalkulator.php'=>'grid',
        'kalkulator_jantung.php'=>'grid', 'kalkulator_kesehatan.php'=>'grid',
        'kalistenik.php'=>'feed', 'donasi.php'=>'list',
    ];
    if (isset($_skelMap[$_base])) $pageSkeleton = $_skelMap[$_base];
    else $pageSkeleton = 'list'; // default umum
}
?>
<!doctype html>
<html lang="id" data-bs-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#0f172a">
<title><?= htmlspecialchars(($pageTitle ?? 'KawanKeringat') . ' · KawanKeringat') ?></title>
<link rel="manifest" href="/manifest.php">
<link rel="apple-touch-icon" href="/assets/icon-192.png">
<link rel="icon" type="image/png" sizes="192x192" href="/assets/icon-192.png">
<link rel="icon" type="image/png" sizes="512x512" href="/assets/icon-512.png">
<link rel="shortcut icon" href="/assets/icon-192.png">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/app.css">
<link rel="stylesheet" href="/assets/css/app-v3.css">
<link rel="stylesheet" href="/assets/css/desktop-fix.css">
<!-- Revisi 4 Jun 2026: header atas biru-kehitaman (bukan hijau) -->
<link rel="stylesheet" href="/assets/css/gojek-top.css?v=4jun2026">
<!-- Revisi Nov 2026 — Global Theme Engine & UI modernization overlay -->
<link rel="stylesheet" href="/assets/css/redesign-2026.css?v=nov2026">
<!-- Revisi 4 Jun 2026: SFX klik di semua halaman -->
<script defer src="/assets/js/sfx.js?v=4jun2026"></script>
<style>
.user-with-avatar{display:inline-flex;align-items:center;gap:.4rem;position:relative;}
.user-avatar-fallback{display:inline-flex;align-items:center;justify-content:center;border-radius:50%;background:linear-gradient(135deg,#0ea5e9,#6366f1);color:#fff;font-weight:700;}
.online-dot{width:9px;height:9px;border-radius:50%;background:#22c55e;box-shadow:0 0 0 2px #fff;display:inline-block;position:absolute;bottom:0;right:0;}
.chat-bubble{background:var(--bs-tertiary-bg,#f1f5f9);border-radius:12px;padding:.5rem .75rem;margin-bottom:.4rem;}
.chat-reply{margin-left:2rem;border-left:3px solid #0ea5e9;}
.chat-meta{font-size:.7rem;color:var(--bs-secondary-color,#64748b);}
.pill{display:inline-block;padding:.15rem .6rem;border-radius:999px;background:var(--bs-tertiary-bg,#f1f5f9);font-size:.75rem;color:var(--bs-secondary-color,#475569);}
.card-stat .stat-icon{width:38px;height:38px;border-radius:10px;background:#e0f2fe;color:#0369a1;display:flex;align-items:center;justify-content:center;font-size:1.1rem;margin-bottom:.4rem;}
.card-stat .stat-label{font-size:.75rem;color:var(--bs-secondary-color,#64748b);}
.card-stat .stat-value{font-size:1.4rem;font-weight:700;}
.hero{padding:.5rem 0;}
.badge-soft{display:inline-block;padding:.25rem .75rem;border-radius:999px;background:#e0f2fe;color:#0369a1;font-size:.8rem;font-weight:500;}
.ql-container{min-height:140px;}
.ql-editor{min-height:140px;}
.wysiwyg-wrap{margin-bottom:1.25rem;}
.heatmap{display:grid;grid-auto-flow:column;grid-template-rows:repeat(7,12px);gap:3px;overflow-x:auto;padding:4px 0;}
.heatmap .cell{width:12px;height:12px;border-radius:2px;background:#ebedf0;}
.heatmap .l1{background:#9be9a8;} .heatmap .l2{background:#40c463;} .heatmap .l3{background:#30a14e;} .heatmap .l4{background:#216e39;}
[data-bs-theme=dark] .heatmap .cell{background:#1f2937;}
.navbar .navbar-nav .nav-link{padding-left:.7rem;padding-right:.7rem;white-space:nowrap;}
.navbar .navbar-nav .nav-link i{margin-right:.25rem;}
@media (min-width:992px){
  .navbar .navbar-nav.me-auto{column-gap:.15rem;}
  .navbar form[role=search]{margin-right:.5rem;}
  .navbar .navbar-nav.align-items-lg-center .nav-item + .nav-item{margin-left:.25rem;}
}
.navbar .dropdown-menu{min-width:240px;}
@media (max-width:991.98px){
  .navbar .dropdown-menu{ max-height: 70vh; overflow-y: auto; -webkit-overflow-scrolling: touch; }
  .navbar .navbar-collapse{ max-height: 85vh; overflow-y: auto; -webkit-overflow-scrolling: touch; }
}
#appTopLoader{position:fixed;top:0;left:0;height:3px;width:0;background:linear-gradient(90deg,#0ea5e9,#6366f1,#22c55e);z-index:99999;box-shadow:0 0 8px rgba(14,165,233,.6);transition:width .25s ease,opacity .35s ease;opacity:0;pointer-events:none;border-radius:0 2px 2px 0;}
#appTopLoader.active{opacity:1;}
#appCornerSpinner{position:fixed;top:10px;right:14px;width:22px;height:22px;border:3px solid rgba(14,165,233,.25);border-top-color:#0ea5e9;border-radius:50%;animation:hfspin .8s linear infinite;z-index:99999;display:none;pointer-events:none;}
#appCornerSpinner.active{display:block;}
@keyframes hfspin{to{transform:rotate(360deg);}}
#appPreloader{display:none !important;}
#liveRefreshBadge{position:fixed;right:14px;bottom:78px;z-index:1080;display:none;}
@media (max-width:991.98px){
  nav.navbar.sticky-top{ position: fixed !important; top:0; left:0; right:0; z-index:1050;
    box-shadow: 0 2px 8px rgba(0,0,0,.15); }
}
/* Revisi 6 Jun 2026 — Logo KawanKeringat berwarna (tidak biru semua) */
.brand-logo-colored{font-family:'Plus Jakarta Sans',system-ui,sans-serif;letter-spacing:.2px;}
.brand-logo-colored .bi-lightning-charge-fill{filter:drop-shadow(0 0 6px rgba(250,204,21,.55));}
.brand-logo-colored .bl-1{color:#ef4444;}   /* Hap   - merah */
.brand-logo-colored .bl-2{color:#f59e0b;}   /* Fam   - oranye */
.brand-logo-colored .bl-3{color:#10b981;}   /* Sport - hijau */
.brand-logo-colored .bl-4{color:#6366f1;}   /* App   - indigo */
.navbar-dark .brand-logo-colored .bl-1,.bg-dark .brand-logo-colored .bl-1{color:#fda4af;}
.navbar-dark .brand-logo-colored .bl-2,.bg-dark .brand-logo-colored .bl-2{color:#fcd34d;}
.navbar-dark .brand-logo-colored .bl-3,.bg-dark .brand-logo-colored .bl-3{color:#6ee7b7;}
.navbar-dark .brand-logo-colored .bl-4,.bg-dark .brand-logo-colored .bl-4{color:#a5b4fc;}

/* === Revisi 28 Juni 2026 — PAKSA tampilan MOBILE di desktop (FINAL/CANONICAL) ===
   Sumber kebenaran tunggal frame ponsel ~480px di tengah layar.
   Menggantikan blok lama yang bentrok dengan gojek-top.css. */
@media (min-width: 992px){
  /* Sembunyikan SEMUA varian navbar desktop bootstrap */
  nav.navbar.sticky-top,
  nav.navbar.sticky-top.kk-desktop-nav,
  nav.navbar.fixed-top.kk-desktop-nav { display: none !important; }

  /* Tampilkan top-bar, chips, bottom nav seperti versi mobile */
  .gt-top   { display: block !important; }
  .gt-chips { display: flex  !important; }
  .gj-nav, .gj-nav.d-lg-none { display: flex !important; }

  /* Latar luar gelap supaya frame ponsel terlihat menonjol di tengah */
  html { background:#0f172a !important; }

  /* FRAME PONSEL — body sebagai kontainer 480px terpusat */
  body{
    max-width: 480px !important;
    width: 100% !important;
    margin: 0 auto !important;
    background: var(--gt-bg, #ffffff) !important;
    box-shadow: 0 0 32px rgba(0,0,0,.35) !important;
    border-left: 1px solid rgba(0,0,0,.08) !important;
    border-right: 1px solid rgba(0,0,0,.08) !important;
    overflow-x: hidden !important;
    /* gunakan padding yang sama dgn versi mobile gojek-top.css */
    padding-top: calc(var(--gt-h, 56px) + 56px) !important;
    padding-bottom: 76px !important;
    min-height: 100vh !important;
  }

  /* Semua container bootstrap mengikuti lebar body, JANGAN dibatasi lagi */
  body > .container, body > .container-fluid, body > main, body > section,
  main.container, main > .container,
  .container, .container-sm, .container-md, .container-lg,
  .container-xl, .container-xxl, .container-fluid{
    max-width: 100% !important;
    width: auto !important;
    padding-left: 12px !important;
    padding-right: 12px !important;
    margin-left: 0 !important;
    margin-right: 0 !important;
    background: transparent !important;
  }

  /* Bar-bar position:fixed harus center di viewport, bukan di body.
     Pakai left:50% + translateX(-50%) saja, JANGAN dicampur dgn left/right:0. */
  .gt-top, .gt-chips, .gj-nav{
    position: fixed !important;
    left: 50% !important;
    right: auto !important;
    transform: translateX(-50%) !important;
    width: 100% !important;
    max-width: 480px !important;
    margin: 0 !important;
  }
  .gt-top   { top: 0 !important; }
  .gt-chips { top: calc(var(--gt-h, 56px) + env(safe-area-inset-top, 0px)) !important; }
  .gj-nav   { bottom: 0 !important; top: auto !important; }

  /* Popup notifikasi & DM floating juga harus center di frame */
  .gt-notif-pop{
    position: fixed !important;
    left: 50% !important; right: auto !important;
    transform: translateX(-50%) !important;
    max-width: 460px !important;
    width: calc(480px - 20px) !important;
  }
  #fbDmPanel, #fbDmChat{
    left: 50% !important; right: auto !important;
    transform: translateX(calc(-50% + 220px));
    max-width: 360px !important;
  }

  /* Modal & offcanvas tetap proporsional di dlm frame */
  .modal-dialog{ max-width: min(100%, 460px) !important; margin: 1rem auto !important; }
  .offcanvas-start.gt-drawer{ max-width: 320px !important; }

  /* === Revisi: PAKSA drawer tertutup di desktop ===
     desktop-fix.css versi lama memaksa .gt-drawer selalu tampil sebagai sidebar
     permanen — hal ini bertentangan dengan tampilan ponsel yg diharapkan.
     Override di sini agar drawer hanya muncul ketika tombol burger ditekan
     (Bootstrap menambahkan class .show). */
  .gt-drawer,
  .offcanvas.offcanvas-start.gt-drawer{
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    height: 100vh !important;
    transform: translateX(-100%) !important;
    visibility: hidden !important;
    box-shadow: none !important;
    z-index: 1055 !important;
    transition: transform .25s ease, visibility 0s linear .25s !important;
  }
  .gt-drawer.show,
  .offcanvas.offcanvas-start.gt-drawer.show{
    transform: translateX(0) !important;
    visibility: visible !important;
    transition: transform .25s ease !important;
  }
  /* Backdrop tetap berfungsi seperti di mobile */
  .offcanvas-backdrop{ z-index: 1054 !important; }

  /* Body JANGAN diberi margin/padding kiri untuk "menampung" sidebar */
  body{ margin-left: auto !important; margin-right: auto !important; }
}

/* === Revisi 28 Jun 2026 — FINAL phone-frame di desktop (sesuai mockup 4.png) ===
   Sebelumnya `.gt-top` / `.gt-chips` masih bisa melebar melewati frame ponsel 480px
   karena gojek-top.css memakai selector + !important yg menang specificity.
   Blok ini menggunakan selector lebih spesifik (html body header.gt-top dll)
   agar override selalu menang, dan menambahkan box-sizing + overflow guard
   supaya konten di dalam top-bar (search input) tidak mendorong lebar keluar. */
@media (min-width: 992px){
  html, html body{ background:#0f172a !important; }

  /* Matikan semua varian navbar bootstrap di desktop */
  nav.navbar, nav.navbar.kk-desktop-nav, .kk-desktop-nav{ display:none !important; }

  /* Frame ponsel 480px terpusat — scrollbar di html, body bersih */
  html{ overflow-y: auto !important; }
  html body{
    max-width: 480px !important;
    width: 480px !important;
    margin-left: auto !important;
    margin-right: auto !important;
    background:#ffffff !important;
    box-shadow: 0 0 32px rgba(0,0,0,.45) !important;
    overflow-x: hidden !important;
    box-sizing: border-box !important;
  }

  /* Bar fixed (top/chips/bottom-nav) — selector lebih spesifik supaya menang
     atas gojek-top.css. KUNCI lebar ke 480px + box-sizing border-box agar
     padding/border tidak menambah lebar. */
  html body header.gt-top,
  html body nav.gt-chips,
  html body nav.gj-nav,
  html body .gt-top,
  html body .gt-chips,
  html body .gj-nav{
    display: flex !important;
    position: fixed !important;
    left: 50% !important;
    right: auto !important;
    transform: translateX(-50%) !important;
    width: 480px !important;
    max-width: 480px !important;
    min-width: 0 !important;
    margin: 0 !important;
    box-sizing: border-box !important;
    overflow: hidden !important;
  }
  html body header.gt-top { top: 0 !important; }
  html body nav.gt-chips  { top: calc(var(--gt-h, 56px) + env(safe-area-inset-top, 0px)) !important;
                            overflow-x: auto !important; overflow-y: hidden !important;
                            -webkit-overflow-scrolling: touch; flex-wrap: nowrap !important; }
  html body nav.gj-nav    { bottom: 0 !important; top: auto !important; }

  /* Isi dalam top-bar tidak boleh memaksa lebar > 480 */
  html body .gt-top .gt-row{ width: 100% !important; max-width: 100% !important;
                             min-width: 0 !important; box-sizing: border-box !important;
                             flex-wrap: nowrap !important; }
  html body .gt-top .gt-search{ flex: 1 1 auto !important; min-width: 0 !important; }
  html body .gt-top .gt-search input{ width: 100% !important; min-width: 0 !important; }

  /* Container bootstrap mengikuti lebar frame, jangan dibatasi lagi */
  body > .container, body > .container-fluid, body > main, body > section,
  main.container, main > .container,
  .container, .container-sm, .container-md, .container-lg,
  .container-xl, .container-xxl, .container-fluid{
    max-width: 100% !important; width: auto !important;
    padding-left: 12px !important; padding-right: 12px !important;
    margin-left: 0 !important; margin-right: 0 !important;
    background: transparent !important;
  }

  /* Stack kolom + jaga tabel/form agar tidak overflow frame */
  table{ width: 100% !important; }
  .table-responsive{ overflow-x: auto !important; -webkit-overflow-scrolling: touch; }
  .row{ margin-left: 0 !important; margin-right: 0 !important; }
  .row > [class^="col-"], .row > [class*=" col-"]{
    flex: 0 0 100% !important; max-width: 100% !important;
    padding-left: 6px !important; padding-right: 6px !important;
  }
  .card, .form-control, .form-select, .btn, .input-group{ max-width: 100% !important; }
  img, video, iframe{ max-width: 100% !important; height: auto !important; }

  /* Tutup sidebar permanen apapun */
  .gt-sidebar, .kk-sidebar, .desktop-sidebar{ display: none !important; }

  /* Popup notif & DM tetap di dalam frame */
  html body .gt-notif-pop{
    position: fixed !important; left: 50% !important; right: auto !important;
    transform: translateX(-50%) !important;
    width: calc(480px - 20px) !important; max-width: 460px !important;
  }
}


</style>
<style id="userTheme"><?= user_theme_css() ?></style>
<!-- Revisi 29 Juni 2026 — SweetAlert2 untuk popup konfirmasi cantik (mengganti window.confirm bawaan browser) -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js" defer></script>
<script>
// Override window.confirm agar tidak menampilkan URL bawaan browser & tampil estetik.
// Karena window.confirm sinkron, kita intercept submit form berbasis [onsubmit="return confirm(...)"]
// dan klik link berbasis [data-confirm="..."]. Pemanggilan confirm() lainnya tetap sinkron (fallback bawaan).
(function(){
  function ready(fn){ if(document.readyState!=='loading') fn(); else document.addEventListener('DOMContentLoaded', fn); }
  function ask(message, opts){
    opts = opts || {};
    if (typeof Swal === 'undefined') return Promise.resolve(window.__nativeConfirm ? window.__nativeConfirm(message) : true);
    return Swal.fire({
      title: opts.title || 'Konfirmasi',
      text:  message,
      icon:  opts.icon || 'question',
      showCancelButton: true,
      confirmButtonText: opts.yes || 'Ya',
      cancelButtonText:  opts.no  || 'Batal',
      confirmButtonColor: '#0ea5e9',
      cancelButtonColor:  '#64748b',
      reverseButtons: true,
      focusCancel: true,
    }).then(function(r){ return !!r.isConfirmed; });
  }
  window.gjConfirm = ask;
  // Intercept form submit yang punya onsubmit="return confirm(...)" — override agar pakai Swal.
  ready(function(){
    document.querySelectorAll('form[onsubmit*="confirm("]').forEach(function(f){
      var orig = f.getAttribute('onsubmit') || '';
      var m = orig.match(/confirm\(\s*['"]([\s\S]*?)['"]\s*\)/);
      var msg = m ? m[1] : 'Lanjutkan aksi ini?';
      f.removeAttribute('onsubmit');
      f.addEventListener('submit', function(ev){
        if (f.dataset._gjOk) return; // sudah di-konfirmasi
        ev.preventDefault();
        ask(msg).then(function(ok){ if(ok){ f.dataset._gjOk='1'; f.submit(); } });
      });
    });
    // Revisi — Intercept anchor/button onclick="return confirm('...')" agar pakai Swal (no URL bawaan).
    document.querySelectorAll('a[onclick*="confirm("], button[onclick*="confirm("]').forEach(function(el){
      var orig = el.getAttribute('onclick') || '';
      var m = orig.match(/confirm\(\s*['"]([\s\S]*?)['"]\s*\)/);
      var msg = m ? m[1] : 'Lanjutkan?';
      el.removeAttribute('onclick');
      el.addEventListener('click', function(ev){
        if (el.dataset._gjOk) return;
        ev.preventDefault();
        ev.stopPropagation();
        ask(msg, {title:'Konfirmasi', yes:'Ya', no:'Batal'}).then(function(ok){
          if (!ok) return;
          el.dataset._gjOk = '1';
          var href = el.getAttribute('href');
          if (href && href !== '#') { window.location.href = href; }
          else { el.click(); }
        });
      });
    });
    document.querySelectorAll('a[data-confirm], button[data-confirm]').forEach(function(el){
      el.addEventListener('click', function(ev){
        if (el.dataset._gjOk) return;
        ev.preventDefault();
        ask(el.dataset.confirm || 'Lanjutkan?').then(function(ok){
          if(ok){ el.dataset._gjOk='1'; el.click(); }
        });
      });
    });
  });
})();
</script>
</head>
<body<?= !empty($pageSkeleton) ? ' data-skeleton="'.htmlspecialchars($pageSkeleton).'"' : '' ?>>

<?php /* ===== Revisi — Peringatan: hanya bisa dibuka di mobile / install aplikasi =====
        Overlay full-screen muncul untuk semua viewport ≥ 900px (desktop / tablet besar)
        dan menutupi seluruh halaman sehingga isi aplikasi tidak dapat diakses. */ ?>
<div id="kkDesktopBlocker" role="dialog" aria-modal="true" aria-labelledby="kkDesktopBlockerTitle">
  <div class="kk-db-card">
    <div class="kk-db-icon"><i class="bi bi-phone-fill"></i></div>
    <h2 id="kkDesktopBlockerTitle" class="kk-db-title">Buka di Handphone</h2>
    <p class="kk-db-desc">
      Aplikasi <strong>KawanKeringat</strong> hanya bisa dibuka di <strong>handphone (mobile)</strong>.
      Silakan buka halaman ini di handphone Anda dan <strong>install aplikasinya</strong> agar dapat digunakan sepenuhnya.
    </p>
    <div class="kk-db-steps">
      <div><i class="bi bi-1-circle-fill"></i> Buka URL ini di browser handphone Anda</div>
      <div><i class="bi bi-2-circle-fill"></i> Pilih menu browser → <em>Tambahkan ke Layar Utama</em> / <em>Install App</em></div>
      <div><i class="bi bi-3-circle-fill"></i> Jalankan dari ikon di layar utama HP Anda</div>
    </div>
    <div class="kk-db-foot">
      <i class="bi bi-info-circle"></i> Tampilan desktop dinonaktifkan demi konsistensi pengalaman aplikasi mobile.
    </div>
  </div>
</div>
<style>
#kkDesktopBlocker{ display:none; }
@media (min-width: 900px){
  #kkDesktopBlocker{
    display:flex; position:fixed; inset:0; z-index:2147483646;
    background:radial-gradient(1200px 600px at 50% -10%, #1e293b 0%, #0f172a 60%, #0b1220 100%);
    color:#e2e8f0; align-items:center; justify-content:center; padding:24px;
    font-family: 'Plus Jakarta Sans', system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
  }
  /* Hentikan scroll dan sembunyikan konten di bawah overlay supaya benar-benar di-block */
  html.kk-blocked, html.kk-blocked body{ overflow:hidden !important; }
  html.kk-blocked body > *:not(#kkDesktopBlocker){ visibility:hidden !important; }
  #kkDesktopBlocker .kk-db-card{
    max-width:520px; width:100%; background:rgba(15,23,42,.85);
    border:1px solid rgba(148,163,184,.25); border-radius:18px;
    padding:32px 28px; text-align:center; box-shadow:0 20px 60px rgba(0,0,0,.45);
    backdrop-filter: blur(6px);
  }
  #kkDesktopBlocker .kk-db-icon{
    width:72px; height:72px; border-radius:50%; margin:0 auto 14px;
    display:flex; align-items:center; justify-content:center;
    background:linear-gradient(135deg,#22d3ee,#3b82f6); color:#0b1220; font-size:34px;
    box-shadow:0 10px 25px rgba(59,130,246,.35);
  }
  #kkDesktopBlocker .kk-db-title{ margin:0 0 8px; font-weight:800; font-size:1.55rem; color:#f8fafc; }
  #kkDesktopBlocker .kk-db-desc{ margin:0 0 18px; line-height:1.55; color:#cbd5e1; }
  #kkDesktopBlocker .kk-db-steps{
    text-align:left; background:rgba(30,41,59,.6); border:1px solid rgba(148,163,184,.18);
    border-radius:12px; padding:14px 16px; display:flex; flex-direction:column; gap:8px; font-size:.93rem;
  }
  #kkDesktopBlocker .kk-db-steps i{ color:#22d3ee; margin-right:8px; }
  #kkDesktopBlocker .kk-db-foot{
    margin-top:16px; font-size:.82rem; color:#94a3b8;
  }
}
</style>
<script>
(function(){
  function apply(){
    var mq = window.matchMedia('(min-width: 900px)');
    document.documentElement.classList.toggle('kk-blocked', mq.matches);
  }
  apply();
  window.addEventListener('resize', apply, { passive: true });
})();
</script>

<div id="liveRefreshBadge" class="badge bg-success rounded-pill shadow"><i class="bi bi-arrow-clockwise"></i> Data diperbarui</div>

<?php /* ===== TOP header (mobile only) — biru-kehitaman ===== */ ?>
<header class="gt-top" role="banner">
  <div class="gt-row">
    <button class="gt-burger" type="button" data-bs-toggle="offcanvas" data-bs-target="#gtDrawer" aria-label="Buka menu" data-sfx="tap">
      <i class="bi bi-list"></i>
    </button>
    <form class="gt-search" role="search" action="/search.php" method="get" data-sfx-off>
      <i class="bi bi-search"></i>
      <input type="search" name="q" placeholder="<?= $u ? 'Cari aktivitas, tempat, member…' : 'Cari di KawanKeringat…' ?>" autocomplete="off">
    </form>
    <?php if ($u): ?>
      <!-- LONCENG: klik => buka popup notifikasi (BUKAN redirect) -->
      <button type="button" class="gt-bell" id="gtBellBtn" aria-label="Notifikasi" data-sfx="tap" title="Notifikasi"
              aria-haspopup="true" aria-expanded="false">
        <i class="bi bi-bell-fill"></i>
        <?php if ($nUnread): ?><span class="gt-badge-dot" id="gtBellBadge"><?= $nUnread > 9 ? '9+' : (int)$nUnread ?></span><?php endif; ?>
      </button>
      <a href="/logout.php" class="gt-logout" aria-label="Keluar" title="Keluar" data-sfx="tap"
         onclick="return confirm('Keluar dari akun?')"><i class="bi bi-box-arrow-right"></i><?= nav_lock_badge_for('logout.php') ?></a>
    <?php else: ?>
      <a href="/login.php" class="gt-bell" aria-label="Masuk" data-sfx="tap"><i class="bi bi-box-arrow-in-right"></i><?= nav_lock_badge_for('login.php') ?></a>
    <?php endif; ?>
  </div>
</header>

<?php if ($u): ?>
<!-- Popup notifikasi yang muncul saat lonceng diklik -->
<div class="gt-notif-pop" id="gtNotifPop" role="dialog" aria-modal="false" aria-label="Notifikasi terbaru">
  <div class="gt-notif-head">
    <span><i class="bi bi-bell-fill"></i> Notifikasi</span>
    <a href="#" id="gtNotifMark">Tandai dibaca</a>
  </div>
  <div class="gt-notif-list" id="gtNotifList">
    <div class="gt-notif-empty"><i class="bi bi-arrow-clockwise"></i> Memuat…</div>
  </div>
  <div class="gt-notif-foot">
    <a href="/profile.php#notif">Lihat semua di Profil</a>
  </div>
</div>
<?php endif; ?>

<nav class="gt-chips" aria-label="Pintasan">
  <a class="gt-chip <?= basename($_SERVER['SCRIPT_NAME'] ?? '')==='index.php'?'active':'' ?>" href="/index.php" data-sfx="tap"><i class="bi bi-house-door-fill"></i>Beranda</a>
  <?php if ($u): ?>
    <a class="gt-chip" href="/run.php" data-sfx="tap"><i class="bi bi-stopwatch-fill"></i>Tracking Jalur<?= nav_lock_badge_for('run.php') ?></a>
    <a class="gt-chip" href="/upload.php" data-sfx="tap"><i class="bi bi-cloud-upload-fill"></i>Upload<?= nav_lock_badge_for('upload.php') ?></a>
    <?php /* Menu Jajan & Kurir dihilangkan dari navigasi pengguna sesuai revisi. */ ?>
    <a class="gt-chip" href="/tempat_list.php" data-sfx="tap"><i class="bi bi-geo-alt-fill"></i>Tempat<?= nav_lock_badge_for('tempat_list.php') ?></a>
    <a class="gt-chip" href="/event.php" data-sfx="tap"><i class="bi bi-trophy-fill"></i>Event<?= nav_lock_badge_for('event.php') ?></a>
    <?php /* Revisi 6 Juni 2026: menu Check-in via barcode dihapus. */ ?>
    <?php /* Revisi 22 Juni 2026 R7 — chip Pesan (dm.php) dihapus dari menu. */ ?>
    <?php if (function_exists('scope_can_access_islami') && scope_can_access_islami()): ?><a class="gt-chip" href="/islami.php" data-sfx="tap"><i class="bi bi-stars"></i>Islami<?= nav_lock_badge_for('islami.php') ?></a><?php endif; ?>
    <a class="gt-chip" href="/kalkulator.php" data-sfx="tap"><i class="bi bi-calculator-fill"></i>Kalkulator<?= nav_lock_badge_for('kalkulator.php') ?></a>
  <?php else: ?>
    <a class="gt-chip" href="/login.php" data-sfx="tap"><i class="bi bi-box-arrow-in-right"></i>Masuk<?= nav_lock_badge_for('login.php') ?></a>
    <a class="gt-chip" href="/register.php" data-sfx="tap"><i class="bi bi-person-plus-fill"></i>Daftar<?= nav_lock_badge_for('register.php') ?></a>
  <?php endif; ?>
</nav>

<?php /* Drawer menu lengkap — "Profil Saya" duplikat DIHILANGKAN (sudah ada
        di avatar header atas dan ikon "Saya" di bottom nav). */ ?>
<?php /* Revisi Nov 2026 R12 — Warna ikon drawer mengikuti tema (--primary dari profile).
     Semua ikon Bootstrap Icons di dalam gt-drawer diwarnai --primary, kecuali
     ikon di dalam badge (agar badge tetap kontras). */ ?>
<style>
.gt-drawer .list-group-item i.bi,
.gt-drawer .offcanvas-title i.bi{ color: var(--primary, #0ea5e9) !important; }
.gt-drawer .list-group-item .badge i.bi{ color: inherit !important; }
</style>
<div class="offcanvas offcanvas-start gt-drawer" tabindex="-1" id="gtDrawer" aria-labelledby="gtDrawerLabel">
  <div class="offcanvas-header">
    <h5 class="offcanvas-title brand-logo-colored d-flex align-items-center gap-2" id="gtDrawerLabel">
      <img src="/assets/img/hapfam-logo.png" alt="KawanKeringat" height="26" style="height:26px;width:auto;border-radius:6px;background:#fff;padding:2px">
      <span><span class="bl-1">Kawan</span><span class="bl-3">Keringat</span></span>
    </h5>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Tutup"></button>
  </div>
  <div class="offcanvas-body p-0">
    <div class="list-group list-group-flush">
      <a class="list-group-item list-group-item-action" href="/index.php"><i class="bi bi-house-door-fill"></i> Beranda<?= nav_lock_badge_for('index.php') ?></a>
      <?php if ($u): ?>
        <?php /* ============================================================
              Revisi 13 Juni 2026:
              - Semua grup yang punya tombol dropdown disimpan PALING ATAS.
              - Menu Kalkulator dijadikan dropdown (collapse).
              - Menu non-dropdown (Beranda, Tempat, Pesan, dst.) ditaruh
                di bawah agar urutan grup berbelang dropdown rapi.
           ============================================================ */ ?>

        <?php /* Grup: Jogging Progress */ ?>
        <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" data-bs-toggle="collapse" href="#grpJogging" role="button" aria-expanded="false">
          <span><i class="bi bi-activity text-success"></i> Jogging Progress</span><i class="bi bi-chevron-down small"></i>
        </a>
        <div class="collapse" id="grpJogging">
          <a class="list-group-item list-group-item-action ps-4" href="/monitoring.php"><i class="bi bi-graph-up-arrow"></i> Monitoring<?= nav_lock_badge_for('monitoring.php') ?></a>
          <a class="list-group-item list-group-item-action ps-4" href="/upload.php"><i class="bi bi-cloud-upload"></i> Upload<?= nav_lock_badge_for('upload.php') ?></a>
          <a class="list-group-item list-group-item-action ps-4" href="/riwayat.php"><i class="bi bi-clock-history"></i> Riwayat<?= nav_lock_badge_for('riwayat.php') ?></a>
          <a class="list-group-item list-group-item-action ps-4" href="/run.php"><i class="bi bi-stopwatch-fill"></i> Tracking Jalur<?= nav_lock_badge_for('run.php') ?></a>
          <a class="list-group-item list-group-item-action ps-4" href="/live_tracking.php"><i class="bi bi-broadcast text-danger"></i> Live Tracking / Beacon<?= nav_lock_badge_for('live_tracking.php') ?></a>
          <a class="list-group-item list-group-item-action ps-4" href="/flyover.php"><i class="bi bi-camera-reels text-info"></i> Video Flyover 3D<?= nav_lock_badge_for('flyover.php') ?></a>
          <!-- Revisi 20 Juni 2026 R3 — Menu terpisah: Eksplorasi Rute & Peta Canggih -->
          <a class="list-group-item list-group-item-action ps-4" href="/run.php#eksplorasi"><i class="bi bi-compass text-primary"></i> Eksplorasi Rute &amp; Peta Canggih <span class="badge bg-primary ms-1">Paket Komunitas</span></a>
        </div>

        <?php /* Grup: Perhitungan Kalori Olahraga */ ?>
        <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" data-bs-toggle="collapse" href="#grpKalori" role="button" aria-expanded="false">
          <span><i class="bi bi-fire text-danger"></i> Perhitungan Kalori Olahraga</span><i class="bi bi-chevron-down small"></i>
        </a>
        <div class="collapse" id="grpKalori">
          <a class="list-group-item list-group-item-action ps-4" href="/kalori_badminton.php"><i class="bi bi-stopwatch text-success"></i> Kalori Badminton<?= nav_lock_badge_for('kalori_badminton.php') ?></a>
          <a class="list-group-item list-group-item-action ps-4" href="/kalori_renang.php"><i class="bi bi-water text-info"></i> Kalori Renang<?= nav_lock_badge_for('kalori_renang.php') ?></a>
          <a class="list-group-item list-group-item-action ps-4" href="/kalori_pingpong.php"><i class="bi bi-circle-fill text-warning"></i> Kalori Ping Pong<?= nav_lock_badge_for('kalori_pingpong.php') ?></a>
          <a class="list-group-item list-group-item-action ps-4" href="/kalori_futsal.php"><i class="bi bi-dribbble text-success"></i> Kalori Futsal<?= nav_lock_badge_for('kalori_futsal.php') ?></a>
          <a class="list-group-item list-group-item-action ps-4" href="/kalori_mingguan.php"><i class="bi bi-egg-fried text-warning"></i> Kalori Mingguan (Makanan)<?= nav_lock_badge_for('kalori_mingguan.php') ?></a>
        </div>

        <?php /* Grup: Agenda Kita */ ?>
        <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" data-bs-toggle="collapse" href="#grpAgenda" role="button" aria-expanded="false">
          <span><i class="bi bi-calendar-week-fill text-primary"></i> Agenda Kita</span><i class="bi bi-chevron-down small"></i>
        </a>
        <div class="collapse" id="grpAgenda">
          <a class="list-group-item list-group-item-action ps-4" href="/calendar.php"><i class="bi bi-calendar3"></i> Kalender<?= nav_lock_badge_for('calendar.php') ?></a>
          <a class="list-group-item list-group-item-action ps-4" href="/event.php"><i class="bi bi-trophy-fill"></i> Event<?= nav_lock_badge_for('event.php') ?></a>
          <a class="list-group-item list-group-item-action ps-4" href="/tempat.php"><i class="bi bi-calendar2-week"></i> Booking<?= nav_lock_badge_for('tempat.php') ?></a>
          <?php if (function_exists('is_admin') ? is_admin() : (($_SESSION['role'] ?? '') === 'admin')): ?>
          <a class="list-group-item list-group-item-action ps-4" href="/admin/tim.php"><i class="bi bi-people-fill"></i> Pembuatan Tim</a>
          <?php endif; ?>
        </div>

        <?php /* Grup: Kalkulator (Revisi 13 Juni 2026 — sekarang dropdown seperti grup Kalori) */ ?>
        <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" data-bs-toggle="collapse" href="#grpKalkulator" role="button" aria-expanded="false">
          <span><i class="bi bi-calculator-fill text-primary"></i> Kalkulator</span><i class="bi bi-chevron-down small"></i>
        </a>
        <div class="collapse" id="grpKalkulator">
          <a class="list-group-item list-group-item-action ps-4" href="/kalkulator.php"><i class="bi bi-heart-pulse-fill"></i> Kalkulator Sehat<?= nav_lock_badge_for('kalkulator.php') ?></a>
          <a class="list-group-item list-group-item-action ps-4" href="/kalkulator_jantung.php"><i class="bi bi-heart-pulse text-danger"></i> Kalkulator Detak Jantung<?= nav_lock_badge_for('kalkulator_jantung.php') ?></a>
          <a class="list-group-item list-group-item-action ps-4" href="/kalkulator_kesehatan.php"><i class="bi bi-clipboard2-pulse text-primary"></i> Kalkulator Kesehatan<?= nav_lock_badge_for('kalkulator_kesehatan.php') ?></a>
          <a class="list-group-item list-group-item-action ps-4" href="/gaya_hidup.php"><i class="bi bi-heart-pulse-fill text-danger"></i> Kalkulator Gaya Hidup<?= nav_lock_badge_for('gaya_hidup.php') ?></a>
        </div>

        <?php /* Grup: Info dan Wawasan (revisi 12 Juni 2026) — dipindah dari index.php */ ?>
        <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" data-bs-toggle="collapse" href="#grpInfoWawasan" role="button" aria-expanded="false">
          <span><i class="bi bi-compass text-primary"></i> Info dan Wawasan</span><i class="bi bi-chevron-down small"></i>
        </a>
        <div class="collapse" id="grpInfoWawasan">
          <a class="list-group-item list-group-item-action ps-4" href="/berita.php"><i class="bi bi-newspaper text-primary"></i> Berita Terkini<?= nav_lock_badge_for('berita.php') ?></a>
          <a class="list-group-item list-group-item-action ps-4" href="/opini_viral.php"><i class="bi bi-megaphone-fill text-danger"></i> Informasi Opini Terkini/Viral<?= nav_lock_badge_for('opini_viral.php') ?></a>
          <a class="list-group-item list-group-item-action ps-4" href="/cuaca.php"><i class="bi bi-cloud-sun-fill text-info"></i> Perkiraan Cuaca<?= nav_lock_badge_for('cuaca.php') ?></a>
          <a class="list-group-item list-group-item-action ps-4" href="/iptv.php"><i class="bi bi-tv text-info"></i> IPTV<?= nav_lock_badge_for('iptv.php') ?></a>
          <a class="list-group-item list-group-item-action ps-4" href="/toko_olahraga.php"><i class="bi bi-shop text-primary"></i> Toko Perlengkapan Olahraga Terdekat<?= nav_lock_badge_for('toko_olahraga.php') ?></a>
          <a class="list-group-item list-group-item-action ps-4" href="/hidup_sehat.php"><i class="bi bi-heart-fill text-success"></i> Hidup Sehat<?= nav_lock_badge_for('hidup_sehat.php') ?></a>
          <a class="list-group-item list-group-item-action ps-4" href="/kesehatan.php"><i class="bi bi-capsule text-danger"></i> Penyakit Umum dan Obat Herbal<?= nav_lock_badge_for('kesehatan.php') ?></a>
          <a class="list-group-item list-group-item-action ps-4" href="/artikel_olahraga.php"><i class="bi bi-journal-richtext text-info"></i> Artikel Olahraga &amp; Teknik <span class="badge bg-danger ms-1">+Video</span><?= nav_lock_badge_for('artikel_olahraga.php') ?></a>
          <a class="list-group-item list-group-item-action ps-4" href="/cedera_olahraga.php"><i class="bi bi-bandaid text-danger"></i> Cedera Olahraga &amp; Penanganan<?= nav_lock_badge_for('cedera_olahraga.php') ?></a>
          <a class="list-group-item list-group-item-action ps-4" href="/lacak_faskes.php"><i class="bi bi-hospital-fill text-danger"></i> Lacak Puskesmas / RS Terdekat<?= nav_lock_badge_for('lacak_faskes.php') ?></a>
          <a class="list-group-item list-group-item-action ps-4" href="/survival.php"><i class="bi bi-tree-fill text-success"></i> Survival Mode<?= nav_lock_badge_for('survival.php') ?></a>
        </div>

        <?php /* Revisi: Menu Paket Bugar Kalistenik dipindah ke atas Paket Anak */ ?>
        <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" href="/kalistenik.php">
          <span><i class="bi bi-person-arms-up text-success"></i> Paket Bugar Kalistenik</span><?= nav_lock_badge_for('kalistenik.php') ?>
        </a>

        <?php /* Revisi R23 (27 Juni 2026) — Grup Paket Anak & Paket Lansia (di atas menu Tempat) */ ?>
        <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" data-bs-toggle="collapse" href="#grpPaketAnak" role="button" aria-expanded="false">
          <span><i class="bi bi-emoji-smile-fill text-success"></i> Paket Anak</span><i class="bi bi-chevron-down small"></i>
        </a>
        <div class="collapse" id="grpPaketAnak">
          <a class="list-group-item list-group-item-action ps-4" href="/paket_anak_2_4.php"><i class="bi bi-balloon"></i> Usia 2–4 Tahun<?= nav_lock_badge_for('paket_anak_2_4.php') ?></a>
          <a class="list-group-item list-group-item-action ps-4" href="/paket_anak_4_6.php"><i class="bi bi-balloon-heart"></i> Usia 4–6 Tahun<?= nav_lock_badge_for('paket_anak_4_6.php') ?></a>
          <a class="list-group-item list-group-item-action ps-4" href="/paket_anak_7_9.php"><i class="bi bi-trophy"></i> Usia 7–9 Tahun<?= nav_lock_badge_for('paket_anak_7_9.php') ?></a>
          <a class="list-group-item list-group-item-action ps-4" href="/paket_anak_10_12.php"><i class="bi bi-stars"></i> Usia 10–12 Tahun<?= nav_lock_badge_for('paket_anak_10_12.php') ?></a>
          <a class="list-group-item list-group-item-action ps-4" href="https://wa.me/6281386369207?text=Halo%20KawanKeringat%2C%20saya%20ingin%20memesan%20Pemandu%20Olahraga." target="_blank" rel="noopener"><i class="bi bi-person-badge-fill text-success"></i> Pesan / Pemandu Olahraga <span class="badge bg-success ms-1">WA</span></a>
        </div>

        <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" data-bs-toggle="collapse" href="#grpPaketLansia" role="button" aria-expanded="false">
          <span><i class="bi bi-heart-pulse-fill text-info"></i> Paket Lansia</span><i class="bi bi-chevron-down small"></i>
        </a>
        <div class="collapse" id="grpPaketLansia">
          <a class="list-group-item list-group-item-action ps-4" href="/paket_lansia_55_69.php"><i class="bi bi-person-walking"></i> Usia 55–69 Tahun<?= nav_lock_badge_for('paket_lansia_55_69.php') ?></a>
          <a class="list-group-item list-group-item-action ps-4" href="/paket_lansia_70.php"><i class="bi bi-house-heart"></i> Usia 70+ Tahun<?= nav_lock_badge_for('paket_lansia_70.php') ?></a>
          <a class="list-group-item list-group-item-action ps-4" href="https://wa.me/6281386369207?text=Halo%20KawanKeringat%2C%20saya%20ingin%20memesan%20Pemandu%20Olahraga." target="_blank" rel="noopener"><i class="bi bi-person-badge-fill text-success"></i> Pesan / Pemandu Olahraga <span class="badge bg-success ms-1">WA</span></a>
        </div>

        <?php /* Revisi Juli 2026 R10 — Grup Paket Perokok (di bawah Paket Lansia) */ ?>
        <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" data-bs-toggle="collapse" href="#grpPaketPerokok" role="button" aria-expanded="false">
          <span><i class="bi bi-lungs-fill text-danger"></i> Paket Perokok</span><i class="bi bi-chevron-down small"></i>
        </a>
        <div class="collapse" id="grpPaketPerokok">
          <a class="list-group-item list-group-item-action ps-4" href="/paket_perokok_jogging.php"><i class="bi bi-person-walking"></i> Jogging<?= nav_lock_badge_for('paket_perokok_jogging.php') ?></a>
        </div>

        <?php /* Revisi 14 Juni 2026: shortcut Tempat/Pesan/Bookmark/Islami pindah ke bawah Info dan Wawasan */ ?>
        <a class="list-group-item list-group-item-action" href="/tempat_list.php"><i class="bi bi-geo-alt-fill"></i> Tempat<?= nav_lock_badge_for('tempat_list.php') ?></a>
        <?php /* Revisi 22 Juni 2026 R7 — menu drawer Pesan (dm.php) dihapus. */ ?>
        <a class="list-group-item list-group-item-action" href="/bookmark.php"><i class="bi bi-bookmark-star-fill"></i> Bookmark<?= nav_lock_badge_for('bookmark.php') ?></a>
        <?php if (function_exists('scope_can_access_islami') && scope_can_access_islami()): ?><a class="list-group-item list-group-item-action" href="/islami.php"><i class="bi bi-stars"></i> Islami<?= nav_lock_badge_for('islami.php') ?></a><?php endif; ?>


        <?php if (in_array($u['role'], ['admin','superadmin'], true)): $__isSuperNav = scope_is_super(); ?>
          <div class="px-3 pt-3 pb-1 small text-muted text-uppercase fw-bold" style="letter-spacing:.06em">Admin</div>

          <?php /* Admin > Giat Olahraga */ ?>
          <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" data-bs-toggle="collapse" href="#grpGiat" role="button" aria-expanded="false">
            <span><i class="bi bi-clipboard-data text-primary"></i> Giat Olahraga</span><i class="bi bi-chevron-down small"></i>
          </a>
          <div class="collapse" id="grpGiat">
            <a class="list-group-item list-group-item-action ps-4" href="/admin/jadwal.php"><i class="bi bi-shield-lock"></i> Manajemen Jadwal</a>
            <a class="list-group-item list-group-item-action ps-4" href="/admin/absensi.php"><i class="bi bi-clipboard-check"></i> Input Absensi</a>
            <a class="list-group-item list-group-item-action ps-4" href="/admin/pengeluaran.php"><i class="bi bi-cash-stack text-danger"></i> Rekap Pengeluaran Kegiatan</a>
            <a class="list-group-item list-group-item-action ps-4" href="/admin/tim.php"><i class="bi bi-people-fill"></i> Pengaturan Tim</a>
            <a class="list-group-item list-group-item-action ps-4" href="/admin/tempat.php"><i class="bi bi-geo-alt-fill"></i> CRUD Tempat</a>
            <a class="list-group-item list-group-item-action ps-4" href="/admin/tempat_survei.php"><i class="bi bi-hourglass-split text-warning"></i> Usulan Tempat (Survei)</a>
            <?php if ($__isSuperNav): ?><a class="list-group-item list-group-item-action ps-4" href="/admin/jenis.php"><i class="bi bi-tags"></i> Jenis Olahraga</a><?php endif; ?>
          </div>

          <?php /* Admin > Event Organize */ ?>
          <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" data-bs-toggle="collapse" href="#grpEventOrg" role="button" aria-expanded="false">
            <span><i class="bi bi-trophy text-warning"></i> Event Organize</span><i class="bi bi-chevron-down small"></i>
          </a>
          <div class="collapse" id="grpEventOrg">
            <a class="list-group-item list-group-item-action ps-4" href="/admin/event_absensi.php"><i class="bi bi-clipboard2-check text-warning"></i> Input Absensi Event</a>
            <a class="list-group-item list-group-item-action ps-4" href="/admin/event.php"><i class="bi bi-trophy"></i> Pengaturan Event</a>
          </div>

          <?php /* Admin > Member Organize */ ?>
          <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" data-bs-toggle="collapse" href="#grpMember" role="button" aria-expanded="false">
            <span><i class="bi bi-people-fill text-info"></i> Member Organize</span><i class="bi bi-chevron-down small"></i>
          </a>
          <div class="collapse" id="grpMember">
            <a class="list-group-item list-group-item-action ps-4" href="/admin/members.php"><i class="bi bi-people"></i> Member</a>
            <?php if ($__isSuperNav): ?><a class="list-group-item list-group-item-action ps-4" href="/admin/referal.php"><i class="bi bi-ticket-perforated"></i> Kode Referal Pendaftaran</a><?php endif; ?>
            <a class="list-group-item list-group-item-action ps-4" href="/admin/stats.php"><i class="bi bi-bar-chart"></i> Statistik</a>
            <?php /* Revisi Nov 2026 — Menu "Lacak HP Member" (drawer) DIHAPUS atas permintaan. */ ?>
            <?php /* Revisi Juli 2026 R3 — Pantau Progress Islami Member */ ?>
            <?php if ($__isSuperNav): ?><a class="list-group-item list-group-item-action ps-4" href="/admin/paket_pesanan.php"><i class="bi bi-receipt-cutoff text-success"></i> Pesanan Paket Member</a><?php endif; ?>
            <?php if ($__isSuperNav): ?><a class="list-group-item list-group-item-action ps-4" href="/pantau_progress_member.php"><i class="bi bi-graph-up-arrow text-danger"></i> Pantau Progress Islami</a><?php endif; ?>
            <?php /* Revisi 15 Juni 2026: menu "Riwayat Login Member" dihapus sesuai permintaan. */ ?>
          </div>

          <?php /* Admin > Komunitas Organize (Revisi Jul 2026) — super-only */ ?>
          <?php if ($__isSuperNav): ?>
          <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" data-bs-toggle="collapse" href="#grpKomunitas" role="button" aria-expanded="false">
            <span><i class="bi bi-people text-success"></i> Komunitas Organize</span><i class="bi bi-chevron-down small"></i>
          </a>
          <div class="collapse" id="grpKomunitas">
            <a class="list-group-item list-group-item-action ps-4" href="/admin/komunitas.php"><i class="bi bi-people-fill text-success"></i> Komunitas</a>
          </div>
          <?php endif; ?>


          <?php /* Admin > Pengaturan Lainnya — super-only */ ?>
          <?php if ($__isSuperNav): ?>
          <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" data-bs-toggle="collapse" href="#grpLainnya" role="button" aria-expanded="false">
            <span><i class="bi bi-gear-fill text-secondary"></i> Pengaturan Lainnya</span><i class="bi bi-chevron-down small"></i>
          </a>
          <div class="collapse" id="grpLainnya">
            <a class="list-group-item list-group-item-action ps-4" href="/admin/reports.php"><i class="bi bi-flag text-danger"></i> Laporan Postingan</a>
            <a class="list-group-item list-group-item-action ps-4" href="/admin/privasi.php"><i class="bi bi-shield-check text-success"></i> Kebijakan Privasi (UU PDP)</a>
            <a class="list-group-item list-group-item-action ps-4" href="/admin/iptv.php"><i class="bi bi-tv text-info"></i> IPTV</a>
            <a class="list-group-item list-group-item-action ps-4" href="/admin/sistem.php"><i class="bi bi-cpu text-info"></i> Cek Sistem</a>
            <?php /* Revisi 22 Juni 2026 R7 — CRUD kata kunci filter pencarian video (kalistenik & survival) */ ?>
            <a class="list-group-item list-group-item-action ps-4" href="/admin/keywords.php"><i class="bi bi-funnel-fill text-primary"></i> Kata Kunci Filter Video</a>
            <?php /* Revisi Juli 2026 — Menu "Pengaturan Paket Member" (admin/paket_member.php) dan "Navigasi Menu" (admin/menu.php) DIHAPUS dari drawer. */ ?>
          </div>
          <?php endif; /* Pengaturan Lainnya super-only */ ?>

        <?php endif; ?>

        <a class="list-group-item list-group-item-action text-danger" href="/logout.php"><i class="bi bi-box-arrow-right"></i> Keluar<?= nav_lock_badge_for('logout.php') ?></a>
        <?php /* Revisi 13 Juni 2026: menu non-dropdown disimpan PALING BAWAH agar
               grup ber-dropdown selalu berada di paling atas drawer. */ ?>
      <?php else: ?>
        <a class="list-group-item list-group-item-action" href="/login.php"><i class="bi bi-box-arrow-in-right"></i> Masuk<?= nav_lock_badge_for('login.php') ?></a>
        <a class="list-group-item list-group-item-action" href="/register.php"><i class="bi bi-person-plus-fill"></i> Daftar<?= nav_lock_badge_for('register.php') ?></a>
      <?php endif; ?>
    </div>
  </div>
</div>
<!-- /Mobile top header -->

<!-- Revisi 24 Juni 2026 — Navbar desktop:
     warna disamakan dengan top header mobile (.gt-top) yaitu gradien
     #0f172a → #1e293b → #243049, dan logo brand memakai gambar
     KawanKeringat (hapfam-logo.png), bukan icon petir. -->
<nav class="navbar navbar-expand-lg sticky-top kk-desktop-nav" data-bs-theme="dark"
     style="background:linear-gradient(135deg,#0f172a 0%, #1e293b 60%, #243049 100%);">
  <div class="container">
    <a class="navbar-brand fw-bold brand-logo-colored d-flex align-items-center gap-2" href="/index.php">
      <img src="/assets/img/hapfam-logo.png" alt="KawanKeringat" height="28" style="height:28px;width:auto;border-radius:6px;background:#fff;padding:2px">
      <span><span class="bl-1">Kawan</span><span class="bl-3">Keringat</span></span>
    <?= nav_lock_badge_for('index.php') ?></a>
    <button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#nav"><span class="navbar-toggler-icon"></span></button>
    <div class="collapse navbar-collapse" id="nav">
      <ul class="navbar-nav me-auto">
        <li class="nav-item"><a class="nav-link" href="/index.php"><i class="bi bi-house-door"></i> Beranda<?= nav_lock_badge_for('index.php') ?></a></li>
        <?php if ($u): ?>
          <li class="nav-item"><a class="nav-link" href="/calendar.php"><i class="bi bi-calendar3"></i> Kalender<?= nav_lock_badge_for('calendar.php') ?></a></li>
          <li class="nav-item"><a class="nav-link" href="/riwayat.php"><i class="bi bi-clock-history"></i> Riwayat<?= nav_lock_badge_for('riwayat.php') ?></a></li>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown" role="button" aria-expanded="false"><i class="bi bi-emoji-smile-fill"></i> Paket Anak</a>
            <ul class="dropdown-menu shadow">
              <li><a class="dropdown-item" href="/paket_anak_2_4.php"><i class="bi bi-balloon"></i> Usia 2–4 Tahun<?= nav_lock_badge_for('paket_anak_2_4.php') ?></a></li>
              <li><a class="dropdown-item" href="/paket_anak_4_6.php"><i class="bi bi-balloon-heart"></i> Usia 4–6 Tahun<?= nav_lock_badge_for('paket_anak_4_6.php') ?></a></li>
              <li><a class="dropdown-item" href="/paket_anak_7_9.php"><i class="bi bi-trophy"></i> Usia 7–9 Tahun<?= nav_lock_badge_for('paket_anak_7_9.php') ?></a></li>
              <li><a class="dropdown-item" href="/paket_anak_10_12.php"><i class="bi bi-stars"></i> Usia 10–12 Tahun<?= nav_lock_badge_for('paket_anak_10_12.php') ?></a></li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item" href="https://wa.me/6281386369207?text=Halo%20KawanKeringat%2C%20saya%20ingin%20memesan%20Pemandu%20Olahraga." target="_blank" rel="noopener"><i class="bi bi-person-badge-fill text-success"></i> Pesan / Pemandu Olahraga</a></li>
            </ul>
          </li>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown" role="button" aria-expanded="false"><i class="bi bi-lungs-fill"></i> Paket Perokok</a>
            <ul class="dropdown-menu shadow">
              <li><a class="dropdown-item" href="/paket_perokok_jogging.php"><i class="bi bi-person-walking"></i> Jogging<?= nav_lock_badge_for('paket_perokok_jogging.php') ?></a></li>
            </ul>
          </li>
          <li class="nav-item"><a class="nav-link" href="/tempat_list.php"><i class="bi bi-geo-alt"></i> Tempat<?= nav_lock_badge_for('tempat_list.php') ?></a></li>
          <?php /* Revisi 6 Juni 2026: Check-in via barcode dihapus dari navbar desktop. */ ?>
          <li class="nav-item"><a class="nav-link" href="/upload.php"><i class="bi bi-cloud-upload"></i> Upload<?= nav_lock_badge_for('upload.php') ?></a></li>
          <li class="nav-item"><a class="nav-link" href="/monitoring.php"><i class="bi bi-graph-up-arrow"></i> Monitoring<?= nav_lock_badge_for('monitoring.php') ?></a></li>
          <li class="nav-item"><a class="nav-link" href="/event.php"><i class="bi bi-trophy"></i> Event<?= nav_lock_badge_for('event.php') ?></a></li>
          <li class="nav-item"><a class="nav-link" href="/tempat.php"><i class="bi bi-calendar2-week"></i> Booking<?= nav_lock_badge_for('tempat.php') ?></a></li>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown" role="button" aria-expanded="false"><i class="bi bi-calculator-fill"></i> Kalkulator</a>
            <ul class="dropdown-menu shadow">
              <li><h6 class="dropdown-header">Kalkulator</h6></li>
              <li><a class="dropdown-item" href="/kalkulator.php"><i class="bi bi-heart-pulse-fill"></i> Kalkulator Sehat<?= nav_lock_badge_for('kalkulator.php') ?></a></li>
              <li><a class="dropdown-item" href="/kalkulator_jantung.php"><i class="bi bi-heart-pulse text-danger"></i> Kalkulator Detak Jantung<?= nav_lock_badge_for('kalkulator_jantung.php') ?></a></li>
              <li><a class="dropdown-item" href="/kalkulator_kesehatan.php"><i class="bi bi-clipboard2-pulse text-primary"></i> Kalkulator Kesehatan<?= nav_lock_badge_for('kalkulator_kesehatan.php') ?></a></li>
              <li><hr class="dropdown-divider"></li>
              <li><h6 class="dropdown-header">Kalori</h6></li>
              <li><a class="dropdown-item" href="/kalori_badminton.php"><i class="bi bi-stopwatch text-success"></i> Kalori Badminton<?= nav_lock_badge_for('kalori_badminton.php') ?></a></li>
              <li><a class="dropdown-item" href="/kalori_renang.php"><i class="bi bi-water text-info"></i> Kalori Renang<?= nav_lock_badge_for('kalori_renang.php') ?></a></li>
              <li><a class="dropdown-item" href="/kalori_pingpong.php"><i class="bi bi-circle-fill text-warning"></i> Kalori Ping Pong<?= nav_lock_badge_for('kalori_pingpong.php') ?></a></li>
              <li><a class="dropdown-item" href="/kalori_futsal.php"><i class="bi bi-dribbble text-success"></i> Kalori Futsal<?= nav_lock_badge_for('kalori_futsal.php') ?></a></li>
              <li><a class="dropdown-item" href="/kalori_mingguan.php"><i class="bi bi-egg-fried text-warning"></i> Kalori Mingguan (Makanan)<?= nav_lock_badge_for('kalori_mingguan.php') ?></a></li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item" href="/gaya_hidup.php"><i class="bi bi-heart-pulse-fill text-danger"></i> Kalkulator Gaya Hidup<?= nav_lock_badge_for('gaya_hidup.php') ?></a></li>
            </ul>
          </li>
          <li class="nav-item"><a class="nav-link" href="/run.php"><i class="bi bi-stopwatch text-danger"></i> Tracking Jalur<?= nav_lock_badge_for('run.php') ?></a></li>
          <?php /* Revisi 22 Juni 2026 R7 — nav-link Pesan (dm.php) dihapus dari navbar desktop. */ ?>
          <li class="nav-item"><a class="nav-link" href="/bookmark.php"><i class="bi bi-bookmark-star text-warning"></i> Bookmark<?= nav_lock_badge_for('bookmark.php') ?></a></li>
          <?php if (function_exists('scope_can_access_islami') && scope_can_access_islami()): ?><li class="nav-item"><a class="nav-link" href="/islami.php"><i class="bi bi-stars text-warning"></i> Islami<?= nav_lock_badge_for('islami.php') ?></a></li><?php endif; ?>
        <?php endif; ?>
        <?php if ($u && in_array($u['role'], ['admin','superadmin'], true)): $__isSuperNav2 = scope_is_super(); ?>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-shield-lock"></i> Admin</a>
            <ul class="dropdown-menu dropdown-menu-end shadow">
              <li><a class="dropdown-item" href="/admin/jadwal.php">Manajemen Jadwal</a></li>
              <li><a class="dropdown-item" href="/admin/pengeluaran.php"><i class="bi bi-cash-stack text-danger"></i> Rekap Pengeluaran Kegiatan</a></li>
              <li><a class="dropdown-item" href="/admin/absensi.php">Input Absensi</a></li>
              <?php /* Revisi 6 Juni 2026: QR Check-in admin dihapus dari dropdown. */ ?>
              <li><a class="dropdown-item" href="/admin/members.php">Member</a></li>
              <li><a class="dropdown-item" href="/admin/tim.php">Tim</a></li>
              <li><a class="dropdown-item" href="/admin/tempat.php">Tempat</a></li>
              <li><a class="dropdown-item" href="/admin/toko_olahraga.php"><i class="bi bi-shop text-primary"></i> Toko Perlengkapan Olahraga</a></li>
              <li><a class="dropdown-item" href="/admin/event.php">Event / Tournament</a></li>
              <li><a class="dropdown-item" href="/admin/stats.php">📊 Statistik Pintar</a></li>
              <?php if ($__isSuperNav2): ?><li><a class="dropdown-item" href="/admin/jenis.php">Jenis Olahraga</a></li><?php endif; ?>
              <?php if ($__isSuperNav2): ?><li><a class="dropdown-item" href="/admin/referal.php"><i class="bi bi-ticket-perforated"></i> Kode Referal</a></li><?php endif; ?>
              <li><a class="dropdown-item" href="/admin/reports.php"><i class="bi bi-flag text-danger"></i> Laporan Postingan</a></li>
              <li><hr class="dropdown-divider"></li>
              <li><hr class="dropdown-divider"></li>
              <li><h6 class="dropdown-header">CMS &amp; Pengaturan</h6></li>
              <li><a class="dropdown-item" href="/admin/privasi.php"><i class="bi bi-shield-check text-success"></i> Kebijakan Privasi (UU PDP)</a></li>
              <li><hr class="dropdown-divider"></li>
                                                                                    <?php if ($__isSuperNav2): ?><li><a class="dropdown-item" href="/admin/lacak.php"><i class="bi bi-broadcast-pin text-danger"></i> Lacak HP Member</a></li><?php endif; ?>
              <li><hr class="dropdown-divider"></li>
              <li><h6 class="dropdown-header">Export Data</h6></li>
              <li><a class="dropdown-item" href="/export.php?type=members&format=csv">Member · Excel</a></li>
              <li><a class="dropdown-item" href="/export.php?type=jadwal&format=csv">Jadwal · Excel</a></li>
              <li><a class="dropdown-item" href="/export.php?type=tempat&format=csv">Tempat · Excel</a></li>
              <li><a class="dropdown-item" href="/export.php?type=aktivitas&format=csv">Aktivitas · Excel</a></li>
              <li><a class="dropdown-item" href="/export.php?type=booking&format=csv">Booking · Excel</a></li>
              <li><a class="dropdown-item" href="/export.php?type=members&format=pdf">Member · PDF</a></li>
            </ul>
          </li>
        <?php endif; ?>
      </ul>
      <?php if ($u): ?>
      <form class="d-flex me-2" role="search" action="/search.php">
        <input class="form-control form-control-sm" name="q" placeholder="🔍 Cari semua..." style="min-width:180px">
      </form>
      <?php endif; ?>

      <ul class="navbar-nav align-items-lg-center">
        <?php if ($u): ?>
          <!-- Lonceng desktop: dropdown notifikasi, BUKAN redirect -->
          <li class="nav-item">
            <button id="gtBellBtnDesktop" type="button" class="nav-link position-relative btn btn-link" title="Notifikasi" aria-haspopup="true" aria-expanded="false">
              <i class="bi bi-bell-fill"></i>
              <?php if($nUnread): ?><span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:.6rem;"><?= $nUnread ?></span><?php endif; ?>
            </button>
          </li>
          <!-- Avatar = satu-satunya pintu ke Profil Saya di navbar (duplikat dibersihkan) -->
          <li class="nav-item"><a class="nav-link" href="/profile.php" title="Profil saya">
            <?= user_avatar($navFoto, $u['nama'], 28) ?>
          <?= nav_lock_badge_for('profile.php') ?></a></li>
          <li class="nav-item"><a class="nav-link" href="/logout.php" title="Keluar"><i class="bi bi-box-arrow-right"></i><?= nav_lock_badge_for('logout.php') ?></a></li>
        <?php else: ?>
          <li class="nav-item"><a class="nav-link" href="/login.php">Login<?= nav_lock_badge_for('login.php') ?></a></li>
          <li class="nav-item"><a class="nav-link" href="/register.php">Daftar<?= nav_lock_badge_for('register.php') ?></a></li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>
<?php require_once __DIR__.'/skeleton.php'; ?>

<!-- Revisi 18 Juni 2026 — Interactive page transition.
     Klik link internal → main content langsung diganti skeleton (sesuai $pageSkeleton)
     sementara nav atas + bottom-nav tetap tampil → memberi kesan halaman terbuka
     dulu, baru data load. Saat halaman baru selesai load, skeleton hilang otomatis. -->
<style>
/* Revisi 18 Juni 2026 — Skeleton overlay rapih: tidak menabrak header atas (gt-top + gt-chips)
   di tampilan mobile, dan tidak menabrak bottom-nav. */
#hfPageTransOverlay{
  position:fixed; left:0; right:0; z-index:1040;
  background:var(--bs-body-bg,#fff);
  padding:1rem 1rem 1.25rem;
  overflow:auto; display:none;
  -webkit-overflow-scrolling:touch;
}
#hfPageTransOverlay.active{display:block;}
#hfPageTransOverlay .hf-skel-wrap{max-width:980px;margin:0 auto;}
body[data-hf-loading="1"] main{visibility:hidden;}
.hf-page-trans-bar{height:3px;background:linear-gradient(90deg,#16a34a,#2563eb,#f59e0b);
  position:fixed;top:0;left:0;right:0;z-index:1080;transform-origin:left;animation:hfTransBar 1.6s ease-in-out infinite;}
@keyframes hfTransBar{0%{transform:scaleX(0)}50%{transform:scaleX(.7)}100%{transform:scaleX(1)}}

/* Skeleton auto-flash (data-live / data-skel-shape) juga diberi margin atas di mobile
   agar tidak ketutup header sticky gt-top + gt-chips. */
@media (max-width: 991.98px){
  .hf-skel-wrap{padding-top:.35rem;}
  #hfPageTransOverlay{padding-top:1.25rem;}
}
</style>
<script>
(function(){
  // Hitung offset header atas (gt-top + gt-chips) supaya overlay tidak menabrak.
  function topOffset(){
    var gt = document.querySelector('.gt-top');
    var ch = document.querySelector('.gt-chips');
    var nb = document.querySelector('nav.navbar.sticky-top');
    var top = 0;
    if (gt && getComputedStyle(gt).display !== 'none') top += gt.offsetHeight;
    if (ch && getComputedStyle(ch).position === 'fixed') top += ch.offsetHeight;
    if (!top && nb && getComputedStyle(nb).position === 'fixed') top += nb.offsetHeight;
    return top;
  }
  function bottomOffset(){
    // Revisi 19 Juni 2026 — sertakan juga .gj-nav (navigasi mobile baru, gaya Gojek)
    // agar overlay skeleton tidak menutupi bottom nav PWA di mobile.
    var bn = document.querySelector('.gj-nav,.bottom-nav,.mobile-bottom-nav,nav.fixed-bottom');
    return bn ? bn.offsetHeight : 0;
  }
  function isInternalNav(a){
    if (!a || a.target==='_blank') return false;
    if (a.hasAttribute('download') || a.dataset.noTrans==='1') return false;
    var href = a.getAttribute('href')||''; if (!href) return false;
    if (href.startsWith('#') || href.startsWith('javascript:') || href.startsWith('mailto:') || href.startsWith('tel:')) return false;
    try{
      var u = new URL(href, location.href);
      if (u.origin !== location.origin) return false;
      if (u.pathname === location.pathname && u.search === location.search) return false;
      if (/\.(jpg|jpeg|png|gif|webp|pdf|mp4|mp3|zip|json)$/i.test(u.pathname)) return false;
      return true;
    }catch(e){ return false; }
  }
  function skeletonHtml(shape){
    var fn = (window.HFSkel && window.HFSkel.shapes && window.HFSkel.shapes[shape])
              ? window.HFSkel.shapes[shape] : null;
    if (!fn) fn = (window.HFSkel && window.HFSkel.shapes && window.HFSkel.shapes.list);
    if (!fn) return '<div style="padding:2rem;text-align:center;color:#888"><span class="spinner-border spinner-border-sm"></span> Memuat halaman…</div>';
    return '<div class="hf-skel-wrap">'+fn()+'</div>';
  }
  function showTransition(shape){
    if (!document.getElementById('hfTransBar')){
      var b = document.createElement('div'); b.id='hfTransBar'; b.className='hf-page-trans-bar';
      document.body.appendChild(b);
    }
    var ov = document.getElementById('hfPageTransOverlay');
    if (!ov){
      ov = document.createElement('div'); ov.id='hfPageTransOverlay';
      document.body.appendChild(ov);
    }
    var top = topOffset(), bot = bottomOffset();
    // Padding ekstra 8px agar skeleton tidak nempel persis ke nav atas.
    ov.style.top    = (top + 8) + 'px';
    ov.style.bottom = (bot + 4) + 'px';
    ov.innerHTML = skeletonHtml(shape) +
      '<div id="hfReloadBox" style="position:absolute;left:50%;bottom:24px;transform:translateX(-50%);'+
      'display:none;z-index:5;background:rgba(15,23,42,.92);color:#fff;padding:10px 14px;border-radius:999px;'+
      'box-shadow:0 6px 20px rgba(0,0,0,.25);font-size:.9rem;align-items:center;gap:.5rem;">'+
      '<span>Memuat terlalu lama?</span>'+
      '<button type="button" id="hfReloadBtn" class="btn btn-sm btn-warning" style="border-radius:999px;">'+
      '<i class="bi bi-arrow-clockwise"></i> Muat ulang</button></div>';
    ov.classList.add('active');
    document.body.setAttribute('data-hf-loading','1');
    // Revisi R12 — tampilkan tombol refresh jika loading > 6 detik
    if (window._hfReloadTimer) clearTimeout(window._hfReloadTimer);
    window._hfReloadTimer = setTimeout(function(){
      var box = document.getElementById('hfReloadBox');
      if (box) box.style.display = 'inline-flex';
    }, 6000);
    var btn = document.getElementById('hfReloadBtn');
    if (btn) btn.addEventListener('click', function(){ try{ location.reload(); }catch(_){ } });
  }

  document.addEventListener('click', function(ev){
    var a = ev.target.closest('a');
    if (!a || !isInternalNav(a)) return;
    if (ev.metaKey||ev.ctrlKey||ev.shiftKey||ev.altKey||ev.button) return;
    // Tentukan shape berdasarkan path target (heuristik sederhana).
    var path = (new URL(a.href, location.href)).pathname.split('/').pop() || 'index.php';
    var map = {
      'index.php':'feed','islami.php':'feed','feed_islami.php':'feed',
      'artikel_olahraga.php':'feed','artikel_sunnah.php':'feed','berita.php':'feed','hidup_sehat.php':'feed',
      'kalori_mingguan.php':'table','riwayat.php':'table','leaderboard_islami.php':'table',
      'run.php':'grid','tempat.php':'grid','buku.php':'grid','jajanan.php':'grid','beasiswa.php':'grid',
      'dm.php':'chat','profile.php':'profile','user.php':'profile',
      'jadwal_sholat.php':'jadwal','event.php':'jadwal','calendar.php':'jadwal',
      'doa.php':'list','dzikir.php':'list','hadist.php':'list','quran.php':'list'
    };
    var shape = map[path] || 'list';
    showTransition(shape);
    // Biarkan navigasi default berjalan
  }, true);

  // Saat halaman baru load (incl back/forward), bersihkan overlay.
  window.addEventListener('pageshow', function(){
    if (window._hfReloadTimer){ clearTimeout(window._hfReloadTimer); window._hfReloadTimer=null; }
    var ov = document.getElementById('hfPageTransOverlay'); if (ov) ov.classList.remove('active');
    var bar = document.getElementById('hfTransBar'); if (bar) bar.remove();
    document.body.removeAttribute('data-hf-loading');
  });

  // Revisi 19 Juni 2026 — Issue #7: ketika user beralih tab di mobile saat halaman
  // sedang loading, lalu kembali ke aplikasi, kadang overlay/skeleton stuck dan
  // tampilan jadi "tanpa CSS". Bersihkan overlay saat visibility kembali visible
  // dan pastikan halaman di-paint ulang. Bila document.readyState belum complete
  // setelah 1.5s, paksa reload lembut sekali agar CSS/JS lengkap.
  var _hfHidAt = 0;
  document.addEventListener('visibilitychange', function(){
    if (document.visibilityState === 'hidden'){ _hfHidAt = Date.now(); return; }
    // visible again
    var ov = document.getElementById('hfPageTransOverlay'); if (ov) ov.classList.remove('active');
    var bar = document.getElementById('hfTransBar'); if (bar) bar.remove();
    document.body.removeAttribute('data-hf-loading');
    // Paksa repaint untuk fix layout/CSS yang stuck di mobile Chrome
    try {
      document.body.style.opacity = '0.999';
      requestAnimationFrame(function(){ document.body.style.opacity = ''; });
    } catch(_){}
    // Bila balik dari background >5 detik & dokumen belum complete -> reload sekali
    if (_hfHidAt && (Date.now() - _hfHidAt) > 5000 && document.readyState !== 'complete'){
      if (!sessionStorage.getItem('_hfReloaded')){
        sessionStorage.setItem('_hfReloaded','1');
        setTimeout(function(){ if (document.readyState !== 'complete') location.reload(); }, 1200);
      }
    }
  });
  window.addEventListener('load', function(){ try{ sessionStorage.removeItem('_hfReloaded'); }catch(_){} });

  // Saat halaman baru DOMContentLoaded, jika $pageSkeleton diset → isi skeleton awal
  // pada elemen utama (main/.main-content/container) sampai window load selesai.
  document.addEventListener('DOMContentLoaded', function(){
    var shape = document.body.getAttribute('data-skeleton');
    if (!shape) return;
    // Cari kontainer utama
    var main = document.querySelector('main, .main-content, .container, .container-fluid, .page-body');
    if (!main) return;
    // Cari kandidat list/feed di main; jika ada [data-live] tanpa konten kuat, isi.
    var hostsLive = main.querySelectorAll('[data-live],[data-skel-shape]').length>0;
    if (hostsLive) return; // skeleton.php sudah handle saat refresh
  });
})();
</script>

<?php if ($u): ?>
<script>
// === Revisi 4 Jun 2026: lonceng = popup notifikasi (BUKAN redirect) ===
(function(){
  var btnM = document.getElementById('gtBellBtn');
  var btnD = document.getElementById('gtBellBtnDesktop');
  var pop  = document.getElementById('gtNotifPop');
  var list = document.getElementById('gtNotifList');
  var badge= document.getElementById('gtBellBadge');
  var loaded = false;
  function fmtTime(ts){
    try { var d = new Date((ts||'').replace(' ','T')); if(isNaN(d)) return ''; 
      var diff = (Date.now()-d.getTime())/1000;
      if (diff<60) return 'baru saja';
      if (diff<3600) return Math.floor(diff/60)+' mnt lalu';
      if (diff<86400) return Math.floor(diff/3600)+' jam lalu';
      return d.toLocaleDateString('id-ID',{day:'2-digit',month:'short'});
    } catch(e){ return ''; }
  }
  function icoFor(jenis){
    var map = { booking:'calendar2-week', jadwal:'calendar-event', donasi:'cash-coin',
      challenge:'trophy', dm:'chat-dots-fill', tempat:'geo-alt-fill',
      event:'trophy-fill', upload:'cloud-upload-fill', sistem:'gear-fill' };
    return map[jenis] || 'bell-fill';
  }
  function render(items){
    if (!items || !items.length){
      list.innerHTML = '<div class="gt-notif-empty"><i class="bi bi-inbox"></i><br>Belum ada notifikasi.</div>';
      return;
    }
    var html = '';
    items.forEach(function(n){
      var href = n.url || '#';
      html += '<a class="gt-notif-item" href="'+href+'" data-sfx="tap">'+
              '<span class="gt-notif-ico"><i class="bi bi-'+icoFor(n.jenis||'')+'"></i></span>'+
              '<span class="gt-notif-body">'+
                '<span class="gt-notif-title">'+(n.judul||'(tanpa judul)')+'</span>'+
                '<span class="gt-notif-text">'+(n.isi||'')+'</span>'+
                '<span class="gt-notif-text" style="opacity:.7;margin-top:2px">'+fmtTime(n.dibuat_pada)+'</span>'+
              '</span></a>';
    });
    list.innerHTML = html;
  }
  function load(){
    if (loaded) return;
    fetch('/api_notif_list.php', { credentials:'same-origin' })
      .then(function(r){ return r.json(); })
      .then(function(d){ render(d.items||[]); loaded = true; })
      .catch(function(){ list.innerHTML = '<div class="gt-notif-empty">Gagal memuat notifikasi.</div>'; });
  }
  function toggle(force){
    if (!pop) return;
    var show = (force!=null) ? force : !pop.classList.contains('show');
    pop.classList.toggle('show', show);
    if (btnM) btnM.setAttribute('aria-expanded', show?'true':'false');
    if (btnD) btnD.setAttribute('aria-expanded', show?'true':'false');
    if (show) load();
  }
  if (btnM) btnM.addEventListener('click', function(e){ e.preventDefault(); toggle(); });
  if (btnD) btnD.addEventListener('click', function(e){ e.preventDefault(); toggle(); });
  document.addEventListener('click', function(e){
    if (!pop || !pop.classList.contains('show')) return;
    if (pop.contains(e.target)) return;
    if (btnM && btnM.contains(e.target)) return;
    if (btnD && btnD.contains(e.target)) return;
    toggle(false);
  });
  var mark = document.getElementById('gtNotifMark');
  if (mark) mark.addEventListener('click', function(e){
    e.preventDefault();
    fetch('/api_notif_list.php?mark=1', { credentials:'same-origin' })
      .then(function(r){ return r.json(); })
      .then(function(d){ render(d.items||[]); if (badge) badge.remove();
        document.querySelectorAll('.badge.bg-danger').forEach(function(b){
          if (/^\d+$/.test((b.textContent||'').trim())) b.remove();
        });
      });
  });
})();
</script>
<?php endif; ?>

<main class="container py-3">

<?php if (!empty($u)): ?>
<script>
// Heartbeat lokasi HP (untuk fitur Lacak HP oleh Admin)
(function(){
  if (!navigator.geolocation) return;
  var csrf = '<?= csrf_token() ?>';
  function ping(){
    navigator.geolocation.getCurrentPosition(function(pos){
      var fd = new FormData();
      fd.append('csrf', csrf);
      fd.append('lat', pos.coords.latitude);
      fd.append('lng', pos.coords.longitude);
      fd.append('acc', pos.coords.accuracy || '');
      fd.append('device', navigator.userAgent.substring(0,120));
      fetch('/api_device_loc.php', {method:'POST', body:fd, keepalive:true}).catch(function(){});
    }, function(){}, {enableHighAccuracy:false, timeout:20000, maximumAge:60000});
  }
  setTimeout(ping, 5000);
  setInterval(ping, 2*60*1000);
})();
</script>
<?php endif; ?>


<?php /* Revisi 18 Juni 2026 — Loading spinner kecil di samping teks item nav samping (drawer) mobile */ ?>
<style>
.gt-drawer .list-group-item .gt-side-spin{
  display:none; width:.85rem; height:.85rem; margin-left:6px; vertical-align:-1px;
  border:2px solid currentColor; border-right-color:transparent; border-radius:50%;
  animation: gtSideSpin .7s linear infinite;
}
.gt-drawer .list-group-item.is-loading .gt-side-spin{ display:inline-block; }
.gt-drawer .list-group-item.is-loading{ opacity:.75; pointer-events:none; }
@keyframes gtSideSpin { to { transform: rotate(360deg); } }
</style>
<script>
(function(){
  var drawer = document.getElementById("gtDrawer");
  if (!drawer) return;
  drawer.querySelectorAll(".list-group-item-action").forEach(function(a){
    if (a.getAttribute("data-bs-toggle") === "collapse") return; // skip dropdown toggle
    if (!a.querySelector(".gt-side-spin")){
      var s = document.createElement("span");
      s.className = "gt-side-spin";
      s.setAttribute("aria-hidden","true");
      a.appendChild(s);
    }
    a.addEventListener("click", function(e){
      if (e.metaKey||e.ctrlKey||e.shiftKey||e.button) return;
      var href = (a.getAttribute("href")||"").trim();
      if (!href || href.startsWith("#") || href.startsWith("javascript:")) return;
      try{
        var u = new URL(href, location.href);
        if (u.origin !== location.origin) return;
      }catch(_){ return; }
      a.classList.add("is-loading");
    });
  });
  window.addEventListener("pageshow", function(){
    drawer.querySelectorAll(".list-group-item.is-loading").forEach(function(it){ it.classList.remove("is-loading"); });
  });
})();
</script>

<style>
/* Revisi 30 Jun 2026 — kompak untuk lock-badge di menu */
.nav-lock-badge{ font-size:.68rem; font-weight:600; padding:.18rem .45rem; vertical-align:1px; }
.nav-lock-badge .bi{ font-size:.72rem; margin-right:2px; }
</style>


<script>
/* Revisi R12 — Watchdog global: jika halaman belum selesai memuat setelah 6 detik,
   tampilkan tombol muat ulang di kanan bawah agar pengguna bisa refresh. */
(function(){
  if (window._hfInitWatch) return; window._hfInitWatch = 1;
  var shown = false;
  function ensureBtn(){
    if (shown) return; shown = true;
    var el = document.createElement('div');
    el.id = 'hfLoadReloadFab';
    el.style.cssText = 'position:fixed;right:14px;bottom:90px;z-index:10050;background:#f59e0b;color:#111;'
      + 'padding:10px 14px;border-radius:999px;box-shadow:0 8px 24px rgba(0,0,0,.25);font-size:.9rem;'
      + 'display:inline-flex;align-items:center;gap:.5rem;cursor:pointer;font-weight:600;';
    el.innerHTML = '<i class="bi bi-arrow-clockwise"></i> Muat ulang halaman';
    el.addEventListener('click', function(){ try{ location.reload(); }catch(_){ } });
    document.body.appendChild(el);
  }
  setTimeout(function(){
    if (document.readyState !== 'complete') ensureBtn();
  }, 6000);
  window.addEventListener('load', function(){
    var el = document.getElementById('hfLoadReloadFab'); if (el) el.remove();
  });
})();
</script>
