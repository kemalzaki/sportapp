<?php
require __DIR__.'/../config/db.php';
require __DIR__.'/../includes/auth.php';
require __DIR__.'/../includes/helpers.php';
require_role('admin');
$pageTitle = 'Rekap Pengeluaran Kegiatan';

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    $a = $_POST['_action'] ?? '';
    $jadwal_id = (int)($_POST['jadwal_id'] ?? 0) ?: null;
    $tanggal   = $_POST['tanggal'] ?? date('Y-m-d');
    $kategori  = substr(trim($_POST['kategori'] ?? ''),0,60);
    $judul     = substr(trim($_POST['judul'] ?? ''),0,200);
    $jumlah    = max(0,(int)($_POST['jumlah'] ?? 0));
    $catatan   = trim($_POST['catatan'] ?? '');
    $bukti     = substr(trim($_POST['bukti_url'] ?? ''),0,500);
    if ($a==='add' && $judul!=='') {
        db_exec("INSERT INTO pengeluaran_kegiatan(jadwal_id,tanggal,kategori,judul,jumlah,catatan,bukti_url,created_by)
                 VALUES($1,$2,$3,$4,$5,$6,$7,$8)",
          [$jadwal_id,$tanggal,$kategori?:null,$judul,$jumlah,$catatan?:null,$bukti?:null,(int)current_user()['id']]);
    } elseif ($a==='edit') {
        $id=(int)$_POST['id'];
        db_exec("UPDATE pengeluaran_kegiatan SET jadwal_id=$1,tanggal=$2,kategori=$3,judul=$4,jumlah=$5,catatan=$6,bukti_url=$7 WHERE id=$8",
          [$jadwal_id,$tanggal,$kategori?:null,$judul,$jumlah,$catatan?:null,$bukti?:null,$id]);
    } elseif ($a==='delete') {
        db_exec("DELETE FROM pengeluaran_kegiatan WHERE id=$1", [(int)$_POST['id']]);
    }
    header('Location: pengeluaran.php'.(isset($_GET['jadwal_id'])?'?jadwal_id='.(int)$_GET['jadwal_id']:'')); exit;
}

