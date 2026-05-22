<?php
require __DIR__.'/../config/db.php';
require __DIR__.'/../includes/auth.php';
require __DIR__.'/../includes/helpers.php';
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
        $tempatId = (int)($_POST['tempat_id'] ?? 0) ?: null;
        $tempatNama = $_POST['tempat'] ?? '';
        if ($tempatId) { $row = db_one("SELECT nama FROM tempat WHERE id=$1", [$tempatId]); if ($row) $tempatNama = $row['nama']; }
        $jm = $_POST['jam_mulai'] ?: null;
        $js = $_POST['jam_selesai'] ?: null;
        db_exec("UPDATE jadwal SET tanggal=$1, bulan=$2, minggu_ke=$3, jenis=$4, tempat=$5,
                                   tempat_id=$6, durasi_menit=$7, koordinator_id=$8,
                                   konten_obrolan=$9, catatan=$10, jam_mulai=$11, jam_selesai=$12
                 WHERE id=$13",
                [$tgl, $bulan, $w, $_POST['jenis'], $tempatNama,
                 $tempatId, ((int)($_POST['durasi_menit'] ?? 0) ?: null),
                 (int)($_POST['koordinator_id'] ?? 0) ?: null,
                 $_POST['konten'] ?? '', $_POST['catatan'] ?? '', $jm, $js, $id]);
    } else {
        $tgl   = $_POST['tanggal'];
        $bulan = date('F', strtotime($tgl));
        $w     = 'W' . (int)ceil(date('j', strtotime($tgl))/7);
        $tempatId = (int)($_POST['tempat_id'] ?? 0) ?: null;
        $tempatNama = '';
        if ($tempatId) { $row = db_one("SELECT nama FROM tempat WHERE id=$1", [$tempatId]); if ($row) $tempatNama = $row['nama']; }
        $jm = $_POST['jam_mulai'] ?: null;
        $js = $_POST['jam_selesai'] ?: null;
        db_exec("INSERT INTO jadwal(tanggal,bulan,minggu_ke,jenis,tempat,tempat_id,durasi_menit,koordinator_id,konten_obrolan,catatan,jam_mulai,jam_selesai)
                 VALUES($1,$2,$3,$4,$5,$6,$7,$8,$9,$10,$11,$12)",
                [$tgl, $bulan, $w, $_POST['jenis'], $tempatNama, $tempatId,
                 ((int)($_POST['durasi_menit'] ?? 0) ?: null),
                 (int)($_POST['koordinator_id'] ?? 0) ?: current_user()['id'],
                 $_POST['konten'] ?? '', $_POST['catatan'] ?? '', $jm, $js]);
    }
    header('Location: jadwal.php'); exit;
}

$rows   = db_all("SELECT j.*, u.nama AS koord, u.foto_url AS koord_foto FROM jadwal j LEFT JOIN users u ON u.id=j.koordinator_id ORDER BY tanggal DESC");
$admins = db_all("SELECT id,nama FROM users WHERE role='admin' ORDER BY nama");
$jenisList = array_column(db_all("SELECT nama FROM jenis_olahraga ORDER BY nama"), 'nama');
if (!$jenisList) $jenisList = ['Jogging','Badminton','Futsal','Senam','Renang','Lainnya'];
$tempatList = db_all("SELECT id,nama FROM tempat ORDER BY nama");
include __DIR__.'/../includes/header.php'; ?>

<h2 class="mb-3"><i class="bi bi-calendar-event text-primary"></i> Manajemen Jadwal</h2>

