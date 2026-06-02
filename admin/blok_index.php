<?php
/**
 * admin/blok_index.php
 * CRUD blok / komponen yang ditampilkan di index.php (gaya CMS WordPress).
 * Posisi: top / middle / bottom. Konten HTML (gunakan editor Quill / paste HTML).
 */
require __DIR__.'/../config/db.php';
require __DIR__.'/../includes/auth.php';
require __DIR__.'/../includes/helpers.php';
require_role('admin');
$pageTitle = 'Blok Beranda';

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    $a = $_POST['_action'] ?? '';
    try {
        if ($a === 'add' || $a === 'edit') {
            $judul  = substr(trim($_POST['judul'] ?? ''),0,120);
            $konten = trim($_POST['konten'] ?? '');
            $posisi = in_array($_POST['posisi'] ?? 'top', ['top','middle','bottom'], true) ? $_POST['posisi'] : 'top';
            $urut   = (int)($_POST['urutan'] ?? 0);
            $aktif  = !empty($_POST['aktif']);
            if ($judul === '') throw new RuntimeException('Judul wajib diisi.');
            if (function_exists('sanitize_html')) $konten = sanitize_html($konten);
            if ($a === 'add') {
                db_exec("INSERT INTO index_blok(judul,konten,posisi,urutan,aktif) VALUES($1,$2,$3,$4,$5)",
                    [$judul,$konten,$posisi,$urut,$aktif?'t':'f']);
            } else {
                db_exec("UPDATE index_blok SET judul=$1,konten=$2,posisi=$3,urutan=$4,aktif=$5,updated_at=now() WHERE id=$6",
                    [$judul,$konten,$posisi,$urut,$aktif?'t':'f',(int)$_POST['id']]);
            }
            $_SESSION['flash'] = 'Blok disimpan.';
        } elseif ($a === 'delete') {
            db_exec("DELETE FROM index_blok WHERE id=$1",[(int)$_POST['id']]);
            $_SESSION['flash'] = 'Blok dihapus.';
        }
    } catch (Throwable $e) {
        $_SESSION['flash_err'] = 'Gagal: '.$e->getMessage();
    }
    header('Location: blok_index.php'); exit;
}

$editId = (int)($_GET['edit'] ?? 0);
$edit   = $editId ? db_one("SELECT * FROM index_blok WHERE id=$1",[$editId]) : null;
$rows   = db_all("SELECT * FROM index_blok ORDER BY posisi, urutan, id DESC");

include __DIR__.'/../includes/header.php';
?>
<h2 class="mb-3"><i class="bi bi-grid-3x3-gap text-success"></i> Blok Beranda (index.php)</h2>
<?php if (!empty($_SESSION['flash'])): ?><div class="alert alert-success py-2 small"><?= htmlspecialchars($_SESSION['flash']) ?></div><?php unset($_SESSION['flash']); endif; ?>
<?php if (!empty($_SESSION['flash_err'])): ?><div class="alert alert-danger py-2 small"><?= htmlspecialchars($_SESSION['flash_err']) ?></div><?php unset($_SESSION['flash_err']); endif; ?>

<div class="card mb-3">
  <div class="card-header"><i class="bi <?= $edit?'bi-pencil-square':'bi-plus-circle' ?>"></i> <?= $edit?'Edit Blok #'.(int)$edit['id']:'Tambah Blok' ?></div>
  <form method="post" class="card-body row g-2">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <input type="hidden" name="_action" value="<?= $edit?'edit':'add' ?>">
    <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>
    <div class="col-md-6"><label class="small">Judul</label>
      <input class="form-control form-control-sm" name="judul" required value="<?= htmlspecialchars($edit['judul'] ?? '') ?>"></div>
    <div class="col-md-2"><label class="small">Posisi</label>
      <select class="form-select form-select-sm" name="posisi">
        <?php foreach (['top','middle','bottom'] as $p): ?>
          <option value="<?= $p ?>" <?= (($edit['posisi'] ?? '')==$p?'selected':'') ?>><?= $p ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-md-2"><label class="small">Urutan</label>
      <input type="number" class="form-control form-control-sm" name="urutan" value="<?= (int)($edit['urutan'] ?? 0) ?>"></div>
    <div class="col-md-2 mt-4"><div class="form-check"><input class="form-check-input" type="checkbox" name="aktif" id="ak3" <?= (!$edit || ($edit['aktif']==='t'||$edit['aktif']===true))?'checked':'' ?>><label for="ak3" class="small">aktif</label></div></div>
    <div class="col-12"><label class="small">Konten (HTML)</label>
      <textarea class="form-control" name="konten" id="konten" rows="10"><?= htmlspecialchars($edit['konten'] ?? '') ?></textarea>
    </div>
    <div class="col-12">
      <button class="btn btn-primary btn-sm"><i class="bi bi-save"></i> Simpan</button>
      <?php if ($edit): ?><a href="blok_index.php" class="btn btn-link btn-sm">Batal</a><?php endif; ?>
    </div>
  </form>
</div>

<div class="table-responsive">
<table class="table table-sm align-middle">
  <thead><tr><th>#</th><th>Judul</th><th>Posisi</th><th>Urut</th><th>Aktif</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td><?= (int)$r['id'] ?></td>
      <td><?= htmlspecialchars($r['judul']) ?></td>
      <td><span class="badge bg-secondary"><?= htmlspecialchars($r['posisi']) ?></span></td>
      <td><?= (int)$r['urutan'] ?></td>
      <td><?= ($r['aktif']==='t'||$r['aktif']===true)?'✅':'⬜' ?></td>
      <td>
        <a class="btn btn-sm btn-outline-primary" href="?edit=<?= (int)$r['id'] ?>"><i class="bi bi-pencil"></i></a>
        <form method="post" class="d-inline" onsubmit="return confirm('Hapus blok ini?')">
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
