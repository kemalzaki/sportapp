<?php
/**
 * includes/bottom_nav.php — Revisi Design 2026
 * Bottom Navigation premium (Strava/NRC-style) dengan Floating Upload
 * yang MENYATU dengan bar (notched circle). Semua warna mengikuti tema
 * aktif via CSS Variable (--primary, --primary-light, --primary-dark,
 * --surface, --text-primary, --text-secondary) yang di-emit oleh
 * includes/theme_user.php.
 *
 * TIDAK mengubah logika PHP/session/DB/API. Hanya UI/UX.
 */
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
<style>
/* ============================================================
   Bottom Navigation Premium — Strava-style w/ integrated FAB
   ============================================================ */
.gj-nav{
  position:fixed;left:0;right:0;bottom:0;z-index:1080;
  display:flex;align-items:flex-end;justify-content:space-around;
  background:var(--surface,#fff);
  border-top:1px solid var(--border,#e5e7eb);
  border-top-left-radius:22px;border-top-right-radius:22px;
  padding:8px 6px calc(10px + env(safe-area-inset-bottom,0px));
  box-shadow:0 -6px 24px rgba(15,23,42,.08);
  min-height:76px;overflow:visible;
}
.gj-nav .gj-item{
  flex:1 1 0;display:flex;flex-direction:column;align-items:center;justify-content:flex-end;
  gap:3px;padding:6px 2px;min-width:0;text-decoration:none;
  color:var(--text-secondary,#64748b);position:relative;
  transition:transform .18s ease, color .2s ease;
}
.gj-nav .gj-item:active{transform:scale(.94);}
.gj-nav .gj-item .gj-ico{
  width:28px;height:28px;display:inline-flex;align-items:center;justify-content:center;
  font-size:1.35rem;color:var(--text-secondary,#94a3b8);transition:color .2s ease;
}
.gj-nav .gj-item .gj-label{
  font-size:.7rem;line-height:1.05;color:var(--text-secondary,#64748b);max-width:100%;
  overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-weight:500;
}
.gj-nav .gj-item.active .gj-ico,
.gj-nav .gj-item.active .gj-ico i{color:var(--primary,#0ea5e9)!important;}
.gj-nav .gj-item.active .gj-label{color:var(--primary,#0ea5e9);font-weight:700;}
.gj-nav .gj-item.active::before{
  content:"";position:absolute;top:0;left:35%;right:35%;height:3px;
  background:var(--primary,#0ea5e9);border-radius:0 0 3px 3px;
}
.gj-nav .gj-avatar{width:26px;height:26px;border-radius:50%;object-fit:cover;
  border:2px solid transparent;}
.gj-nav .gj-item.active .gj-avatar{border-color:var(--primary,#0ea5e9);}
.gj-nav .gj-avatar-fb{
  width:26px;height:26px;border-radius:50%;
  background:var(--primary,#0ea5e9);color:#fff;
  display:inline-flex;align-items:center;justify-content:center;font-size:.75rem;font-weight:700;
}
.gj-nav .gj-badge{
  position:absolute;top:2px;right:18%;
  background:#ef4444;color:#fff;font-size:.6rem;font-weight:700;
  padding:1px 5px;border-radius:9px;line-height:1;
}

/* Floating Upload — Strava-style, naik ~24px, menyatu dengan bar */
.gj-nav .gj-fab{
  flex:1 1 0;display:flex;flex-direction:column;align-items:center;justify-content:flex-end;
  text-decoration:none;color:var(--primary,#0ea5e9);position:relative;
}
.gj-nav .gj-fab .gj-fab-inner{
  width:64px;height:64px;border-radius:50%;
  background:var(--gradient-primary, linear-gradient(135deg,var(--primary-dark,#0369a1),var(--primary,#0ea5e9)));
  color:#fff;display:inline-flex;align-items:center;justify-content:center;
  font-size:1.55rem;font-weight:700;line-height:1;
  box-shadow:
    0 8px 22px -4px color-mix(in oklab, var(--primary,#0ea5e9) 60%, transparent),
    0 0 0 5px var(--surface,#fff),
    inset 0 1px 0 rgba(255,255,255,.35);
  border:none;position:relative;top:-22px;margin-bottom:-14px;
  transition:transform .18s ease, box-shadow .2s ease;
}
.gj-nav .gj-fab:hover .gj-fab-inner{transform:translateY(-2px) scale(1.04);}
.gj-nav .gj-fab:active .gj-fab-inner{transform:scale(.9);}
.gj-nav .gj-fab .gj-fab-label{
  font-size:.68rem;color:var(--primary,#0ea5e9);font-weight:700;line-height:1.05;margin-top:-8px;
}

/* Drawer ikon ikuti tema */
.gt-drawer .list-group-item i.bi,
.gt-drawer .list-group-item .bi{color:var(--primary,#0ea5e9)!important;}
.gt-drawer .list-group-item i.bi-chevron-down,
.gt-drawer .list-group-item i.bi-chevron-up{color:var(--text-secondary,#64748b)!important;}
.gt-drawer .list-group-item.active,
.gt-drawer .list-group-item[aria-current='true']{
  background:var(--primary-soft, rgba(14,165,233,.12))!important;
  color:var(--primary-dark,#0369a1)!important;
  border-left:3px solid var(--primary,#0ea5e9)!important;
}

/* Padding bawah body agar tidak tertutup nav */
body{padding-bottom:calc(6rem + env(safe-area-inset-bottom,0px))!important;}

[data-bs-theme=dark] .gj-nav{background:var(--surface,#0f172a);border-top-color:var(--border,#1e293b);}
[data-bs-theme=dark] .gj-nav .gj-fab .gj-fab-inner{box-shadow:0 8px 22px -4px rgba(0,0,0,.6),0 0 0 5px var(--surface,#0f172a);}

@media (min-width: 992px){.gj-nav{display:none;} body{padding-bottom:0!important;}}
</style>
<nav class="gj-nav" aria-label="Navigasi utama">
  <a href="/index.php" class="gj-item <?= _gj_active(['index.php',''], $_cur) ?>">
    <span class="gj-ico"><i class="bi bi-house-door-fill"></i></span>
    <span class="gj-label">Beranda</span>
  </a>
  <a href="/riwayat.php" class="gj-item <?= _gj_active(['riwayat.php','statistik_islami.php'], $_cur) ?>">
    <span class="gj-ico"><i class="bi bi-activity"></i></span>
    <span class="gj-label">Aktivitas</span>
  </a>
  <a href="/upload.php" class="gj-fab" aria-label="Upload aktivitas">
    <span class="gj-fab-inner"><i class="bi bi-plus-lg"></i></span>
    <span class="gj-fab-label">Upload</span>
  </a>
  <a href="/kalori_mingguan.php" class="gj-item <?= _gj_active(['kalori_mingguan.php'], $_cur) ?>">
    <span class="gj-ico"><i class="bi bi-fire"></i></span>
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

<?php /* Kompat lama */ ?>
<div class="bottom-nav d-none" aria-hidden="true"></div>

<style>
.gj-nav .gj-item .gj-spin{
  display:none;width:.85rem;height:.85rem;margin-left:4px;vertical-align:-1px;
  border:2px solid currentColor;border-right-color:transparent;border-radius:50%;
  animation:gjspin .7s linear infinite;
}
.gj-nav .gj-item.is-loading .gj-spin{display:inline-block;}
.gj-nav .gj-item.is-loading{opacity:.85;pointer-events:none;}
@keyframes gjspin{to{transform:rotate(360deg);}}
.gj-topbar{position:fixed;top:0;left:0;height:3px;width:0;
  background:var(--gradient-primary,linear-gradient(90deg,#0ea5e9,#22d3ee));
  z-index:9999;transition:width .25s ease;box-shadow:0 0 8px var(--primary,#0ea5e9);}
.gj-topbar.active{width:80%;}
</style>
<div class="gj-topbar" id="gjTopBar" aria-hidden="true"></div>
<script>
(function(){
  document.querySelectorAll('.gj-nav .gj-item').forEach(function(it){
    var lab = it.querySelector('.gj-label');
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
    document.querySelectorAll('.gj-nav .gj-item.is-loading').forEach(function(it){ it.classList.remove('is-loading'); });
    var tb = document.getElementById('gjTopBar'); if (tb) tb.classList.remove('active');
  });
})();
</script>
<style>
@media (max-width: 991.98px){
  .gj-nav { view-transition-name: gj-bottom-nav; }
  .gj-topbar { view-transition-name: gj-topbar; }
}
::view-transition-old(gj-bottom-nav),
::view-transition-new(gj-bottom-nav){animation:none!important;mix-blend-mode:normal;}
</style>
