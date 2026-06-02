<?php
/**
 * admin/privasi.php
 * CRUD Kebijakan Privasi (UU PDP No. 27/2022).
 */
require __DIR__.'/../config/db.php';
require __DIR__.'/../includes/auth.php';
require __DIR__.'/../includes/helpers.php';
require_role('admin');
$pageTitle = 'Kebijakan Privasi';

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    $a = $_POST['_action'] ?? '';
    try {
        if ($a === 'add' || $a === 'edit') {
            $judul  = substr(trim($_POST['judul'] ?? ''),0,160);
            $versi  = substr(trim($_POST['versi'] ?? '1.0'),0,20);
            $konten = trim($_POST['konten'] ?? '');
            $aktif  = !empty($_POST['aktif']);
            if ($judul === '' || $konten === '') throw new RuntimeException('Judul dan konten wajib diisi.');
            if (function_exists('sanitize_html')) $konten = sanitize_html($konten);

            if ($aktif) db_exec("UPDATE kebijakan_privasi SET aktif=false");
            if ($a === 'add') {
                db_exec("INSERT INTO kebijakan_privasi(judul,versi,konten,aktif) VALUES($1,$2,$3,$4)",
                    [$judul,$versi,$konten,$aktif?'t':'f']);
            } else {
                db_exec("UPDATE kebijakan_privasi SET judul=$1,versi=$2,konten=$3,aktif=$4,updated_at=now() WHERE id=$5",
                    [$judul,$versi,$konten,$aktif?'t':'f',(int)$_POST['id']]);
            }
            $_SESSION['flash'] = 'Kebijakan privasi disimpan.';
        } elseif ($a === 'delete') {
            db_exec("DELETE FROM kebijakan_privasi WHERE id=$1",[(int)$_POST['id']]);
            $_SESSION['flash'] = 'Kebijakan dihapus.';
        }
    } catch (Throwable $e) {
        $_SESSION['flash_err'] = 'Gagal: '.$e->getMessage();
    }
    header('Location: privasi.php'); exit;
}

$editId = (int)($_GET['edit'] ?? 0);
$edit   = $editId ? db_one("SELECT * FROM kebijakan_privasi WHERE id=$1",[$editId]) : null;
$rows   = db_all("SELECT id, judul, versi, aktif, updated_at FROM kebijakan_privasi ORDER BY aktif DESC, id DESC");

include __DIR__.'/../includes/header.php';
?>
<h2 class="mb-3"><i class="bi bi-shield-check text-success"></i> Kebijakan Privasi (UU PDP)</h2>
<?php if (!empty($_SESSION['flash'])): ?><div class="alert alert-success py-2 small"><?= htmlspecialchars($_SESSION['flash']) ?></div><?php unset($_SESSION['flash']); endif; ?>
<?php if (!empty($_SESSION['flash_err'])): ?><div class="alert alert-danger py-2 small"><?= htmlspecialchars($_SESSION['flash_err']) ?></div><?php unset($_SESSION['flash_err']); endif; ?>

<div class="card mb-3">
  <div class="card-header"><i class="bi <?= $edit?'bi-pencil-square':'bi-plus-circle' ?>"></i> <?= $edit?'Edit #'.(int)$edit['id']:'Tambah Versi' ?></div>
  <form method="post" class="card-body row g-2">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <input type="hidden" name="_action" value="<?= $edit?'edit':'add' ?>">
    <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>
    <div class="col-md-8"><label class="small">Judul</label>
      <input class="form-control form-control-sm" name="judul" required value="<?= htmlspecialchars($edit['judul'] ?? 'Kebijakan Privasi (UU PDP)') ?>"></div>
    <div class="col-md-2"><label class="small">Versi</label>
      <input class="form-control form-control-sm" name="versi" value="<?= htmlspecialchars($edit['versi'] ?? '1.0') ?>"></div>
    <div class="col-md-2 mt-4"><div class="form-check">
      <input class="form-check-input" type="checkbox" name="aktif" id="akP" <?= (!$edit || ($edit['aktif']==='t'||$edit['aktif']===true))?'checked':'' ?>>
      <label for="akP" class="small">aktif (versi yang ditampilkan publik)</label>
    </div></div>
    <div class="col-12"><label class="small">Konten HTML</label>
      <textarea class="form-control" name="konten" rows="18" data-wysiwyg><?= htmlspecialchars($edit['konten'] ?? '') ?></textarea>
    </div>
    <div class="col-12">
      <button class="btn btn-primary btn-sm"><i class="bi bi-save"></i> Simpan</button>
      <?php if ($edit): ?><a href="privasi.php" class="btn btn-link btn-sm">Batal</a><?php endif; ?>
      <a href="/privasi.php" target="_blank" class="btn btn-outline-secondary btn-sm"><i class="bi bi-eye"></i> Preview Publik</a>
    </div>
  </form>
</div>

<div class="table-responsive">
<table class="table table-sm align-middle">
  <thead><tr><th>#</th><th>Judul</th><th>Versi</th><th>Aktif</th><th>Update</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td><?= (int)$r['id'] ?></td>
      <td><?= htmlspecialchars($r['judul']) ?></td>
      <td><?= htmlspecialchars($r['versi']) ?></td>
      <td><?= ($r['aktif']==='t'||$r['aktif']===true)?'✅':'⬜' ?></td>
      <td class="small text-muted"><?= htmlspecialchars($r['updated_at'] ?? '') ?></td>
      <td>
        <a class="btn btn-sm btn-outline-primary" href="?edit=<?= (int)$r['id'] ?>"><i class="bi bi-pencil"></i></a>
        <form method="post" class="d-inline" onsubmit="return confirm('Hapus versi ini?')">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
          <input type="hidden" name="_action" value="delete">
          <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
          <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>

<?php include __DIR__.'/../includes/footer.php'; ?>
