<?php
require __DIR__.'/../config/db.php';
require __DIR__.'/../includes/auth.php';
require __DIR__.'/../includes/helpers.php';
require_role('admin');
$pageTitle='Manajemen Tim';

// Default kuota per jenis
function default_kuota(string $jenis): int {
    $map = ['Badminton'=>4,'Futsal'=>10,'Jogging'=>0,'Senam'=>0,'Renang'=>0,'Lainnya'=>0];
    return $map[$jenis] ?? 0;
}

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    $a = $_POST['_action'] ?? '';
    if ($a==='create') {
        $jenis = $_POST['jenis'] ?? 'Lainnya';
        $kuota = (int)($_POST['kuota'] ?? 0) ?: default_kuota($jenis);
        $koord = (int)($_POST['koordinator_id'] ?? 0) ?: null;
        db_exec("INSERT INTO tim(nama,jenis,koordinator_id,kuota,catatan) VALUES($1,$2,$3,$4,$5)",
            [trim($_POST['nama']), $jenis, $koord, $kuota, trim($_POST['catatan'] ?? '')]);
    } elseif ($a==='edit') {
        $jenis = $_POST['jenis'] ?? 'Lainnya';
        $kuota = (int)($_POST['kuota'] ?? 0) ?: default_kuota($jenis);
        $koord = (int)($_POST['koordinator_id'] ?? 0) ?: null;
        db_exec("UPDATE tim SET nama=$1, jenis=$2, koordinator_id=$3, kuota=$4, catatan=$5 WHERE id=$6",
            [trim($_POST['nama']), $jenis, $koord, $kuota, trim($_POST['catatan'] ?? ''), (int)$_POST['id']]);
    } elseif ($a==='delete') {
        db_exec("DELETE FROM tim WHERE id=$1", [(int)$_POST['id']]);
    } elseif ($a==='add_member') {
        $tid = (int)$_POST['tim_id'];
        $uid = (int)$_POST['user_id'];
        $tim = db_one("SELECT t.kuota, (SELECT COUNT(*) FROM tim_member tm WHERE tm.tim_id=t.id) AS jumlah FROM tim t WHERE t.id=$1", [$tid]);
        if ($tim && ($tim['kuota']==0 || $tim['jumlah'] < $tim['kuota'])) {
            try { db_exec("INSERT INTO tim_member(tim_id,user_id) VALUES($1,$2)", [$tid, $uid]); }
            catch (Throwable $e) { $_SESSION['flash_err']='Member sudah ada di tim ini.'; }
        } else { $_SESSION['flash_err']='Kuota tim sudah penuh.'; }
    } elseif ($a==='remove_member') {
        db_exec("DELETE FROM tim_member WHERE tim_id=$1 AND user_id=$2", [(int)$_POST['tim_id'], (int)$_POST['user_id']]);
    }
    header('Location: tim.php'); exit;
}

$tims = db_all("SELECT t.*, u.nama AS koord, u.foto_url AS koord_foto,
                       (SELECT COUNT(*) FROM tim_member tm WHERE tm.tim_id=t.id) AS jumlah
                FROM tim t LEFT JOIN users u ON u.id=t.koordinator_id ORDER BY t.jenis, t.nama");
$members = db_all("SELECT id,nama,foto_url,jenis_kelamin FROM users WHERE role IN ('member','admin') ORDER BY nama");
$admins  = db_all("SELECT id,nama FROM users WHERE role='admin' ORDER BY nama");
$jenisList = array_column(db_all("SELECT nama FROM jenis_olahraga ORDER BY nama"), 'nama');
if (!$jenisList) $jenisList = ['Jogging','Badminton','Futsal','Senam','Renang','Lainnya'];

$memberByTim = [];
foreach (db_all("SELECT tm.tim_id, u.id, u.nama, u.foto_url FROM tim_member tm JOIN users u ON u.id=tm.user_id") as $r) {
    $memberByTim[$r['tim_id']][] = $r;
}

$err = $_SESSION['flash_err'] ?? null; unset($_SESSION['flash_err']);
include __DIR__.'/../includes/header.php'; ?>

<h2 class="mb-3"><i class="bi bi-people-fill text-primary"></i> Manajemen Tim</h2>
<p class="text-muted">Bagi member ke dalam tim per jenis olahraga. Kuota disarankan sesuai jenis (mis. Badminton ganda = 4 orang per tim).</p>

<?php if($err): ?><div class="alert alert-danger py-2"><?= htmlspecialchars($err) ?></div><?php endif; ?>

<div class="card shadow-sm mb-3"><div class="card-header"><i class="bi bi-plus-circle me-1 text-primary"></i> Tambah Tim</div>
<div class="card-body">
  <form method="post" class="row g-2">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <input type="hidden" name="_action" value="create">
    <div class="col-md-3"><label class="form-label small fw-semibold">Nama Tim</label><input class="form-control" name="nama" required placeholder="cth: Tim Ganda A"></div>
    <div class="col-md-2"><label class="form-label small fw-semibold">Jenis</label>
      <select name="jenis" class="form-select">
        <?php foreach($jenisList as $j): ?><option><?= htmlspecialchars($j) ?></option><?php endforeach; ?>
      </select></div>
    <div class="col-md-3"><label class="form-label small fw-semibold">Koordinator Tim</label>
      <select name="koordinator_id" class="form-select">
        <option value="">— Pilih (admin) —</option>
        <?php foreach($admins as $a): ?><option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['nama']) ?></option><?php endforeach; ?>
      </select></div>
    <div class="col-md-2"><label class="form-label small fw-semibold">Kuota</label>
      <input type="number" name="kuota" min="0" class="form-control" placeholder="auto" >
      <small class="text-muted">Kosongkan untuk default (Badminton=4, Futsal=10)</small></div>
    <div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary w-100"><i class="bi bi-plus-lg"></i> Tambah</button></div>
    <div class="col-12"><input class="form-control" name="catatan" placeholder="Catatan (opsional)"></div>
  </form>
