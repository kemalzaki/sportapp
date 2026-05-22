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
        db_exec("UPDATE tempat SET nama=$1, alamat=$2, harga_lapang=$3, harga_per_jam=$4,
                   harga_tiket=$5, harga_parkir=$6, status_booking=$7, catatan=$8,
                   pic_user_id=$9, kontak_wa=$10, jenis_id=$11
                 WHERE id=$12",
            [trim($_POST['nama']), trim($_POST['alamat'] ?? ''),
             (float)($_POST['harga_lapang'] ?? 0), (float)($_POST['harga_per_jam'] ?? 0),
             (float)($_POST['harga_tiket'] ?? 0), (float)($_POST['harga_parkir'] ?? 0),
             $_POST['status_booking'] ?? 'tersedia', trim($_POST['catatan'] ?? ''),
             ($_POST['pic_user_id'] ?? '') !== '' ? (int)$_POST['pic_user_id'] : null,
             trim($_POST['kontak_wa'] ?? '') ?: null,
             ($_POST['jenis_id'] ?? '') !== '' ? (int)$_POST['jenis_id'] : null,
             (int)$_POST['id']]);
    } else {
        db_exec("INSERT INTO tempat(nama,alamat,harga_lapang,harga_per_jam,harga_tiket,harga_parkir,status_booking,catatan,pic_user_id,kontak_wa,jenis_id)
                 VALUES($1,$2,$3,$4,$5,$6,$7,$8,$9,$10,$11)",
            [trim($_POST['nama']), trim($_POST['alamat'] ?? ''),
             (float)($_POST['harga_lapang'] ?? 0), (float)($_POST['harga_per_jam'] ?? 0),
             (float)($_POST['harga_tiket'] ?? 0), (float)($_POST['harga_parkir'] ?? 0),
             $_POST['status_booking'] ?? 'tersedia', trim($_POST['catatan'] ?? ''),
             ($_POST['pic_user_id'] ?? '') !== '' ? (int)$_POST['pic_user_id'] : null,
             trim($_POST['kontak_wa'] ?? '') ?: null,
             ($_POST['jenis_id'] ?? '') !== '' ? (int)$_POST['jenis_id'] : null]);
    }
    header('Location: tempat.php'); exit;
}

