<?php
require __DIR__.'/../config/db.php';
require __DIR__.'/../includes/auth.php';
require __DIR__.'/../includes/helpers.php';
require_role('admin');
$pageTitle='Input Absensi';

$jadwalId = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    $jadwalId = (int)$_POST['jadwal_id'];
    db_exec("DELETE FROM absensi WHERE jadwal_id=$1", [$jadwalId]);
    db_exec("DELETE FROM member_eksternal WHERE jadwal_id=$1", [$jadwalId]);
    foreach (($_POST['hadir'] ?? []) as $uid => $v) {
        db_exec("INSERT INTO absensi(jadwal_id,user_id,hadir) VALUES($1,$2,$3)",
                [$jadwalId, (int)$uid, (int)$v]);
    }
    foreach (($_POST['tamu_nama'] ?? []) as $i => $n) {
        $n = trim($n); if (!$n) continue;
        $dibawa = (int)($_POST['tamu_oleh'][$i] ?? 0) ?: null;
        db_exec("INSERT INTO member_eksternal(jadwal_id,nama_tamu,dibawa_oleh_id) VALUES($1,$2,$3)",
                [$jadwalId, $n, $dibawa]);
    }
    header("Location: absensi.php?id={$jadwalId}&saved=1"); exit;
}

$jadwal=null; $members=[]; $current=[]; $tamu=[];
if ($jadwalId) {
    $jadwal  = db_one("SELECT j.*, u.nama AS koord, u.foto_url AS koord_foto FROM jadwal j LEFT JOIN users u ON u.id=j.koordinator_id WHERE j.id=$1", [$jadwalId]);
    $members = db_all("SELECT id,nama,role,foto_url,last_seen FROM users WHERE role IN ('member','admin') ORDER BY nama");
    foreach (db_all("SELECT user_id,hadir FROM absensi WHERE jadwal_id=$1", [$jadwalId]) as $a) {
        $current[$a['user_id']] = $a['hadir'];
    }
    $tamu = db_all("SELECT * FROM member_eksternal WHERE jadwal_id=$1", [$jadwalId]);
}
$jadwalList = db_all("SELECT id,tanggal,jenis,tempat FROM jadwal ORDER BY tanggal DESC");
include __DIR__.'/../includes/header.php'; ?>

<h2 class="mb-3"><i class="bi bi-check2-square text-primary"></i> Input Absensi</h2>

<form method="get" class="row g-2 mb-3"><div class="col-md-6"><select name="id" class="form-select" onchange="this.form.submit()">
  <option value="">— Pilih Jadwal —</option>
  <?php foreach($jadwalList as $j): ?>
    <option value="<?= $j['id'] ?>" <?= $j['id']==$jadwalId?'selected':'' ?>><?= $j['tanggal'] ?> — <?= $j['jenis'] ?> @ <?= htmlspecialchars($j['tempat']) ?></option>
  <?php endforeach; ?>
</select></div></form>

<?php if(isset($_GET['saved'])): ?><div class="alert alert-success py-2"><i class="bi bi-check-circle"></i> Absensi tersimpan.</div><?php endif; ?>

<?php if($jadwal): ?>
<div class="mb-3 small text-muted">Koordinator: <?= user_name_with_avatar($jadwal['koord_foto'] ?? null, $jadwal['koord'] ?? '-', false, 24) ?></div>
<form method="post">
  <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
  <input type="hidden" name="jadwal_id" value="<?= $jadwal['id'] ?>">
  <div class="row g-3">
    <div class="col-lg-7">
      <div class="card shadow-sm"><div class="card-header"><i class="bi bi-people me-1 text-primary"></i> Member Internal — centang yang hadir</div>
      <ul class="list-group list-group-flush">
        <?php foreach($members as $m): $h=$current[$m['id']]??0; ?>
        <li class="list-group-item d-flex justify-content-between align-items-center flex-wrap gap-2">
          <span><?= user_name_with_avatar($m['foto_url'] ?? null, $m['nama'], is_online($m['last_seen'] ?? null), 28) ?> <span class="role role-<?= $m['role'] ?>"><?= $m['role'] ?></span></span>
          <div class="btn-group btn-group-sm">
            <input type="radio" class="btn-check" name="hadir[<?= $m['id'] ?>]" value="1" id="h<?= $m['id'] ?>" <?= $h==1?'checked':'' ?>>
            <label class="btn btn-outline-success" for="h<?= $m['id'] ?>"><i class="bi bi-check"></i> Hadir</label>
            <input type="radio" class="btn-check" name="hadir[<?= $m['id'] ?>]" value="0" id="a<?= $m['id'] ?>" <?= $h==0?'checked':'' ?>>
            <label class="btn btn-outline-danger" for="a<?= $m['id'] ?>"><i class="bi bi-x"></i> Absen</label>
          </div>
        </li>
        <?php endforeach; ?>
      </ul></div>
    </div>
    <div class="col-lg-5">
      <div class="card shadow-sm"><div class="card-header"><i class="bi bi-person-plus me-1 text-primary"></i> Tamu Eksternal</div>
      <div class="card-body" id="tamuBox">
        <?php foreach($tamu as $t): ?>
          <div class="row g-1 mb-2"><div class="col-7"><input class="form-control" name="tamu_nama[]" value="<?= htmlspecialchars($t['nama_tamu']) ?>"></div>
            <div class="col-5"><select class="form-select" name="tamu_oleh[]"><option value="">Dibawa oleh…</option>
              <?php foreach($members as $m): ?><option value="<?= $m['id'] ?>" <?= $m['id']==$t['dibawa_oleh_id']?'selected':'' ?>><?= htmlspecialchars($m['nama']) ?></option><?php endforeach; ?>
            </select></div></div>
        <?php endforeach; ?>
        <div class="row g-1 mb-2"><div class="col-7"><input class="form-control" name="tamu_nama[]" placeholder="Nama tamu"></div>
          <div class="col-5"><select class="form-select" name="tamu_oleh[]"><option value="">Dibawa oleh…</option>
            <?php foreach($members as $m): ?><option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['nama']) ?></option><?php endforeach; ?>
          </select></div></div>
        <button type="button" class="btn btn-sm btn-outline-primary" onclick="document.getElementById('tamuBox').insertAdjacentHTML('beforeend', document.querySelector('#tamuBox .row:last-child').outerHTML)"><i class="bi bi-plus"></i> Tambah tamu</button>
      </div></div>
    </div>
  </div>
  <button class="btn btn-primary mt-3"><i class="bi bi-save"></i> Simpan Absensi</button>
</form>
<?php endif; ?>

<?php include __DIR__.'/../includes/footer.php'; ?>
