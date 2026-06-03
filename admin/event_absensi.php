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

// Pastikan tabel event_tamu tersedia (auto-migrate; tidak menghapus data).
try {
    db_exec("CREATE TABLE IF NOT EXISTS event_tamu (
        id BIGSERIAL PRIMARY KEY,
        event_id INTEGER NOT NULL REFERENCES event(id) ON DELETE CASCADE,
        nama_tamu VARCHAR(120) NOT NULL,
        dibawa_oleh_id INTEGER NULL REFERENCES users(id) ON DELETE SET NULL,
        created_at TIMESTAMP NOT NULL DEFAULT now()
    )");
    db_exec("CREATE INDEX IF NOT EXISTS idx_event_tamu_event ON event_tamu(event_id)");
} catch (Throwable $e) {}

$eventId = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    $eventId = (int)$_POST['event_id'];
    $allowed = ['hadir','izin','sakit','telat','absen'];

    // Tambah tamu eksternal (mirip absensi.php)
    if (($_POST['_action'] ?? '') === 'add_tamu') {
        $nama   = trim((string)($_POST['nama_tamu'] ?? ''));
        $dibawa = (int)($_POST['dibawa_oleh_id'] ?? 0) ?: null;
        if ($nama !== '') {
            try {
                db_exec("INSERT INTO event_tamu(event_id,nama_tamu,dibawa_oleh_id) VALUES($1,$2,$3)",
                    [$eventId, substr($nama,0,120), $dibawa]);
            } catch (Throwable $e) {}
        }
        header("Location: event_absensi.php?id={$eventId}"); exit;
    }
    if (($_POST['_action'] ?? '') === 'del_tamu') {
        $tid = (int)($_POST['tamu_id'] ?? 0);
        if ($tid) {
            try { db_exec("DELETE FROM event_tamu WHERE id=$1 AND event_id=$2", [$tid, $eventId]); } catch (Throwable $e) {}
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

$event = null; $peserta = []; $allMembers = []; $allTims = []; $tamuList = [];
if ($eventId) {
    $event = db_one("SELECT * FROM event WHERE id=$1", [$eventId]);
    // Dedupe: hanya 1 baris per (user_id,tim_id); pilih yang sudah berstatus jika ada.
    $peserta = db_all(
      "SELECT ep.id, ep.status, ep.keterangan, ep.score,
              u.id AS user_id, u.nama AS user_nama, u.foto_url,
              t.id AS tim_id, t.nama AS tim_nama, t.jenis AS tim_jenis
       FROM (
         SELECT DISTINCT ON (COALESCE(user_id,0), COALESCE(tim_id,0)) *
         FROM event_peserta
         WHERE event_id=$1
         ORDER BY COALESCE(user_id,0), COALESCE(tim_id,0),
           CASE WHEN status IS NOT NULL AND status<>'absen' THEN 0 ELSE 1 END, id
       ) ep
       LEFT JOIN users u ON u.id=ep.user_id
       LEFT JOIN tim   t ON t.id=ep.tim_id
       ORDER BY COALESCE(u.nama, t.nama)", [$eventId]);
    $allMembers = db_all("SELECT id,nama FROM users WHERE role IN ('member','admin') ORDER BY nama");
    $allTims    = db_all("SELECT id,nama,jenis FROM tim ORDER BY nama");
    try {
      $tamuList = db_all("SELECT et.*, u.nama AS dibawa_nama FROM event_tamu et
                          LEFT JOIN users u ON u.id=et.dibawa_oleh_id
                          WHERE et.event_id=$1 ORDER BY et.id", [$eventId]);
    } catch (Throwable $e) { $tamuList = []; }
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

<div class="card shadow-sm mb-4"><div class="card-header"><i class="bi bi-person-plus"></i> Tambah Tamu Eksternal</div>
<div class="card-body">
  <p class="small text-muted mb-2">Catat tamu yang bukan member (mis. tamu undangan, peserta dari luar). Untuk menambahkan member, gunakan halaman <a href="/admin/event.php">Kelola Event</a>.</p>
  <?php if($tamuList): ?>
    <div class="table-responsive mb-2"><table class="table table-sm align-middle mb-0">
      <thead class="table-light small"><tr><th>#</th><th>Nama Tamu</th><th>Dibawa Oleh</th><th class="text-end"></th></tr></thead>
      <tbody>
      <?php $tno=1; foreach($tamuList as $tm): ?>
        <tr>
          <td class="small text-muted"><?= $tno++ ?></td>
          <td><i class="bi bi-person-badge text-warning"></i> <?= htmlspecialchars($tm['nama_tamu']) ?></td>
          <td class="small"><?= $tm['dibawa_nama'] ? htmlspecialchars($tm['dibawa_nama']) : '<span class="text-muted">—</span>' ?></td>
          <td class="text-end">
            <form method="post" class="d-inline" onsubmit="return confirm('Hapus tamu ini?')">
              <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
              <input type="hidden" name="_action" value="del_tamu">
              <input type="hidden" name="event_id" value="<?= $event['id'] ?>">
              <input type="hidden" name="tamu_id" value="<?= (int)$tm['id'] ?>">
              <button class="btn btn-sm btn-link text-danger p-0"><i class="bi bi-x-circle"></i></button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody></table></div>
  <?php endif; ?>
  <form method="post" class="row g-2">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <input type="hidden" name="_action" value="add_tamu">
    <input type="hidden" name="event_id" value="<?= $event['id'] ?>">
    <div class="col-md-6">
      <label class="small fw-semibold">Nama Tamu</label>
      <input class="form-control form-control-sm" name="nama_tamu" maxlength="120" placeholder="cth: Pak Budi (tamu dari RT 03)" required>
    </div>
    <div class="col-md-4">
      <label class="small fw-semibold">Dibawa oleh (opsional)</label>
      <select name="dibawa_oleh_id" class="form-select form-select-sm">
        <option value="">— Tidak ada —</option>
        <?php foreach($allMembers as $m): ?><option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['nama']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2 d-flex align-items-end">
      <button class="btn btn-primary btn-sm w-100"><i class="bi bi-plus-lg"></i> Tambah Tamu</button>
    </div>
  </form>
</div></div>

<?php else: ?>
  <div class="alert alert-info">Pilih event di atas untuk mulai input absensi.</div>
<?php endif; ?>

<?php include __DIR__.'/../includes/footer.php'; ?>
