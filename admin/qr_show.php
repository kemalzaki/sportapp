<?php
// Admin: generate / tampilkan QR per jadwal
require __DIR__.'/../config/db.php';
require __DIR__.'/../includes/auth.php';
require __DIR__.'/../includes/security.php';
send_security_headers(); enforce_session_timeout();
require_role('admin');
$pageTitle = 'QR Sesi';

$jadwalId = (int)($_GET['jadwal_id'] ?? 0);
$j = $jadwalId ? db_one("SELECT j.*, t.lat AS t_lat, t.lng AS t_lng FROM jadwal j LEFT JOIN tempat t ON t.id=j.tempat_id WHERE j.id=$1", [$jadwalId]) : null;

$flashErr = null;
if ($_SERVER['REQUEST_METHOD']==='POST' && $j) {
    csrf_check();
    // Cegah generate QR untuk sesi yang tanggalnya sudah lewat
    if (!empty($j['tanggal']) && strtotime($j['tanggal']) < strtotime(date('Y-m-d'))) {
        $flashErr = 'Tidak bisa generate QR: tanggal sesi sudah lewat ('.htmlspecialchars($j['tanggal']).').';
    } else {
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
}

$jadwalList = db_all("SELECT id,tanggal,jenis,tempat FROM jadwal ORDER BY tanggal DESC LIMIT 50");
$tokens = $j ? db_all("SELECT * FROM qr_tokens WHERE jadwal_id=$1 ORDER BY id DESC", [$jadwalId]) : [];
include __DIR__.'/../includes/header.php';
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
      <?php if($flashErr): ?><div class="alert alert-danger py-2 mt-2 mb-0"><i class="bi bi-exclamation-triangle"></i> <?= $flashErr ?></div><?php endif; ?>
      <?php $isExpired = !empty($j['tanggal']) && strtotime($j['tanggal']) < strtotime(date('Y-m-d')); ?>
      <?php if($isExpired): ?>
        <div class="alert alert-warning mt-3 mb-0"><i class="bi bi-clock-history"></i> Tanggal sesi ini sudah lewat — tidak bisa generate QR baru.</div>
      <?php else: ?>
      <hr>
      <form method="post" class="row g-2" id="qrForm">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <div class="col-md-4"><label class="small fw-semibold">Berlaku (jam)</label>
          <input type="number" name="hours" class="form-control" value="3" min="1" max="24"></div>
        <div class="col-md-4"><label class="small fw-semibold">Radius (m)</label>
          <input type="number" name="radius" class="form-control" value="150" min="20" max="2000"></div>
        <div class="col-md-4"><label class="small fw-semibold">Koordinat</label>
          <div class="input-group">
            <input name="lat" id="lat" class="form-control" placeholder="lat" value="<?= htmlspecialchars($j['t_lat'] ?? '') ?>" readonly>
            <input name="lng" id="lng" class="form-control" placeholder="lng" value="<?= htmlspecialchars($j['t_lng'] ?? '') ?>" readonly>
          </div>
        </div>
        <div class="col-12">
          <label class="small fw-semibold">Pin lokasi di peta (klik / drag marker)</label>
          <div id="map" style="height:320px;border-radius:8px;border:1px solid #ddd;"></div>
          <small class="text-muted">Tip: klik peta untuk memindahkan pin.</small>
        </div>
        <div class="col-12 d-flex justify-content-end"><button class="btn btn-primary"><i class="bi bi-plus-lg"></i> Generate QR Baru</button></div>
      </form>
      <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
      <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
      <script>
        document.addEventListener('DOMContentLoaded', function(){
          var lat = parseFloat(document.getElementById('lat').value) || -6.2;
          var lng = parseFloat(document.getElementById('lng').value) || 106.816666;
          var map = L.map('map').setView([lat,lng], 14);
          L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19, attribution:'© OpenStreetMap'}).addTo(map);
          var marker = L.marker([lat,lng], {draggable:true}).addTo(map);
          function setLL(ll){
            document.getElementById('lat').value = ll.lat.toFixed(6);
            document.getElementById('lng').value = ll.lng.toFixed(6);
          }
          setLL({lat:lat,lng:lng});
          marker.on('dragend', function(e){ setLL(e.target.getLatLng()); });
          map.on('click', function(e){ marker.setLatLng(e.latlng); setLL(e.latlng); });
        });
      </script>
      <?php endif; ?>
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
<?php include __DIR__.'/../includes/footer.php'; ?>
