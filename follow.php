<?php
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
send_security_headers(); require_login();
$u = current_user(); $uid = (int)$u['id'];

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    rate_limit_or_die('follow:'.$uid, 60, 60);
    $target = (int)($_POST['target_id'] ?? 0);
    $a = $_POST['_action'] ?? '';
    if ($target>0 && $target!==$uid) {
        if ($a==='follow') {
            db_exec("INSERT INTO user_follows(follower_id,following_id) VALUES($1,$2) ON CONFLICT DO NOTHING", [$uid,$target]);
            @pg_query_params(db(), "INSERT INTO notifications(user_id,judul,body,url) VALUES($1,$2,$3,$4)",
                [$target,'Pengikut baru', $u['nama'].' mulai mengikuti Anda', '/user.php?id='.$uid]);
        } elseif ($a==='unfollow') {
            db_exec("DELETE FROM user_follows WHERE follower_id=$1 AND following_id=$2", [$uid,$target]);
        }
    }
    if (!empty($_POST['ajax'])) { header('Content-Type: application/json'); echo json_encode(['ok'=>true]); exit; }
    header('Location: '.($_SERVER['HTTP_REFERER'] ?? '/follow.php')); exit;
}

$target = (int)($_GET['u'] ?? $uid);
$peer = db_one("SELECT id,nama,foto_url FROM users WHERE id=$1", [$target]);
if (!$peer) { http_response_code(404); die('User tidak ditemukan.'); }
$pageTitle = 'Follow · '.$peer['nama'];

$tab = $_GET['tab'] ?? 'followers';
if ($tab==='following') {
    $list = db_all("SELECT u.id,u.nama,u.foto_url FROM user_follows f JOIN users u ON u.id=f.following_id WHERE f.follower_id=$1 ORDER BY f.created_at DESC", [$target]);
} else {
    $list = db_all("SELECT u.id,u.nama,u.foto_url FROM user_follows f JOIN users u ON u.id=f.follower_id  WHERE f.following_id=$1 ORDER BY f.created_at DESC", [$target]);
}
$nFollowers = (int)db_val("SELECT COUNT(*) FROM user_follows WHERE following_id=$1", [$target]);
$nFollowing = (int)db_val("SELECT COUNT(*) FROM user_follows WHERE follower_id=$1",  [$target]);
$iFollow = $target!==$uid && db_one("SELECT 1 FROM user_follows WHERE follower_id=$1 AND following_id=$2", [$uid,$target]);

include __DIR__.'/includes/header.php';
?>
<div class="card shadow-sm mb-3"><div class="card-body d-flex gap-3 align-items-center">
  <?= user_avatar($peer['foto_url'], $peer['nama'], 56) ?>
  <div class="flex-grow-1">
    <h5 class="m-0"><?= htmlspecialchars($peer['nama']) ?></h5>
    <div class="small text-muted"><?= $nFollowers ?> pengikut · <?= $nFollowing ?> mengikuti</div>
  </div>
  <?php if($target!==$uid): ?>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="target_id" value="<?= $target ?>">
      <input type="hidden" name="_action" value="<?= $iFollow?'unfollow':'follow' ?>">
      <button class="btn btn-<?= $iFollow?'outline-secondary':'primary' ?>">
        <i class="bi bi-<?= $iFollow?'person-dash':'person-plus' ?>"></i> <?= $iFollow?'Berhenti Ikuti':'Ikuti' ?>
      </button>
    </form>
  <?php endif; ?>
</div></div>

<ul class="nav nav-tabs mb-3">
  <li class="nav-item"><a class="nav-link <?= $tab==='followers'?'active':'' ?>" href="?u=<?= $target ?>&tab=followers">Pengikut (<?= $nFollowers ?>)</a></li>
  <li class="nav-item"><a class="nav-link <?= $tab==='following'?'active':'' ?>" href="?u=<?= $target ?>&tab=following">Mengikuti (<?= $nFollowing ?>)</a></li>
</ul>

<div class="list-group">
  <?php if(!$list): ?><div class="list-group-item text-muted">Belum ada.</div><?php endif; ?>
  <?php foreach($list as $row): ?>
    <a href="/user.php?id=<?= (int)$row['id'] ?>" class="list-group-item d-flex gap-2 align-items-center">
      <?= user_avatar($row['foto_url'], $row['nama'], 36) ?>
      <strong><?= htmlspecialchars($row['nama']) ?></strong>
    </a>
  <?php endforeach; ?>
</div>
<?php include __DIR__.'/includes/footer.php'; ?>
