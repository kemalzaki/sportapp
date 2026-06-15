<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/notifications.php';
require_once __DIR__ . '/theme_user.php';
require_once __DIR__ . '/migrations_v7.php';
require_once __DIR__ . '/migrations_v8.php';
require_once __DIR__ . '/migrations_v9.php';
send_security_headers(); enforce_session_timeout();
$u = current_user();
if ($u) touch_online();
$navFoto = null; $nUnread = 0; $darkMode = 0;
if ($u) {
  $_uf = db_one("SELECT foto_url, COALESCE(dark_mode,0) AS dark_mode FROM users WHERE id=$1", [(int)$u['id']]);
  $navFoto = $_uf['foto_url'] ?? null;
  $darkMode = (int)($_uf['dark_mode'] ?? 0);
  $nUnread = unread_notif_count((int)$u['id']);
}
?>
<!doctype html>
<html lang="id" data-bs-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#0f172a">
<title><?= htmlspecialchars(($pageTitle ?? 'HapFam SportApp') . ' · HapFam SportApp') ?></title>
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
/* Revisi 6 Jun 2026 — Logo HapFam SportApp berwarna (tidak biru semua) */
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


</style>
<style id="userTheme"><?= user_theme_css() ?></style>
</head>
<body<?= !empty($pageSkeleton) ? ' data-skeleton="'.htmlspecialchars($pageSkeleton).'"' : '' ?>>
<div id="liveRefreshBadge" class="badge bg-success rounded-pill shadow"><i class="bi bi-arrow-clockwise"></i> Data diperbarui</div>

<?php /* ===== TOP header (mobile only) — biru-kehitaman ===== */ ?>
<header class="gt-top" role="banner">
  <div class="gt-row">
    <button class="gt-burger" type="button" data-bs-toggle="offcanvas" data-bs-target="#gtDrawer" aria-label="Buka menu" data-sfx="tap">
      <i class="bi bi-list"></i>
    </button>
    <form class="gt-search" role="search" action="/search.php" method="get" data-sfx-off>
      <i class="bi bi-search"></i>
      <input type="search" name="q" placeholder="<?= $u ? 'Cari aktivitas, tempat, member…' : 'Cari di HapFam SportApp…' ?>" autocomplete="off">
    </form>
    <?php if ($u): ?>
      <!-- LONCENG: klik => buka popup notifikasi (BUKAN redirect) -->
      <button type="button" class="gt-bell" id="gtBellBtn" aria-label="Notifikasi" data-sfx="tap" title="Notifikasi"
              aria-haspopup="true" aria-expanded="false">
        <i class="bi bi-bell-fill"></i>
        <?php if ($nUnread): ?><span class="gt-badge-dot" id="gtBellBadge"><?= $nUnread > 9 ? '9+' : (int)$nUnread ?></span><?php endif; ?>
      </button>
      <a href="/logout.php" class="gt-logout" aria-label="Keluar" title="Keluar" data-sfx="tap"
         onclick="return confirm('Keluar dari akun?')"><i class="bi bi-box-arrow-right"></i></a>
    <?php else: ?>
      <a href="/login.php" class="gt-bell" aria-label="Masuk" data-sfx="tap"><i class="bi bi-box-arrow-in-right"></i></a>
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
    <a class="gt-chip" href="/run.php" data-sfx="tap"><i class="bi bi-stopwatch-fill"></i>Tracking Jalur</a>
    <a class="gt-chip" href="/upload.php" data-sfx="tap"><i class="bi bi-cloud-upload-fill"></i>Upload</a>
    <?php /* Menu Jajan & Kurir dihilangkan dari navigasi pengguna sesuai revisi. */ ?>
    <a class="gt-chip" href="/tempat_list.php" data-sfx="tap"><i class="bi bi-geo-alt-fill"></i>Tempat</a>
    <a class="gt-chip" href="/event.php" data-sfx="tap"><i class="bi bi-trophy-fill"></i>Event</a>
    <?php /* Revisi 6 Juni 2026: menu Check-in via barcode dihapus. */ ?>
    <a class="gt-chip" href="/dm.php" data-sfx="tap"><i class="bi bi-chat-dots-fill"></i>Pesan</a>
    <a class="gt-chip" href="/islami.php" data-sfx="tap"><i class="bi bi-stars"></i>Islami</a>
    <a class="gt-chip" href="/kalkulator.php" data-sfx="tap"><i class="bi bi-calculator-fill"></i>Kalkulator</a>
  <?php else: ?>
    <a class="gt-chip" href="/login.php" data-sfx="tap"><i class="bi bi-box-arrow-in-right"></i>Masuk</a>
    <a class="gt-chip" href="/register.php" data-sfx="tap"><i class="bi bi-person-plus-fill"></i>Daftar</a>
  <?php endif; ?>
