<?php
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
require __DIR__.'/includes/islami_helpers.php';
send_security_headers(); require_login();
$pageTitle = 'Donasi Yayasan KRB';
$u = current_user();

// ====== Data Yayasan KRB (silakan sesuaikan di sini) ======
$YAYASAN = [
    'nama'   => 'Yayasan KRB',
    'alamat' => 'Sekretariat Yayasan KRB',
    'kontak' => '+62 8xx-xxxx-xxxx',
    'email'  => 'donasi@yayasan-krb.org',
    'rekening' => [
        ['bank'=>'BCA',     'no'=>'1234567890', 'an'=>'Yayasan KRB'],
        ['bank'=>'Mandiri', 'no'=>'9876543210', 'an'=>'Yayasan KRB'],
        ['bank'=>'BSI',     'no'=>'7000123456', 'an'=>'Yayasan KRB'],
        ['bank'=>'DANA',    'no'=>'081234567890','an'=>'Yayasan KRB'],
        ['bank'=>'OVO',     'no'=>'081234567890','an'=>'Yayasan KRB'],
    ],
];

$upDir = __DIR__.'/uploads/donasi';
if (!is_dir($upDir)) @mkdir($upDir, 0775, true);

if ($_SERVER['REQUEST_METHOD']==='POST' && $u) {
    csrf_check();
    $a = $_POST['_action'] ?? '';
    if ($a === 'donate') {
        $jml  = max(1000, (int)($_POST['jumlah'] ?? 0));
        $bank = substr(trim($_POST['bank'] ?? ''), 0, 40);
        $noRef= substr(trim($_POST['no_ref'] ?? ''), 0, 60);
        $cat  = substr(trim($_POST['catatan'] ?? ''), 0, 500);
        $nama = substr(trim($_POST['nama'] ?? $u['nama']), 0, 120);

        $bukti = null;
        if (!empty($_FILES['bukti']['name']) && is_uploaded_file($_FILES['bukti']['tmp_name'])) {
            $ext = strtolower(pathinfo($_FILES['bukti']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','pdf'], true) && $_FILES['bukti']['size'] <= 5*1024*1024) {
                $name = 'donasi_'.time().'_'.bin2hex(random_bytes(4)).'.'.$ext;
                if (move_uploaded_file($_FILES['bukti']['tmp_name'], $upDir.'/'.$name)) {
                    $bukti = '/uploads/donasi/'.$name;
                }
            }
        }

        db_exec("INSERT INTO donasi_krb(user_id,nama,jumlah,metode,bank,no_ref,bukti_path,catatan,status)
                 VALUES($1,$2,$3,'transfer',$4,$5,$6,$7,'pending')",
          [(int)$u['id'], $nama, $jml, $bank, $noRef, $bukti, $cat]);
        islami_touch_streak((int)$u['id'], 'sedekah');
        $_SESSION['flash'] = 'Jazakallahu khairan 🤍 Donasi Anda akan diverifikasi admin.';
    } elseif ($a === 'verify' && $u['role']==='admin') {
        db_exec("UPDATE donasi_krb SET status=$1 WHERE id=$2",
          [in_array($_POST['status'] ?? '', ['verified','rejected','pending'], true) ? $_POST['status'] : 'pending', (int)$_POST['id']]);
    } elseif ($a === 'delete' && $u['role']==='admin') {
        db_exec("DELETE FROM donasi_krb WHERE id=$1", [(int)$_POST['id']]);
    }
    header('Location: /donasi.php'); exit;
}

$page = max(1, (int)($_GET['page'] ?? 1));
$per  = 10;
$off  = ($page-1)*$per;
$total = (int) db_val("SELECT COUNT(*) FROM donasi_krb");
$totalVerified = (int) db_val("SELECT COALESCE(SUM(jumlah),0) FROM donasi_krb WHERE status='verified'");
$donasi = db_all("SELECT d.*, u.nama AS u_nama FROM donasi_krb d LEFT JOIN users u ON u.id=d.user_id
                  ORDER BY d.created_at DESC LIMIT $per OFFSET $off");
$totalPages = max(1, (int)ceil($total/$per));

include __DIR__.'/includes/header.php';
?>
<?php if (!empty($_SESSION['flash'])): ?><div class="alert alert-success py-2 small"><?= htmlspecialchars($_SESSION['flash']) ?></div><?php unset($_SESSION['flash']); endif; ?>

<h4 class="mb-1"><i class="bi bi-heart-fill text-danger"></i> Donasi ke <?= htmlspecialchars($YAYASAN['nama']) ?></h4>
<p class="text-muted small mb-3">Tunjukkan kepedulianmu dengan berdonasi untuk kegiatan dakwah, sosial & pendidikan Yayasan KRB.</p>

<div class="row g-3 mb-3">
  <div class="col-md-7">
    <div class="card border-success"><div class="card-header bg-success text-white"><i class="bi bi-bank"></i> Nomor Rekening Donasi</div>
    <div class="card-body">
      <table class="table table-sm mb-2">
        <thead><tr><th>Bank / Channel</th><th>Nomor</th><th>Atas Nama</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($YAYASAN['rekening'] as $i=>$rek): ?>
          <tr>
            <td><strong><?= htmlspecialchars($rek['bank']) ?></strong></td>
            <td><code id="rek<?= $i ?>"><?= htmlspecialchars($rek['no']) ?></code></td>
            <td><?= htmlspecialchars($rek['an']) ?></td>
            <td><button class="btn btn-sm btn-outline-success" type="button" onclick="navigator.clipboard.writeText('<?= htmlspecialchars($rek['no']) ?>');this.innerText='✓ Disalin'"><i class="bi bi-clipboard"></i> Salin</button></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <div class="small text-muted">Kontak: <?= htmlspecialchars($YAYASAN['kontak']) ?> · <?= htmlspecialchars($YAYASAN['email']) ?></div>
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
      <input class="form-control" name="nama" value="<?= htmlspecialchars($u['nama']) ?>" required></div>
    <div class="col-md-3"><label class="small">Jumlah (Rp)</label>
      <input class="form-control" type="number" name="jumlah" min="1000" step="1000" required placeholder="50000"></div>
    <div class="col-md-3"><label class="small">Transfer ke</label>
      <select class="form-select" name="bank">
        <?php foreach ($YAYASAN['rekening'] as $rek): ?>
          <option value="<?= htmlspecialchars($rek['bank']) ?>"><?= htmlspecialchars($rek['bank']) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-md-2"><label class="small">No. Referensi</label>
      <input class="form-control" name="no_ref" maxlength="60" placeholder="opsional"></div>
    <div class="col-md-6"><label class="small">Bukti Transfer (jpg/png/pdf, maks 5MB)</label>
      <input class="form-control" type="file" name="bukti" accept="image/*,application/pdf"></div>
    <div class="col-md-6"><label class="small">Catatan / Doa</label>
      <input class="form-control" name="catatan" maxlength="500"></div>
    <div class="col-12"><button class="btn btn-success"><i class="bi bi-send"></i> Kirim Konfirmasi Donasi</button></div>
  </form>
</div></div>

<h6 class="mt-4 mb-2"><i class="bi bi-receipt"></i> Riwayat Donasi</h6>
<div class="card"><div class="table-responsive"><table class="table mb-0 align-middle">
  <thead><tr><th>Tgl</th><th>Nama</th><th class="text-end">Jumlah</th><th>Bank</th><th>No. Ref</th><th>Bukti</th><th>Status</th><?php if($u['role']==='admin'): ?><th>Aksi</th><?php endif; ?></tr></thead>
  <tbody>
  <?php foreach ($donasi as $d):
    $stColor = ['verified'=>'success','pending'=>'warning','rejected'=>'danger'][$d['status']] ?? 'secondary'; ?>
    <tr>
      <td class="small"><?= htmlspecialchars(substr($d['created_at'],0,16)) ?></td>
      <td><?= htmlspecialchars($d['nama']) ?></td>
      <td class="text-end">Rp <?= number_format((int)$d['jumlah'],0,',','.') ?></td>
      <td><?= htmlspecialchars($d['bank'] ?? '-') ?></td>
      <td class="small"><?= htmlspecialchars($d['no_ref'] ?? '-') ?></td>
      <td><?php if (!empty($d['bukti_path'])): ?><a href="<?= htmlspecialchars($d['bukti_path']) ?>" target="_blank"><i class="bi bi-file-earmark-image"></i></a><?php else: ?>-<?php endif; ?></td>
      <td><span class="badge bg-<?= $stColor ?>"><?= htmlspecialchars($d['status']) ?></span></td>
      <?php if ($u['role']==='admin'): ?>
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
  <?php endforeach; if(!$donasi): ?><tr><td colspan="8" class="text-center text-muted">Belum ada donasi.</td></tr><?php endif; ?>
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
