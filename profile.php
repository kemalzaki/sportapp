<?php
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/helpers.php';
require_login();
$pageTitle='Profil Saya';
$u = current_user();
$msg=''; $err='';

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    $a = $_POST['_action'] ?? '';
    $me = db_one("SELECT * FROM users WHERE id=$1", [(int)$u['id']]);
    if ($a==='upload_foto' && !empty($_FILES['foto']['name'])) {
        require_once __DIR__.'/config/imagekit.php';
        global $imageKit;
        $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
        $safe = preg_replace('/[^a-z0-9]/i','_',$u['nama'])."-avatar-".time().".".$ext;
        $up = $imageKit->uploadFile([
            'file' => base64_encode(file_get_contents($_FILES['foto']['tmp_name'])),
            'fileName' => $safe, 'folder' => '/sportapp/avatar'
        ]);
        if (!$up->error) {
            if (!empty($me['foto_file_id'])) { try { $imageKit->deleteFile($me['foto_file_id']); } catch(Throwable $e){} }
            db_exec("UPDATE users SET foto_url=$1, foto_file_id=$2 WHERE id=$3", [$up->result->url, $up->result->fileId, (int)$u['id']]);
            $msg='Foto profil diperbarui.';
        } else { $err='Gagal upload.'; }
    } elseif ($a==='delete_foto') {
        if (!empty($me['foto_file_id'])) {
            require_once __DIR__.'/config/imagekit.php';
            global $imageKit;
            try { $imageKit->deleteFile($me['foto_file_id']); } catch(Throwable $e){}
        }
        db_exec("UPDATE users SET foto_url=NULL, foto_file_id=NULL WHERE id=$1", [(int)$u['id']]);
        $msg='Foto dihapus.';
    } elseif ($a==='edit_profile') {
        db_exec("UPDATE users SET nama=$1 WHERE id=$2", [trim($_POST['nama']), (int)$u['id']]);
        $_SESSION['user']['nama'] = trim($_POST['nama']);
        $msg='Profil diperbarui.';
    }
}
$me = db_one("SELECT * FROM users WHERE id=$1", [(int)$u['id']]);
include __DIR__.'/includes/header.php'; ?>

<h2 class="mb-3"><i class="bi bi-person-circle text-primary"></i> Profil Saya</h2>
<?php if($msg): ?><div class="alert alert-success py-2"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if($err): ?><div class="alert alert-danger py-2"><?= htmlspecialchars($err) ?></div><?php endif; ?>

<div class="row g-3">
  <div class="col-md-4">
    <div class="card shadow-sm"><div class="card-body text-center">
      <div class="mb-3"><?= user_avatar($me['foto_url'] ?? null, $me['nama'], 120) ?></div>
      <h5><?= htmlspecialchars($me['nama']) ?></h5>
      <p class="text-muted small mb-3"><?= htmlspecialchars($me['email']) ?></p>
      <form method="post" enctype="multipart/form-data" class="mb-2">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="_action" value="upload_foto">
        <input type="file" name="foto" class="form-control mb-2" accept="image/*" required>
        <button class="btn btn-primary btn-sm w-100"><i class="bi bi-upload"></i> Ganti Foto</button>
      </form>
      <?php if(!empty($me['foto_url'])): ?>
      <form method="post" onsubmit="return confirm('Hapus foto?')">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="_action" value="delete_foto">
        <button class="btn btn-outline-danger btn-sm w-100"><i class="bi bi-trash"></i> Hapus Foto</button>
      </form>
      <?php endif; ?>
    </div></div>
  </div>
  <div class="col-md-8">
    <div class="card shadow-sm"><div class="card-header">Edit Profil</div>
    <div class="card-body"><form method="post">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="_action" value="edit_profile">
      <div class="mb-2"><label class="form-label small fw-semibold">Nama</label><input name="nama" class="form-control" value="<?= htmlspecialchars($me['nama']) ?>" required></div>
      <div class="mb-2"><label class="form-label small fw-semibold">Email</label><input class="form-control" value="<?= htmlspecialchars($me['email']) ?>" disabled></div>
      <button class="btn btn-primary"><i class="bi bi-save"></i> Simpan</button>
    </form></div></div>
  </div>
</div>

<?php include __DIR__.'/includes/footer.php'; ?>