</nav>

<?php /* Drawer menu lengkap — "Profil Saya" duplikat DIHILANGKAN (sudah ada
        di avatar header atas dan ikon "Saya" di bottom nav). */ ?>
<div class="offcanvas offcanvas-start gt-drawer" tabindex="-1" id="gtDrawer" aria-labelledby="gtDrawerLabel">
  <div class="offcanvas-header">
    <h5 class="offcanvas-title brand-logo-colored" id="gtDrawerLabel"><i class="bi bi-lightning-charge-fill text-warning"></i> <span class="bl-1">Hap</span><span class="bl-2">Fam</span> <span class="bl-3">Sport</span><span class="bl-4">App</span></h5>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Tutup"></button>
  </div>
  <div class="offcanvas-body p-0">
    <div class="list-group list-group-flush">
      <a class="list-group-item list-group-item-action" href="/index.php"><i class="bi bi-house-door-fill"></i> Beranda</a>
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
          <a class="list-group-item list-group-item-action ps-4" href="/monitoring.php"><i class="bi bi-graph-up-arrow"></i> Monitoring</a>
          <a class="list-group-item list-group-item-action ps-4" href="/upload.php"><i class="bi bi-cloud-upload"></i> Upload</a>
          <a class="list-group-item list-group-item-action ps-4" href="/riwayat.php"><i class="bi bi-clock-history"></i> Riwayat</a>
          <a class="list-group-item list-group-item-action ps-4" href="/run.php"><i class="bi bi-stopwatch-fill"></i> Tracking Jalur</a>
          <a class="list-group-item list-group-item-action ps-4" href="/live_tracking.php"><i class="bi bi-broadcast text-danger"></i> Live Tracking / Beacon</a>
          <a class="list-group-item list-group-item-action ps-4" href="/flyover.php"><i class="bi bi-camera-reels text-info"></i> Video Flyover 3D</a>
        </div>

        <?php /* Grup: Perhitungan Kalori Olahraga */ ?>
        <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" data-bs-toggle="collapse" href="#grpKalori" role="button" aria-expanded="false">
          <span><i class="bi bi-fire text-danger"></i> Perhitungan Kalori Olahraga</span><i class="bi bi-chevron-down small"></i>
        </a>
        <div class="collapse" id="grpKalori">
          <a class="list-group-item list-group-item-action ps-4" href="/kalori_badminton.php"><i class="bi bi-stopwatch text-success"></i> Kalori Badminton</a>
          <a class="list-group-item list-group-item-action ps-4" href="/kalori_renang.php"><i class="bi bi-water text-info"></i> Kalori Renang</a>
          <a class="list-group-item list-group-item-action ps-4" href="/kalori_pingpong.php"><i class="bi bi-circle-fill text-warning"></i> Kalori Ping Pong</a>
          <a class="list-group-item list-group-item-action ps-4" href="/kalori_futsal.php"><i class="bi bi-dribbble text-success"></i> Kalori Futsal</a>
          <a class="list-group-item list-group-item-action ps-4" href="/kalori_mingguan.php"><i class="bi bi-egg-fried text-warning"></i> Kalori Mingguan (Makanan)</a>
        </div>

        <?php /* Grup: Agenda Kita */ ?>
        <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" data-bs-toggle="collapse" href="#grpAgenda" role="button" aria-expanded="false">
          <span><i class="bi bi-calendar-week-fill text-primary"></i> Agenda Kita</span><i class="bi bi-chevron-down small"></i>
        </a>
        <div class="collapse" id="grpAgenda">
          <a class="list-group-item list-group-item-action ps-4" href="/calendar.php"><i class="bi bi-calendar3"></i> Kalender</a>
          <a class="list-group-item list-group-item-action ps-4" href="/event.php"><i class="bi bi-trophy-fill"></i> Event</a>
          <a class="list-group-item list-group-item-action ps-4" href="/tempat.php"><i class="bi bi-calendar2-week"></i> Booking</a>
        </div>

        <?php /* Grup: Kalkulator (Revisi 13 Juni 2026 — sekarang dropdown seperti grup Kalori) */ ?>
        <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" data-bs-toggle="collapse" href="#grpKalkulator" role="button" aria-expanded="false">
          <span><i class="bi bi-calculator-fill text-primary"></i> Kalkulator</span><i class="bi bi-chevron-down small"></i>
        </a>
        <div class="collapse" id="grpKalkulator">
          <a class="list-group-item list-group-item-action ps-4" href="/kalkulator.php"><i class="bi bi-heart-pulse-fill"></i> Kalkulator Sehat</a>
          <a class="list-group-item list-group-item-action ps-4" href="/kalkulator_jantung.php"><i class="bi bi-heart-pulse text-danger"></i> Kalkulator Detak Jantung</a>
          <a class="list-group-item list-group-item-action ps-4" href="/kalkulator_kesehatan.php"><i class="bi bi-clipboard2-pulse text-primary"></i> Kalkulator Kesehatan</a>
          <a class="list-group-item list-group-item-action ps-4" href="/gaya_hidup.php"><i class="bi bi-heart-pulse-fill text-danger"></i> Kalkulator Gaya Hidup</a>
        </div>

        <?php /* Grup: Info dan Wawasan (revisi 12 Juni 2026) — dipindah dari index.php */ ?>
        <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" data-bs-toggle="collapse" href="#grpInfoWawasan" role="button" aria-expanded="false">
          <span><i class="bi bi-compass text-primary"></i> Info dan Wawasan</span><i class="bi bi-chevron-down small"></i>
        </a>
        <div class="collapse" id="grpInfoWawasan">
          <a class="list-group-item list-group-item-action ps-4" href="/berita.php"><i class="bi bi-newspaper text-primary"></i> Berita Terkini</a>
          <a class="list-group-item list-group-item-action ps-4" href="/iptv.php"><i class="bi bi-tv text-info"></i> IPTV</a>
          <a class="list-group-item list-group-item-action ps-4" href="/hidup_sehat.php"><i class="bi bi-heart-fill text-success"></i> Hidup Sehat</a>
          <a class="list-group-item list-group-item-action ps-4" href="/kesehatan.php"><i class="bi bi-capsule text-danger"></i> Penyakit Umum dan Obat Herbal</a>
          <a class="list-group-item list-group-item-action ps-4" href="/kalistenik.php"><i class="bi bi-person-arms-up text-success"></i> Paket Bugar Kalistenik</a>
          <a class="list-group-item list-group-item-action ps-4" href="/artikel_olahraga.php"><i class="bi bi-journal-richtext text-info"></i> Artikel Olahraga &amp; Teknik</a>
          <a class="list-group-item list-group-item-action ps-4" href="https://www.youtube.com/results?search_query=panduan+olahraga+teknik" target="_blank" rel="noopener"><i class="bi bi-youtube text-danger"></i> Panduan Olahraga</a>
          <a class="list-group-item list-group-item-action ps-4" href="https://www.youtube.com/watch?v=Ks5dz69gsDk" target="_blank" rel="noopener"><i class="bi bi-fire text-warning"></i> Paket Pemanasan Olahraga</a>
          <a class="list-group-item list-group-item-action ps-4" href="https://www.youtube.com/watch?v=uXznjq2BLMI" target="_blank" rel="noopener"><i class="bi bi-snow text-info"></i> Paket Pendinginan Olahraga</a>
          <a class="list-group-item list-group-item-action ps-4" href="/cedera_olahraga.php"><i class="bi bi-bandaid text-danger"></i> Cedera Olahraga &amp; Penanganan</a>
        </div>

        <?php /* Revisi 14 Juni 2026: shortcut Tempat/Pesan/Bookmark/Islami pindah ke bawah Info dan Wawasan */ ?>
        <a class="list-group-item list-group-item-action" href="/tempat_list.php"><i class="bi bi-geo-alt-fill"></i> Tempat</a>
        <a class="list-group-item list-group-item-action" href="/dm.php"><i class="bi bi-chat-dots-fill"></i> Pesan</a>
        <a class="list-group-item list-group-item-action" href="/bookmark.php"><i class="bi bi-bookmark-star-fill"></i> Bookmark</a>
        <a class="list-group-item list-group-item-action" href="/islami.php"><i class="bi bi-stars"></i> Islami</a>


        <?php if ($u['role']==='admin'): ?>
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
            <a class="list-group-item list-group-item-action ps-4" href="/admin/jenis.php"><i class="bi bi-tags"></i> Jenis Olahraga</a>
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
            <a class="list-group-item list-group-item-action ps-4" href="/admin/referal.php"><i class="bi bi-ticket-perforated"></i> Kode Referal</a>
            <a class="list-group-item list-group-item-action ps-4" href="/admin/stats.php"><i class="bi bi-bar-chart"></i> Statistik</a>
            <a class="list-group-item list-group-item-action ps-4" href="/admin/lacak.php"><i class="bi bi-broadcast-pin"></i> Lacak HP Member</a>
            <?php /* Revisi 15 Juni 2026: menu "Riwayat Login Member" dihapus sesuai permintaan. */ ?>
          </div>

          <?php /* Admin > Pengaturan Lainnya */ ?>
          <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" data-bs-toggle="collapse" href="#grpLainnya" role="button" aria-expanded="false">
            <span><i class="bi bi-gear-fill text-secondary"></i> Pengaturan Lainnya</span><i class="bi bi-chevron-down small"></i>
          </a>
          <div class="collapse" id="grpLainnya">
            <a class="list-group-item list-group-item-action ps-4" href="/admin/reports.php"><i class="bi bi-flag text-danger"></i> Laporan Postingan</a>
            <a class="list-group-item list-group-item-action ps-4" href="/admin/privasi.php"><i class="bi bi-shield-check text-success"></i> Kebijakan Privasi (UU PDP)</a>
            <a class="list-group-item list-group-item-action ps-4" href="/admin/iptv.php"><i class="bi bi-tv text-info"></i> IPTV</a>
            <a class="list-group-item list-group-item-action ps-4" href="/admin/sistem.php"><i class="bi bi-cpu text-info"></i> Cek Sistem</a>
          </div>

        <?php endif; ?>

        <a class="list-group-item list-group-item-action text-danger" href="/logout.php"><i class="bi bi-box-arrow-right"></i> Keluar</a>
        <?php /* Revisi 13 Juni 2026: menu non-dropdown disimpan PALING BAWAH agar
               grup ber-dropdown selalu berada di paling atas drawer. */ ?>
      <?php else: ?>
        <a class="list-group-item list-group-item-action" href="/login.php"><i class="bi bi-box-arrow-in-right"></i> Masuk</a>
        <a class="list-group-item list-group-item-action" href="/register.php"><i class="bi bi-person-plus-fill"></i> Daftar</a>
      <?php endif; ?>
    </div>
  </div>
