<?php
// Event / Tournament user-facing
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/notifications.php';
send_security_headers(); enforce_session_timeout();
require_login();
$u = current_user();
$pageTitle = 'Event & Tournament';
$pageSkeleton = 'table'; // Skeleton sesuai data: tabel event

// Helper: cek apakah event sudah lewat tanggal (Revisi 19 Juni 2026 Part Q)
function event_is_closed(array $ev): bool {
    $today = date('Y-m-d');
    // Patokan: batas_daftar > tanggal_selesai > tanggal_mulai
    $deadline = $ev['batas_daftar'] ?? null;
    if (!$deadline) $deadline = $ev['tanggal_selesai'] ?? null;
    if (!$deadline) $deadline = $ev['tanggal_mulai'] ?? null;
    if (!$deadline) return false;
    return strcmp(substr((string)$deadline,0,10), $today) < 0;
}

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    rate_limit_or_die('event:'.$u['id'], 10, 60);
    $a = $_POST['_action'] ?? '';
    $eid = (int)$_POST['event_id'];
    if ($a === 'register') {
        // Tolak pendaftaran kalau event sudah lewat tanggal
        $evChk = db_one("SELECT id, tanggal_mulai, tanggal_selesai, batas_daftar, status FROM event WHERE id=$1", [$eid]);
        if ($evChk && event_is_closed($evChk)) {
            $_SESSION['flash_err'] = 'Pendaftaran ditutup — event sudah lewat tanggal.';
            header("Location: event.php?id=$eid"); exit;
        }
        $tim = (int)($_POST['tim_id'] ?? 0) ?: null;
        try {
            db_exec("INSERT INTO event_peserta(event_id,tim_id,user_id) VALUES($1,$2,$3)", [$eid, $tim, (int)$u['id']]);
            notify((int)$u['id'], 'event', 'Pendaftaran event berhasil', 'Anda terdaftar di event #'.$eid, "/event.php?id=$eid");
        } catch (Throwable $e) {}
    }
    header("Location: event.php".($eid?"?id=$eid":'')); exit;
}

$detailId = (int)($_GET['id'] ?? 0);
$events = db_all("SELECT e.*, (SELECT COUNT(*) FROM event_peserta p WHERE p.event_id=e.id) AS jml FROM event e ORDER BY e.tanggal_mulai DESC");
$timsUser = db_all("SELECT t.id, t.nama, t.jenis FROM tim t JOIN tim_member tm ON tm.tim_id=t.id WHERE tm.user_id=$1", [(int)$u['id']]);

