<?php
// Public profile (klik nama user)
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/badges.php';
send_security_headers(); enforce_session_timeout();
$id = (int)($_GET['id'] ?? 0);
$user = db_one("SELECT id,nama,email,foto_url,xp,level,streak_minggu,bio,role,last_seen FROM users WHERE id=$1", [$id]);
if (!$user) { http_response_code(404); die('User tidak ditemukan.'); }
$pageTitle = 'Profil '.$user['nama'];

$badges = user_badges($id);
$hadir = (int) db_val("SELECT COUNT(*) FROM absensi WHERE user_id=$1 AND hadir=1", [$id]);
$sesi  = (int) db_val("SELECT COUNT(*) FROM absensi WHERE user_id=$1", [$id]);
$lastPosts = db_all("SELECT * FROM posts WHERE user_id=$1 AND jenis='post' ORDER BY created_at DESC LIMIT 6", [$id]);
// Performa Lari Mingguan (7 hari terakhir) — bersifat publik
$weeklyRuns = db_all("SELECT tanggal, durasi_menit, jarak_km, kalori, pace
                      FROM upload_harian
                      WHERE user_id=$1 AND tanggal >= CURRENT_DATE - INTERVAL '7 days'
                      ORDER BY tanggal ASC", [$id]);
$wkTotalKm    = 0; $wkTotalMin = 0; $wkTotalKcal = 0;
foreach ($weeklyRuns as $r) {
    $wkTotalKm   += (float)$r['jarak_km'];
    $wkTotalMin  += (int)$r['durasi_menit'];
    $wkTotalKcal += (int)$r['kalori'];
}
include __DIR__.'/includes/header.php';
?>
<div class="card shadow-sm mb-3"><div class="card-body d-flex gap-3 align-items-center">
  <?= user_avatar($user['foto_url'] ?? null, $user['nama'], 88) ?>
  <div class="flex-grow-1">
    <h4 class="mb-0"><?= htmlspecialchars($user['nama']) ?> <span class="badge bg-light text-dark">Lv <?= (int)$user['level'] ?></span></h4>
    <div class="text-muted small"><?= htmlspecialchars($user['role']) ?> · ⭐ <?= (int)$user['xp'] ?> XP · 🔥 <?= (int)$user['streak_minggu'] ?> minggu</div>
    <?php if($user['bio']): ?><p class="mb-0 mt-1"><?= htmlspecialchars($user['bio']) ?></p><?php endif; ?>
  </div>
  <div class="text-end">
    <div class="small text-muted">Hadir</div><div class="h5 mb-0"><?= $hadir ?>/<?= $sesi ?></div>
  </div>
</div></div>

<div class="card shadow-sm mb-3"><div class="card-header"><i class="bi bi-speedometer2 text-success"></i> Performa Lari Mingguan (7 hari terakhir)</div>
<div class="card-body">
  <div class="row g-2 mb-2">
    <div class="col-4"><div class="card card-stat text-center"><div class="card-body p-2"><div class="stat-label">Total Jarak</div><div class="stat-value"><?= number_format($wkTotalKm,2) ?> km</div></div></div></div>
    <div class="col-4"><div class="card card-stat text-center"><div class="card-body p-2"><div class="stat-label">Total Durasi</div><div class="stat-value"><?= (int)$wkTotalMin ?> mnt</div></div></div></div>
    <div class="col-4"><div class="card card-stat text-center"><div class="card-body p-2"><div class="stat-label">Total Kalori</div><div class="stat-value"><?= number_format($wkTotalKcal) ?></div></div></div></div>
  </div>
  <?php if($weeklyRuns): ?>
  <div class="table-responsive"><table class="table table-sm mb-0">
    <thead><tr><th>Tanggal</th><th>Jarak (km)</th><th>Durasi</th><th>Pace</th><th>Kalori</th></tr></thead>
    <tbody>
    <?php foreach($weeklyRuns as $r): ?>
      <tr>
        <td><?= htmlspecialchars($r['tanggal']) ?></td>
        <td><?= htmlspecialchars($r['jarak_km'] ?? '0') ?></td>
        <td><?= (int)$r['durasi_menit'] ?> mnt</td>
        <td><?= htmlspecialchars($r['pace'] ?? '-') ?: '-' ?></td>
        <td><?= (int)$r['kalori'] ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
  <?php else: ?><p class="text-muted small text-center mb-0">Belum ada lari minggu ini.</p><?php endif; ?>
</div></div>

<div class="card shadow-sm mb-3"><div class="card-header"><i class="bi bi-award text-warning"></i> Badge (<?= count($badges) ?>)</div>
<div class="card-body"><div class="row g-2">
<?php foreach($badges as $b): ?>
  <div class="col-6 col-md-3"><div class="badge-tile">
    <i class="bi <?= htmlspecialchars($b['icon']) ?> text-<?= htmlspecialchars($b['warna']) ?>"></i>
    <div class="fw-semibold small mt-1"><?= htmlspecialchars($b['nama']) ?></div>
    <div class="text-muted small"><?= date('d M Y', strtotime($b['earned_at'])) ?></div>
  </div></div>
<?php endforeach; if(!$badges): ?><div class="col-12 text-muted small">Belum punya badge.</div><?php endif; ?>
</div></div></div>

<?php if($lastPosts): ?>
<div class="card shadow-sm"><div class="card-header"><i class="bi bi-images"></i> Postingan Terbaru</div>
<div class="card-body"><div class="row g-2">
<?php foreach($lastPosts as $p): ?>
  <div class="col-6 col-md-4">
    <?php if($p['foto_url']): ?><img src="<?= htmlspecialchars($p['foto_url']) ?>" class="img-fluid rounded"><?php endif; ?>
    <div class="small mt-1"><?= htmlspecialchars(mb_substr($p['caption'] ?? '',0,80)) ?></div>
  </div>
<?php endforeach; ?>
</div></div></div>
<?php endif; ?>
<?php include __DIR__.'/includes/footer.php'; ?>