</div>
<!-- /Mobile top header -->

<nav class="navbar navbar-expand-lg sticky-top" data-bs-theme="dark" style="background:linear-gradient(135deg,#0f172a,#1e293b);">
  <div class="container">
    <a class="navbar-brand fw-bold brand-logo-colored" href="/index.php"><i class="bi bi-lightning-charge-fill text-warning"></i> <span class="bl-1">Hap</span><span class="bl-2">Fam</span> <span class="bl-3">Sport</span><span class="bl-4">App</span></a>
    <button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#nav"><span class="navbar-toggler-icon"></span></button>
    <div class="collapse navbar-collapse" id="nav">
      <ul class="navbar-nav me-auto">
        <li class="nav-item"><a class="nav-link" href="/index.php"><i class="bi bi-house-door"></i> Beranda</a></li>
        <?php if ($u): ?>
          <li class="nav-item"><a class="nav-link" href="/calendar.php"><i class="bi bi-calendar3"></i> Kalender</a></li>
          <li class="nav-item"><a class="nav-link" href="/riwayat.php"><i class="bi bi-clock-history"></i> Riwayat</a></li>
          <li class="nav-item"><a class="nav-link" href="/tempat_list.php"><i class="bi bi-geo-alt"></i> Tempat</a></li>
          <?php /* Revisi 6 Juni 2026: Check-in via barcode dihapus dari navbar desktop. */ ?>
          <li class="nav-item"><a class="nav-link" href="/upload.php"><i class="bi bi-cloud-upload"></i> Upload</a></li>
          <li class="nav-item"><a class="nav-link" href="/monitoring.php"><i class="bi bi-graph-up-arrow"></i> Monitoring</a></li>
          <li class="nav-item"><a class="nav-link" href="/event.php"><i class="bi bi-trophy"></i> Event</a></li>
          <li class="nav-item"><a class="nav-link" href="/tempat.php"><i class="bi bi-calendar2-week"></i> Booking</a></li>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown" role="button" aria-expanded="false"><i class="bi bi-calculator-fill"></i> Kalkulator</a>
            <ul class="dropdown-menu shadow">
              <li><h6 class="dropdown-header">Kalkulator</h6></li>
              <li><a class="dropdown-item" href="/kalkulator.php"><i class="bi bi-heart-pulse-fill"></i> Kalkulator Sehat</a></li>
              <li><a class="dropdown-item" href="/kalkulator_jantung.php"><i class="bi bi-heart-pulse text-danger"></i> Kalkulator Detak Jantung</a></li>
              <li><a class="dropdown-item" href="/kalkulator_kesehatan.php"><i class="bi bi-clipboard2-pulse text-primary"></i> Kalkulator Kesehatan</a></li>
              <li><hr class="dropdown-divider"></li>
              <li><h6 class="dropdown-header">Kalori</h6></li>
              <li><a class="dropdown-item" href="/kalori_badminton.php"><i class="bi bi-stopwatch text-success"></i> Kalori Badminton</a></li>
              <li><a class="dropdown-item" href="/kalori_renang.php"><i class="bi bi-water text-info"></i> Kalori Renang</a></li>
              <li><a class="dropdown-item" href="/kalori_pingpong.php"><i class="bi bi-circle-fill text-warning"></i> Kalori Ping Pong</a></li>
              <li><a class="dropdown-item" href="/kalori_futsal.php"><i class="bi bi-dribbble text-success"></i> Kalori Futsal</a></li>
              <li><a class="dropdown-item" href="/kalori_mingguan.php"><i class="bi bi-egg-fried text-warning"></i> Kalori Mingguan (Makanan)</a></li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item" href="/gaya_hidup.php"><i class="bi bi-heart-pulse-fill text-danger"></i> Kalkulator Gaya Hidup</a></li>
            </ul>
          </li>
          <li class="nav-item"><a class="nav-link" href="/run.php"><i class="bi bi-stopwatch text-danger"></i> Tracking Jalur</a></li>
          <li class="nav-item"><a class="nav-link" href="/dm.php"><i class="bi bi-chat-dots text-info"></i> Pesan</a></li>
          <li class="nav-item"><a class="nav-link" href="/bookmark.php"><i class="bi bi-bookmark-star text-warning"></i> Bookmark</a></li>
          <li class="nav-item"><a class="nav-link" href="/islami.php"><i class="bi bi-stars text-warning"></i> Islami</a></li>
        <?php endif; ?>
        <?php if ($u && $u['role']==='admin'): ?>
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
              <li><a class="dropdown-item" href="/admin/event.php">Event / Tournament</a></li>
              <li><a class="dropdown-item" href="/admin/stats.php">📊 Statistik Pintar</a></li>
              <li><a class="dropdown-item" href="/admin/jenis.php">Jenis Olahraga</a></li>
              <li><a class="dropdown-item" href="/admin/referal.php"><i class="bi bi-ticket-perforated"></i> Kode Referal</a></li>
              <li><a class="dropdown-item" href="/admin/reports.php"><i class="bi bi-flag text-danger"></i> Laporan Postingan</a></li>
              <li><hr class="dropdown-divider"></li>
              <li><hr class="dropdown-divider"></li>
              <li><h6 class="dropdown-header">CMS &amp; Pengaturan</h6></li>
              <li><a class="dropdown-item" href="/admin/privasi.php"><i class="bi bi-shield-check text-success"></i> Kebijakan Privasi (UU PDP)</a></li>
              <li><hr class="dropdown-divider"></li>
                                                                                    <li><a class="dropdown-item" href="/admin/lacak.php"><i class="bi bi-broadcast-pin text-danger"></i> Lacak HP Member</a></li>
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
          </a></li>
          <li class="nav-item"><a class="nav-link" href="/logout.php" title="Keluar"><i class="bi bi-box-arrow-right"></i></a></li>
        <?php else: ?>
          <li class="nav-item"><a class="nav-link" href="/login.php">Login</a></li>
          <li class="nav-item"><a class="nav-link" href="/register.php">Daftar</a></li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>
<?php require_once __DIR__.'/skeleton.php'; ?>

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
