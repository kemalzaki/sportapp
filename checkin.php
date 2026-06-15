<?php
// QR Check-in: user scan QR -> auto absen hadir, validasi waktu + GPS radius
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/badges.php';
require __DIR__.'/includes/notifications.php';
send_security_headers(); enforce_session_timeout();
require_login();
$u = current_user();
$pageTitle = 'Check-in QR';

$msg = null; $err = null;

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    rate_limit_or_die('checkin:'.$u['id'], 20, 60);
    $token = trim($_POST['token'] ?? '');
    $lat = isset($_POST['lat']) ? (float)$_POST['lat'] : null;
    $lng = isset($_POST['lng']) ? (float)$_POST['lng'] : null;

    $qr = db_one("SELECT q.*, j.tanggal, j.jam_mulai, j.jenis FROM qr_tokens q JOIN jadwal j ON j.id=q.jadwal_id WHERE q.token=$1", [$token]);
    if (!$qr) { $err = "QR tidak valid."; }
    elseif (strtotime($qr['valid_until']) < time()) { $err = "QR sudah kedaluwarsa."; }
    elseif (strtotime($qr['valid_from']) > time()) { $err = "QR belum aktif."; }
    else {
        // GPS validation kalau ada koordinat
        $okGps = true; $dist = null;
        if ($qr['lat'] !== null && $qr['lng'] !== null && $lat !== null && $lng !== null) {
            $dist = haversine_m((float)$qr['lat'], (float)$qr['lng'], $lat, $lng);
            if ($dist > ((int)($qr['radius_meter'] ?: 150))) {
                $okGps = false;
                $err = "Lokasi di luar radius (~".(int)$dist." m dari titik venue).";
            }
        }
        if ($okGps) {
            // Hitung telat (menit)
            $telat = 0;
            if ($qr['jam_mulai']) {
                $start = strtotime($qr['tanggal'].' '.$qr['jam_mulai']);
                if (time() > $start) $telat = (int) round((time()-$start)/60);
            }
            try {
                db_exec("INSERT INTO absensi(jadwal_id,user_id,hadir,metode,checkin_at,lat,lng,telat_menit)
                         VALUES($1,$2,1,'qr',now(),$3,$4,$5)
                         ON CONFLICT (jadwal_id,user_id)
                         DO UPDATE SET hadir=1, metode='qr', checkin_at=now(), lat=EXCLUDED.lat, lng=EXCLUDED.lng, telat_menit=EXCLUDED.telat_menit",
                    [(int)$qr['jadwal_id'], (int)$u['id'], $lat, $lng, $telat]);
                $msg = "✅ Check-in berhasil! Sesi ".htmlspecialchars($qr['jenis'])." pada ".$qr['tanggal'].($telat>0?" (telat $telat mnt)":"");
                recompute_badges((int)$u['id']);
                notify((int)$u['id'], 'checkin', 'Check-in tercatat', 'Sesi '.$qr['jenis'].' '.$qr['tanggal'], '/riwayat.php');
            } catch (Throwable $e) { $err = "Gagal menyimpan absensi."; }
        }
    }
}

// helper haversine (meter)
function haversine_m(float $a1, float $a2, float $b1, float $b2): float {
    $R = 6371000; $p1 = deg2rad($a1); $p2 = deg2rad($b1);
    $dp = deg2rad($b1-$a1); $dl = deg2rad($b2-$a2);
    $h = sin($dp/2)**2 + cos($p1)*cos($p2)*sin($dl/2)**2;
    return 2 * $R * asin(sqrt($h));
}

require_once __DIR__.'/includes/htmx.php'; htmx_layout_start($pageTitle ?? 'Checkin');
?>
<h2 class="mb-3"><i class="bi bi-qr-code-scan text-primary"></i> Check-in QR</h2>
<div class="alert alert-info py-2 small">
  <i class="bi bi-info-circle"></i> <strong>Kode QR diperoleh dari Admin.</strong>
  Bila belum punya kode, hubungi <em>Admin</em> sesi olahraga atau cek panel <a href="/index.php" class="alert-link">Beranda</a> (bagian "QR aktif" muncul saat ada sesi).
</div>
<?php if($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if($err): ?><div class="alert alert-danger"><?= htmlspecialchars($err) ?></div><?php endif; ?>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="card shadow-sm"><div class="card-body">
      <p class="small text-muted mb-2">Arahkan kamera ke QR sesi olahraga, atau masukkan kode manual.</p>
      <div id="reader" style="width:100%;max-width:400px;"></div>
      <form method="post" id="checkinForm" class="mt-3">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="lat" id="lat"><input type="hidden" name="lng" id="lng">
        <div class="input-group">
          <input class="form-control" name="token" id="token" placeholder="Kode QR (auto saat scan)" value="<?= htmlspecialchars($_GET['prefill'] ?? '') ?>" required>
          <button class="btn btn-primary"><i class="bi bi-check2-circle"></i> Check-in</button>
        </div>
        <div class="form-text" id="gpsStatus">📍 Mendeteksi lokasi…</div>
      </form>
    </div></div>
  </div>
  <div class="col-lg-5">
    <div class="card shadow-sm"><div class="card-header"><i class="bi bi-info-circle"></i> Cara Pakai</div>
    <ul class="list-group list-group-flush small">
      <li class="list-group-item">Pastikan izin <b>kamera</b> & <b>lokasi</b> diaktifkan.</li>
      <li class="list-group-item">QR aktif berlaku sebatas waktu sesi (default 3 jam).</li>
      <li class="list-group-item">Sistem memvalidasi lokasi (radius default 150 m).</li>
      <li class="list-group-item">Otomatis tercatat sebagai <b>HADIR</b> di Riwayat & Monitoring.</li>
    </ul></div>
  </div>
</div>

<script src="https://unpkg.com/html5-qrcode" integrity="" crossorigin="anonymous"></script>
<script>
navigator.geolocation && navigator.geolocation.getCurrentPosition(
  p => {
    document.getElementById('lat').value = p.coords.latitude;
    document.getElementById('lng').value = p.coords.longitude;
    document.getElementById('gpsStatus').innerHTML = '📍 Lokasi siap: '+p.coords.latitude.toFixed(5)+', '+p.coords.longitude.toFixed(5);
  },
  () => document.getElementById('gpsStatus').innerHTML = '⚠️ GPS dimatikan — check-in tetap bisa tapi tanpa validasi lokasi.',
  { enableHighAccuracy: true, timeout: 8000 }
);
try {
  const qr = new Html5Qrcode('reader');
  qr.start({ facingMode: 'environment' }, { fps: 10, qrbox: 240 }, txt => {
    document.getElementById('token').value = txt;
    qr.stop(); document.getElementById('checkinForm').submit();
  }).catch(e => { document.getElementById('reader').innerHTML = '<div class="alert alert-warning small">Kamera tidak tersedia — input kode manual.</div>'; });
} catch(e) {}
</script>
<?php htmx_layout_end(); ?>
