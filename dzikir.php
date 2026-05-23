<?php
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
require __DIR__.'/includes/islami_data.php';
require __DIR__.'/includes/islami_helpers.php';
send_security_headers();
$pageTitle = 'Dzikir Pagi & Petang';
$u = current_user();
$w = ($_GET['w'] ?? '') === 'petang' ? 'petang' : 'pagi';

if ($_SERVER['REQUEST_METHOD']==='POST' && $u) {
    csrf_check();
    $a = $_POST['_action'] ?? '';
    if ($a === 'dzikir_done') {
        $key = $_POST['w'] === 'petang' ? 'dzikir_petang' : 'dzikir_pagi';
        islami_touch_streak((int)$u['id'], $key);
        islami_log_challenge((int)$u['id'], $key);
        $_SESSION['flash'] = 'Dzikir tercatat ✨';
    }
    header('Location: /dzikir.php?w='.$w); exit;
}
$list = $w==='petang' ? $ISLAMI_DZIKIR_PETANG : $ISLAMI_DZIKIR_PAGI;
include __DIR__.'/includes/header.php';
?>
<?php if (!empty($_SESSION['flash'])): ?><div class="alert alert-success py-2 small"><?= htmlspecialchars($_SESSION['flash']) ?></div><?php unset($_SESSION['flash']); endif; ?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="m-0"><i class="bi bi-<?= $w==='petang'?'sunset':'sunrise' ?> text-warning"></i> Dzikir <?= ucfirst($w) ?></h4>
  <div>
    <a href="?w=pagi"  class="btn btn-sm <?= $w==='pagi'?'btn-warning':'btn-outline-warning' ?>">Pagi</a>
    <a href="?w=petang" class="btn btn-sm <?= $w==='petang'?'btn-warning':'btn-outline-warning' ?>">Petang</a>
  </div>
</div>
<?php if ($u): ?>
<form method="post" class="mb-3"><input type="hidden" name="csrf" value="<?= csrf_token() ?>">
  <input type="hidden" name="_action" value="dzikir_done"><input type="hidden" name="w" value="<?= $w ?>">
  <button class="btn btn-success btn-sm"><i class="bi bi-check2-circle"></i> Catat dzikir <?= $w ?> selesai</button></form>
<?php endif; ?>
<div class="row g-3">
<?php foreach ($list as $d): ?>
  <div class="col-md-6"><div class="card h-100"><div class="card-body">
    <div class="fw-semibold text-warning mb-1"><?= htmlspecialchars($d[0]) ?></div>
    <div class="text-end fs-5" style="font-family:'Amiri',serif"><?= htmlspecialchars($d[1]) ?></div>
    <div class="small fst-italic mt-2"><?= htmlspecialchars($d[2]) ?></div>
  </div></div></div>
<?php endforeach; ?>
</div>
<?php include __DIR__.'/includes/footer.php'; ?>