// ===== Filter & Sort =====
$q       = trim($_GET['q'] ?? '');
$fStatus = $_GET['status'] ?? '';
$fJenis  = $_GET['jenis']  ?? '';
$sort    = $_GET['sort']   ?? 'nama';
$dir     = strtolower($_GET['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

$allowSort = ['nama'=>'t.nama','status'=>'t.status_booking','harga_lapang'=>'t.harga_lapang',
              'harga_per_jam'=>'t.harga_per_jam','harga_tiket'=>'t.harga_tiket','harga_parkir'=>'t.harga_parkir',
              'pic'=>'u.nama','jenis'=>'jo.nama','created_at'=>'t.created_at'];
$sortSql = $allowSort[$sort] ?? 't.nama';

$where = []; $params = []; $i = 1;
if ($q !== '')      { $where[] = "(t.nama ILIKE \$$i OR t.alamat ILIKE \$$i)"; $params[] = "%$q%"; $i++; }
if ($fStatus !== ''){ $where[] = "t.status_booking = \$$i"; $params[] = $fStatus; $i++; }
if ($fJenis !== '') { $where[] = "t.jenis_id = \$$i"; $params[] = (int)$fJenis; $i++; }
$whereSql = $where ? ('WHERE '.implode(' AND ',$where)) : '';

$rows = db_all("SELECT t.*, u.nama AS pic_nama, u.foto_url AS pic_foto, jo.nama AS jenis_nama
                FROM tempat t
                LEFT JOIN users u ON u.id = t.pic_user_id
                LEFT JOIN jenis_olahraga jo ON jo.id = t.jenis_id
                $whereSql
                ORDER BY $sortSql $dir NULLS LAST", $params);

$admins = db_all("SELECT id, nama FROM users WHERE role='admin' ORDER BY nama");
$jenisList = db_all("SELECT id, nama FROM jenis_olahraga ORDER BY nama");
$statuses = ['tersedia','booked','renovasi','tutup'];

function sort_link($key, $label, $sort, $dir){
    $nextDir = ($sort===$key && $dir==='asc') ? 'desc' : 'asc';
    $arrow = $sort===$key ? ($dir==='asc' ? ' <i class="bi bi-caret-up-fill small"></i>' : ' <i class="bi bi-caret-down-fill small"></i>') : '';
    $qs = $_GET; $qs['sort']=$key; $qs['dir']=$nextDir;
    return '<a class="text-decoration-none text-dark" href="?'.http_build_query($qs).'">'.$label.$arrow.'</a>';
}

include __DIR__.'/../includes/header.php'; ?>

<h2 class="mb-3"><i class="bi bi-geo-alt text-primary"></i> Manajemen Tempat</h2>
<p class="text-muted">Daftar lapangan / GOR beserta detail biaya, PIC admin, kontak WA, dan jenis olahraga.</p>

<div class="card shadow-sm mb-3"><div class="card-header"><i class="bi bi-plus-circle me-1 text-primary"></i> Tambah Tempat</div>
<div class="card-body">
  <form method="post" class="row g-2">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <input type="hidden" name="_action" value="create">
    <div class="col-md-3"><label class="form-label small fw-semibold">Nama Tempat</label><input class="form-control" name="nama" required></div>
    <div class="col-md-3"><label class="form-label small fw-semibold">Alamat</label><input class="form-control" name="alamat"></div>
    <div class="col-md-3"><label class="form-label small fw-semibold">PIC (Admin)</label>
      <select class="form-select" name="pic_user_id">
        <option value="">— pilih PIC —</option>
        <?php foreach($admins as $ad): ?><option value="<?= (int)$ad['id'] ?>"><?= htmlspecialchars($ad['nama']) ?></option><?php endforeach; ?>
      </select></div>
    <div class="col-md-3"><label class="form-label small fw-semibold"><i class="bi bi-whatsapp text-success"></i> Kontak WA</label>
      <input class="form-control" name="kontak_wa" placeholder="cth: 08123456789"></div>

    <div class="col-md-3"><label class="form-label small fw-semibold">Jenis Olahraga</label>
      <select class="form-select" name="jenis_id">
        <option value="">— pilih jenis —</option>
        <?php foreach($jenisList as $jn): ?><option value="<?= (int)$jn['id'] ?>"><?= htmlspecialchars($jn['nama']) ?></option><?php endforeach; ?>
      </select></div>
    <div class="col-md-2"><label class="form-label small fw-semibold">Harga Lapang</label><input type="number" step="0.01" min="0" class="form-control" name="harga_lapang" value="0"></div>
    <div class="col-md-2"><label class="form-label small fw-semibold">Harga / Jam</label><input type="number" step="0.01" min="0" class="form-control" name="harga_per_jam" value="0"></div>
    <div class="col-md-2"><label class="form-label small fw-semibold">Harga Tiket</label><input type="number" step="0.01" min="0" class="form-control" name="harga_tiket" value="0"></div>
    <div class="col-md-2"><label class="form-label small fw-semibold">Harga Parkir</label><input type="number" step="0.01" min="0" class="form-control" name="harga_parkir" value="0"></div>

    <div class="col-md-3"><label class="form-label small fw-semibold">Status</label>
      <select class="form-select" name="status_booking">
        <?php foreach($statuses as $s): ?><option><?= $s ?></option><?php endforeach; ?>
      </select></div>
    <div class="col-md-9"><label class="form-label small fw-semibold">Catatan</label><input class="form-control" name="catatan" placeholder="cth: butuh DP, jam buka, dll"></div>
    <div class="col-12"><button class="btn btn-primary"><i class="bi bi-plus-lg"></i> Tambah</button></div>
  </form>
</div></div>

<!-- ===== Filter & Sort ===== -->
<div class="card shadow-sm mb-3"><div class="card-body">
  <form class="row g-2 align-items-end" method="get">
    <div class="col-md-4"><label class="form-label small fw-semibold">Cari nama / alamat</label>
      <input class="form-control form-control-sm" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="🔍 ketik kata kunci...">
    </div>
    <div class="col-md-3"><label class="form-label small fw-semibold">Status</label>
      <select class="form-select form-select-sm" name="status">
        <option value="">Semua</option>
        <?php foreach($statuses as $s): ?><option value="<?= $s ?>" <?= $fStatus===$s?'selected':'' ?>><?= $s ?></option><?php endforeach; ?>
      </select></div>
    <div class="col-md-3"><label class="form-label small fw-semibold">Jenis Olahraga</label>
      <select class="form-select form-select-sm" name="jenis">
        <option value="">Semua</option>
        <?php foreach($jenisList as $jn): ?><option value="<?= (int)$jn['id'] ?>" <?= (string)$fJenis===(string)$jn['id']?'selected':'' ?>><?= htmlspecialchars($jn['nama']) ?></option><?php endforeach; ?>
      </select></div>
    <div class="col-md-2 d-flex gap-1">
      <button class="btn btn-sm btn-primary w-100"><i class="bi bi-funnel"></i> Filter</button>
      <a class="btn btn-sm btn-outline-secondary" href="tempat.php" title="Reset"><i class="bi bi-x-lg"></i></a>
    </div>
  </form>
</div></div>

<div class="card shadow-sm"><div class="table-responsive"><table class="table table-hover mb-0 align-middle">
  <thead><tr>
    <th>#</th>
    <th><?= sort_link('nama','Nama',$sort,$dir) ?></th>
    <th>Alamat</th>
    <th><?= sort_link('jenis','Jenis',$sort,$dir) ?></th>
    <th><?= sort_link('pic','PIC',$sort,$dir) ?></th>
    <th>Kontak</th>
    <th class="text-end"><?= sort_link('harga_lapang','Lapang',$sort,$dir) ?></th>
    <th class="text-end"><?= sort_link('harga_per_jam','/Jam',$sort,$dir) ?></th>
    <th class="text-end"><?= sort_link('harga_tiket','Tiket',$sort,$dir) ?></th>
    <th class="text-end"><?= sort_link('harga_parkir','Parkir',$sort,$dir) ?></th>
    <th><?= sort_link('status','Status',$sort,$dir) ?></th>
    <th class="text-end">Aksi</th>
  </tr></thead><tbody>
  <?php foreach($rows as $i=>$r):
    $waDigits = preg_replace('/\D+/', '', $r['kontak_wa'] ?? '');
    if ($waDigits && str_starts_with($waDigits, '0')) $waDigits = '62'.substr($waDigits,1);
  ?>
    <tr>
      <td class="text-muted"><?= $i+1 ?></td>
      <td class="fw-semibold"><?= htmlspecialchars($r['nama']) ?>
        <?php if(!empty($r['catatan'])): ?><br><small class="text-muted"><?= htmlspecialchars($r['catatan']) ?></small><?php endif; ?>
      </td>
      <td class="text-muted small"><?= htmlspecialchars($r['alamat'] ?? '') ?: '—' ?></td>
      <td><?= $r['jenis_nama'] ? '<span class="pill">'.htmlspecialchars($r['jenis_nama']).'</span>' : '<span class="text-muted small">—</span>' ?></td>
      <td><?= $r['pic_nama'] ? user_name_with_avatar($r['pic_foto'] ?? null, $r['pic_nama'], false, 24) : '<span class="text-muted small">—</span>' ?></td>
      <td>
        <?php if($waDigits): ?>
          <a href="https://wa.me/<?= htmlspecialchars($waDigits) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-success">
            <i class="bi bi-whatsapp"></i> <?= htmlspecialchars($r['kontak_wa']) ?>
          </a>
        <?php else: ?><span class="text-muted small">—</span><?php endif; ?>
      </td>
      <td class="text-end small">Rp <?= number_format((float)$r['harga_lapang'],0,',','.') ?></td>
      <td class="text-end small">Rp <?= number_format((float)$r['harga_per_jam'],0,',','.') ?></td>
      <td class="text-end small">Rp <?= number_format((float)($r['harga_tiket'] ?? 0),0,',','.') ?></td>
      <td class="text-end small">Rp <?= number_format((float)($r['harga_parkir'] ?? 0),0,',','.') ?></td>
      <td>
        <?php $st=$r['status_booking']; $cls=$st==='tersedia'?'success':($st==='booked'?'warning':'secondary'); ?>
        <span class="badge bg-<?= $cls ?>"><?= htmlspecialchars($st) ?></span>
      </td>
      <td class="text-end text-nowrap">
        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#tpE<?= $r['id'] ?>"><i class="bi bi-pencil"></i></button>
        <form method="post" class="d-inline" onsubmit="return confirm('Hapus tempat ini?')">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="_action" value="delete"><input type="hidden" name="id" value="<?= $r['id'] ?>">
          <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
        </form>
      </td>
    </tr>
  <?php endforeach; if(!$rows): ?><tr><td colspan="12" class="text-center text-muted py-3">Tidak ada tempat sesuai filter.</td></tr><?php endif; ?>
  </tbody></table></div></div>

<?php foreach($rows as $r): ?>
<div class="modal fade" id="tpE<?= $r['id'] ?>" tabindex="-1"><div class="modal-dialog modal-dialog-centered modal-lg"><form method="post" class="modal-content">
  <input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="_action" value="edit"><input type="hidden" name="id" value="<?= $r['id'] ?>">
  <div class="modal-header"><h5 class="modal-title"><i class="bi bi-pencil-square"></i> Edit Tempat</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <div class="row g-2">
      <div class="col-md-6"><label class="form-label small fw-semibold">Nama</label><input class="form-control" name="nama" value="<?= htmlspecialchars($r['nama']) ?>" required></div>
      <div class="col-md-6"><label class="form-label small fw-semibold">Alamat</label><input class="form-control" name="alamat" value="<?= htmlspecialchars($r['alamat'] ?? '') ?>"></div>
      <div class="col-md-6"><label class="form-label small fw-semibold">PIC (Admin)</label>
        <select class="form-select" name="pic_user_id">
          <option value="">— pilih PIC —</option>
          <?php foreach($admins as $ad): ?><option value="<?= (int)$ad['id'] ?>" <?= (string)$r['pic_user_id']===(string)$ad['id']?'selected':'' ?>><?= htmlspecialchars($ad['nama']) ?></option><?php endforeach; ?>
        </select></div>
      <div class="col-md-6"><label class="form-label small fw-semibold"><i class="bi bi-whatsapp text-success"></i> Kontak WA</label>
        <input class="form-control" name="kontak_wa" value="<?= htmlspecialchars($r['kontak_wa'] ?? '') ?>" placeholder="cth: 08123456789"></div>
      <div class="col-md-6"><label class="form-label small fw-semibold">Jenis Olahraga</label>
        <select class="form-select" name="jenis_id">
          <option value="">— pilih jenis —</option>
          <?php foreach($jenisList as $jn): ?><option value="<?= (int)$jn['id'] ?>" <?= (string)$r['jenis_id']===(string)$jn['id']?'selected':'' ?>><?= htmlspecialchars($jn['nama']) ?></option><?php endforeach; ?>
        </select></div>
      <div class="col-md-6"><label class="form-label small fw-semibold">Status</label>
        <select class="form-select" name="status_booking">
          <?php foreach($statuses as $s): ?><option <?= $s===$r['status_booking']?'selected':'' ?>><?= $s ?></option><?php endforeach; ?>
        </select></div>
      <div class="col-md-3"><label class="form-label small fw-semibold">Harga Lapang</label><input type="number" step="0.01" min="0" class="form-control" name="harga_lapang" value="<?= htmlspecialchars($r['harga_lapang']) ?>"></div>
      <div class="col-md-3"><label class="form-label small fw-semibold">Harga / Jam</label><input type="number" step="0.01" min="0" class="form-control" name="harga_per_jam" value="<?= htmlspecialchars($r['harga_per_jam']) ?>"></div>
      <div class="col-md-3"><label class="form-label small fw-semibold">Harga Tiket</label><input type="number" step="0.01" min="0" class="form-control" name="harga_tiket" value="<?= htmlspecialchars($r['harga_tiket'] ?? 0) ?>"></div>
      <div class="col-md-3"><label class="form-label small fw-semibold">Harga Parkir</label><input type="number" step="0.01" min="0" class="form-control" name="harga_parkir" value="<?= htmlspecialchars($r['harga_parkir'] ?? 0) ?>"></div>
      <div class="col-12"><label class="form-label small fw-semibold">Catatan</label><textarea class="form-control" name="catatan" rows="2"><?= htmlspecialchars($r['catatan'] ?? '') ?></textarea></div>
    </div>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button><button class="btn btn-primary"><i class="bi bi-save"></i> Simpan</button></div>
</form></div></div>
<?php endforeach; ?>

<?php include __DIR__.'/../includes/footer.php'; ?>