</div></div>

<div class="row g-3">
<?php foreach($tims as $t): $mbrs = $memberByTim[$t['id']] ?? []; $kuotaTxt = $t['kuota']>0 ? $t['kuota'] : '∞'; ?>
  <div class="col-lg-6">
    <div class="card shadow-sm h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><strong><?= htmlspecialchars($t['nama']) ?></strong>
          <span class="pill ms-1"><?= htmlspecialchars($t['jenis']) ?></span></span>
        <span class="badge bg-primary rounded-pill"><?= (int)$t['jumlah'] ?>/<?= $kuotaTxt ?></span>
      </div>
      <div class="card-body">
        <div class="small text-muted mb-2">Koordinator: <?= user_name_with_avatar($t['koord_foto'] ?? null, $t['koord'] ?? '-', false, 22) ?></div>
        <?php if($t['catatan']): ?><div class="small mb-2"><?= htmlspecialchars($t['catatan']) ?></div><?php endif; ?>
        <ul class="list-group list-group-flush mb-2">
          <?php foreach($mbrs as $m): ?>
            <li class="list-group-item d-flex justify-content-between align-items-center px-0">
              <?= user_name_with_avatar($m['foto_url'] ?? null, $m['nama'], false, 26) ?>
              <form method="post" onsubmit="return confirm('Keluarkan dari tim?')">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="_action" value="remove_member">
                <input type="hidden" name="tim_id" value="<?= $t['id'] ?>"><input type="hidden" name="user_id" value="<?= $m['id'] ?>">
                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-x"></i></button>
              </form>
            </li>
          <?php endforeach; if(!$mbrs): ?><li class="list-group-item text-muted text-center small px-0">Belum ada member.</li><?php endif; ?>
        </ul>
        <?php if($t['kuota']==0 || $t['jumlah'] < $t['kuota']): ?>
        <form method="post" class="d-flex gap-2">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="_action" value="add_member"><input type="hidden" name="tim_id" value="<?= $t['id'] ?>">
          <select name="user_id" class="form-select form-select-sm" required>
            <option value="">+ Tambah Member…</option>
            <?php foreach($members as $m): ?><option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['nama']) ?><?= !empty($m['jenis_kelamin'])?' ('.$m['jenis_kelamin'].')':'' ?></option><?php endforeach; ?>
          </select>
          <button class="btn btn-sm btn-primary"><i class="bi bi-plus"></i></button>
        </form>
        <?php else: ?><div class="text-success small"><i class="bi bi-check-circle"></i> Kuota penuh.</div><?php endif; ?>
      </div>
      <div class="card-footer d-flex justify-content-end gap-2">
        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#tEdit<?= $t['id'] ?>"><i class="bi bi-pencil"></i> Edit</button>
        <form method="post" onsubmit="return confirm('Hapus tim ini?')">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="_action" value="delete"><input type="hidden" name="id" value="<?= $t['id'] ?>">
          <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
        </form>
      </div>
    </div>
  </div>
<?php endforeach; if(!$tims): ?><div class="col-12"><div class="alert alert-info">Belum ada tim. Tambahkan tim pertama di atas.</div></div><?php endif; ?>
</div>

<?php foreach($tims as $t): ?>
<div class="modal fade" id="tEdit<?= $t['id'] ?>" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form method="post" class="modal-content">
  <input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="_action" value="edit"><input type="hidden" name="id" value="<?= $t['id'] ?>">
  <div class="modal-header"><h5 class="modal-title"><i class="bi bi-pencil-square"></i> Edit Tim</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <div class="mb-2"><label class="form-label small fw-semibold">Nama Tim</label><input class="form-control" name="nama" value="<?= htmlspecialchars($t['nama']) ?>" required></div>
    <div class="row g-2">
      <div class="col-md-6"><label class="form-label small fw-semibold">Jenis</label>
        <select name="jenis" class="form-select">
          <?php foreach($jenisList as $j): ?><option <?= $j===$t['jenis']?'selected':'' ?>><?= htmlspecialchars($j) ?></option><?php endforeach; ?>
        </select></div>
      <div class="col-md-6"><label class="form-label small fw-semibold">Koordinator</label>
        <select name="koordinator_id" class="form-select">
          <option value="">— Pilih —</option>
          <?php foreach($admins as $a): ?><option value="<?= $a['id'] ?>" <?= $a['id']==$t['koordinator_id']?'selected':'' ?>><?= htmlspecialchars($a['nama']) ?></option><?php endforeach; ?>
        </select></div>
      <div class="col-md-6"><label class="form-label small fw-semibold">Kuota</label><input type="number" min="0" name="kuota" class="form-control" value="<?= (int)$t['kuota'] ?>"></div>
      <div class="col-12"><label class="form-label small fw-semibold">Catatan</label><input class="form-control" name="catatan" value="<?= htmlspecialchars($t['catatan'] ?? '') ?>"></div>
    </div>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button><button class="btn btn-primary"><i class="bi bi-save"></i> Simpan</button></div>
</form></div></div>
<?php endforeach; ?>

<?php include __DIR__.'/../includes/footer.php'; ?>