<div class="card shadow-sm mb-3"><div class="card-header"><i class="bi bi-plus-circle me-1 text-primary"></i> Tambah Jadwal</div>
<div class="card-body">
  <form method="post" class="row g-2">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <div class="col-md-2"><label class="form-label small fw-semibold">Tanggal</label>
      <input type="date" name="tanggal" class="form-control" required></div>
    <div class="col-md-2"><label class="form-label small fw-semibold">Jam Mulai</label>
      <input type="time" name="jam_mulai" class="form-control"></div>
    <div class="col-md-2"><label class="form-label small fw-semibold">Jam Selesai</label>
      <input type="time" name="jam_selesai" class="form-control"></div>
    <div class="col-md-2"><label class="form-label small fw-semibold">Jenis</label>
      <select name="jenis" class="form-select">
        <?php foreach($jenisList as $j): ?><option><?= htmlspecialchars($j) ?></option><?php endforeach; ?>
      </select></div>
    <div class="col-md-4"><label class="form-label small fw-semibold">Tempat</label>
      <select name="tempat_id" class="form-select" required>
        <option value="">— Pilih Tempat —</option>
        <?php foreach($tempatList as $t): ?><option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['nama']) ?></option><?php endforeach; ?>
      </select></div>
    <div class="col-md-3"><label class="form-label small fw-semibold">Koordinator</label>
      <select name="koordinator_id" class="form-select">
        <option value="">— Pilih —</option>
        <?php foreach($admins as $a): ?><option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['nama']) ?></option><?php endforeach; ?>
      </select></div>
    <div class="col-md-2"><label class="form-label small fw-semibold">Lama Main (mnt)</label>
      <input type="number" name="durasi_menit" min="0" class="form-control" placeholder="cth 120"></div>
    <div class="col-md-1 d-flex align-items-end"><button class="btn btn-primary w-100"><i class="bi bi-plus-lg"></i></button></div>
    <div class="col-12"><label class="form-label small fw-semibold mt-2">Konten Obrolan (WYSIWYG)</label>
      <textarea name="konten" data-wysiwyg placeholder="Topik obrolan, hikmah, dll..."></textarea></div>
    <div class="col-12"><label class="form-label small fw-semibold">Catatan Kondisi Kegiatan (WYSIWYG)</label>
      <textarea name="catatan" data-wysiwyg placeholder="Kondisi, cedera, izin, dll..."></textarea></div>
  </form>
</div></div>

<div class="card shadow-sm"><div class="table-responsive"><table class="table table-hover mb-0">
  <thead><tr><th>#</th><th>Tanggal</th><th>Hari</th><th>Jam</th><th>Bulan</th><th>W</th><th>Jenis</th><th>Tempat</th><th>Durasi</th><th>Koordinator</th><th class="text-end">Aksi</th></tr></thead>
  <tbody>
  <?php foreach($rows as $i=>$r): ?>
    <tr>
      <td class="text-muted"><?= $i+1 ?></td>
      <td><?= htmlspecialchars($r['tanggal']) ?></td>
      <td><span class="pill"><?= hari_id($r['tanggal']) ?></span></td>
      <td><small><?= htmlspecialchars(substr($r['jam_mulai'] ?? '',0,5)) ?: '—' ?><?= !empty($r['jam_selesai']) ? '<br>s/d '.htmlspecialchars(substr($r['jam_selesai'],0,5)) : '' ?></small></td>
      <td><?= htmlspecialchars($r['bulan']) ?></td>
      <td><span class="pill"><?= htmlspecialchars($r['minggu_ke']) ?></span></td>
      <td><?= htmlspecialchars($r['jenis']) ?></td>
      <td><?= htmlspecialchars($r['tempat']) ?></td>
      <td><?= !empty($r['durasi_menit']) ? ((int)$r['durasi_menit'].' mnt') : '<span class="text-muted small">—</span>' ?></td>
      <td><?= user_name_with_avatar($r['koord_foto'] ?? null, $r['koord'] ?? '-', false, 26) ?></td>
      <td class="text-end" style="white-space:nowrap">
        <button class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#viewJ<?= $r['id'] ?>" title="Lihat obrolan & catatan"><i class="bi bi-eye"></i></button>
        <a class="btn btn-sm btn-outline-primary" href="absensi.php?id=<?= $r['id'] ?>" title="Absensi"><i class="bi bi-check2-square"></i></a>
        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editJ<?= $r['id'] ?>" title="Edit"><i class="bi bi-pencil"></i></button>
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

