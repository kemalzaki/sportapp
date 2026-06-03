<?php
/**
 * Input Absensi Event — meniru pola admin/absensi.php tapi sumber data dari event_peserta
 *
 * Memanfaatkan kolom yang sudah ada di event_peserta:
 *   - status     (varchar(12))   -> 'hadir','telat','izin','sakit','absen'
 *   - keterangan (text)
 * Tidak perlu skema PostgreSQL tambahan.
 */
require __DIR__.'/../config/db.php';
require __DIR__.'/../includes/auth.php';
require __DIR__.'/../includes/helpers.php';
require __DIR__.'/../includes/security.php';
require_role('admin');
$pageTitle = 'Input Absensi Event';

$eventId = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    $eventId = (int)$_POST['event_id'];
    $allowed = ['hadir','izin','sakit','telat','absen'];

    // Tambah peserta baru (cepat) dari form
    if (($_POST['_action'] ?? '') === 'add_peserta') {
        $uid = (int)($_POST['user_id'] ?? 0);
        $tid = (int)($_POST['tim_id'] ?? 0) ?: null;
        if ($uid || $tid) {
            try {
                db_exec("INSERT INTO event_peserta(event_id,user_id,tim_id) VALUES($1,$2,$3)",
                    [$eventId, $uid ?: null, $tid]);
            } catch (Throwable $e) {}
        }
        header("Location: event_absensi.php?id={$eventId}"); exit;
    }

    // Simpan status absensi untuk semua peserta
    foreach (($_POST['status'] ?? []) as $pid => $st) {
        $pid = (int)$pid;
        $st = in_array($st, $allowed, true) ? $st : 'absen';
        $ket = trim((string)($_POST['keterangan'][$pid] ?? ''));
        db_exec("UPDATE event_peserta SET status=$1, keterangan=$2 WHERE id=$3 AND event_id=$4",
            [$st, $ket ?: null, $pid, $eventId]);
    }
    header("Location: event_absensi.php?id={$eventId}&saved=1"); exit;
}

