<?php
/**
 * admin/komunitas.php — Revisi R5 (Juli 2026)
 * CRUD Komunitas + kolom Total Member (menggantikan kolom "Data" sebelumnya).
 */
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/security.php';
require_login();
$me = current_user();
if (($me['role'] ?? '') !== 'admin') { http_response_code(403); exit('Khusus admin.'); }

try {
    db_exec("CREATE TABLE IF NOT EXISTS komunitas (
        id SERIAL PRIMARY KEY,
        nama VARCHAR(120) NOT NULL,
        deskripsi TEXT NULL,
        created_at TIMESTAMP DEFAULT NOW()
    )");
    db_exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS komunitas_id INTEGER NULL");
} catch (Throwable $e) {}

$flash=null; $err=null;
if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    $act = (string)($_POST['act'] ?? '');
    try {
        if ($act==='create') {
            $n = trim((string)($_POST['nama'] ?? ''));
            $d = trim((string)($_POST['deskripsi'] ?? ''));
            if ($n==='') throw new RuntimeException('Nama komunitas wajib diisi.');
            db_exec("INSERT INTO komunitas (nama, deskripsi) VALUES ($1,$2)", [$n, $d ?: null]);
            $flash='Komunitas ditambahkan.';
        } elseif ($act==='update') {
            $id=(int)($_POST['id']??0);
            $n=trim((string)($_POST['nama']??''));
            $d=trim((string)($_POST['deskripsi']??''));
            if ($id<=0||$n==='') throw new RuntimeException('Data tidak lengkap.');
            db_exec("UPDATE komunitas SET nama=$1, deskripsi=$2 WHERE id=$3", [$n, $d?:null, $id]);
            $flash='Komunitas diperbarui.';
        } elseif ($act==='delete') {
            $id=(int)($_POST['id']??0);
            if ($id>0) {
                db_exec("UPDATE users SET komunitas_id=NULL WHERE komunitas_id=$1", [$id]);
                db_exec("DELETE FROM komunitas WHERE id=$1", [$id]);
                $flash='Komunitas dihapus.';
            }
        }
    } catch (Throwable $e) { $err = $e->getMessage(); }
}

$rows = db_all("SELECT k.id, k.nama, k.deskripsi,
                       (SELECT COUNT(*) FROM users u WHERE u.komunitas_id = k.id) AS total_member
                FROM komunitas k ORDER BY k.nama ASC");
$csrf = csrf_token();
$pageTitle = 'Daftar Komunitas';
include __DIR__ . '/../includes/header.php';
?>
<div class="container my-4">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0"><i class="bi bi-people"></i> Daftar Komunitas</h4>
  </div>

  <?php if ($flash): ?><div class="alert alert-success py-2"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
  <?php if ($err):   ?><div class="alert alert-danger  py-2"><?= htmlspecialchars($err)   ?></div><?php endif; ?>

  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
      <form method="post" class="row g-2">
        <input type="hidden" name="csrf" value="<?= $csrf ?>">
        <input type="hidden" name="act" value="create">
        <div class="col-md-4"><input name="nama" class="form-control" placeholder="Nama komunitas" required></div>
        <div class="col-md-6"><input name="deskripsi" class="form-control" placeholder="Deskripsi (opsional)"></div>
        <div class="col-md-2"><button class="btn btn-primary w-100"><i class="bi bi-plus-lg"></i> Tambah</button></div>
      </form>
    </div>
  </div>

  <div class="table-responsive card border-0 shadow-sm">
    <table class="table mb-0 align-middle">
      <thead class="table-light">
        <tr>
          <th>#</th><th>Nama</th><th>Deskripsi</th>
          <th class="text-center">Total Member</th>
          <th class="text-end">Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
        <tr>
          <form method="post">
            <input type="hidden" name="csrf" value="<?= $csrf ?>">
            <input type="hidden" name="act" value="update">
            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <td><?= (int)$r['id'] ?></td>
            <td><input name="nama" class="form-control form-control-sm" value="<?= htmlspecialchars($r['nama']) ?>" required></td>
            <td><input name="deskripsi" class="form-control form-control-sm" value="<?= htmlspecialchars((string)$r['deskripsi']) ?>"></td>
            <td class="text-center"><span class="badge bg-info-subtle text-info-emphasis"><?= (int)$r['total_member'] ?> member</span></td>
            <td class="text-end text-nowrap">
              <button class="btn btn-sm btn-outline-primary" title="Simpan"><i class="bi bi-save"></i></button>
          </form>
              <form method="post" class="d-inline" onsubmit="return confirm('Hapus komunitas ini? Anggota akan dilepas dari komunitas.')">
                <input type="hidden" name="csrf" value="<?= $csrf ?>">
                <input type="hidden" name="act" value="delete">
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
              </form>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="5" class="text-center text-muted py-4">Belum ada komunitas.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
