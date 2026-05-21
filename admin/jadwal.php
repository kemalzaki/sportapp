<?php
require __DIR__.'/../config/db.php';
require __DIR__.'/../includes/auth.php';
require_role('admin');
$pageTitle='Manajemen Jadwal';

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    $a = $_POST['_action'] ?? 'create';

    if ($a === 'delete') {
        db_exec("DELETE FROM jadwal WHERE id=$1", [(int)$_POST['id']]);
    } elseif ($a === 'edit') {
        $id    = (int)$_POST['id'];
        $tgl   = $_POST['tanggal'];
        $bulan = date('F', strtotime($tgl));
        $w     = 'W' . (int)ceil(date('j', strtotime($tgl))/7);
        db_exec("UPDATE jadwal SET tanggal=$1, bulan=$2, minggu_ke=$3, jenis=$4, tempat=$5,
                                   koordinator_id=$6, konten_obrolan=$7, catatan=$8
                 WHERE id=$9",
                [$tgl, $bulan, $w, $_POST['jenis'], $_POST['tempat'],
                 (int)($_POST['koordinator_id'] ?? 0) ?: null,
                 $_POST['konten'] ?? '', $_POST['catatan'] ?? '', $id]);
    } else { // create
        $tgl   = $_POST['tanggal'];
        $bulan = date('F', strtotime($tgl));
        $w     = 'W' . (int)ceil(date('j', strtotime($tgl))/7);
        db_exec("INSERT INTO jadwal(tanggal,bulan,minggu_ke,jenis,tempat,koordinator_id,konten_obrolan,catatan)
                 VALUES($1,$2,$3,$4,$5,$6,$7,$8)",
                [$tgl, $bulan, $w, $_POST['jenis'], $_POST['tempat'],
                 current_user()['id'], $_POST['konten'] ?? '', $_POST['catatan'] ?? '']);
    }
    header('Location: jadwal.php'); exit;
}

$rows  = db_all("SELECT j.*, u.nama AS koord FROM jadwal j LEFT JOIN users u ON u.id=j.koordinator_id ORDER BY tanggal DESC");
$admins = db_all("SELECT id,nama FROM users WHERE role IN ('admin','member') ORDER BY nama");
$jenisList = array_column(db_all("SELECT nama FROM jenis_olahraga ORDER BY nama"), 'nama');
if (!$jenisList) $jenisList = ['Jogging','Badminton','Futsal','Senam','Renang','Lainnya'];
include __DIR__.'/../includes/header.php'; ?>

<h2 class="mb-3"><i class="bi bi-calendar-event text-primary"></i> Manajemen Jadwal</h2>

<div class="card shadow-sm mb-3"><div class="card-header"><i class="bi bi-plus-circle me-1 text-primary"></i> Tambah Jadwal</div>
<div class="card-body">
  <form method="post" class="row g-2">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <div class="col-md-2"><input type="date" name="tanggal" class="form-control" required></div>
    <div class="col-md-2"><select name="jenis" class="form-select">
      <?php foreach($jenisList as $j): ?><option><?= htmlspecialchars($j) ?></option><?php endforeach; ?>
    </select></div>
    <div class="col-md-3"><input name="tempat" class="form-control" placeholder="Tempat / GOR" required></div>
    <div class="col-md-2"><input name="konten" class="form-control" placeholder="Konten Obrolan"></div>
    <div class="col-md-2"><input name="catatan" class="form-control" placeholder="Catatan"></div>
    <div class="col-md-1"><button class="btn btn-primary w-100"><i class="bi bi-plus-lg"></i></button></div>
  </form>
</div></div>

<div class="card shadow-sm"><div class="table-responsive"><table class="table table-hover mb-0">
  <thead><tr><th>#</th><th>Tanggal</th><th>Bulan</th><th>W</th><th>Jenis</th><th>Tempat</th><th>Koordinator</th><th class="text-end">Aksi</th></tr></thead>
  <tbody>
  <?php foreach($rows as $i=>$r): ?>
    <tr>
      <td class="text-muted"><?= $i+1 ?></td>
      <td><?= htmlspecialchars($r['tanggal']) ?></td>
      <td><?= htmlspecialchars($r['bulan']) ?></td>
      <td><span class="pill"><?= htmlspecialchars($r['minggu_ke']) ?></span></td>
      <td><?= htmlspecialchars($r['jenis']) ?></td>
      <td><?= htmlspecialchars($r['tempat']) ?></td>
      <td><?= htmlspecialchars($r['koord'] ?? '-') ?></td>
      <td class="text-end">
        <a class="btn btn-sm btn-outline-primary" href="absensi.php?id=<?= $r['id'] ?>"><i class="bi bi-check2-square"></i></a>
        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editJ<?= $r['id'] ?>"><i class="bi bi-pencil"></i></button>
        <form method="post" class="d-inline" onsubmit="return confirm('Hapus jadwal ini?')">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
          <input type="hidden" name="_action" value="delete">
          <input type="hidden" name="id" value="<?= $r['id'] ?>">
          <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div></div>

<!-- Modal Edit -->
<?php foreach($rows as $r): ?>
<div class="modal fade" id="editJ<?= $r['id'] ?>" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <form method="post" class="modal-content">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="_action" value="edit">
      <input type="hidden" name="id" value="<?= $r['id'] ?>">
      <div class="modal-header"><h5 class="modal-title"><i class="bi bi-pencil-square"></i> Edit Jadwal</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="row g-2">
          <div class="col-md-4"><label class="form-label small fw-semibold">Tanggal</label>
            <input type="date" name="tanggal" class="form-control" value="<?= htmlspecialchars($r['tanggal']) ?>" required></div>
          <div class="col-md-4"><label class="form-label small fw-semibold">Jenis</label>
            <select name="jenis" class="form-select">
              <?php foreach($jenisList as $j): ?><option <?= $r['jenis']===$j?'selected':'' ?>><?= htmlspecialchars($j) ?></option><?php endforeach; ?>
              <?php if (!in_array($r['jenis'], $jenisList, true)): ?><option selected><?= htmlspecialchars($r['jenis']) ?></option><?php endif; ?>
            </select></div>
          <div class="col-md-4"><label class="form-label small fw-semibold">Koordinator</label>
            <select name="koordinator_id" class="form-select"><option value="">—</option>
              <?php foreach($admins as $a): ?><option value="<?= $a['id'] ?>" <?= $a['id']==$r['koordinator_id']?'selected':'' ?>><?= htmlspecialchars($a['nama']) ?></option><?php endforeach; ?>
            </select></div>
          <div class="col-12"><label class="form-label small fw-semibold">Tempat</label>
            <input name="tempat" class="form-control" value="<?= htmlspecialchars($r['tempat']) ?>" required></div>
          <div class="col-md-6"><label class="form-label small fw-semibold">Konten Obrolan</label>
            <textarea name="konten" class="form-control" rows="3"><?= htmlspecialchars($r['konten_obrolan'] ?? '') ?></textarea></div>
          <div class="col-md-6"><label class="form-label small fw-semibold">Catatan</label>
            <textarea name="catatan" class="form-control" rows="3"><?= htmlspecialchars($r['catatan'] ?? '') ?></textarea></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
        <button class="btn btn-primary"><i class="bi bi-save"></i> Simpan Perubahan</button>
      </div>
    </form>
  </div>
</div>
<?php endforeach; ?>

<?php include __DIR__.'/../includes/footer.php'; ?>