$filterJadwal = (int)($_GET['jadwal_id'] ?? 0);
$where = ''; $params = [];
if ($filterJadwal) { $where = "WHERE p.jadwal_id=$1"; $params=[$filterJadwal]; }
$rows = db_all("SELECT p.*, j.tanggal AS j_tgl, j.jenis AS j_jenis, j.tempat AS j_tempat, u.nama AS pencatat
                FROM pengeluaran_kegiatan p
                LEFT JOIN jadwal j ON j.id=p.jadwal_id
                LEFT JOIN users u ON u.id=p.created_by
                $where ORDER BY p.tanggal DESC, p.id DESC", $params);
$total = 0; foreach($rows as $r) $total += (int)$r['jumlah'];
$jadwalList = db_all("SELECT id, tanggal, jenis, tempat FROM jadwal ORDER BY tanggal DESC LIMIT 200");
include __DIR__.'/../includes/header.php';
?>
<h2 class="mb-3"><i class="bi bi-cash-stack text-danger"></i> Rekap Pengeluaran Kegiatan</h2>

<form method="get" class="mb-3 d-flex gap-2 align-items-end">
  <div><label class="small">Filter Jadwal</label>
    <select name="jadwal_id" class="form-select form-select-sm" onchange="this.form.submit()">
      <option value="0">-- Semua --</option>
      <?php foreach($jadwalList as $j): ?>
        <option value="<?= (int)$j['id'] ?>" <?= $filterJadwal===(int)$j['id']?'selected':'' ?>>
          <?= date('d M Y',strtotime($j['tanggal'])) ?> · <?= htmlspecialchars($j['jenis']) ?> @ <?= htmlspecialchars($j['tempat']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="ms-auto small text-muted">Total: <strong class="text-danger">Rp <?= number_format($total,0,',','.') ?></strong> · <?= count($rows) ?> entri</div>
</form>

<div class="card mb-3"><div class="card-header"><i class="bi bi-plus-circle"></i> Tambah Pengeluaran</div>
<div class="card-body">
  <form method="post" class="row g-2">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <input type="hidden" name="_action" value="add">
    <div class="col-md-3"><label class="small">Jadwal Olahraga (relasi)</label>
      <select class="form-select form-select-sm" name="jadwal_id">
        <option value="0">-- Tidak terkait jadwal --</option>
        <?php foreach($jadwalList as $j): ?>
          <option value="<?= (int)$j['id'] ?>" <?= $filterJadwal===(int)$j['id']?'selected':'' ?>>
            <?= date('d M Y',strtotime($j['tanggal'])) ?> · <?= htmlspecialchars($j['jenis']) ?> @ <?= htmlspecialchars($j['tempat']) ?>
          </option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-md-2"><label class="small">Tanggal</label>
      <input type="date" class="form-control form-control-sm" name="tanggal" value="<?= date('Y-m-d') ?>" required></div>
    <div class="col-md-2"><label class="small">Kategori</label>
      <input class="form-control form-control-sm" name="kategori" placeholder="Sewa Lapangan / Konsumsi / Shuttlecock"></div>
    <div class="col-md-3"><label class="small">Judul Pengeluaran</label>
      <input class="form-control form-control-sm" name="judul" required></div>
    <div class="col-md-2"><label class="small">Jumlah (Rp)</label>
      <input type="number" class="form-control form-control-sm" name="jumlah" min="0" step="1000" required></div>
    <div class="col-md-6"><label class="small">Catatan</label>
      <input class="form-control form-control-sm" name="catatan"></div>
    <div class="col-md-6"><label class="small">URL Bukti (opsional)</label>
      <input class="form-control form-control-sm" name="bukti_url" placeholder="https://..."></div>
    <div class="col-12"><button class="btn btn-sm btn-primary"><i class="bi bi-save"></i> Simpan</button></div>
  </form>
</div></div>

<div class="card"><div class="table-responsive"><table class="table table-sm align-middle mb-0">
  <thead><tr><th>Tgl</th><th>Jadwal</th><th>Kategori</th><th>Judul</th><th class="text-end">Jumlah</th><th>Catatan</th><th>Bukti</th><th>Pencatat</th><th class="text-end">Aksi</th></tr></thead>
  <tbody>
  <?php foreach($rows as $r): ?>
    <tr>
      <td class="small"><?= htmlspecialchars($r['tanggal']) ?></td>
      <td class="small">
        <?php if($r['jadwal_id']): ?>
          <?= date('d M',strtotime($r['j_tgl'])) ?> · <?= htmlspecialchars($r['j_jenis']) ?>
          <div class="text-muted"><?= htmlspecialchars($r['j_tempat']) ?></div>
        <?php else: ?><span class="text-muted">—</span><?php endif; ?>
      </td>
      <td><span class="badge bg-secondary-subtle text-secondary"><?= htmlspecialchars($r['kategori'] ?? '-') ?></span></td>
      <td><?= htmlspecialchars($r['judul']) ?></td>
      <td class="text-end text-danger">Rp <?= number_format((int)$r['jumlah'],0,',','.') ?></td>
      <td class="small text-muted"><?= htmlspecialchars($r['catatan'] ?? '') ?></td>
      <td><?php if(!empty($r['bukti_url'])): ?><a href="<?= htmlspecialchars($r['bukti_url']) ?>" target="_blank"><i class="bi bi-file-earmark-image"></i></a><?php else: ?>-<?php endif; ?></td>
      <td class="small"><?= htmlspecialchars($r['pencatat'] ?? '-') ?></td>
      <td class="text-end">
        <form method="post" class="d-inline" onsubmit="return confirm('Hapus pengeluaran ini?')">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
          <input type="hidden" name="_action" value="delete">
          <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
          <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
        </form>
      </td>
    </tr>
  <?php endforeach; if(!$rows): ?><tr><td colspan="9" class="text-center text-muted small">Belum ada pengeluaran.</td></tr><?php endif; ?>
  </tbody>
  <?php if($rows): ?>
  <tfoot><tr class="table-light"><th colspan="4" class="text-end">Total</th><th class="text-end text-danger">Rp <?= number_format($total,0,',','.') ?></th><th colspan="4"></th></tr></tfoot>
  <?php endif; ?>
</table></div></div>

<?php include __DIR__.'/../includes/footer.php'; ?>
