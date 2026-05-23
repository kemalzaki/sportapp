<?php
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
send_security_headers(); require_login();
$u = current_user();
$uid = (int)$u['id'];
$pageTitle = 'Bookmark';

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    rate_limit_or_die('bm:'.$uid, 60, 60);
    $pid = (int)($_POST['post_id'] ?? 0);
    $a = $_POST['_action'] ?? '';
    if ($pid && $a==='add')   db_exec("INSERT INTO post_bookmarks(user_id,post_id) VALUES($1,$2) ON CONFLICT DO NOTHING", [$uid,$pid]);
    if ($pid && $a==='remove') db_exec("DELETE FROM post_bookmarks WHERE user_id=$1 AND post_id=$2", [$uid,$pid]);
    if (!empty($_POST['ajax'])) { header('Content-Type: application/json'); echo json_encode(['ok'=>true]); exit; }
    header('Location: '.($_SERVER['HTTP_REFERER'] ?? '/bookmark.php')); exit;
}

$rows = db_all("SELECT p.*, u.nama, u.foto_url AS u_foto, b.created_at AS saved_at
                FROM post_bookmarks b
                JOIN posts p ON p.id=b.post_id
                JOIN users u ON u.id=p.user_id
                WHERE b.user_id=$1
                ORDER BY b.created_at DESC LIMIT 100", [$uid]);
include __DIR__.'/includes/header.php';
?>
<h4 class="mb-3"><i class="bi bi-bookmark-star text-warning"></i> Bookmark Saya</h4>
<?php if(!$rows): ?>
  <div class="alert alert-info">Belum ada post yang di-bookmark. Klik ikon <i class="bi bi-bookmark"></i> pada post untuk menyimpannya.</div>
<?php else: foreach($rows as $p): ?>
  <div class="card shadow-sm mb-2"><div class="card-body">
    <div class="d-flex gap-2 mb-2">
      <?= user_avatar($p['u_foto'], $p['nama'], 32) ?>
      <div><strong><?= htmlspecialchars($p['nama']) ?></strong>
        <div class="small text-muted">Dibookmark: <?= htmlspecialchars($p['saved_at']) ?></div></div>
    </div>
    <?php if(!empty($p['foto_url'])): ?><img src="<?= htmlspecialchars($p['foto_url']) ?>" class="img-fluid rounded mb-2"><?php endif; ?>
    <div><?= nl2br(htmlspecialchars($p['caption']??'')) ?></div>
    <form method="post" class="mt-2">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="_action" value="remove">
      <input type="hidden" name="post_id" value="<?= (int)$p['id'] ?>">
      <button class="btn btn-sm btn-outline-danger"><i class="bi bi-bookmark-x"></i> Hapus bookmark</button>
    </form>
  </div></div>
<?php endforeach; endif; ?>
<?php include __DIR__.'/includes/footer.php'; ?>
