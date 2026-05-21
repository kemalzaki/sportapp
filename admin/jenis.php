<?php
require __DIR__.'/../config/db.php';
require __DIR__.'/../includes/auth.php';
require_role('admin');
$pageTitle='Jenis Olahraga';

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    $a = $_POST['_action'] ?? '';
    if ($a === 'create') {
        $n = trim($_POST['nama'] ?? '');
        $d = trim($_POST['deskripsi'] ?? '');
        if ($n !== '') {
            try {
                db_exec("INSERT INTO jenis_olahraga(nama,deskripsi) VALUES($1,$2)", [$n, $d]);
            } catch (Throwable $e) { $_SESSION['flash_err'] = 'Nama jenis sudah ada.'; }
        }
    } elseif ($a === 'edit') {
        db_exec("UPDATE jenis_olahraga SET nama=$1, deskripsi=$2 WHERE id=$3",
                [trim($_POST['nama']), trim($_POST['deskripsi'] ?? ''), (int)$_POST['id']]);
    } elseif ($a === 'delete') {
        db_exec("DELETE FROM jenis_olahraga WHERE id=$1", [(int)$_POST['id']]);
    }
    header('Location: jenis.php'); exit;
}

$rows = db_all("SELECT * FROM jenis_olahraga ORDER BY nama");
$err  = $_SESSION['flash_err'] ?? null; unset($_SESSION['flash_err']);
include __DIR__.'/../includes/header.php'; ?>

<h2 class="mb-3"><i class="bi bi-tags text-primary"></i> Jenis Olahraga</h2>
<p class="text-muted">Kelola daftar jenis olahraga yang dipakai pada pemilihan jadwal &amp; filter riwayat.</p>

<?php if($err): ?><div class="alert alert-danger py-2"><?= htmlspecialchars($err) ?></div><?php endif; ?>

<div class="card shadow-sm mb-3"><div class="card-header"><i class="bi bi-plus-circle me-1 text-primary"></i> Tambah Jenis</div>
<div class="card-body">
  <form method="post" class="row g-2">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <input type="hidden" name="_action" value="create">
    <div class="col-md-4"><input class="form-control" name="nama" placeholder="cth: Basket" required></div>
    <div class="col-md-7"><input class="form-control" name="deskripsi" placeholder="Deskripsi singkat (opsional)"></div>
    <div class="col-md-1"><button class="btn btn-primary w-100"><i class="bi bi-plus-lg"></i></button></div>
  </form>
</div></div>

<div class="card shadow-sm"><div class="table-responsive"><table class="table table-hover mb-0">
  <thead><tr><th>#</th><th>Nama</th><th>Deskripsi</th><th class="text-end">Aksi</th></tr></thead><tbody>
  <?php foreach($rows as $i=>$r): ?>
    <tr>
      <td class="text-muted"><?= $i+1 ?></td>
      <td class="fw-semibold"><?= htmlspecialchars($r['nama']) ?></td>
      <td class="text-muted"><?= htmlspecialchars($r['deskripsi'] ?? '') ?: '—' ?></td>
      <td class="text-end">
        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#jEdit<?= $r['id'] ?>"><i class="bi bi-pencil"></i></button>
        <form method="post" class="d-inline" onsubmit="return confirm('Hapus jenis ini?')">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
          <input type="hidden" name="_action" value="delete">
          <input type="hidden" name="id" value="<?= $r['id'] ?>">
          <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
        </form>
      </td>
    </tr>
  <?php endforeach; if(!$rows): ?><tr><td colspan="4" class="text-center text-muted py-3">Belum ada jenis olahraga.</td></tr><?php endif; ?>
  </tbody></table></div></div>

<?php foreach($rows as $r): ?>
<div class="modal fade" id="jEdit<?= $r['id'] ?>" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <form method="post" class="modal-content">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="_action" value="edit">
      <input type="hidden" name="id" value="<?= $r['id'] ?>">
      <div class="modal-header"><h5 class="modal-title">Edit Jenis</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="mb-2"><label class="form-label small fw-semibold">Nama</label>
          <input name="nama" class="form-control" value="<?= htmlspecialchars($r['nama']) ?>" required></div>
        <div class="mb-2"><label class="form-label small fw-semibold">Deskripsi</label>
          <textarea name="deskripsi" class="form-control" rows="2"><?= htmlspecialchars($r['deskripsi'] ?? '') ?></textarea></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
        <button class="btn btn-primary"><i class="bi bi-save"></i> Simpan</button>
      </div>
    </form>
  </div>
</div>
<?php endforeach; ?>

<?php include __DIR__.'/../includes/footer.php'; ?>
