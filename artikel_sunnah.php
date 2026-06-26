<?php
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
require __DIR__.'/includes/islami_helpers.php';
send_security_headers(); require_login();
$pageTitle = 'Artikel Sunnah Menjaga Kesehatan';
$u = current_user();

if ($_SERVER['REQUEST_METHOD']==='POST' && $u) {
    csrf_check();
    $a = $_POST['_action'] ?? '';
    if ($a === 'create' && $u['role']==='admin') {
        db_exec("INSERT INTO islami_artikel(user_id,judul,isi) VALUES($1,$2,$3)",
          [(int)$u['id'], substr(trim($_POST['judul'] ?? ''), 0, 180), substr($_POST['isi'] ?? '', 0, 8000)]);
        $_SESSION['flash'] = 'Artikel dipublikasikan.';
    } elseif ($a === 'edit' && $u['role']==='admin') {
        db_exec("UPDATE islami_artikel SET judul=$1, isi=$2, updated_at=now() WHERE id=$3",
          [substr(trim($_POST['judul'] ?? ''), 0, 180), substr($_POST['isi'] ?? '', 0, 8000), (int)$_POST['id']]);
        $_SESSION['flash'] = 'Artikel diperbarui.';
    } elseif ($a === 'delete') {
        $id = (int)$_POST['id'];
        $o = db_one("SELECT user_id FROM islami_artikel WHERE id=$1", [$id]);
        if ($o && ((int)$o['user_id'] === (int)$u['id'] || $u['role']==='admin')) {
            db_exec("DELETE FROM islami_artikel WHERE id=$1", [$id]);
            $_SESSION['flash'] = 'Artikel dihapus.';
        }
    }
    header('Location: /artikel_sunnah.php'); exit;
}

if (!(int)db_val("SELECT COUNT(*) FROM islami_artikel")) {
    $seed = [
       ['Pola Tidur ala Rasulullah','Tidur lebih awal setelah Isya, bangun sebelum Subuh. Posisi tidur miring ke kanan, dengan dzikir sebelum tidur.'],
       ['Makan Tidak Berlebihan','Sepertiga untuk makanan, sepertiga air, sepertiga udara. Pola makan yang menjaga kesehatan jangka panjang.'],
       ['Berbekam (Hijamah)','Sunnah Nabi yang baik dilakukan di tanggal 17, 19, 21 bulan hijriyah untuk membantu sirkulasi darah.'],
       ['Madu, Habbatussauda, Kurma','Tiga makanan sunnah yang memiliki manfaat kesehatan luar biasa.'],
       ['Berjalan Kaki & Olahraga','Rasulullah menganjurkan memanah, berenang, dan menunggang kuda. Bergeraklah setiap hari.'],
    ];
    foreach ($seed as $s) db_exec("INSERT INTO islami_artikel(user_id,judul,isi) VALUES(NULL,$1,$2)", [$s[0],$s[1]]);
}

// Revisi R17 (26 Juni 2026) — Pagination Artikel Sunnah
$perPage = 5;
$page = max(1, (int)($_GET['p'] ?? 1));
$totalRows = (int)db_val("SELECT COUNT(*) FROM islami_artikel");
$totalPages = max(1, (int)ceil($totalRows / $perPage));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $perPage;
$rows = db_all("SELECT a.*, u.nama FROM islami_artikel a LEFT JOIN users u ON u.id=a.user_id ORDER BY a.created_at DESC LIMIT $perPage OFFSET $offset");
$editId = (int)($_GET['edit'] ?? 0);
$editRow = ($editId && $u['role']==='admin') ? db_one("SELECT * FROM islami_artikel WHERE id=$1", [$editId]) : null;

include __DIR__.'/includes/header.php';
?>
<nav aria-label="breadcrumb" class="mb-2"><ol class="breadcrumb small mb-0"><li class="breadcrumb-item"><a href="/index.php">Beranda</a></li><li class="breadcrumb-item"><a href="/islami.php">Islami</a></li><li class="breadcrumb-item active">Artikel Sunnah Menjaga Kesehatan</li></ol></nav>