$event = null; $peserta = []; $allMembers = []; $allTims = [];
if ($eventId) {
    $event = db_one("SELECT * FROM event WHERE id=$1", [$eventId]);
    $peserta = db_all("SELECT ep.id, ep.status, ep.keterangan, ep.score,
                              u.id AS user_id, u.nama AS user_nama, u.foto_url,
                              t.id AS tim_id, t.nama AS tim_nama, t.jenis AS tim_jenis
                       FROM event_peserta ep
                       LEFT JOIN users u ON u.id=ep.user_id
                       LEFT JOIN tim   t ON t.id=ep.tim_id
                       WHERE ep.event_id=$1
                       ORDER BY COALESCE(u.nama, t.nama)", [$eventId]);
    $allMembers = db_all("SELECT id,nama FROM users WHERE role IN ('member','admin') ORDER BY nama");
    $allTims    = db_all("SELECT id,nama,jenis FROM tim ORDER BY nama");
}
$eventList = db_all("SELECT id,nama,tanggal_mulai,jenis FROM event ORDER BY tanggal_mulai DESC");
include __DIR__.'/../includes/header.php'; ?>

<h2 class="mb-3"><i class="bi bi-clipboard2-check text-warning"></i> Input Absensi Event</h2>
<p class="text-muted small mb-3">Catat kehadiran peserta event/kegiatan. Mirip dengan absensi jadwal latihan di halaman utama, namun untuk daftar peserta event.</p>

<form method="get" class="row g-2 mb-3"><div class="col-md-8">
  <select name="id" class="form-select" onchange="this.form.submit()">
    <option value="">— Pilih Event —</option>
    <?php foreach($eventList as $e): ?>
      <option value="<?= $e['id'] ?>" <?= $e['id']==$eventId?'selected':'' ?>>
        <?= htmlspecialchars($e['tanggal_mulai']) ?> — <?= htmlspecialchars($e['nama']) ?> (<?= htmlspecialchars($e['jenis']) ?>)
      </option>
    <?php endforeach; ?>
  </select>
</div>
<?php if($eventId): ?>
<div class="col-md-4 d-flex gap-2">
  <a href="/event.php?id=<?= $eventId ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-eye"></i> Lihat Event</a>
  <a href="/admin/event.php" class="btn btn-outline-primary btn-sm"><i class="bi bi-gear"></i> Kelola Event</a>
</div>
<?php endif; ?>
</form>

<?php if(isset($_GET['saved'])): ?><div class="alert alert-success py-2"><i class="bi bi-check-circle"></i> Absensi event tersimpan.</div><?php endif; ?>

<?php if($event):
  $opts = [
    'hadir' => ['Hadir','success','bi-check-circle'],
    'telat' => ['Telat','warning','bi-clock-history'],
    'izin'  => ['Izin','info','bi-envelope'],
    'sakit' => ['Sakit','danger','bi-bandaid'],
    'absen' => ['Absen','secondary','bi-x-circle'],
  ];
  $cnt = ['hadir'=>0,'telat'=>0,'izin'=>0,'sakit'=>0,'absen'=>0];
  foreach($peserta as $p){ $s = $p['status'] ?: 'absen'; if(isset($cnt[$s])) $cnt[$s]++; }
?>

<div class="card shadow-sm mb-3"><div class="card-body">
  <div class="d-flex justify-content-between align-items-start flex-wrap">
    <div>
      <h4 class="mb-1"><?= htmlspecialchars($event['nama']) ?></h4>
      <div class="small text-muted">
        <i class="bi bi-calendar"></i> <?= htmlspecialchars($event['tanggal_mulai']) ?>
        <?php if($event['lokasi']): ?> · <i class="bi bi-geo-alt"></i> <?= htmlspecialchars($event['lokasi']) ?><?php endif; ?>
        · <span class="pill"><?= htmlspecialchars($event['jenis']) ?></span>
        <span class="pill"><?= htmlspecialchars($event['tipe']) ?></span>
      </div>
    </div>
    <div>
      <?php foreach($opts as $k=>$o): if($cnt[$k]): ?>
        <span class="badge bg-<?= $o[1] ?>-subtle text-<?= $o[1] ?>"><?= $o[0] ?>: <?= $cnt[$k] ?></span>
      <?php endif; endforeach; ?>
    </div>
  </div>
</div></div>

<form method="post">
  <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
  <input type="hidden" name="event_id" value="<?= $event['id'] ?>">

  <div class="card shadow-sm mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span><i class="bi bi-people"></i> Daftar Peserta (<?= count($peserta) ?>)</span>
      <button class="btn btn-sm btn-success"><i class="bi bi-save"></i> Simpan Absensi</button>
    </div>
    <div class="table-responsive">
      <table class="table table-sm mb-0 align-middle">
        <thead class="table-light small">
          <tr>
            <th style="width:32px">#</th>
            <th>Peserta</th>
            <th>Status</th>
            <th>Keterangan</th>
          </tr>
        </thead>
        <tbody>
        <?php if(!$peserta): ?>
          <tr><td colspan="4" class="text-center text-muted small py-4">Belum ada peserta. Tambahkan di bawah.</td></tr>
        <?php else: $no=1; foreach($peserta as $p):
          $cur = $p['status'] ?: 'absen';
          $label = $p['user_nama'] ?? ($p['tim_nama'] ? 'Tim: '.$p['tim_nama'] : '—');
        ?>
          <tr>
            <td class="small text-muted"><?= $no++ ?></td>
            <td>
              <?php if($p['user_nama']): ?>
                <?= user_name_with_avatar($p['foto_url'] ?? null, $p['user_nama'], false, 26) ?>
                <?php if($p['tim_nama']): ?><small class="text-muted">· <?= htmlspecialchars($p['tim_nama']) ?></small><?php endif; ?>
              <?php else: ?>
                <i class="bi bi-people-fill text-warning"></i> <?= htmlspecialchars($label) ?>
              <?php endif; ?>
            </td>
            <td>
              <div class="btn-group btn-group-sm" role="group">
              <?php foreach($opts as $k=>$o):
                $id = 's_'.$p['id'].'_'.$k;
              ?>
                <input type="radio" class="btn-check" name="status[<?= $p['id'] ?>]" id="<?= $id ?>" value="<?= $k ?>" <?= $cur===$k?'checked':'' ?>>
                <label class="btn btn-outline-<?= $o[1] ?>" for="<?= $id ?>" title="<?= $o[0] ?>"><i class="bi <?= $o[2] ?>"></i><span class="d-none d-md-inline ms-1"><?= $o[0] ?></span></label>
              <?php endforeach; ?>
              </div>
            </td>
            <td>
              <input class="form-control form-control-sm" name="keterangan[<?= $p['id'] ?>]" value="<?= htmlspecialchars($p['keterangan'] ?? '') ?>" placeholder="(opsional)">
            </td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
    <?php if($peserta): ?>
    <div class="card-footer text-end">
      <button class="btn btn-success"><i class="bi bi-save"></i> Simpan Absensi</button>
    </div>
    <?php endif; ?>
  </div>
</form>

<div class="card shadow-sm mb-4"><div class="card-header"><i class="bi bi-person-plus"></i> Tambah Peserta Cepat</div>
<div class="card-body">
  <form method="post" class="row g-2">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <input type="hidden" name="_action" value="add_peserta">
    <input type="hidden" name="event_id" value="<?= $event['id'] ?>">
    <div class="col-md-5">
      <label class="small fw-semibold">Member (individu)</label>
      <select name="user_id" class="form-select form-select-sm">
        <option value="">— Pilih member —</option>
        <?php foreach($allMembers as $m): ?><option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['nama']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-5">
      <label class="small fw-semibold">Atau Tim</label>
      <select name="tim_id" class="form-select form-select-sm">
        <option value="">— Pilih tim —</option>
        <?php foreach($allTims as $t): ?><option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['nama']) ?> (<?= htmlspecialchars($t['jenis']) ?>)</option><?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2 d-flex align-items-end">
      <button class="btn btn-primary btn-sm w-100"><i class="bi bi-plus-lg"></i> Tambah</button>
    </div>
  </form>
</div></div>

<?php else: ?>
  <div class="alert alert-info">Pilih event di atas untuk mulai input absensi.</div>
<?php endif; ?>

<?php include __DIR__.'/../includes/footer.php'; ?>
