<?php
// Sticky bottom nav + floating upload button. Hanya tampil saat login.
$u = current_user();
if (!$u) return;
require_once __DIR__ . '/notifications.php';
$nUnread = unread_notif_count((int)$u['id']);
?>
<nav class="bottom-nav d-lg-none">
  <a href="/index.php" class="bn-item"><i class="bi bi-house-door"></i><span>Beranda</span></a>
  <a href="/riwayat.php" class="bn-item"><i class="bi bi-bar-chart"></i><span>Riwayat</span></a>
  <a href="/upload.php" class="bn-fab" aria-label="Upload"><i class="bi bi-plus-lg"></i></a>
  <a href="/event.php" class="bn-item"><i class="bi bi-trophy"></i><span>Event</span></a>
  <a href="/profile.php" class="bn-item position-relative">
    <i class="bi bi-person-circle"></i><span>Saya</span>
    <?php if ($nUnread): ?><span class="bn-badge"><?= $nUnread ?></span><?php endif; ?>
  </a>
</nav>
