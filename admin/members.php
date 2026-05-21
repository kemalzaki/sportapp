<?php
require __DIR__.'/../config/db.php';
require __DIR__.'/../includes/auth.php';
require_role('admin');
$pageTitle='Manajemen Member';
$msg='';

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    $a = $_POST['_action'] ?? '';
    if ($a==='update_role') {
        db_exec("UPDATE users SET role=$1 WHERE id=$2", [$_POST['role'], (int)$_POST['id']]);
    } elseif ($a==='delete') {
        db_exec("DELETE FROM users WHERE id=$1", [(int)$_POST['id']]);
    } elseif ($a==='create') {
        $pwd = $_POST['password'] ?: 'changeme';
        db_exec("INSERT INTO users(nama,email,password_hash,role) VALUES($1,$2,$3,$4)",
                [$_POST['nama'], $_POST['email'], password_hash($pwd, PASSWORD_BCRYPT), $_POST['role']]);
    } elseif ($a==='reset_pwd') {
        $new = $_POST['new_password'] ?? '';
        if (strlen($new) >= 6) {
            db_exec("UPDATE users SET password_hash=$1 WHERE id=$2",
                    [password_hash($new, PASSWORD_BCRYPT), (int)$_POST['id']]);
            $_SESSION['flash'] = 'Password member berhasil diubah.';
        } else {
            $_SESSION['flash_err'] = 'Password minimal 6 karakter.';
        }
    } elseif ($a==='edit') {
        db_exec("UPDATE users SET nama=$1, email=$2 WHERE id=$3",
                [$_POST['nama'], $_POST['email'], (int)$_POST['id']]);
    }
    header('Location: members.php'); exit;
}

$users = db_all("SELECT * FROM users ORDER BY role, nama");
$flash = $_SESSION['flash'] ?? null; $flashE = $_SESSION['flash_err'] ?? null;
unset($_SESSION['flash'], $_SESSION['flash_err']);
include __DIR__.'/../includes/header.php'; ?>

<h2 class="mb-3"><i class="bi bi-people text-primary"></i> Manajemen Member</h2>

<?php if($flash): ?><div class="alert alert-success py-2"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
<?php if($flashE): ?><div class="alert alert-danger py-2"><?= htmlspecialchars($flashE) ?></div><?php endif; ?>

<div class="card shadow-sm mb-3"><div class="card-header"><i class="bi bi-person-plus me-1 text-primary"></i> Tambah Member</div>
<div class="card-body">
  <form method="post" class="row g-2">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <input type="hidden" name="_action" value="create">
    <div class="col-md-3"><input class="form-control" name="nama" placeholder="Nama lengkap" required></div>
    <div class="col-md-3"><input class="form-control" type="email" name="email" placeholder="Email" required></div>
    <div class="col-md-3"><input class="form-control" name="password" placeholder="Password (default: changeme)"></div>
    <div class="col-md-2"><select class="form-select" name="role"><option value="member">member</option><option value="admin">admin</option></select></div>
    <div class="col-md-1"><button class="btn btn-primary w-100"><i class="bi bi-plus-lg"></i></button></div>
  </form>
</div></div>

<div class="card shadow-sm"><div class="table-responsive"><table class="table table-hover mb-0">
  <thead><tr><th>#</th><th>Nama</th><th>Email</th><th>Role</th><th class="text-end">Aksi</th></tr></thead><tbody>
  <?php foreach($users as $i=>$u): ?>
    <tr>
      <td class="text-muted"><?= $i+1 ?></td>
      <td class="fw-semibold"><i class="bi bi-person-circle text-muted me-1"></i> <?= htmlspecialchars($u['nama']) ?></td>
      <td class="text-muted"><?= htmlspecialchars($u['email']) ?></td>
      <td>
        <form method="post" class="d-flex gap-1">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
          <input type="hidden" name="_action" value="update_role">
          <input type="hidden" name="id" value="<?= $u['id'] ?>">
          <select name="role" class="form-select form-select-sm" onchange="this.form.submit()">
            <?php foreach(['publik','member','admin'] as $r): ?><option <?= $u['role']===$r?'selected':'' ?>><?= $r ?></option><?php endforeach; ?>
          </select>
        </form>
      </td>
      <td class="text-end">
        <button class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#pwd<?= $u['id'] ?>" title="Reset Password"><i class="bi bi-key"></i></button>
        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#edt<?= $u['id'] ?>" title="Edit"><i class="bi bi-pencil"></i></button>
        <form method="post" class="d-inline" onsubmit="return confirm('Hapus user <?= htmlspecialchars($u['nama']) ?>?')">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
          <input type="hidden" name="_action" value="delete"><input type="hidden" name="id" value="<?= $u['id'] ?>">
          <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody></table></div></div>

<!-- Modal Reset Password & Edit (per user) -->
<?php foreach($users as $u): ?>
<div class="modal fade" id="pwd<?= $u['id'] ?>" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <form method="post" class="modal-content">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="_action" value="reset_pwd">
      <input type="hidden" name="id" value="<?= $u['id'] ?>">
      <div class="modal-header"><h5 class="modal-title"><i class="bi bi-key"></i> Reset Password</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <p class="text-muted small">Atur password baru untuk <strong><?= htmlspecialchars($u['nama']) ?></strong> (<?= htmlspecialchars($u['email']) ?>).</p>
        <label class="form-label small fw-semibold">Password Baru (min 6)</label>
        <input type="text" name="new_password" class="form-control" minlength="6" required autocomplete="off">
        <small class="text-muted">Sampaikan password ini ke member secara aman.</small>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
        <button class="btn btn-warning"><i class="bi bi-shield-check"></i> Reset Password</button>
      </div>
    </form>
  </div>
</div>

<div class="modal fade" id="edt<?= $u['id'] ?>" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <form method="post" class="modal-content">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="_action" value="edit">
      <input type="hidden" name="id" value="<?= $u['id'] ?>">
      <div class="modal-header"><h5 class="modal-title"><i class="bi bi-pencil-square"></i> Edit Member</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="mb-2"><label class="form-label small fw-semibold">Nama</label>
          <input name="nama" class="form-control" value="<?= htmlspecialchars($u['nama']) ?>" required></div>
        <div class="mb-2"><label class="form-label small fw-semibold">Email</label>
          <input name="email" type="email" class="form-control" value="<?= htmlspecialchars($u['email']) ?>" required></div>
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