$detail = $detailId ? db_one("SELECT * FROM event WHERE id=$1", [$detailId]) : null;
$peserta = $detail ? db_all(
    "SELECT ep.*, u.nama, u.foto_url, t.nama AS tim_nama
     FROM (
       SELECT DISTINCT ON (COALESCE(user_id,0), COALESCE(tim_id,0)) *
       FROM event_peserta
       WHERE event_id=$1
       ORDER BY COALESCE(user_id,0), COALESCE(tim_id,0),
         CASE WHEN status IS NOT NULL AND status<>'absen' THEN 0 ELSE 1 END, id
     ) ep
     LEFT JOIN users u ON u.id=ep.user_id
     LEFT JOIN tim   t ON t.id=ep.tim_id
     ORDER BY ep.score DESC, COALESCE(u.nama, t.nama)", [$detailId]) : [];
$matches = $detail ? db_all("SELECT m.*, a.nama AS a_nama, b.nama AS b_nama FROM event_match m
                             LEFT JOIN tim a ON a.id=m.tim_a LEFT JOIN tim b ON b.id=m.tim_b
                             WHERE m.event_id=$1 ORDER BY round, id", [$detailId]) : [];
include __DIR__.'/includes/header.php';
?>
<h2 class="mb-3"><i class="bi bi-trophy-fill text-warning"></i> Event & Tournament</h2>

<div class="swipe-row mb-3">
<?php foreach($events as $e): ?>
  <div class="swipe-card">
    <?php if($e['banner_url']): ?><img src="<?= htmlspecialchars($e['banner_url']) ?>" class="w-100" style="height:120px;object-fit:cover"><?php else: ?>
      <div style="height:120px;background:linear-gradient(135deg,#f59e0b,#ef4444)"></div><?php endif; ?>
    <div class="p-3">
      <span class="pill"><?= htmlspecialchars($e['tipe']) ?></span>
      <span class="pill"><?= htmlspecialchars($e['jenis']) ?></span>
      <h6 class="mt-2 mb-1"><?= htmlspecialchars($e['nama']) ?></h6>
      <small class="text-muted"><?= htmlspecialchars($e['tanggal_mulai']) ?> · <?= (int)$e['jml'] ?> peserta</small>
      <div class="mt-2"><a class="btn btn-sm btn-primary w-100" href="?id=<?= $e['id'] ?>">Lihat detail</a></div>
    </div>
  </div>
<?php endforeach; if(!$events): ?><div class="text-muted small">Belum ada event.</div><?php endif; ?>
</div>

<?php if($detail): ?>
<div class="card shadow-sm mb-3"><div class="card-body">
  <h4 class="mb-1"><?= htmlspecialchars($detail['nama']) ?></h4>
  <div><span class="pill"><?= htmlspecialchars($detail['tipe']) ?></span>
       <span class="pill"><?= htmlspecialchars($detail['jenis']) ?></span>
       <span class="pill">Status: <?= htmlspecialchars($detail['status']) ?></span></div>
  <p class="mt-2"><?= nl2br(htmlspecialchars($detail['deskripsi'] ?? '')) ?></p>
  <div class="row small g-2">
    <div class="col-md-4"><b><i class="bi bi-calendar-event"></i> Mulai:</b> <?= htmlspecialchars($detail['tanggal_mulai']) ?><?php if(!empty($detail['jam_mulai'])): ?> · <?= substr($detail['jam_mulai'],0,5) ?><?php endif; ?></div>
    <div class="col-md-4"><b><i class="bi bi-calendar-check"></i> Selesai:</b> <?= htmlspecialchars($detail['tanggal_selesai'] ?? '-') ?><?php if(!empty($detail['jam_selesai'])): ?> · <?= substr($detail['jam_selesai'],0,5) ?><?php endif; ?></div>
    <div class="col-md-4"><b><i class="bi bi-trophy"></i> Hadiah:</b> <?= htmlspecialchars($detail['hadiah'] ?? '-') ?></div>
    <?php if(!empty($detail['lokasi'])): ?><div class="col-md-6"><b><i class="bi bi-geo-alt"></i> Lokasi:</b> <?= htmlspecialchars($detail['lokasi']) ?></div><?php endif; ?>
    <?php if(!empty($detail['batas_daftar'])): ?><div class="col-md-6"><b><i class="bi bi-hourglass-split"></i> Batas Pendaftaran:</b> <?= htmlspecialchars($detail['batas_daftar']) ?></div><?php endif; ?>
  </div>

  <?php
    // Revisi 19 Juni 2026 Part Q — sembunyikan form & tampilkan notice jika sudah lewat tanggal
    $evClosed = event_is_closed($detail);
    if (!empty($_SESSION['flash_err'])): ?>
      <div class="alert alert-danger mt-3 mb-0 py-2 small"><i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($_SESSION['flash_err']) ?></div>
      <?php unset($_SESSION['flash_err']); endif; ?>
  <?php if ($evClosed): ?>
    <div class="alert alert-warning mt-3 mb-0 py-2 small">
      <i class="bi bi-hourglass-bottom"></i> <strong>Pendaftaran ditutup.</strong>
      Event ini sudah lewat tanggal — tombol Daftar dinonaktifkan.
    </div>
  <?php elseif($detail['status']==='open'): ?>
  <form method="post" class="row g-2 mt-3 align-items-end">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <input type="hidden" name="_action" value="register">
    <input type="hidden" name="event_id" value="<?= $detail['id'] ?>">
    <div class="col-md-6"><label class="small fw-semibold">Daftar sebagai (pilih tim Anda)</label>
      <select name="tim_id" class="form-select"><option value="">— Individu —</option>
        <?php foreach($timsUser as $t): ?><option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['nama']) ?> (<?= htmlspecialchars($t['jenis']) ?>)</option><?php endforeach; ?>
      </select></div>
    <div class="col-md-3"><button class="btn btn-primary w-100"><i class="bi bi-pencil-square"></i> Daftar</button></div>
  </form>
  <?php endif; ?>
</div></div>

<?php
  $eOpts = [
    'hadir' => ['Hadir','success','bi-check-circle'],
    'telat' => ['Telat','warning','bi-clock-history'],
    'izin'  => ['Izin','info','bi-envelope'],
    'sakit' => ['Sakit','danger','bi-bandaid'],
    'absen' => ['Absen','secondary','bi-x-circle'],
  ];
  $eCnt = ['hadir'=>0,'telat'=>0,'izin'=>0,'sakit'=>0,'absen'=>0,'belum'=>0];
  foreach($peserta as $p){ $s = $p['status'] ?: 'belum'; if(isset($eCnt[$s])) $eCnt[$s]++; else $eCnt['belum']++; }
?>
<div class="card shadow-sm mb-3"><div class="card-body py-2">
  <strong class="small text-muted me-2">Ringkasan absensi:</strong>
  <?php foreach($eOpts as $k=>$o): if($eCnt[$k]): ?>
    <span class="badge bg-<?= $o[1] ?>-subtle text-<?= $o[1] ?> me-1"><i class="bi <?= $o[2] ?>"></i> <?= $o[0] ?>: <?= $eCnt[$k] ?></span>
  <?php endif; endforeach; ?>
  <?php if($eCnt['belum']): ?><span class="badge bg-light text-muted border me-1">Belum diabsen: <?= $eCnt['belum'] ?></span><?php endif; ?>
</div></div>

<div class="row g-3">
  <div class="col-lg-6"><div class="card shadow-sm"><div class="card-header"><i class="bi bi-people text-primary"></i> Peserta &amp; Kehadiran</div>
    <div class="table-responsive"><table class="table table-sm mb-0 align-middle">
      <thead class="table-light small"><tr><th>#</th><th>Peserta</th><th>Status</th><th>Catatan</th><th class="text-end">Score</th></tr></thead>
      <tbody>
      <?php $no=1; foreach($peserta as $p):
        $st = $p['status'] ?: 'belum';
        $stMap = ['hadir'=>'success','telat'=>'warning','izin'=>'info','sakit'=>'secondary','absen'=>'danger','belum'=>'light'];
        $cls = $stMap[$st] ?? 'secondary';
        $label = $p['nama'] ?: ($p['tim_nama'] ? 'Tim: '.$p['tim_nama'] : '—');
      ?>
        <tr>
          <td class="small text-muted"><?= $no++ ?></td>
          <td><?= htmlspecialchars($label) ?><?php if(!empty($p['nama']) && !empty($p['tim_nama'])): ?> <small class="text-muted">· <?= htmlspecialchars($p['tim_nama']) ?></small><?php endif; ?></td>
          <td><span class="badge bg-<?= $cls ?>-subtle text-<?= $cls==='light'?'muted':$cls ?> text-uppercase border"><?= htmlspecialchars($st) ?></span></td>
          <td class="small"><?= $p['keterangan'] ? htmlspecialchars($p['keterangan']) : '<span class="text-muted">—</span>' ?></td>
          <td class="text-end"><span class="badge bg-warning text-dark"><?= number_format((float)$p['score'],2) ?></span></td>
        </tr>
      <?php endforeach; if(!$peserta): ?>
        <tr><td colspan="5" class="text-center text-muted small py-3">Belum ada peserta.</td></tr>
      <?php endif; ?>
      </tbody>
    </table></div>
  </div></div>
  <div class="col-lg-6"><div class="card shadow-sm"><div class="card-header">Bracket / Match</div>
    <ul class="list-group list-group-flush">
    <?php foreach($matches as $m): ?>
      <li class="list-group-item d-flex justify-content-between">
        <span>R<?= $m['round'] ?>: <?= htmlspecialchars($m['a_nama'] ?? '-') ?> vs <?= htmlspecialchars($m['b_nama'] ?? '-') ?></span>
        <span class="fw-bold"><?= (int)$m['score_a'] ?> - <?= (int)$m['score_b'] ?></span>
      </li>
    <?php endforeach; if(!$matches): ?><li class="list-group-item text-muted small">Belum ada match.</li><?php endif; ?>
    </ul></div></div>
</div>
<?php endif; ?>
<?php include __DIR__.'/includes/footer.php'; ?>