<?php foreach($rows as $r): ?>
<!-- View modal: konten obrolan + catatan kondisi -->
<div class="modal fade" id="viewJ<?= $r['id'] ?>" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title"><i class="bi bi-journal-text"></i> <?= htmlspecialchars($r['tanggal']) ?> · <?= htmlspecialchars($r['jenis']) ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <h6 class="text-primary"><i class="bi bi-chat-square-text"></i> Konten Obrolan</h6>
        <div class="border rounded p-2 mb-3"><?= $r['konten_obrolan'] ?: '<span class="text-muted small">—</span>' ?></div>
        <h6 class="text-warning"><i class="bi bi-clipboard-pulse"></i> Catatan Kondisi Kegiatan</h6>
        <div class="border rounded p-2"><?= $r['catatan'] ?: '<span class="text-muted small">—</span>' ?></div>
      </div>
    </div>
  </div>
</div>

<!-- Edit modal -->
<div class="modal fade" id="editJ<?= $r['id'] ?>" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
    <form method="post" class="modal-content">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="_action" value="edit">
      <input type="hidden" name="id" value="<?= $r['id'] ?>">
      <div class="modal-header"><h5 class="modal-title"><i class="bi bi-pencil-square"></i> Edit Jadwal</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body wysiwyg-body">
        <div class="row g-2">
          <div class="col-md-3"><label class="form-label small fw-semibold">Tanggal</label>
            <input type="date" name="tanggal" class="form-control" value="<?= htmlspecialchars($r['tanggal']) ?>" required></div>
          <div class="col-md-3"><label class="form-label small fw-semibold">Jam Mulai</label>
            <input type="time" name="jam_mulai" class="form-control" value="<?= htmlspecialchars(substr($r['jam_mulai'] ?? '',0,5)) ?>"></div>
          <div class="col-md-3"><label class="form-label small fw-semibold">Jam Selesai</label>
            <input type="time" name="jam_selesai" class="form-control" value="<?= htmlspecialchars(substr($r['jam_selesai'] ?? '',0,5)) ?>"></div>
          <div class="col-md-3"><label class="form-label small fw-semibold">Jenis</label>
            <select name="jenis" class="form-select">
              <?php foreach($jenisList as $j): ?><option <?= $r['jenis']===$j?'selected':'' ?>><?= htmlspecialchars($j) ?></option><?php endforeach; ?>
              <?php if (!in_array($r['jenis'], $jenisList, true)): ?><option selected><?= htmlspecialchars($r['jenis']) ?></option><?php endif; ?>
            </select></div>
          <div class="col-md-4"><label class="form-label small fw-semibold">Koordinator (admin)</label>
            <select name="koordinator_id" class="form-select">
              <option value="">— Pilih —</option>
              <?php foreach($admins as $a): ?><option value="<?= $a['id'] ?>" <?= $a['id']==$r['koordinator_id']?'selected':'' ?>><?= htmlspecialchars($a['nama']) ?></option><?php endforeach; ?>
            </select></div>
          <div class="col-md-5"><label class="form-label small fw-semibold">Tempat</label>
            <select name="tempat_id" class="form-select">
              <option value="">— Pilih Tempat —</option>
              <?php foreach($tempatList as $t): ?><option value="<?= $t['id'] ?>" <?= $t['id']==$r['tempat_id']?'selected':'' ?>><?= htmlspecialchars($t['nama']) ?></option><?php endforeach; ?>
            </select>
            <input type="hidden" name="tempat" value="<?= htmlspecialchars($r['tempat']) ?>">
            <small class="text-muted">Saat ini: <?= htmlspecialchars($r['tempat']) ?: '—' ?></small></div>
          <div class="col-md-3"><label class="form-label small fw-semibold">Lama Main (menit)</label>
            <input type="number" name="durasi_menit" min="0" class="form-control" value="<?= (int)($r['durasi_menit'] ?? 0) ?: '' ?>" placeholder="cth 120"></div>
          <div class="col-12"><label class="form-label small fw-semibold">Konten Obrolan</label>
            <textarea name="konten" data-wysiwyg><?= htmlspecialchars($r['konten_obrolan'] ?? '') ?></textarea></div>
          <div class="col-12"><label class="form-label small fw-semibold">Catatan Kondisi Kegiatan</label>
            <textarea name="catatan" data-wysiwyg><?= htmlspecialchars($r['catatan'] ?? '') ?></textarea></div>
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
