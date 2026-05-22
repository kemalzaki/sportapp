<?php
// Admin: generate / tampilkan QR per jadwal
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
send_security_headers(); enforce_session_timeout();
require_role('admin');
$pageTitle = 'QR Sesi';

$jadwalId = (int)($_GET['jadwal_id'] ?? 0);
$j = $jadwalId ? db_one("SELECT j.*, t.lat AS t_lat, t.lng AS t_lng FROM jadwal j LEFT JOIN tempat t ON t.id=j.tempat_id WHERE j.id=$1", [$jadwalId]) : null;

if ($_SERVER['REQUEST_METHOD']==='POST' && $j) {
    csrf_check();
    $token = bin2hex(random_bytes(16));
    $hours = max(1, min(24, (int)($_POST['hours'] ?? 3)));
    $radius = max(20, min(2000, (int)($_POST['radius'] ?? 150)));
    $lat = $_POST['lat'] !== '' ? (float)$_POST['lat'] : ($j['t_lat'] ?? null);
    $lng = $_POST['lng'] !== '' ? (float)$_POST['lng'] : ($j['t_lng'] ?? null);
    db_exec("INSERT INTO qr_tokens(jadwal_id,token,valid_from,valid_until,lat,lng,radius_meter)
             VALUES($1,$2, now(), now() + ($3 || ' hours')::interval, $4, $5, $6)",
        [$jadwalId, $token, $hours, $lat, $lng, $radius]);
    header("Location: qr_show.php?jadwal_id=$jadwalId"); exit;
}

$jadwalList = db_all("SELECT id,tanggal,jenis,tempat FROM jadwal ORDER BY tanggal DESC LIMIT 50");
$tokens = $j ? db_all("SELECT * FROM qr_tokens WHERE jadwal_id=$1 ORDER BY id DESC", [$jadwalId]) : [];
include __DIR__.'/includes/header.php';
?>
<h2 class="mb-3"><i class="bi bi-qr-code text-primary"></i> QR Check-in Sesi</h2>

<div class="row g-3">
  <div class="col-lg-4">
    <div class="card shadow-sm"><div class="card-header">Pilih Sesi</div>
    <ul class="list-group list-group-flush" style="max-height:60vh;overflow:auto;">
      <?php foreach($jadwalList as $jj): ?>
        <li class="list-group-item d-flex justify-content-between <?= $jj['id']==$jadwalId?'active':'' ?>">
          <a href="?jadwal_id=<?= $jj['id'] ?>" class="text-decoration-none <?= $jj['id']==$jadwalId?'text-white':'' ?>"><?= htmlspecialchars($jj['tanggal']) ?> · <?= htmlspecialchars($jj['jenis']) ?></a>
          <small class="<?= $jj['id']==$jadwalId?'text-white-50':'text-muted' ?>"><?= htmlspecialchars($jj['tempat']) ?></small>
        </li>
      <?php endforeach; ?>
    </ul></div>
  </div>
  <div class="col-lg-8">
    <?php if(!$j): ?><div class="alert alert-info">Pilih sesi di kiri untuk generate QR.</div>
    <?php else:
      $latest = $tokens[0] ?? null;
      $baseUrl = (isset($_SERVER['HTTPS'])?'https':'http').'://'.$_SERVER['HTTP_HOST'];
    ?>
    <div class="card shadow-sm mb-3"><div class="card-body">
      <h5><?= htmlspecialchars($j['jenis']) ?> · <?= htmlspecialchars($j['tanggal']) ?></h5>
      <div class="text-muted"><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($j['tempat']) ?></div>
      <hr>
      <form method="post" class="row g-2">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <div class="col-md-3"><label class="small fw-semibold">Berlaku (jam)</label>
          <input type="number" name="hours" class="form-control" value="3" min="1" max="24"></div>
        <div class="col-md-3"><label class="small fw-semibold">Radius (m)</label>
          <input type="number" name="radius" class="form-control" value="150" min="20" max="2000"></div>
        <div class="col-md-3"><label class="small fw-semibold">Lat (opsional)</label>
          <input name="lat" class="form-control" value="<?= htmlspecialchars($j['t_lat'] ?? '') ?>"></div>
        <div class="col-md-3"><label class="small fw-semibold">Lng (opsional)</label>
          <input name="lng" class="form-control" value="<?= htmlspecialchars($j['t_lng'] ?? '') ?>"></div>
        <div class="col-12 d-flex justify-content-end"><button class="btn btn-primary"><i class="bi bi-plus-lg"></i> Generate QR Baru</button></div>
      </form>
    </div></div>

    <?php if($latest):
      $qrUrl = $baseUrl.'/checkin.php?prefill='.urlencode($latest['token']);
      $apiQr = 'https://api.qrserver.com/v1/create-qr-code/?size=320x320&data='.urlencode($latest['token']);
    ?>
    <div class="card shadow-sm"><div class="card-body text-center">
      <h6 class="mb-2">QR Aktif</h6>
      <img src="<?= $apiQr ?>" class="img-fluid" alt="QR" style="max-width:320px">
      <div class="mt-2"><code><?= htmlspecialchars($latest['token']) ?></code></div>
      <div class="small text-muted">Berlaku sampai: <?= $latest['valid_until'] ?> · Radius <?= (int)$latest['radius_meter'] ?> m</div>
      <a class="btn btn-outline-primary mt-3" href="<?= htmlspecialchars($qrUrl) ?>" target="_blank">Buka link check-in</a>
    </div></div>
    <?php endif; ?>
    <?php endif; ?>
  </div>
</div>
<?php include __DIR__.'/includes/footer.php'; ?>
