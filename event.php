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

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    rate_limit_or_die('event:'.$u['id'], 10, 60);
    $a = $_POST['_action'] ?? '';
    $eid = (int)$_POST['event_id'];
    if ($a === 'register') {
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
$peserta = $detail ? db_all("SELECT ep.*, u.nama, u.foto_url, t.nama AS tim_nama
                             FROM event_peserta ep
                             LEFT JOIN users u ON u.id=ep.user_id
                             LEFT JOIN tim t ON t.id=ep.tim_id
                             WHERE ep.event_id=$1 ORDER BY ep.score DESC", [$detailId]) : [];
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
  <div class="row small">
    <div class="col-md-4"><b>Mulai:</b> <?= htmlspecialchars($detail['tanggal_mulai']) ?></div>
    <div class="col-md-4"><b>Selesai:</b> <?= htmlspecialchars($detail['tanggal_selesai'] ?? '-') ?></div>
    <div class="col-md-4"><b>Hadiah:</b> <?= htmlspecialchars($detail['hadiah'] ?? '-') ?></div>
  </div>

  <?php if($detail['status']==='open'): ?>
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

<div class="row g-3">
  <div class="col-lg-6"><div class="card shadow-sm"><div class="card-header">Peserta & Score</div>
    <ol class="list-group list-group-flush list-group-numbered">
    <?php foreach($peserta as $p): ?>
      <li class="list-group-item d-flex justify-content-between">
        <span><?= htmlspecialchars($p['tim_nama'] ?? $p['nama'] ?? '—') ?></span>
        <span class="badge bg-warning text-dark"><?= number_format((float)$p['score'],2) ?></span>
      </li>
    <?php endforeach; if(!$peserta): ?><li class="list-group-item text-muted small">Belum ada peserta.</li><?php endif; ?>
    </ol></div></div>
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
