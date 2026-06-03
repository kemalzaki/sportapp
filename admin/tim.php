<?php
require __DIR__.'/../config/db.php';
require __DIR__.'/../includes/auth.php';
require __DIR__.'/../includes/helpers.php';
require_role('admin');
$pageTitle='Manajemen Tim';

// Auto-tambahkan kolom 'peran' di tim_member jika belum ada
// peran: 'pemain' (default) atau 'wasit'
try {
    db_exec("ALTER TABLE tim_member ADD COLUMN IF NOT EXISTS peran VARCHAR(20) NOT NULL DEFAULT 'pemain'");
} catch (Throwable $e) {}

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
        $tid   = (int)$_POST['tim_id'];
        $uid   = (int)$_POST['user_id'];
        $peran = ($_POST['peran'] ?? 'pemain') === 'wasit' ? 'wasit' : 'pemain';

        $tim = db_one("SELECT t.kuota,
                              (SELECT COUNT(*) FROM tim_member tm WHERE tm.tim_id=t.id AND tm.peran='pemain') AS jumlah_pemain
                       FROM tim t WHERE t.id=$1", [$tid]);
        if (!$tim) {
            $_SESSION['flash_err']='Tim tidak ditemukan.';
        } elseif ($peran === 'pemain' && !($tim['kuota']==0 || $tim['jumlah_pemain'] < $tim['kuota'])) {
            $_SESSION['flash_err']='Kuota pemain tim sudah penuh.';
        } else {
            try {
                db_exec("INSERT INTO tim_member(tim_id,user_id,peran) VALUES($1,$2,$3)", [$tid, $uid, $peran]);
            } catch (Throwable $e) {
                $_SESSION['flash_err']='Member sudah terdaftar di tim ini.';
            }
        }
    } elseif ($a==='remove_member') {
        db_exec("DELETE FROM tim_member WHERE tim_id=$1 AND user_id=$2", [(int)$_POST['tim_id'], (int)$_POST['user_id']]);
    } elseif ($a==='toggle_peran') {
        // Ubah peran existing (pemain <-> wasit)
        $tid = (int)$_POST['tim_id']; $uid = (int)$_POST['user_id'];
        $new = ($_POST['peran'] ?? 'pemain') === 'wasit' ? 'wasit' : 'pemain';
        db_exec("UPDATE tim_member SET peran=$1 WHERE tim_id=$2 AND user_id=$3", [$new, $tid, $uid]);
    }
    header('Location: tim.php'); exit;
}