<?php if (!empty($_SESSION['flash'])): ?><div class="alert alert-success py-2 small"><?= htmlspecialchars($_SESSION['flash']) ?></div><?php unset($_SESSION['flash']); endif; ?>
<h4 class="mb-3"><i class="bi bi-journal-text text-success"></i> Artikel Sunnah Menjaga Kesehatan</h4>

<?php if ($u && $u['role']==='admin'): ?>
<form method="post" class="card card-body mb-3">
  <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
  <input type="hidden" name="_action" value="<?= $editRow ? 'edit' : 'create' ?>">
  <?php if ($editRow): ?><input type="hidden" name="id" value="<?= (int)$editRow['id'] ?>"><?php endif; ?>
  <h6 class="mb-2"><?= $editRow ? '✏️ Edit Artikel' : '📝 Tulis Artikel' ?></h6>
  <input class="form-control mb-2" name="judul" maxlength="180" placeholder="Judul artikel" required value="<?= htmlspecialchars($editRow['judul'] ?? '') ?>">
  <textarea class="form-control mb-2" name="isi" rows="5" required><?= htmlspecialchars($editRow['isi'] ?? '') ?></textarea>
  <div><button class="btn btn-success"><?= $editRow ? 'Simpan Perubahan' : 'Publikasikan' ?></button>
    <?php if ($editRow): ?><a href="/artikel_sunnah.php" class="btn btn-link">Batal</a><?php endif; ?>
  </div>
</form>
<?php endif; ?>

<?php foreach ($rows as $r): ?>
<div class="card mb-2"><div class="card-body">
  <div class="d-flex justify-content-between">
    <div>
      <h6 class="m-0"><?= htmlspecialchars($r['judul']) ?></h6>
      <div class="small text-muted">Oleh <?= htmlspecialchars($r['nama'] ?? 'Admin') ?> · <?= htmlspecialchars($r['created_at']) ?></div>
    </div>
    <?php if ($u && $u['role']==='admin'): ?>
    <div class="d-flex gap-1">
      <a class="btn btn-sm btn-outline-secondary" href="?edit=<?= (int)$r['id'] ?>"><i class="bi bi-pencil"></i></a>
      <form method="post" onsubmit="return confirm('Hapus?')"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="_action" value="delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
    </div>
    <?php endif; ?>
  </div>
  <div class="mt-2"><?= nl2br(htmlspecialchars($r['isi'])) ?></div>
</div></div>
<?php endforeach; ?>

<?php if ($totalPages > 1): ?>
<nav aria-label="Pagination Artikel Sunnah" class="mt-3">
  <ul class="pagination pagination-sm justify-content-center mb-1">
    <li class="page-item <?= $page<=1?'disabled':'' ?>">
      <a class="page-link" href="?p=<?= max(1,$page-1) ?>" aria-label="Sebelumnya">&laquo;</a>
    </li>
    <?php
      $start = max(1, $page-2); $end = min($totalPages, $page+2);
      if ($start > 1) echo '<li class="page-item"><a class="page-link" href="?p=1">1</a></li>'.($start>2?'<li class="page-item disabled"><span class="page-link">…</span></li>':'');
      for ($i=$start; $i<=$end; $i++): ?>
        <li class="page-item <?= $i===$page?'active':'' ?>"><a class="page-link" href="?p=<?= $i ?>"><?= $i ?></a></li>
    <?php endfor;
      if ($end < $totalPages) echo ($end<$totalPages-1?'<li class="page-item disabled"><span class="page-link">…</span></li>':'').'<li class="page-item"><a class="page-link" href="?p='.$totalPages.'">'.$totalPages.'</a></li>';
    ?>
    <li class="page-item <?= $page>=$totalPages?'disabled':'' ?>">
      <a class="page-link" href="?p=<?= min($totalPages,$page+1) ?>" aria-label="Berikutnya">&raquo;</a>
    </li>
  </ul>
  <div class="text-center small text-muted">Halaman <?= $page ?> dari <?= $totalPages ?> · Total <?= $totalRows ?> artikel</div>
</nav>
<?php endif; ?>

<?php include __DIR__.'/includes/footer.php'; ?>
