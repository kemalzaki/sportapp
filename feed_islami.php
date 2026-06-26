<?php
/**
 * Revisi 31 Mei 2026 (lanjutan)
 *   #2 Social feed dibuat pagination per 2 data
 */
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
        header('Location: /feed_islami.php'); exit;
    } elseif ($a === 'delete') {
        $qid = (int)$_POST['id'];
        $own = db_one("SELECT user_id FROM islami_quotes WHERE id=$1", [$qid]);
        if ($own && ((int)$own['user_id']===(int)$u['id'] || $u['role']==='admin'))
            db_exec("DELETE FROM islami_quotes WHERE id=$1", [$qid]);
        $back = isset($_POST['page']) ? '?page='.(int)$_POST['page'] : '';
        header('Location: /feed_islami.php'.$back); exit;
    }
}

/* ---------- Pagination 2 per halaman ---------- */
$PER_PAGE  = 2;
$total     = (int) db_val("SELECT COUNT(*) FROM islami_quotes");
$totalPage = max(1, (int)ceil($total / $PER_PAGE));
$page      = max(1, (int)($_GET['page'] ?? 1));
if ($page > $totalPage) $page = $totalPage;
$offset    = ($page-1) * $PER_PAGE;

$quotes = db_all("SELECT q.*, u.nama, u.foto_url
                  FROM islami_quotes q LEFT JOIN users u ON u.id=q.user_id
                  ORDER BY q.created_at DESC
                  LIMIT $PER_PAGE OFFSET $offset");

include __DIR__.'/includes/header.php';
?>
<nav aria-label="breadcrumb" class="mb-2"><ol class="breadcrumb small mb-0"><li class="breadcrumb-item"><a href="/index.php">Beranda</a></li><li class="breadcrumb-item"><a href="/islami.php">Islami</a></li><li class="breadcrumb-item active">Feed Quote Islami Komunitas</li></ol></nav>

<h4 class="mb-3"><i class="bi bi-chat-dots text-warning"></i> Feed Quote Islami Komunitas
  <span class="badge bg-secondary ms-1" title="Total quote"><?= $total ?></span>
</h4>

<?php if ($u): ?>
<form method="post" class="card card-body mb-3">
  <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
  <input type="hidden" name="_action" value="post">
  <textarea class="form-control mb-2" name="isi" placeholder="Bagikan quote/ayat/hadist yang menginspirasi..." rows="2" maxlength="500" required></textarea>
  <div class="d-flex gap-2">
    <input class="form-control" name="sumber" maxlength="120" placeholder="Sumber (opsional)">
    <button class="btn btn-warning">Posting</button>
  </div>
</form>
<?php endif; ?>

<?php foreach ($quotes as $q): ?>
  <div class="card mb-2"><div class="card-body">
    <div class="d-flex justify-content-between">
      <div class="d-flex align-items-center gap-2 small text-muted"><?= user_avatar($q['foto_url'] ?? null, $q['nama'] ?? '?', 24) ?> <?= htmlspecialchars($q['nama'] ?? 'Anonim') ?> · <?= htmlspecialchars($q['created_at']) ?></div>
      <?php if ($u && ((int)$q['user_id']===(int)$u['id'] || $u['role']==='admin')): ?>
      <form method="post" onsubmit="return confirm('Hapus?')">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="_action" value="delete">
        <input type="hidden" name="id" value="<?= (int)$q['id'] ?>">
        <input type="hidden" name="page" value="<?= (int)$page ?>">
        <button class="btn btn-sm btn-link text-danger p-0"><i class="bi bi-trash"></i></button>
      </form>
      <?php endif; ?>
    </div>
    <div class="fst-italic mt-2">"<?= htmlspecialchars($q['isi']) ?>"</div>
    <?php if (!empty($q['sumber'])): ?><div class="small text-muted">— <?= htmlspecialchars($q['sumber']) ?></div><?php endif; ?>
  </div></div>
<?php endforeach; if(!$quotes): ?><div class="text-muted">Belum ada quote.</div><?php endif; ?>

<?php if ($totalPage > 1): ?>
<nav class="mt-3"><ul class="pagination pagination-sm justify-content-center mb-1 flex-wrap">
  <li class="page-item <?= $page<=1?'disabled':'' ?>"><a class="page-link" href="?page=<?= max(1,$page-1) ?>">«</a></li>
  <?php
    // Tampilkan jendela halaman (maks 7 angka) supaya tidak meluap kalau quote banyak
    $win = 3; $from = max(1,$page-$win); $to = min($totalPage,$page+$win);
    if ($from > 1): ?>
      <li class="page-item"><a class="page-link" href="?page=1">1</a></li>
      <?php if ($from > 2): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif;
    endif;
    for($p=$from;$p<=$to;$p++): ?>
      <li class="page-item <?= $p===$page?'active':'' ?>"><a class="page-link" href="?page=<?= $p ?>"><?= $p ?></a></li>
    <?php endfor;
    if ($to < $totalPage):
      if ($to < $totalPage-1): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
      <li class="page-item"><a class="page-link" href="?page=<?= $totalPage ?>"><?= $totalPage ?></a></li>
    <?php endif; ?>
  <li class="page-item <?= $page>=$totalPage?'disabled':'' ?>"><a class="page-link" href="?page=<?= min($totalPage,$page+1) ?>">»</a></li>
</ul></nav>
<div class="text-center small text-muted mb-2">Halaman <?= $page ?> dari <?= $totalPage ?> · <?= $total ?> quote · 2 per halaman</div>
<?php endif; ?>

<?php include __DIR__.'/includes/footer.php'; ?>
