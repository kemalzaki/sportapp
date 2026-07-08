<?php
// Sticky bottom nav (gaya Gojek) — Revisi R28 (Juli 2026)
// - FAB Upload SEJAJAR dengan menu lain (tidak melayang), konsisten di semua browser
// - Semua style dipusatkan di /assets/css/gojek-nav.css (tidak ada inline <style> yg konflik)
// - Tidak ada view-transition (menyebabkan render berbeda di Chromium vs Firefox)
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
<link rel="stylesheet" href="/assets/css/gojek-nav.css?v=r28-inline-fab">
<style id="gj-nav-vt">
/* Revisi Juli 2026 — bottom nav terasa persistent antar halaman via View Transitions API */
@view-transition { navigation: auto; }
.gj-nav { view-transition-name: gj-nav; }
::view-transition-old(gj-nav), ::view-transition-new(gj-nav){
  animation: none !important; mix-blend-mode: normal;
}
::view-transition-old(root){ animation: gjFadeOut .18s ease both; }
::view-transition-new(root){ animation: gjFadeIn  .22s ease both; }
@keyframes gjFadeOut { to { opacity: 0; } }
@keyframes gjFadeIn  { from { opacity: 0; } }
</style>

<nav class="gj-nav" aria-label="Navigasi utama">
  <a href="/index.php" class="gj-item <?= _gj_active(['index.php',''], $_cur) ?>">
    <span class="gj-ico"><i class="bi bi-house-door-fill"></i></span>
    <span class="gj-label">Beranda</span>
  </a>
  <a href="/riwayat.php" class="gj-item <?= _gj_active(['riwayat.php','statistik_islami.php'], $_cur) ?>">
    <span class="gj-ico"><i class="bi bi-bar-chart-fill"></i></span>
    <span class="gj-label">Aktivitas</span>
  </a>
  <a href="/upload.php" class="gj-fab <?= _gj_active(['upload.php'], $_cur) ?>" aria-label="Upload aktivitas" title="Upload">
    <span class="gj-fab-inner" aria-hidden="true"><i class="bi bi-plus-lg"></i></span>
  </a>
  <a href="/kalori_mingguan.php" class="gj-item <?= _gj_active(['kalori_mingguan.php'], $_cur) ?>">
    <span class="gj-ico"><i class="bi bi-egg-fried"></i></span>
    <span class="gj-label">Kalori</span>
  </a>
  <a href="/profile.php" class="gj-item position-relative <?= _gj_active(['profile.php','user.php'], $_cur) ?>">
    <span class="gj-ico">
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

<?php /* Loading indicator kecil (top-bar tipis) saat pindah halaman */ ?>
<style>
.gj-topbar{
  position:fixed; top:0; left:0; height:3px; width:0;
  background:linear-gradient(90deg,#0ea5e9,#22d3ee);
  z-index:9999; transition:width .25s ease; box-shadow:0 0 8px #0ea5e9;
}
.gj-topbar.active{ width:80%; }
</style>
<div class="gj-topbar" id="gjTopBar" aria-hidden="true"></div>

<script>
(function(){
  document.querySelectorAll('.gj-nav .gj-item, .gj-nav .gj-fab').forEach(function(it){
    it.addEventListener('click', function(e){
      if (e.metaKey||e.ctrlKey||e.shiftKey||e.button) return;
      try {
        var here = (location.pathname||'').split('/').pop();
        var href = (it.getAttribute('href')||'').split('/').pop().split('?')[0];
        if (href && href === here) return;
      } catch(_) {}
      it.classList.add('is-loading');
      var tb = document.getElementById('gjTopBar');
      if (tb) tb.classList.add('active');
    });
  });
  window.addEventListener('pageshow', function(){
    document.querySelectorAll('.gj-nav .is-loading').forEach(function(it){ it.classList.remove('is-loading'); });
    var tb = document.getElementById('gjTopBar'); if (tb) tb.classList.remove('active');
  });
})();
</script>
