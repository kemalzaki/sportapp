<?php
require __DIR__.'/../config/db.php';
require __DIR__.'/../includes/auth.php';
require __DIR__.'/../includes/helpers.php';
require_role('admin');
$pageTitle = 'Rekening Donasi Kegiatan';

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    $a = $_POST['_action'] ?? '';
    $bank = substr(trim($_POST['bank'] ?? ''),0,60);
    $nomor= substr(trim($_POST['nomor'] ?? ''),0,60);
    $an   = substr(trim($_POST['atas_nama'] ?? ''),0,120);
    $ket  = substr(trim($_POST['keterangan'] ?? ''),0,200);
    $urut = (int)($_POST['urutan'] ?? 0);
    $aktif= !empty($_POST['aktif']);
    if ($a==='add' && $bank && $nomor && $an) {
        db_exec("INSERT INTO donasi_rekening(bank,nomor,atas_nama,keterangan,urutan,aktif) VALUES($1,$2,$3,$4,$5,$6)",
            [$bank,$nomor,$an,$ket?:null,$urut,$aktif?'t':'f']);
    } elseif ($a==='edit') {
        $id = (int)$_POST['id'];
        db_exec("UPDATE donasi_rekening SET bank=$1,nomor=$2,atas_nama=$3,keterangan=$4,urutan=$5,aktif=$6 WHERE id=$7",
            [$bank,$nomor,$an,$ket?:null,$urut,$aktif?'t':'f',$id]);
    } elseif ($a==='delete') {
        db_exec("DELETE FROM donasi_rekening WHERE id=$1", [(int)$_POST['id']]);
    } elseif ($a==='toggle') {
        db_exec("UPDATE donasi_rekening SET aktif=NOT aktif WHERE id=$1", [(int)$_POST['id']]);
    }
    header('Location: donasi_rekening.php'); exit;
}
$rows = db_all("SELECT * FROM donasi_rekening ORDER BY urutan, id");
include __DIR__.'/../includes/header.php';
?>
<h2 class="mb-3"><i class="bi bi-bank text-success"></i> Rekening Donasi Kegiatan</h2>
<p class="text-muted small">Rekening yang aktif akan tampil di halaman <a href="/donasi.php">Donasi Kegiatan</a>.</p>

<div class="card mb-3"><div class="card-header"><i class="bi bi-plus-circle"></i> Tambah Rekening</div>
<div class="card-body">
  <form method="post" class="row g-2">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <input type="hidden" name="_action" value="add">
    <div class="col-md-2"><label class="small">Bank/Channel</label><input class="form-control form-control-sm" name="bank" required placeholder="BCA / DANA"></div>
    <div class="col-md-3"><label class="small">Nomor</label><input class="form-control form-control-sm" name="nomor" required></div>
    <div class="col-md-3"><label class="small">Atas Nama</label><input class="form-control form-control-sm" name="atas_nama" required></div>
    <div class="col-md-2"><label class="small">Keterangan</label><input class="form-control form-control-sm" name="keterangan"></div>
    <div class="col-md-1"><label class="small">Urut</label><input type="number" class="form-control form-control-sm" name="urutan" value="0"></div>
    <div class="col-md-1 d-flex align-items-end"><div class="form-check"><input class="form-check-input" type="checkbox" name="aktif" id="ak" checked><label for="ak" class="form-check-label small">Aktif</label></div></div>
    <div class="col-12"><button class="btn btn-sm btn-primary"><i class="bi bi-save"></i> Simpan</button></div>
  </form>
</div></div>

<div class="card"><div class="table-responsive"><table class="table table-sm align-middle mb-0">
  <thead><tr><th>#</th><th>Bank</th><th>Nomor</th><th>Atas Nama</th><th>Keterangan</th><th>Urut</th><th>Aktif</th><th class="text-end">Aksi</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
      <form method="post" class="d-contents">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="_action" value="edit">
        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
        <td><?= (int)$r['id'] ?></td>
        <td><input class="form-control form-control-sm" name="bank" value="<?= htmlspecialchars($r['bank']) ?>"></td>
        <td><input class="form-control form-control-sm" name="nomor" value="<?= htmlspecialchars($r['nomor']) ?>"></td>
        <td><input class="form-control form-control-sm" name="atas_nama" value="<?= htmlspecialchars($r['atas_nama']) ?>"></td>
        <td><input class="form-control form-control-sm" name="keterangan" value="<?= htmlspecialchars($r['keterangan'] ?? '') ?>"></td>
        <td style="width:80px"><input type="number" class="form-control form-control-sm" name="urutan" value="<?= (int)$r['urutan'] ?>"></td>
        <td><input type="checkbox" name="aktif" <?= ($r['aktif']==='t'||$r['aktif']===true||$r['aktif']==='1')?'checked':'' ?>></td>
        <td class="text-end">
          <button class="btn btn-sm btn-outline-primary" title="Simpan"><i class="bi bi-check2"></i></button>
      </form>
          <form method="post" class="d-inline" onsubmit="return confirm('Hapus rekening ini?')">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="_action" value="delete">
            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
          </form>
        </td>
    </tr>
  <?php endforeach; if(!$rows): ?><tr><td colspan="8" class="text-center text-muted small">Belum ada rekening.</td></tr><?php endif; ?>
  </tbody>
</table></div></div>

<?php include __DIR__.'/../includes/footer.php'; ?>
