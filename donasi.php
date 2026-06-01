<?php
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
@require_once __DIR__.'/includes/islami_helpers.php';
send_security_headers();
// Boleh diakses guest (sesuai revisi: dibuka ke umum dari index.php)
$pageTitle = 'Donasi Kegiatan';
$pageSkeleton = 'list'; // Skeleton sesuai data: daftar rekening/donasi
$u = current_user();

// Pastikan tabel donasi_krb tetap dipakai sebagai log donasi (kompatibilitas data lama)
@pg_query(db(), "CREATE TABLE IF NOT EXISTS donasi_krb (
    id SERIAL PRIMARY KEY,
    user_id INT,
    nama VARCHAR(160),
    jumlah BIGINT NOT NULL DEFAULT 0,
    metode VARCHAR(40),
    bank VARCHAR(60),
    no_ref VARCHAR(80),
    bukti_path TEXT,
    catatan TEXT,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT now()
)");

$rekening = db_all("SELECT * FROM donasi_rekening WHERE aktif=true ORDER BY urutan, id");

$upDir = __DIR__.'/uploads/donasi';
if (!is_dir($upDir)) @mkdir($upDir, 0775, true);

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    $a = $_POST['_action'] ?? '';
    if ($a === 'donate') {
        $jml  = max(1000, (int)($_POST['jumlah'] ?? 0));
        $bank = substr(trim($_POST['bank'] ?? ''), 0, 40);
        $noRef= substr(trim($_POST['no_ref'] ?? ''), 0, 60);
        $cat  = substr(trim($_POST['catatan'] ?? ''), 0, 500);
        $nama = substr(trim($_POST['nama'] ?? ($u['nama'] ?? 'Donatur')), 0, 120);

        $bukti = null;
        if (!empty($_FILES['bukti']['name']) && is_uploaded_file($_FILES['bukti']['tmp_name'])) {
            $ext = strtolower(pathinfo($_FILES['bukti']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','webp'], true) && $_FILES['bukti']['size'] <= 5*1024*1024) {
                $safe = 'donasi_'.($u['id'] ?? 'g').'_'.time().'_'.bin2hex(random_bytes(3)).'.'.$ext;
                if (move_uploaded_file($_FILES['bukti']['tmp_name'], $upDir.'/'.$safe)) {
                    $bukti = '/uploads/donasi/'.$safe;
                }
            }
        }
        db_exec("INSERT INTO donasi_krb(user_id,nama,jumlah,metode,bank,no_ref,bukti_path,catatan,status)
                 VALUES($1,$2,$3,'transfer',$4,$5,$6,$7,'pending')",
          [$u['id'] ?? null, $nama, $jml, $bank, $noRef, $bukti, $cat]);
        if ($u) { @islami_touch_streak((int)$u['id'], 'sedekah'); }
        $_SESSION['flash'] = 'Jazakallahu khairan 🤍 Donasi Anda akan diverifikasi admin.';
    } elseif ($a === 'verify' && $u && $u['role']==='admin') {
        db_exec("UPDATE donasi_krb SET status=$1 WHERE id=$2",
          [in_array($_POST['status'] ?? '', ['verified','rejected','pending'], true) ? $_POST['status'] : 'pending', (int)$_POST['id']]);
    } elseif ($a === 'delete' && $u && $u['role']==='admin') {
        db_exec("DELETE FROM donasi_krb WHERE id=$1", [(int)$_POST['id']]);
    }
    header('Location: /donasi.php'); exit;
}

$page = max(1, (int)($_GET['page'] ?? 1));
$per  = 10; $off = ($page-1)*$per;
$total = (int) db_val("SELECT COUNT(*) FROM donasi_krb");
$totalVerified = (int) db_val("SELECT COALESCE(SUM(jumlah),0) FROM donasi_krb WHERE status='verified'");
$donasi = db_all("SELECT d.*, u.nama AS u_nama FROM donasi_krb d LEFT JOIN users u ON u.id=d.user_id
                  ORDER BY d.created_at DESC LIMIT $per OFFSET $off");
$totalPages = max(1, (int)ceil($total/$per));

include __DIR__.'/includes/header.php';
?>
<?php if (!empty($_SESSION['flash'])): ?><div class="alert alert-success py-2 small"><?= htmlspecialchars($_SESSION['flash']) ?></div><?php unset($_SESSION['flash']); endif; ?>

<h4 class="mb-1"><i class="bi bi-heart-fill text-danger"></i> Donasi Kegiatan</h4>
<p class="text-muted small mb-3">Dukung kegiatan olahraga, sosial &amp; komunitas dengan berdonasi melalui rekening resmi di bawah ini.</p>

<div class="row g-3 mb-3">
  <div class="col-md-7">
    <div class="card border-success"><div class="card-header bg-success text-white"><i class="bi bi-bank"></i> Nomor Rekening Donasi Kegiatan</div>
    <div class="card-body">
      <?php if (!$rekening): ?>
        <p class="small text-muted mb-0">Rekening belum dikonfigurasi admin.</p>
      <?php else: ?>
      <table class="table table-sm mb-2">
        <thead><tr><th>Bank / Channel</th><th>Nomor</th><th>Atas Nama</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rekening as $i=>$rek): ?>
          <tr>
            <td><strong><?= htmlspecialchars($rek['bank']) ?></strong>
              <?php if(!empty($rek['keterangan'])): ?><div class="small text-muted"><?= htmlspecialchars($rek['keterangan']) ?></div><?php endif; ?></td>
            <td><code><?= htmlspecialchars($rek['nomor']) ?></code></td>
            <td><?= htmlspecialchars($rek['atas_nama']) ?></td>
            <td><button class="btn btn-sm btn-outline-success" type="button"
                       onclick="navigator.clipboard.writeText('<?= htmlspecialchars($rek['nomor']) ?>');this.innerText='✓ Disalin'">
                       <i class="bi bi-clipboard"></i> Salin</button></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div></div>
  </div>
  <div class="col-md-5">
    <div class="card border-warning h-100"><div class="card-body text-center">
      <div class="small text-muted">Total Donasi Terverifikasi</div>
      <div class="display-6 text-success">Rp <?= number_format($totalVerified,0,',','.') ?></div>
      <div class="small text-muted"><?= $total ?> transaksi tercatat</div>
    </div></div>
  </div>
