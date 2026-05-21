<?php
require __DIR__.'/../config/db.php';
require __DIR__.'/../includes/auth.php';
require __DIR__.'/../includes/helpers.php';
require_role('admin');
$pageTitle='Manajemen Tempat';

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    $a = $_POST['_action'] ?? 'create';
    if ($a==='delete') {
        db_exec("DELETE FROM tempat WHERE id=$1", [(int)$_POST['id']]);
    } elseif ($a==='edit') {
        db_exec("UPDATE tempat SET nama=$1, alamat=$2, harga_lapang=$3, harga_per_jam=$4, status_booking=$5, catatan=$6 WHERE id=$7",
            [trim($_POST['nama']), trim($_POST['alamat'] ?? ''),
             (float)($_POST['harga_lapang'] ?? 0), (float)($_POST['harga_per_jam'] ?? 0),
             $_POST['status_booking'] ?? 'tersedia', trim($_POST['catatan'] ?? ''),
             (int)$_POST['id']]);
    } else {
        db_exec("INSERT INTO tempat(nama,alamat,harga_lapang,harga_per_jam,status_booking,catatan) VALUES($1,$2,$3,$4,$5,$6)",
            [trim($_POST['nama']), trim($_POST['alamat'] ?? ''),
             (float)($_POST['harga_lapang'] ?? 0), (float)($_POST['harga_per_jam'] ?? 0),
             $_POST['status_booking'] ?? 'tersedia', trim($_POST['catatan'] ?? '')]);
    }
    header('Location: tempat.php'); exit;
}

$rows = db_all("SELECT * FROM tempat ORDER BY nama");
$statuses = ['tersedia','booked','renovasi','tutup'];
include __DIR__.'/../includes/header.php'; ?>

<h2 class="mb-3"><i class="bi bi-geo-alt text-primary"></i> Manajemen Tempat</h2>
<p class="text-muted">Daftar lapangan / GOR beserta detail biaya dan status booking. Dipakai di select-box pada Manajemen Jadwal.</p>

<div class="card shadow-sm mb-3"><div class="card-header"><i class="bi bi-plus-circle me-1 text-primary"></i> Tambah Tempat</div>
<div class="card-body">
  <form method="post" class="row g-2">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <input type="hidden" name="_action" value="create">
    <div class="col-md-3"><label class="form-label small fw-semibold">Nama Tempat</label><input class="form-control" name="nama" required></div>
    <div class="col-md-3"><label class="form-label small fw-semibold">Alamat</label><input class="form-control" name="alamat"></div>
    <div class="col-md-2"><label class="form-label small fw-semibold">Harga Lapang</label><input type="number" step="0.01" min="0" class="form-control" name="harga_lapang" value="0"></div>
    <div class="col-md-2"><label class="form-label small fw-semibold">Harga / Jam</label><input type="number" step="0.01" min="0" class="form-control" name="harga_per_jam" value="0"></div>
    <div class="col-md-2"><label class="form-label small fw-semibold">Status</label>
      <select class="form-select" name="status_booking">
        <?php foreach($statuses as $s): ?><option><?= $s ?></option><?php endforeach; ?>
      </select></div>
    <div class="col-12"><label class="form-label small fw-semibold">Catatan</label><input class="form-control" name="catatan" placeholder="cth: butuh DP, kontak admin GOR, dll"></div>
    <div class="col-12"><button class="btn btn-primary"><i class="bi bi-plus-lg"></i> Tambah</button></div>
  </form>
</div></div>

<div class="card shadow-sm"><div class="table-responsive"><table class="table table-hover mb-0">
  <thead><tr><th>#</th><th>Nama</th><th>Alamat</th><th class="text-end">Harga Lapang</th><th class="text-end">Harga/Jam</th><th>Status</th><th class="text-end">Aksi</th></tr></thead><tbody>
  <?php foreach($rows as $i=>$r): ?>
    <tr>
      <td class="text-muted"><?= $i+1 ?></td>
      <td class="fw-semibold"><?= htmlspecialchars($r['nama']) ?><?php if(!empty($r['catatan'])): ?><br><small class="text-muted"><?= htmlspecialchars($r['catatan']) ?></small><?php endif; ?></td>
      <td class="text-muted"><?= htmlspecialchars($r['alamat'] ?? '') ?: '—' ?></td>
      <td class="text-end">Rp <?= number_format((float)$r['harga_lapang'],0,',','.') ?></td>
      <td class="text-end">Rp <?= number_format((float)$r['harga_per_jam'],0,',','.') ?></td>
      <td>
        <?php $st=$r['status_booking']; $cls=$st==='tersedia'?'success':($st==='booked'?'warning':'secondary'); ?>
        <span class="badge bg-<?= $cls ?>"><?= htmlspecialchars($st) ?></span>
      </td>
      <td class="text-end">
        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#tpE<?= $r['id'] ?>"><i class="bi bi-pencil"></i></button>
        <form method="post" class="d-inline" onsubmit="return confirm('Hapus tempat ini?')">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="_action" value="delete"><input type="hidden" name="id" value="<?= $r['id'] ?>">
          <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
        </form>
      </td>
    </tr>
  <?php endforeach; if(!$rows): ?><tr><td colspan="7" class="text-center text-muted py-3">Belum ada tempat.</td></tr><?php endif; ?>
  </tbody></table></div></div>

<?php foreach($rows as $r): ?>
<div class="modal fade" id="tpE<?= $r['id'] ?>" tabindex="-1"><div class="modal-dialog modal-dialog-centered modal-lg"><form method="post" class="modal-content">
  <input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="_action" value="edit"><input type="hidden" name="id" value="<?= $r['id'] ?>">
  <div class="modal-header"><h5 class="modal-title"><i class="bi bi-pencil-square"></i> Edit Tempat</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <div class="row g-2">
      <div class="col-md-6"><label class="form-label small fw-semibold">Nama</label><input class="form-control" name="nama" value="<?= htmlspecialchars($r['nama']) ?>" required></div>
      <div class="col-md-6"><label class="form-label small fw-semibold">Alamat</label><input class="form-control" name="alamat" value="<?= htmlspecialchars($r['alamat'] ?? '') ?>"></div>
      <div class="col-md-4"><label class="form-label small fw-semibold">Harga Lapang</label><input type="number" step="0.01" min="0" class="form-control" name="harga_lapang" value="<?= htmlspecialchars($r['harga_lapang']) ?>"></div>
      <div class="col-md-4"><label class="form-label small fw-semibold">Harga / Jam</label><input type="number" step="0.01" min="0" class="form-control" name="harga_per_jam" value="<?= htmlspecialchars($r['harga_per_jam']) ?>"></div>
      <div class="col-md-4"><label class="form-label small fw-semibold">Status</label>
        <select class="form-select" name="status_booking">
          <?php foreach($statuses as $s): ?><option <?= $s===$r['status_booking']?'selected':'' ?>><?= $s ?></option><?php endforeach; ?>
        </select></div>
      <div class="col-12"><label class="form-label small fw-semibold">Catatan</label><textarea class="form-control" name="catatan" rows="2"><?= htmlspecialchars($r['catatan'] ?? '') ?></textarea></div>
    </div>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button><button class="btn btn-primary"><i class="bi bi-save"></i> Simpan</button></div>
</form></div></div>
<?php endforeach; ?>

<?php include __DIR__.'/../includes/footer.php'; ?>
