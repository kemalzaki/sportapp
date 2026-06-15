<?php
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
require __DIR__.'/includes/islami_helpers.php';
send_security_headers(); require_login();
$pageTitle = 'Jadwal Sholat';
$u = current_user();
$pref = $u ? islami_pref((int)$u['id']) : ['kota'=>'Jakarta','negara'=>'Indonesia','mode_tenang'=>1];
require_once __DIR__.'/includes/htmx.php'; htmx_layout_start($pageTitle ?? 'Jadwal Sholat');
?>
<h4 class="mb-3"><i class="bi bi-mosque text-primary"></i> Jadwal Sholat Realtime</h4>
<div id="prayerCard" data-kota="<?= htmlspecialchars($pref['kota']) ?>" data-negara="<?= htmlspecialchars($pref['negara']) ?>" data-mode-tenang="<?= (int)$pref['mode_tenang'] ?>" class="card shadow-sm"><div class="card-body">
  <div class="fs-3" id="prayerNext">Memuat…</div>
  <div class="mt-2" id="prayerList"></div>
  <div class="alert alert-info small mt-3"><i class="bi bi-info-circle"></i> Reminder sholat otomatis muncul sebagai notifikasi browser saat waktu tiba. Mode Tenang akan menampilkan layar penuh saat adzan.</div>
  <a href="/islami.php" class="btn btn-sm btn-outline-primary">Ubah Kota</a>
</div></div>
<script src="/assets/js/islami.js" defer></script>
<script>
// Tambahan: reminder notifikasi
if ('Notification' in window && Notification.permission === 'default') Notification.requestPermission();
</script>
<?php htmx_layout_end(); ?>
