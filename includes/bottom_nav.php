<?php
// Sticky bottom nav (gaya Gojek) + upload button di tengah sejajar dengan menu lain.
// Revisi 7 Jul 2026:
//   - Tombol Upload ditengah, pas, dan sejajar dengan menu lain.
//   - Hapus style melayang (FAB besar) agar tidak menutupi konten.
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
<link rel="stylesheet" href="/assets/css/gojek-nav.css?v=audit-nov2026">
<?php /* Semua styling .gj-nav / .gj-fab / .gj-fab-inner / .gj-label ada di
        gojek-nav.css (SOURCE OF TRUTH). Jangan tambahkan <style> lokal
        yang menimpa selector di atas. */ ?>
<style>
/* Drawer menu — netralkan ikon ke warna tema (bukan bagian bottom nav) */
.gt-drawer .list-group-item i.bi,
.gt-drawer .list-group-item .bi{ color: var(--bs-primary,#0ea5e9); }
.gt-drawer .list-group-item i.bi-chevron-down,
.gt-drawer .list-group-item i.bi-chevron-up{ color: var(--bs-secondary-color,#64748b); }
</style>
<nav class="gj-nav" aria-label="Navigasi utama">
  <a href="/index.php" class="gj-item <?= _gj_active(['index.php',''], $_cur) ?>">
    <span class="gj-ico gj-c-home"><i class="bi bi-house-door-fill"></i></span>
    <span class="gj-label">Beranda</span>
  </a>
  <a href="/riwayat.php" class="gj-item <?= _gj_active(['riwayat.php','statistik_islami.php'], $_cur) ?>">
    <span class="gj-ico gj-c-stat"><i class="bi bi-bar-chart-fill"></i></span>
    <span class="gj-label">Aktivitas</span>
  </a>
  <a href="/upload.php" class="gj-fab" id="gjFabUpload" aria-label="Upload" aria-haspopup="dialog" aria-expanded="false">
    <span class="gj-fab-inner" aria-hidden="true"><i class="bi bi-plus-lg"></i></span>
    <span class="gj-label">Upload</span>
  </a>
  <a href="/kalori_mingguan.php" class="gj-item <?= _gj_active(['kalori_mingguan.php'], $_cur) ?>">
    <span class="gj-ico gj-c-event"><i class="bi bi-egg-fried"></i></span>
    <span class="gj-label">Kalori</span>
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

<?php /* Legacy .bottom-nav placeholder dihapus: semua CSS legacy sudah dibersihkan
        (app-v3.css, mobile-shell.css, desktop-fix.css). */ ?>

<?php /* Loading spinner kecil di samping teks item nav saat di-klik */ ?>
<style>
.gj-nav .gj-item .gj-spin,
.gj-nav .gj-fab .gj-spin{
  display:none; width:.85rem; height:.85rem; margin-left:4px; vertical-align:-1px;
  border:2px solid currentColor; border-right-color:transparent; border-radius:50%;
  animation: gjspin .7s linear infinite;
}
.gj-nav .gj-item.is-loading .gj-spin,
.gj-nav .gj-fab.is-loading .gj-spin{ display:inline-block; }
.gj-nav .gj-item.is-loading,
.gj-nav .gj-fab.is-loading{ opacity:.85; pointer-events:none; }
.gj-nav .gj-item.is-loading .gj-label,
.gj-nav .gj-fab.is-loading .gj-label{ color:#0ea5e9; }
@keyframes gjspin { to { transform: rotate(360deg); } }
.gj-topbar{position:fixed;top:0;left:0;height:3px;width:0;background:linear-gradient(90deg,#0ea5e9,#22d3ee);
  z-index:9999;transition:width .25s ease;box-shadow:0 0 8px #0ea5e9}
.gj-topbar.active{width:80%}
</style>
<div class="gj-topbar" id="gjTopBar" aria-hidden="true"></div>
<script>
(function(){
  document.querySelectorAll('.gj-nav .gj-item, .gj-nav .gj-fab').forEach(function(it){
    var lab = it.querySelector('.gj-label, .gj-label');
    if (lab && !lab.querySelector('.gj-spin')){
      var s = document.createElement('span');
      s.className = 'gj-spin'; s.setAttribute('aria-hidden','true');
      lab.appendChild(s);
    }
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
    document.querySelectorAll('.gj-nav .gj-item.is-loading, .gj-nav .gj-fab.is-loading').forEach(function(it){ it.classList.remove('is-loading'); });
    var tb = document.getElementById('gjTopBar'); if (tb) tb.classList.remove('active');
  });
})();
</script>

<?php /* Bottom nav tetap terlihat saat pindah halaman (visual morph). */ ?>
<style>
@media (max-width: 991.98px){
  .gj-nav { view-transition-name: gj-bottom-nav; }
  .gj-topbar { view-transition-name: gj-topbar; }
}
::view-transition-old(gj-bottom-nav),
::view-transition-new(gj-bottom-nav){
  animation: none !important; mix-blend-mode: normal;
}
</style>

<?php /* ===== Bottom Sheet Upload (Strava/Google Fit style) ===== */ ?>
<div class="gj-sheet-backdrop" id="gjSheetBackdrop" hidden></div>
<div class="gj-sheet" id="gjSheetUpload" role="dialog" aria-modal="true" aria-labelledby="gjSheetTitle" hidden>
  <div class="gj-sheet-handle" aria-hidden="true"></div>
  <div class="gj-sheet-title" id="gjSheetTitle">Apa yang ingin kamu unggah?</div>
  <div class="gj-sheet-grid">
    <a href="/upload.php" class="gj-sheet-item">
      <span class="gj-sheet-ic b1"><i class="bi bi-activity"></i></span>
      <span class="gj-sheet-txt"><b>Upload Aktivitas</b><span>Lari, sepeda, latihan</span></span>
    </a>
    <a href="/upload.php?type=foto" class="gj-sheet-item">
      <span class="gj-sheet-ic b2"><i class="bi bi-image"></i></span>
      <span class="gj-sheet-txt"><b>Upload Foto</b><span>Bagikan momenmu</span></span>
    </a>
    <a href="/upload.php?type=story" class="gj-sheet-item">
      <span class="gj-sheet-ic b3"><i class="bi bi-camera-reels"></i></span>
      <span class="gj-sheet-txt"><b>Story</b><span>Kilas 24 jam</span></span>
    </a>
    <a href="/checkin.php" class="gj-sheet-item">
      <span class="gj-sheet-ic b4"><i class="bi bi-geo-alt-fill"></i></span>
      <span class="gj-sheet-txt"><b>Check-in</b><span>Tandai lokasi</span></span>
    </a>
  </div>
</div>
<script>
(function(){
  var fab = document.getElementById('gjFabUpload');
  var sheet = document.getElementById('gjSheetUpload');
  var backdrop = document.getElementById('gjSheetBackdrop');
  if (!fab || !sheet || !backdrop) return;

  function open(){
    sheet.hidden = false; backdrop.hidden = false;
    // force reflow so transition triggers
    void sheet.offsetWidth;
    sheet.classList.add('open'); backdrop.classList.add('open');
    fab.setAttribute('aria-expanded','true');
    document.body.style.overflow = 'hidden';
  }
  function close(){
    sheet.classList.remove('open'); backdrop.classList.remove('open');
    fab.setAttribute('aria-expanded','false');
    document.body.style.overflow = '';
    setTimeout(function(){
      if (!sheet.classList.contains('open')){ sheet.hidden = true; backdrop.hidden = true; }
    }, 260);
  }

  fab.addEventListener('click', function(e){
    if (e.metaKey||e.ctrlKey||e.shiftKey||e.button) return;
    e.preventDefault();
    // Batalkan efek loading/topbar dari handler navigasi umum
    setTimeout(function(){
      fab.classList.remove('is-loading');
      var tb = document.getElementById('gjTopBar'); if (tb) tb.classList.remove('active');
    }, 0);
    fab.classList.add('is-pressed');
    setTimeout(function(){ fab.classList.remove('is-pressed'); }, 180);
    open();
  });
  backdrop.addEventListener('click', close);
  document.addEventListener('keydown', function(e){ if (e.key === 'Escape') close(); });

  // Swipe-down to dismiss
  var startY = null;
  sheet.addEventListener('touchstart', function(e){ startY = e.touches[0].clientY; }, {passive:true});
  sheet.addEventListener('touchmove', function(e){
    if (startY == null) return;
    var dy = e.touches[0].clientY - startY;
    if (dy > 60) { close(); startY = null; }
  }, {passive:true});
})();
</script>
