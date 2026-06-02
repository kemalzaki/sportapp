<?php
// Sticky bottom nav (gaya Gojek) + floating upload button. Hanya tampil saat login.
// Revisi 2 Jun 2026:
//   - Tab "Event" diganti jadi "Berita" (berita.php). Halaman aktif memperhitungkan
//     berita.php sebagai item aktif.
// Revisi 1 Jun 2026: redesign menu navigasi seperti aplikasi Gojek di mobile.
if (defined('GJ_BOTTOM_NAV_RENDERED')) return;
define('GJ_BOTTOM_NAV_RENDERED', true);
$u = current_user();
if (!$u) return;
require_once __DIR__ . '/notifications.php';
require_once __DIR__ . '/helpers.php';
$nUnread  = unread_notif_count((int)$u['id']);
$_navFoto = db_one("SELECT foto_url FROM users WHERE id=$1", [(int)$u['id']]);
$navFoto  = $_navFoto['foto_url'] ?? null;

$_cur = basename($_SERVER['SCRIPT_NAME'] ?? '');
if (!function_exists('_gj_active')) {
  function _gj_active($file, $cur){
    return in_array($cur, (array)$file, true) ? 'active' : '';
  }
}
?>
<link rel="stylesheet" href="/assets/css/gojek-nav.css?v=2jun2026">
<style>
/* Revisi 2 Jun 2026: samakan warna ikon navigasi bawah mobile (tidak warna-warni) */
.gj-nav .gj-item .gj-ico{ color:#0f172a !important; background:transparent !important; }
.gj-nav .gj-item .gj-ico i{ color:#0f172a !important; }
.gj-nav .gj-item.active .gj-ico,
.gj-nav .gj-item.active .gj-ico i{ color:#0ea5e9 !important; }
.gj-nav .gj-item .gj-label{ color:#475569; }
.gj-nav .gj-item.active .gj-label{ color:#0ea5e9; font-weight:600; }
[data-bs-theme=dark] .gj-nav .gj-item .gj-ico,
[data-bs-theme=dark] .gj-nav .gj-item .gj-ico i{ color:#e2e8f0 !important; }
[data-bs-theme=dark] .gj-nav .gj-item.active .gj-ico,
[data-bs-theme=dark] .gj-nav .gj-item.active .gj-ico i{ color:#38bdf8 !important; }
</style>
<nav class="gj-nav d-lg-none" aria-label="Navigasi utama">
  <a href="/index.php" class="gj-item <?= _gj_active(['index.php',''], $_cur) ?>">
    <span class="gj-ico gj-c-home"><i class="bi bi-house-door-fill"></i></span>
    <span class="gj-label">Beranda</span>
  </a>
  <a href="/riwayat.php" class="gj-item <?= _gj_active(['riwayat.php','statistik_islami.php'], $_cur) ?>">
    <span class="gj-ico gj-c-stat"><i class="bi bi-bar-chart-fill"></i></span>
    <span class="gj-label">Aktivitas</span>
  </a>
  <a href="/upload.php" class="gj-fab" aria-label="Upload aktivitas">
    <span class="gj-fab-inner"><i class="bi bi-plus-lg"></i></span>
    <span class="gj-fab-label">Upload</span>
  </a>
  <!-- Revisi 2 Jun 2026: dulu Event, sekarang Berita -->
  <a href="/berita.php" class="gj-item <?= _gj_active(['berita.php'], $_cur) ?>">
    <span class="gj-ico gj-c-event"><i class="bi bi-newspaper"></i></span>
    <span class="gj-label">Berita</span>
  </a>
  <a href="/profile.php" class="gj-item position-relative <?= _gj_active(['profile.php','user.php'], $_cur) ?>">
    <span class="gj-ico gj-c-me">
      <?php if ($navFoto): ?>
        <img src="<?= htmlspecialchars($navFoto) ?>" alt="" class="gj-avatar">
      <?php else: ?>
        <span class="gj-avatar-fb"><?= htmlspecialchars(mb_strtoupper(mb_substr($u['nama'] ?? '?',0,1))) ?></span>
      <?php endif; ?>
    </span>
    <span class="gj-label">Saya</span>
    <?php if ($nUnread): ?><span class="gj-badge"><?= $nUnread > 99 ? '99+' : $nUnread ?></span><?php endif; ?>
  </a>
</nav>

<?php /* Kompat lama: beberapa CSS/JS legacy masih cari .bottom-nav */ ?>
<div class="bottom-nav d-none" aria-hidden="true"></div>
