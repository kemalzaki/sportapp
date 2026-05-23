<?php
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
require __DIR__.'/includes/islami_helpers.php';
send_security_headers(); require_login();
$pageTitle = 'Feed Quote Islami Komunitas';
$u = current_user();
if ($_SERVER['REQUEST_METHOD']==='POST' && $u) {
    csrf_check();
    $a = $_POST['_action'] ?? '';
    if ($a === 'post') {
        $isi = trim(substr($_POST['isi'] ?? '', 0, 500));
        $sumber = trim(substr($_POST['sumber'] ?? '', 0, 120));
        if ($isi !== '') db_exec("INSERT INTO islami_quotes(user_id,isi,sumber) VALUES($1,$2,$3)", [(int)$u['id'], htmlspecialchars($isi), htmlspecialchars($sumber)]);
    } elseif ($a === 'delete') {
        $qid = (int)$_POST['id'];
        $own = db_one("SELECT user_id FROM islami_quotes WHERE id=$1", [$qid]);
        if ($own && ((int)$own['user_id']===(int)$u['id'] || $u['role']==='admin'))
            db_exec("DELETE FROM islami_quotes WHERE id=$1", [$qid]);
    }
    header('Location: /feed_islami.php'); exit;
}
$quotes = db_all("SELECT q.*, u.nama, u.foto_url FROM islami_quotes q LEFT JOIN users u ON u.id=q.user_id ORDER BY q.created_at DESC LIMIT 100");
include __DIR__.'/includes/header.php';
?>
<h4 class="mb-3"><i class="bi bi-chat-dots text-warning"></i> Feed Quote Islami Komunitas</h4>
<?php if ($u): ?>
<form method="post" class="card card-body mb-3"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="_action" value="post">
  <textarea class="form-control mb-2" name="isi" placeholder="Bagikan quote/ayat/hadist yang menginspirasi..." rows="2" maxlength="500" required></textarea>
  <div class="d-flex gap-2"><input class="form-control" name="sumber" maxlength="120" placeholder="Sumber (opsional)">
    <button class="btn btn-warning">Posting</button></div>
</form>
<?php endif; ?>
<?php foreach ($quotes as $q): ?>
  <div class="card mb-2"><div class="card-body">
    <div class="d-flex justify-content-between">
      <div class="d-flex align-items-center gap-2 small text-muted"><?= user_avatar($q['foto_url'] ?? null, $q['nama'] ?? '?', 24) ?> <?= htmlspecialchars($q['nama'] ?? 'Anonim') ?> · <?= htmlspecialchars($q['created_at']) ?></div>
      <?php if ($u && ((int)$q['user_id']===(int)$u['id'] || $u['role']==='admin')): ?>
      <form method="post" onsubmit="return confirm('Hapus?')"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="_action" value="delete"><input type="hidden" name="id" value="<?= (int)$q['id'] ?>"><button class="btn btn-sm btn-link text-danger p-0"><i class="bi bi-trash"></i></button></form>
      <?php endif; ?>
    </div>
    <div class="fst-italic mt-2">"<?= htmlspecialchars($q['isi']) ?>"</div>
    <?php if (!empty($q['sumber'])): ?><div class="small text-muted">— <?= htmlspecialchars($q['sumber']) ?></div><?php endif; ?>
  </div></div>
<?php endforeach; if(!$quotes): ?><div class="text-muted">Belum ada quote.</div><?php endif; ?>
<?php include __DIR__.'/includes/footer.php'; ?>
