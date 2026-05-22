<?php
// Sticky bottom nav + floating upload button. Hanya tampil saat login.
$u = current_user();
if (!$u) return;
require_once __DIR__ . '/notifications.php';
require_once __DIR__ . '/helpers.php';
$nUnread = unread_notif_count((int)$u['id']);
$_navFoto = db_one("SELECT foto_url FROM users WHERE id=$1", [(int)$u['id']]);
$navFoto = $_navFoto['foto_url'] ?? null;
?>
<style>
.bottom-nav .bn-item .nav-avatar{width:24px;height:24px;border-radius:50%;object-fit:cover;display:block;margin:0 auto;}
.bottom-nav .bn-item .nav-avatar-fallback{width:24px;height:24px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#0ea5e9,#6366f1);color:#fff;font-weight:700;font-size:.75rem;}
</style>
<nav class="bottom-nav d-lg-none">
  <a href="/index.php" class="bn-item"><i class="bi bi-house-door"></i><span>Beranda</span></a>
  <a href="/riwayat.php" class="bn-item"><i class="bi bi-bar-chart"></i><span>Riwayat</span></a>
  <a href="/upload.php" class="bn-fab" aria-label="Upload"><i class="bi bi-plus-lg"></i></a>
  <a href="/event.php" class="bn-item"><i class="bi bi-trophy"></i><span>Event</span></a>
  <a href="/profile.php" class="bn-item position-relative">
    <?php if ($navFoto): ?>
      <img src="<?= htmlspecialchars($navFoto) ?>" alt="" class="nav-avatar">
    <?php else: ?>
      <span class="nav-avatar-fallback"><?= htmlspecialchars(mb_strtoupper(mb_substr($u['nama'] ?? '?',0,1))) ?></span>
    <?php endif; ?>
    <span>Saya</span>
    <?php if ($nUnread): ?><span class="bn-badge"><?= $nUnread ?></span><?php endif; ?>
  </a>
</nav>
