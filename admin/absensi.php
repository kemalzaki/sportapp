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
    $allowed = ['hadir','izin','sakit','telat','absen'];
    foreach (($_POST['status'] ?? []) as $uid => $st) {
        $st = in_array($st, $allowed, true) ? $st : 'absen';
        $hadir = ($st === 'hadir' || $st === 'telat') ? 1 : 0;
        $ket = trim((string)($_POST['keterangan'][$uid] ?? ''));
        
        db_exec("INSERT INTO absensi(jadwal_id,user_id,hadir,status,keterangan) VALUES($1,$2,$3,$4,$5)",
                [$jadwalId, (int)$uid, $hadir, $st, $ket ?: null]);
    }
    foreach (($_POST['tamu_nama'] ?? []) as $i => $n) {
        $n = trim($n); if (!$n) continue;
        $dibawa = (int)($_POST['tamu_oleh'][$i] ?? 0) ?: null;
        db_exec("INSERT INTO member_eksternal(jadwal_id,nama_tamu,dibawa_oleh_id) VALUES($1,$2,$3)",
                [$jadwalId, $n, $dibawa]);
    }
    header("Location: absensi.php?id={$jadwalId}&saved=1"); exit;
}

$jadwal=null; $members=[]; $current=[]; $currentKet=[]; $tamu=[];
if ($jadwalId) {
    $jadwal  = db_one("SELECT j.*, u.nama AS koord, u.foto_url AS koord_foto FROM jadwal j LEFT JOIN users u ON u.id=j.koordinator_id WHERE j.id=$1", [$jadwalId]);
    $members = db_all("SELECT id,nama,role,foto_url,last_seen FROM users WHERE role IN ('member','admin') ORDER BY nama");
    foreach (db_all("SELECT user_id,hadir,status,keterangan FROM absensi WHERE jadwal_id=$1", [$jadwalId]) as $a) {
        $current[$a['user_id']] = $a['status'] ?: ($a['hadir']==1?'hadir':'absen');
        $currentKet[$a['user_id']] = $a['keterangan'] ?? '';
    }
    $tamu = db_all("SELECT * FROM member_eksternal WHERE jadwal_id=$1", [$jadwalId]);
}
$jadwalList = db_all("SELECT id,tanggal,jenis,tempat FROM jadwal ORDER BY tanggal DESC");
include __DIR__.'/../includes/header.php'; ?>

<h2 class="mb-3"><i class="bi bi-check2-square text-primary"></i> Input Absensi (RSVP)</h2>

<form method="get" class="row g-2 mb-3"><div class="col-md-8"><select name="id" class="form-select" onchange="this.form.submit()">
  <option value="">— Pilih Jadwal —</option>
  <?php foreach($jadwalList as $j): ?>
    <option value="<?= $j['id'] ?>" <?= $j['id']==$jadwalId?'selected':'' ?>><?= $j['tanggal'] ?> — <?= $j['jenis'] ?> @ <?= htmlspecialchars($j['tempat']) ?></option>
  <?php endforeach; ?>
</select></div>
<?php if($jadwalId): ?><div class="col-md-4"><a href="/export.php?type=absensi&jadwal_id=<?= $jadwalId ?>&format=csv" class="btn btn-outline-success btn-sm"><i class="bi bi-file-earmark-spreadsheet"></i> Export Excel</a>
<a href="/export.php?type=absensi&jadwal_id=<?= $jadwalId ?>&format=pdf" class="btn btn-outline-danger btn-sm"><i class="bi bi-file-earmark-pdf"></i> Export PDF</a></div><?php endif; ?>
</form>

<?php if(isset($_GET['saved'])): ?><div class="alert alert-success py-2"><i class="bi bi-check-circle"></i> Absensi tersimpan.</div><?php endif; ?>

<?php if($jadwal): ?>
<div class="mb-3 small text-muted">Koordinator: <?= user_name_with_avatar($jadwal['koord_foto'] ?? null, $jadwal['koord'] ?? '-', false, 24) ?></div>
<form method="post">
  <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
  <input type="hidden" name="jadwal_id" value="<?= $jadwal['id'] ?>">
  <div class="row g-3">
    <div class="col-lg-8">
      <div class="card shadow-sm"><div class="card-header"><i class="bi bi-people me-1 text-primary"></i> Member Internal — pilih status RSVP</div>
      <ul class="list-group list-group-flush">
        <?php
          $opts = [
            'hadir' => ['Hadir','success','bi-check-circle'],
            'telat' => ['Telat','warning','bi-clock-history'],
            'izin'  => ['Izin','info','bi-envelope'],
            'sakit' => ['Sakit','danger','bi-bandaid'],
            'absen' => ['Absen','secondary','bi-x-circle'],
          ];
          foreach($members as $m): $st = $current[$m['id']] ?? 'absen'; $ket = $currentKet[$m['id']] ?? '';
        ?>
        <li class="list-group-item">
          <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span><?= user_name_with_avatar($m['foto_url'] ?? null, $m['nama'], is_online($m['last_seen'] ?? null), 28) ?> <span class="role role-<?= $m['role'] ?>"><?= $m['role'] ?></span></span>
            <div class="btn-group btn-group-sm flex-wrap">
              <?php foreach($opts as $k=>$o):
                $rid = "rsvp_{$m['id']}_$k"; ?>
                <input type="radio" class="btn-check" name="status[<?= $m['id'] ?>]" value="<?= $k ?>" id="<?= $rid ?>" <?= $st===$k?'checked':'' ?>>
                <label class="btn btn-outline-<?= $o[1] ?>" for="<?= $rid ?>"><i class="bi <?= $o[2] ?>"></i> <?= $o[0] ?></label>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="mt-2">
            <input type="text" class="form-control form-control-sm" name="keterangan[<?= $m['id'] ?>]" placeholder="Catatan (opsional) — mis. cedera, alasan izin/sakit, dll." value="<?= htmlspecialchars($ket) ?>">
          </div>
        </li>
        <?php endforeach; ?>
      </ul></div>
    </div>
    <div class="col-lg-4">
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
