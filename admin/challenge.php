<?php
require __DIR__.'/../config/db.php';
require __DIR__.'/../includes/auth.php';
require __DIR__.'/../includes/security.php';
require __DIR__.'/../includes/helpers.php';
require __DIR__.'/../includes/islami_helpers.php';
require_role('admin');
$pageTitle = 'Kelola Challenge Islami';

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    $a = $_POST['_action'] ?? '';
    if ($a === 'create') {
        $kunci = preg_replace('/[^a-z0-9_]/','', strtolower(trim($_POST['kunci'] ?? '')));
        $judul = trim($_POST['judul'] ?? '');
        if ($kunci && $judul) {
            try {
                db_exec("INSERT INTO challenge_master(kunci,judul,deskripsi,icon,warna,aktif) VALUES($1,$2,$3,$4,$5,$6)",
                  [$kunci, $judul, trim($_POST['deskripsi'] ?? ''),
                   trim($_POST['icon'] ?? 'bi-trophy') ?: 'bi-trophy',
                   trim($_POST['warna'] ?? 'success') ?: 'success',
                   isset($_POST['aktif']) ? 1 : 0]);
                $_SESSION['flash'] = 'Challenge ditambahkan.';
            } catch (Throwable $e) { $_SESSION['flash_err'] = 'Kunci sudah dipakai.'; }
        } else { $_SESSION['flash_err'] = 'Kunci & judul wajib diisi.'; }
    } elseif ($a === 'edit') {
        db_exec("UPDATE challenge_master SET judul=$1, deskripsi=$2, icon=$3, warna=$4, aktif=$5 WHERE id=$6",
          [trim($_POST['judul']), trim($_POST['deskripsi'] ?? ''),
           trim($_POST['icon'] ?? 'bi-trophy') ?: 'bi-trophy',
           trim($_POST['warna'] ?? 'success') ?: 'success',
           isset($_POST['aktif']) ? 1 : 0, (int)$_POST['id']]);
        $_SESSION['flash'] = 'Challenge diperbarui.';
    } elseif ($a === 'delete') {
        db_exec("DELETE FROM challenge_master WHERE id=$1", [(int)$_POST['id']]);
        $_SESSION['flash'] = 'Challenge dihapus.';
    } elseif ($a === 'toggle') {
        db_exec("UPDATE challenge_master SET aktif = 1 - aktif WHERE id=$1", [(int)$_POST['id']]);
    }
    header('Location: challenge.php'); exit;
}

$rows = db_all("SELECT cm.*, COALESCE(c.n,0) AS n FROM challenge_master cm
  LEFT JOIN (SELECT challenge_key, COUNT(*) AS n FROM challenge_log GROUP BY challenge_key) c
  ON c.challenge_key = cm.kunci ORDER BY cm.aktif DESC, cm.id");
$warna_opts = ['success','primary','warning','danger','info','dark','secondary'];
include __DIR__.'/../includes/header.php';
?>
<?php if (!empty($_SESSION['flash'])): ?><div class="alert alert-success py-2"><?= htmlspecialchars($_SESSION['flash']) ?></div><?php unset($_SESSION['flash']); endif; ?>
<?php if (!empty($_SESSION['flash_err'])): ?><div class="alert alert-danger py-2"><?= htmlspecialchars($_SESSION['flash_err']) ?></div><?php unset($_SESSION['flash_err']); endif; ?>

<h2 class="mb-3"><i class="bi bi-trophy text-warning"></i> Kelola Challenge Islami</h2>
<p class="text-muted">CRUD master Challenge Islami yang muncul di halaman <code>/challenge.php</code>.</p>

<div class="card shadow-sm mb-3"><div class="card-header"><i class="bi bi-plus-circle text-primary"></i> Tambah Challenge</div>
<div class="card-body">
  <form method="post" class="row g-2">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <input type="hidden" name="_action" value="create">
    <div class="col-md-2"><input class="form-control" name="kunci" placeholder="kunci (a-z_)" pattern="[a-z0-9_]+" required></div>
    <div class="col-md-3"><input class="form-control" name="judul" placeholder="Judul" required></div>
    <div class="col-md-3"><input class="form-control" name="deskripsi" placeholder="Deskripsi singkat"></div>
    <div class="col-md-2"><input class="form-control" name="icon" placeholder="bi-trophy" value="bi-trophy"></div>
    <div class="col-md-1">
      <select class="form-select" name="warna">
        <?php foreach ($warna_opts as $w): ?><option value="<?= $w ?>"><?= $w ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-1 d-flex align-items-center gap-1">
      <input class="form-check-input" type="checkbox" name="aktif" id="ck" checked><label class="form-check-label small" for="ck">aktif</label>
    </div>
    <div class="col-12"><button class="btn btn-primary"><i class="bi bi-plus-lg"></i> Tambah</button></div>
  </form>
</div></div>

<div class="card shadow-sm"><div class="table-responsive"><table class="table table-hover mb-0">
  <thead><tr><th>#</th><th>Kunci</th><th>Judul</th><th>Deskripsi</th><th>Icon</th><th>Warna</th><th>Aktif</th><th>Total log</th><th class="text-end">Aksi</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $i=>$r): ?>
    <tr>
      <td><?= $i+1 ?></td>
      <td><code><?= htmlspecialchars($r['kunci']) ?></code></td>
      <td>
        <form method="post" class="d-flex gap-1 align-items-center">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
          <input type="hidden" name="_action" value="edit">
          <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
          <input class="form-control form-control-sm" name="judul" value="<?= htmlspecialchars($r['judul']) ?>" style="min-width:180px">
      </td>
      <td><input class="form-control form-control-sm" name="deskripsi" value="<?= htmlspecialchars($r['deskripsi'] ?? '') ?>" style="min-width:220px"></td>
      <td><input class="form-control form-control-sm" name="icon" value="<?= htmlspecialchars($r['icon']) ?>" style="width:120px"><i class="bi <?= htmlspecialchars($r['icon']) ?>"></i></td>
      <td>
        <select class="form-select form-select-sm" name="warna" style="width:110px">
          <?php foreach ($warna_opts as $w): ?>
            <option value="<?= $w ?>" <?= $r['warna']===$w?'selected':'' ?>><?= $w ?></option>
          <?php endforeach; ?>
        </select>
      </td>
      <td><input class="form-check-input" type="checkbox" name="aktif" <?= $r['aktif']?'checked':'' ?>></td>
      <td><span class="badge bg-<?= $r['warna'] ?>"><?= (int)$r['n'] ?>×</span></td>
      <td class="text-end">
          <button class="btn btn-primary btn-sm"><i class="bi bi-save"></i></button>
        </form>
        <form method="post" style="display:inline" onsubmit="return confirm('Hapus challenge ini? Log riwayat tetap tersimpan.');">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
          <input type="hidden" name="_action" value="delete">
          <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
          <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div></div>

<?php include __DIR__.'/../includes/footer.php'; ?>