</div>

<div class="card mb-3"><div class="card-header"><i class="bi bi-pencil-square text-primary"></i> Konfirmasi Donasi</div>
<div class="card-body">
  <form method="post" enctype="multipart/form-data" class="row g-2">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <input type="hidden" name="_action" value="donate">
    <div class="col-md-4"><label class="small">Nama Donatur</label>
      <input class="form-control" name="nama" value="<?= htmlspecialchars($u['nama'] ?? '') ?>" required></div>
    <div class="col-md-3"><label class="small">Jumlah (Rp)</label>
      <input class="form-control" type="number" name="jumlah" min="1000" step="1000" required placeholder="50000"></div>
    <div class="col-md-3"><label class="small">Transfer ke</label>
      <select class="form-select" name="bank">
        <?php foreach ($rekening as $rek): ?>
          <option value="<?= htmlspecialchars($rek['bank']) ?>"><?= htmlspecialchars($rek['bank']) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-md-2"><label class="small">No. Referensi</label>
      <input class="form-control" name="no_ref" maxlength="60" placeholder="opsional"></div>
    <div class="col-md-6"><label class="small">Bukti Transfer (JPG/PNG/WEBP, maks 5MB)</label>
      <input class="form-control" type="file" name="bukti" accept="image/jpeg,image/png,image/webp"></div>
    <div class="col-md-6"><label class="small">Catatan / Doa</label>
      <input class="form-control" name="catatan" maxlength="500"></div>
    <div class="col-12"><button class="btn btn-success"><i class="bi bi-send"></i> Kirim Konfirmasi Donasi</button></div>
  </form>
</div></div>

<h6 class="mt-4 mb-2"><i class="bi bi-receipt"></i> Riwayat Donasi</h6>
<div class="card"><div class="table-responsive"><table class="table mb-0 align-middle">
  <thead><tr><th>Tgl</th><th>Nama</th><th class="text-end">Jumlah</th><th>Bank</th><th>Status</th><?php if($u && $u['role']==='admin'): ?><th>Aksi</th><?php endif; ?></tr></thead>
  <tbody>
  <?php foreach ($donasi as $d):
    $stColor = ['verified'=>'success','pending'=>'warning','rejected'=>'danger'][$d['status']] ?? 'secondary'; ?>
    <tr>
      <td class="small"><?= htmlspecialchars(substr($d['created_at'],0,16)) ?></td>
      <td><?= htmlspecialchars($d['nama']) ?></td>
      <td class="text-end">Rp <?= number_format((int)$d['jumlah'],0,',','.') ?></td>
      <td><?= htmlspecialchars($d['bank'] ?? '-') ?></td>
      <td><span class="badge bg-<?= $stColor ?>"><?= htmlspecialchars($d['status']) ?></span></td>
      <?php if ($u && $u['role']==='admin'): ?>
      <td>
        <form method="post" class="d-flex gap-1">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
          <input type="hidden" name="_action" value="verify">
          <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
          <select name="status" class="form-select form-select-sm">
            <?php foreach (['pending','verified','rejected'] as $st): ?>
              <option value="<?= $st ?>" <?= $d['status']===$st?'selected':'' ?>><?= $st ?></option>
            <?php endforeach; ?>
          </select>
          <button class="btn btn-sm btn-outline-primary">OK</button>
        </form>
      </td>
      <?php endif; ?>
    </tr>
  <?php endforeach; if(!$donasi): ?><tr><td colspan="6" class="text-center text-muted">Belum ada donasi.</td></tr><?php endif; ?>
  </tbody>
</table></div></div>

<?php if ($totalPages>1): ?>
<nav class="mt-3"><ul class="pagination pagination-sm">
  <?php for ($p=1;$p<=$totalPages;$p++): ?>
    <li class="page-item <?= $p===$page?'active':'' ?>"><a class="page-link" href="?page=<?= $p ?>"><?= $p ?></a></li>
  <?php endfor; ?>
</ul></nav>
<?php endif; ?>

<?php include __DIR__.'/includes/footer.php'; ?>