$tims = db_all("SELECT t.*, u.nama AS koord, u.foto_url AS koord_foto,
                       (SELECT COUNT(*) FROM tim_member tm WHERE tm.tim_id=t.id AND tm.peran='pemain') AS jumlah_pemain,
                       (SELECT COUNT(*) FROM tim_member tm WHERE tm.tim_id=t.id AND tm.peran='wasit')  AS jumlah_wasit
                FROM tim t LEFT JOIN users u ON u.id=t.koordinator_id ORDER BY t.jenis, t.nama");
$members = db_all("SELECT id,nama,foto_url,jenis_kelamin FROM users WHERE role IN ('member','admin') ORDER BY nama");
$admins  = db_all("SELECT id,nama FROM users WHERE role='admin' ORDER BY nama");
$jenisList = array_column(db_all("SELECT nama FROM jenis_olahraga ORDER BY nama"), 'nama');
if (!$jenisList) $jenisList = ['Jogging','Badminton','Futsal','Senam','Renang','Lainnya'];

$memberByTim = [];
foreach (db_all("SELECT tm.tim_id, tm.peran, u.id, u.nama, u.foto_url FROM tim_member tm JOIN users u ON u.id=tm.user_id") as $r) {
    $memberByTim[$r['tim_id']][] = $r;
}

$err = $_SESSION['flash_err'] ?? null; unset($_SESSION['flash_err']);
include __DIR__.'/../includes/header.php'; ?>

<h2 class="mb-3"><i class="bi bi-people-fill text-primary"></i> Manajemen Tim</h2>
<p class="text-muted">Bagi member ke dalam tim per jenis olahraga. Anda dapat menambahkan <strong>pemain</strong> maupun <strong>wasit</strong> (peran wasit tidak menghitung kuota tim).</p>

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
      <input type="number" name="kuota" min="0" class="form-control" placeholder="auto">
      <small class="text-muted">Default: Badminton=4, Futsal=10</small></div>
    <div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary w-100"><i class="bi bi-plus-lg"></i> Tambah</button></div>
    <div class="col-12"><input class="form-control" name="catatan" placeholder="Catatan (opsional)"></div>
  </form>
</div></div>

<div class="row g-3">
<?php foreach($tims as $t):
    $mbrs = $memberByTim[$t['id']] ?? [];
    $pemain = array_values(array_filter($mbrs, fn($m)=>($m['peran']??'pemain')==='pemain'));
    $wasit  = array_values(array_filter($mbrs, fn($m)=>($m['peran']??'pemain')==='wasit'));
    $kuotaTxt = $t['kuota']>0 ? $t['kuota'] : '∞';
    $bisaTambahPemain = ($t['kuota']==0 || $t['jumlah_pemain'] < $t['kuota']);
?>
  <div class="col-lg-6">
    <div class="card shadow-sm h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><strong><?= htmlspecialchars($t['nama']) ?></strong>
          <span class="pill ms-1"><?= htmlspecialchars($t['jenis']) ?></span></span>
        <span>
          <span class="badge bg-primary rounded-pill" title="Pemain"><i class="bi bi-person-arms-up"></i> <?= (int)$t['jumlah_pemain'] ?>/<?= $kuotaTxt ?></span>
          <span class="badge bg-warning text-dark rounded-pill" title="Wasit"><i class="bi bi-megaphone"></i> <?= (int)$t['jumlah_wasit'] ?></span>
        </span>
      </div>
      <div class="card-body">
        <div class="small text-muted mb-2">Koordinator: <?= user_name_with_avatar($t['koord_foto'] ?? null, $t['koord'] ?? '-', false, 22) ?></div>
        <?php if($t['catatan']): ?><div class="small mb-2"><?= htmlspecialchars($t['catatan']) ?></div><?php endif; ?>

        <div class="small fw-semibold text-primary mt-2 mb-1"><i class="bi bi-person-arms-up"></i> Pemain</div>
        <ul class="list-group list-group-flush mb-2">
          <?php foreach($pemain as $m): ?>
            <li class="list-group-item d-flex justify-content-between align-items-center px-0">
              <?= user_name_with_avatar($m['foto_url'] ?? null, $m['nama'], false, 26) ?>
              <span class="d-flex gap-1">
                <form method="post" title="Jadikan wasit">
                  <input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="_action" value="toggle_peran">
                  <input type="hidden" name="tim_id" value="<?= $t['id'] ?>"><input type="hidden" name="user_id" value="<?= $m['id'] ?>">
                  <input type="hidden" name="peran" value="wasit">
                  <button class="btn btn-sm btn-outline-warning"><i class="bi bi-megaphone"></i></button>
                </form>
                <form method="post" onsubmit="return confirm('Keluarkan dari tim?')">
                  <input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="_action" value="remove_member">
                  <input type="hidden" name="tim_id" value="<?= $t['id'] ?>"><input type="hidden" name="user_id" value="<?= $m['id'] ?>">
                  <button class="btn btn-sm btn-outline-danger"><i class="bi bi-x"></i></button>
                </form>
              </span>
            </li>
          <?php endforeach; if(!$pemain): ?><li class="list-group-item text-muted text-center small px-0">Belum ada pemain.</li><?php endif; ?>
        </ul>

        <div class="small fw-semibold text-warning mt-2 mb-1"><i class="bi bi-megaphone"></i> Wasit</div>
        <ul class="list-group list-group-flush mb-2">
          <?php foreach($wasit as $m): ?>
            <li class="list-group-item d-flex justify-content-between align-items-center px-0">
              <?= user_name_with_avatar($m['foto_url'] ?? null, $m['nama'], false, 26) ?>
              <span class="d-flex gap-1">
                <form method="post" title="Jadikan pemain">
                  <input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="_action" value="toggle_peran">
                  <input type="hidden" name="tim_id" value="<?= $t['id'] ?>"><input type="hidden" name="user_id" value="<?= $m['id'] ?>">
                  <input type="hidden" name="peran" value="pemain">
                  <button class="btn btn-sm btn-outline-primary"><i class="bi bi-person"></i></button>
                </form>
                <form method="post" onsubmit="return confirm('Keluarkan dari tim?')">
                  <input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="_action" value="remove_member">
                  <input type="hidden" name="tim_id" value="<?= $t['id'] ?>"><input type="hidden" name="user_id" value="<?= $m['id'] ?>">
                  <button class="btn btn-sm btn-outline-danger"><i class="bi bi-x"></i></button>
                </form>
              </span>
            </li>
          <?php endforeach; if(!$wasit): ?><li class="list-group-item text-muted text-center small px-0">Belum ada wasit.</li><?php endif; ?>
        </ul>

        <form method="post" class="row g-2 align-items-end">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="_action" value="add_member"><input type="hidden" name="tim_id" value="<?= $t['id'] ?>">
          <div class="col-7">
            <select name="user_id" class="form-select form-select-sm" required>
              <option value="">+ Pilih Member…</option>
              <?php foreach($members as $m): ?><option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['nama']) ?><?= !empty($m['jenis_kelamin'])?' ('.$m['jenis_kelamin'].')':'' ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="col-3">
            <select name="peran" class="form-select form-select-sm">
              <option value="pemain" <?= $bisaTambahPemain?'':'disabled' ?>>Pemain</option>
              <option value="wasit">Wasit</option>
            </select>
          </div>
          <div class="col-2 d-grid">
            <button class="btn btn-sm btn-primary"><i class="bi bi-plus"></i></button>
          </div>
          <?php if(!$bisaTambahPemain): ?>
            <div class="col-12 small text-warning"><i class="bi bi-info-circle"></i> Kuota pemain penuh — masih bisa menambah wasit.</div>
          <?php endif; ?>
        </form>
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
