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
/* Revisi 28 Juni 2026 + R4 (Juli 2026) — Rapikan PWA Bottom Nav (mobile):
   - Tinggi seragam, label tidak terpotong
   - FAB Upload SELALU tampil di atas seluruh konten (z-index tinggi) & tidak terpotong
   - Body diberi padding-bottom lebih besar agar FAB yang naik ke atas tidak menutup
     konten terakhir halaman. */
.gj-nav{
  position:fixed; left:0; right:0; bottom:0; z-index:1080;
  display:flex; align-items:flex-end; justify-content:space-around;
  background:#fff; border-top:1px solid #e5e7eb;
  padding:6px 4px calc(6px + env(safe-area-inset-bottom,0px));
  box-shadow:0 -4px 16px rgba(15,23,42,.06);
  min-height:68px;
  overflow:visible;
}
.gj-nav .gj-item{
  flex:1 1 0; display:flex; flex-direction:column; align-items:center; justify-content:flex-end;
  gap:2px; padding:6px 2px; min-width:0; text-decoration:none; color:#475569; position:relative;
}
.gj-nav .gj-item .gj-ico{
  width:28px; height:28px; display:inline-flex; align-items:center; justify-content:center;
  font-size:1.25rem; color:#0f172a; background:transparent;
}
.gj-nav .gj-item .gj-ico i{ color:#0f172a; }
.gj-nav .gj-item .gj-label{
  font-size:.7rem; line-height:1.05; color:#475569; max-width:100%;
  overflow:hidden; text-overflow:ellipsis; white-space:nowrap;
}
.gj-nav .gj-item.active .gj-ico,
.gj-nav .gj-item.active .gj-ico i{ color:#0ea5e9 !important; }
.gj-nav .gj-item.active .gj-label{ color:#0ea5e9; font-weight:600; }
.gj-nav .gj-avatar{ width:24px; height:24px; border-radius:50%; object-fit:cover; }
.gj-nav .gj-avatar-fb{
  width:24px;height:24px;border-radius:50%;background:#0ea5e9;color:#fff;
  display:inline-flex;align-items:center;justify-content:center;font-size:.75rem;font-weight:700;
}
.gj-nav .gj-badge{
  position:absolute; top:2px; right:18%;
  background:#ef4444; color:#fff; font-size:.6rem; font-weight:700;
  padding:1px 5px; border-radius:9px; line-height:1;
}
/* FAB Upload — Revisi 30 Juni 2026: polish ulang agar rapi & seimbang dengan item lain.
   - Lingkaran 56px, gradient lembut + ring putih tipis, glow biru halus
   - Posisi naik -18px (cukup menonjol tanpa menutup label item sekitarnya)
   - Label "Upload" sejajar dengan label tab lain (baseline sama) */
.gj-nav .gj-fab{
  flex:1 1 0; display:flex; flex-direction:column; align-items:center; justify-content:flex-end;
  gap:6px; padding:0 2px 6px; text-decoration:none; color:#0ea5e9; position:relative;
  z-index:1090;
}
.gj-nav .gj-fab .gj-fab-inner{
  width:56px; height:56px; border-radius:50%;
  background:linear-gradient(160deg,#38bdf8 0%,#0ea5e9 60%,#0284c7 100%);
  color:#fff; display:inline-flex; align-items:center; justify-content:center;
  font-size:1.55rem; font-weight:700; line-height:1;
  box-shadow:
    0 8px 18px rgba(14,165,233,.40),
    0 2px 4px rgba(15,23,42,.12),
    inset 0 1px 0 rgba(255,255,255,.45),
    inset 0 -3px 6px rgba(2,132,199,.25);
  border:3px solid #fff;
  margin-top:-28px;
  transition: transform .15s ease, box-shadow .15s ease;
  position:relative;
}
.gj-nav .gj-fab .gj-fab-inner::after{
  content:""; position:absolute; inset:6px; border-radius:50%;
  border:1px solid rgba(255,255,255,.35); pointer-events:none;
}
.gj-nav .gj-fab .gj-fab-inner::before{
  content:""; position:absolute; inset:-8px; border-radius:50%;
  background:radial-gradient(circle, rgba(14,165,233,.30) 0%, rgba(14,165,233,0) 70%);
  z-index:-1; animation: gj-fab-pulse 2.6s ease-in-out infinite;
}
.gj-nav .gj-fab .gj-fab-inner i{ line-height:1; transform:translateY(-1px); }
.gj-nav .gj-fab:hover .gj-fab-inner{ transform: translateY(-2px); }
.gj-nav .gj-fab:active .gj-fab-inner{ transform: scale(.94); }
.gj-nav .gj-fab .gj-fab-label{
  font-size:.7rem; color:#0ea5e9; font-weight:700; line-height:1.05; letter-spacing:.02em;
}
@keyframes gj-fab-pulse {
  0%,100%{ transform: scale(.94); opacity:.5; }
  50%    { transform: scale(1.08); opacity:.9; }
}
[data-bs-theme=dark] .gj-nav .gj-fab .gj-fab-inner{ border-color:#0f172a; }
[data-bs-theme=dark] .gj-nav .gj-fab .gj-fab-label{ color:#38bdf8; }
/* Pastikan konten halaman tidak tertutup nav */
body{ padding-bottom: calc(6rem + env(safe-area-inset-bottom,0px)) !important; }

[data-bs-theme=dark] .gj-nav{ background:#0f172a; border-top-color:#1e293b; }
[data-bs-theme=dark] .gj-nav .gj-item .gj-ico,
[data-bs-theme=dark] .gj-nav .gj-item .gj-ico i{ color:#e2e8f0 !important; }
[data-bs-theme=dark] .gj-nav .gj-item .gj-label{ color:#cbd5e1; }
[data-bs-theme=dark] .gj-nav .gj-item.active .gj-ico,
[data-bs-theme=dark] .gj-nav .gj-item.active .gj-ico i,
[data-bs-theme=dark] .gj-nav .gj-item.active .gj-label{ color:#38bdf8 !important; }

/* Sembunyikan di desktop besar (≥992px) */
@media (min-width: 992px){ .gj-nav{ display:none; } body{ padding-bottom:0 !important; } }
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
  <a href="/upload.php" class="gj-fab" aria-label="Upload aktivitas">
    <span class="gj-fab-inner"><i class="bi bi-plus-lg"></i></span>
    <span class="gj-fab-label">Upload</span>
  </a>
  <!-- Revisi 13 Juni 2026: dulu Berita, sekarang Kalori Mingguan (PWA) -->
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

<?php /* Kompat lama: beberapa CSS/JS legacy masih cari .bottom-nav */ ?>
<div class="bottom-nav d-none" aria-hidden="true"></div>

<?php /* Revisi 18 Juni 2026 — Loading spinner kecil di samping teks item nav mobile saat di-klik */ ?>
<style>
.gj-nav .gj-item .gj-spin{
  display:none; width:.85rem; height:.85rem; margin-left:4px; vertical-align:-1px;
  border:2px solid currentColor; border-right-color:transparent; border-radius:50%;
  animation: gjspin .7s linear infinite;
}
.gj-nav .gj-item.is-loading .gj-spin{ display:inline-block; }
.gj-nav .gj-item.is-loading{ opacity:.85; pointer-events:none; }
.gj-nav .gj-item.is-loading .gj-label{ color:#0ea5e9; }
@keyframes gjspin { to { transform: rotate(360deg); } }
.gj-topbar{position:fixed;top:0;left:0;height:3px;width:0;background:linear-gradient(90deg,#0ea5e9,#22d3ee);
  z-index:9999;transition:width .25s ease;box-shadow:0 0 8px #0ea5e9}
.gj-topbar.active{width:80%}
</style>
<div class="gj-topbar" id="gjTopBar" aria-hidden="true"></div>
<script>
(function(){
  // Inject placeholder spinner element ke setiap label item nav (sekali).
  document.querySelectorAll('.gj-nav .gj-item').forEach(function(it){
    var lab = it.querySelector('.gj-label');
    if (lab && !lab.querySelector('.gj-spin')){
      var s = document.createElement('span');
      s.className = 'gj-spin'; s.setAttribute('aria-hidden','true');
      lab.appendChild(s);
    }
    it.addEventListener('click', function(e){
      // Abaikan kalau modifier key / target sama dengan halaman saat ini
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
  // Reset bila user kembali via back-forward cache
  window.addEventListener('pageshow', function(){
    document.querySelectorAll('.gj-nav .gj-item.is-loading').forEach(function(it){ it.classList.remove('is-loading'); });
    var tb = document.getElementById('gjTopBar'); if (tb) tb.classList.remove('active');
  });
})();
</script>

<?php /* ================================================================
   Revisi Juli 2026 — Bottom nav "tetap tampil" saat pindah halaman (mobile).
   Menggunakan MPA View Transitions API sehingga elemen dengan
   view-transition-name yang sama (bottom nav) di-morph antar halaman —
   secara visual nav tidak ikut ter-refresh / berkedip.

   Fallback: browser lama tetap berjalan normal (nav re-render seperti biasa).
   ================================================================ */ ?>
<style>
/* Revisi R2 Juli 2026: nonaktifkan MPA View Transitions agar konten tidak sempat "blank" saat pindah halaman; bottom nav tetap terlihat karena position:fixed. */
@media (max-width: 991.98px){
  .gj-nav { view-transition-name: gj-bottom-nav; }
  .gj-topbar { view-transition-name: gj-topbar; }
}
::view-transition-old(gj-bottom-nav),
::view-transition-new(gj-bottom-nav){
  animation: none !important; mix-blend-mode: normal;
}
</style>
