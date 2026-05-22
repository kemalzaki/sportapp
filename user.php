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
