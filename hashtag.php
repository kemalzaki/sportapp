<?php
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
send_security_headers(); require_login();
$u = current_user();
$tag = strtolower(preg_replace('/[^a-z0-9_]/i','', $_GET['t'] ?? ''));
$pageTitle = '#'.$tag;
require_once __DIR__.'/includes/htmx.php'; htmx_layout_start($pageTitle ?? 'Hashtag');

if ($tag === '') { echo '<div class="alert alert-warning">Hashtag kosong.</div>'; htmx_layout_end(); exit; }

$rows = db_all("SELECT p.*, u.nama, u.foto_url, u.id AS uid
                FROM posts p
                JOIN post_hashtags h ON h.post_id=p.id
                JOIN users u ON u.id=p.user_id
                WHERE h.tag=$1
                ORDER BY p.id DESC LIMIT 100", [$tag]);
$count = (int)db_val("SELECT COUNT(*) FROM post_hashtags WHERE tag=$1", [$tag]);
?>
<div class="d-flex align-items-center justify-content-between mb-3">
  <h4 class="m-0"><i class="bi bi-hash text-primary"></i><?= htmlspecialchars($tag) ?></h4>
  <span class="badge bg-primary-subtle text-primary"><?= $count ?> post</span>
</div>

<?php if(!$rows): ?>
  <div class="alert alert-info">Belum ada postingan dengan #<?= htmlspecialchars($tag) ?>.</div>
<?php else: foreach($rows as $p): ?>
  <div class="card shadow-sm mb-2"><div class="card-body">
    <div class="d-flex gap-2 mb-2">
      <?= user_avatar($p['foto_url'], $p['nama'], 32) ?>
      <div><strong><?= htmlspecialchars($p['nama']) ?></strong><br>
        <span class="small text-muted"><?= htmlspecialchars($p['created_at']) ?></span></div>
    </div>
    <?php if(!empty($p['foto_url'])): endif; ?>
    <?php if(!empty($p['foto_url'])): ?>
      <img src="<?= htmlspecialchars($p['foto_url']) ?>" class="img-fluid rounded mb-2">
    <?php endif; ?>
    <div><?= nl2br(htmlspecialchars($p['caption'] ?? '')) ?></div>
  </div></div>
<?php endforeach; endif; ?>
<?php htmx_layout_end(); ?>
