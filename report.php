<?php
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
send_security_headers(); require_login();
$u = current_user(); $uid = (int)$u['id'];

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    rate_limit_or_die('report:'.$uid, 10, 600);
    $pid = (int)($_POST['post_id'] ?? 0);
    $alasan = substr(trim($_POST['alasan'] ?? ''), 0, 60);
    $catatan = substr(trim($_POST['catatan'] ?? ''), 0, 1000);
    if ($pid && $alasan!=='') {
        db_exec("INSERT INTO post_reports(post_id,reporter_id,alasan,catatan) VALUES($1,$2,$3,$4)",
            [$pid,$uid,$alasan,$catatan]);
        $_SESSION['flash'] = 'Laporan terkirim. Admin akan meninjau.';
    }
    header('Location: '.($_SERVER['HTTP_REFERER'] ?? '/index.php')); exit;
}

$pageTitle = 'Laporkan Post';
$pid = (int)($_GET['post_id'] ?? 0);
$post = $pid ? db_one("SELECT p.*, u.nama FROM posts p JOIN users u ON u.id=p.user_id WHERE p.id=$1", [$pid]) : null;
require_once __DIR__.'/includes/htmx.php'; htmx_layout_start($pageTitle ?? 'Report');
?>
<div class="row justify-content-center"><div class="col-md-6">
<div class="card shadow-sm"><div class="card-body p-4">
  <h5 class="mb-3"><i class="bi bi-flag text-danger"></i> Laporkan Postingan</h5>
  <?php if(!$post): ?>
    <div class="alert alert-warning">Post tidak ditemukan.</div>
  <?php else: ?>
    <div class="alert alert-light small">
      Post oleh <strong><?= htmlspecialchars($post['nama']) ?></strong>:
      <div class="fst-italic"><?= htmlspecialchars(mb_strimwidth($post['caption']??'', 0, 200, '…')) ?></div>
    </div>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="post_id" value="<?= $pid ?>">
      <label class="form-label small fw-semibold">Alasan</label>
      <select name="alasan" class="form-select mb-2" required>
        <option value="">— pilih —</option>
        <option>Spam / Iklan</option>
        <option>Pelecehan / Bullying</option>
        <option>Konten dewasa</option>
        <option>Kekerasan</option>
        <option>Hoax / Misinformasi</option>
        <option>SARA / Ujaran kebencian</option>
        <option>Lainnya</option>
      </select>
      <label class="form-label small fw-semibold">Catatan tambahan (opsional)</label>
      <textarea class="form-control mb-3" name="catatan" rows="3" maxlength="1000"></textarea>
      <button class="btn btn-danger w-100"><i class="bi bi-send"></i> Kirim Laporan</button>
    </form>
  <?php endif; ?>
</div></div>
</div></div>
<?php htmx_layout_end(); ?>
