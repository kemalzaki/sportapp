<?php
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
require __DIR__.'/includes/islami_data.php';
require __DIR__.'/includes/islami_helpers.php';
send_security_headers(); require_login();
$pageTitle = 'Doa Harian';
$u = current_user();
if ($_SERVER['REQUEST_METHOD']==='POST' && $u) {
    csrf_check();
    if (($_POST['_action'] ?? '') === 'doa_done') {
        islami_touch_streak((int)$u['id'], 'doa_done');
        islami_log_challenge((int)$u['id'], 'doa');
        $_SESSION['flash'] = 'Tercatat 🤲';
    }
    header('Location: /doa.php'); exit;
}
include __DIR__.'/includes/header.php';
?>
<?php if (!empty($_SESSION['flash'])): ?><div class="alert alert-success py-2 small"><?= htmlspecialchars($_SESSION['flash']) ?></div><?php unset($_SESSION['flash']); endif; ?>
<h4 class="mb-3"><i class="bi bi-chat-quote text-warning"></i> Doa Harian Singkat</h4>
<?php if ($u): ?>
<form method="post" class="mb-3"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="_action" value="doa_done">
  <button class="btn btn-success btn-sm"><i class="bi bi-check2-circle"></i> Catat: saya sudah berdoa hari ini</button></form>
<?php endif; ?>
<div class="row g-3">
<?php foreach ($ISLAMI_DOA as $d): ?>
  <div class="col-md-6"><div class="card h-100"><div class="card-body">
    <div class="fw-semibold text-warning mb-1"><i class="bi bi-bookmark"></i> <?= htmlspecialchars($d[0]) ?></div>
    <div class="text-end fs-5" style="font-family:'Amiri',serif;line-height:2"><?= htmlspecialchars($d[1]) ?></div>
    <div class="small fst-italic mt-2"><?= htmlspecialchars($d[2]) ?></div>
  </div></div></div>
<?php endforeach; ?>
</div>
<?php include __DIR__.'/includes/footer.php'; ?>
