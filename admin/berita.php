<?php
require __DIR__.'/../config/db.php';
require __DIR__.'/../includes/auth.php';
require __DIR__.'/../includes/helpers.php';
require_role('admin');
$pageTitle='Manajemen Berita';

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    $a = $_POST['_action'] ?? '';
    if ($a==='create' || $a==='edit') {
        $judul = trim($_POST['judul'] ?? '');
        $isi   = $_POST['isi'] ?? '';
        $img_url = null; $img_id = null;
        if (!empty($_FILES['gambar']['name'])) {
            require_once __DIR__.'/../config/imagekit.php';
            global $imageKit;
            $ext = strtolower(pathinfo($_FILES['gambar']['name'], PATHINFO_EXTENSION));
            $safe = 'berita-'.time().'.'.$ext;
            $up = $imageKit->uploadFile([
                'file' => base64_encode(file_get_contents($_FILES['gambar']['tmp_name'])),
                'fileName' => $safe, 'folder' => '/sportapp/berita'
            ]);
            if (!$up->error) { $img_url = $up->result->url; $img_id = $up->result->fileId; }
        }
        if ($a==='create' && $judul) {
            db_exec("INSERT INTO berita(judul,isi,gambar_url,gambar_file_id) VALUES($1,$2,$3,$4)",
                    [$judul, $isi, $img_url, $img_id]);
        } elseif ($a==='edit') {
            $id = (int)$_POST['id'];
            $old = db_one("SELECT * FROM berita WHERE id=$1", [$id]);
            if ($img_url) {
                if (!empty($old['gambar_file_id'])) {
                    require_once __DIR__.'/../config/imagekit.php';
                    global $imageKit;
                    try { $imageKit->deleteFile($old['gambar_file_id']); } catch(Throwable $e){}
                }
                db_exec("UPDATE berita SET judul=$1, isi=$2, gambar_url=$3, gambar_file_id=$4 WHERE id=$5",
                        [$judul, $isi, $img_url, $img_id, $id]);
            } else {
                db_exec("UPDATE berita SET judul=$1, isi=$2 WHERE id=$3", [$judul, $isi, $id]);
            }
        }
    } elseif ($a==='delete') {
        $id = (int)$_POST['id'];
        $b = db_one("SELECT gambar_file_id FROM berita WHERE id=$1", [$id]);
        if ($b && !empty($b['gambar_file_id'])) {
            require_once __DIR__.'/../config/imagekit.php';
            global $imageKit;
            try { $imageKit->deleteFile($b['gambar_file_id']); } catch(Throwable $e){}
        }
        db_exec("DELETE FROM berita WHERE id=$1", [$id]);
    }
    header('Location: berita.php'); exit;
}

$rows = db_all("SELECT * FROM berita ORDER BY created_at DESC");
include __DIR__.'/../includes/header.php'; ?>

<h2 class="mb-3"><i class="bi bi-newspaper text-primary"></i> Manajemen Berita</h2>

<div class="card shadow-sm mb-3"><div class="card-header"><i class="bi bi-plus-circle me-1 text-primary"></i> Tambah Berita</div>
<div class="card-body"><form method="post" enctype="multipart/form-data">
  <input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="_action" value="create">
  <div class="mb-2"><label class="form-label small fw-semibold">Judul</label><input name="judul" class="form-control" required></div>
  <div class="mb-2"><label class="form-label small fw-semibold">Isi (WYSIWYG)</label><textarea name="isi" data-wysiwyg="1" class="form-control"></textarea></div>
  <div class="mb-2"><label class="form-label small fw-semibold">Gambar Slider</label><input type="file" name="gambar" class="form-control" accept="image/*"></div>
  <button class="btn btn-primary"><i class="bi bi-plus-lg"></i> Tambah</button>
</form></div></div>

<div class="card shadow-sm"><div class="table-responsive"><table class="table table-hover mb-0">
  <thead><tr><th>#</th><th>Gambar</th><th>Judul</th><th>Tanggal</th><th class="text-end">Aksi</th></tr></thead><tbody>
  <?php foreach($rows as $i=>$r): ?>
  <tr>
    <td><?= $i+1 ?></td>
    <td><?php if($r['gambar_url']): ?><img src="<?= htmlspecialchars($r['gambar_url']) ?>" style="height:50px;border-radius:6px;"><?php else: ?>-<?php endif; ?></td>
    <td><?= htmlspecialchars($r['judul']) ?></td>
    <td><small class="text-muted"><?= $r['created_at'] ?></small></td>
    <td class="text-end">
      <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#eb<?= $r['id'] ?>"><i class="bi bi-pencil"></i></button>
      <form method="post" class="d-inline" onsubmit="return confirm('Hapus berita?')">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="_action" value="delete"><input type="hidden" name="id" value="<?= $r['id'] ?>">
        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
      </form>
    </td>
  </tr>
  <?php endforeach; if(!$rows): ?><tr><td colspan="5" class="text-center text-muted py-3">Belum ada berita.</td></tr><?php endif; ?>
  </tbody></table></div></div>

<?php foreach($rows as $r): ?>
<div class="modal fade" id="eb<?= $r['id'] ?>" tabindex="-1"><div class="modal-dialog modal-dialog-centered modal-lg"><form method="post" enctype="multipart/form-data" class="modal-content">
  <input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="_action" value="edit"><input type="hidden" name="id" value="<?= $r['id'] ?>">
  <div class="modal-header"><h5 class="modal-title">Edit Berita</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <div class="mb-2"><label class="form-label small fw-semibold">Judul</label><input name="judul" class="form-control" value="<?= htmlspecialchars($r['judul']) ?>" required></div>
    <div class="mb-2"><label class="form-label small fw-semibold">Isi (WYSIWYG)</label><textarea name="isi" data-wysiwyg="1" class="form-control"><?= htmlspecialchars($r['isi'] ?? '') ?></textarea></div>
    <div class="mb-2"><label class="form-label small fw-semibold">Ganti Gambar (opsional)</label><input type="file" name="gambar" class="form-control" accept="image/*"></div>
    <?php if($r['gambar_url']): ?><img src="<?= htmlspecialchars($r['gambar_url']) ?>" style="max-height:120px;border-radius:6px;"><?php endif; ?>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button><button class="btn btn-primary"><i class="bi bi-save"></i> Simpan</button></div>
</form></div></div>
<?php endforeach; ?>

<?php include __DIR__.'/../includes/footer.php'; ?>
