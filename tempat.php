<?php
// Booking lapangan pintar (user-facing): calendar, status, recurring, reminder DP
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/notifications.php';
require __DIR__.'/includes/paket_helpers.php'; // R22 — gate Komunitas
send_security_headers(); enforce_session_timeout();
require_login();
$u = current_user();
$pageTitle = 'Booking Lapangan';

// Revisi R22 — Fitur Tempat khusus paket KOMUNITAS
paket_require_or_lock('komunitas', $u, 'Booking Tempat / Lapangan',
    'Fitur reservasi lapangan komunitas (kalender, status, recurring, reminder DP) tersedia untuk paket Komunitas.');

$isAdmin = ($u['role'] ?? '') === 'admin';

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    if (!$isAdmin) { http_response_code(403); die('Hanya admin yang dapat membuat / membatalkan booking. Silakan hubungi admin untuk pemesanan lapangan.'); }
    rate_limit_or_die('book:'.$u['id'], 10, 60);
    $a = $_POST['_action'] ?? '';
    if ($a==='book') {
        $tempat = (int)$_POST['tempat_id'];
        $tgl = $_POST['tanggal']; $j1 = $_POST['jam_mulai']; $j2 = $_POST['jam_selesai'];
        $rec = $_POST['recurring'] ?: null; $until = $_POST['recurring_until'] ?: null;
        $dates = [$tgl];
        if ($rec === 'weekly' && $until) {
            $cur = strtotime($tgl); $end = strtotime($until);
            while (($cur = strtotime('+1 week', $cur)) <= $end) $dates[] = date('Y-m-d', $cur);
        }
        foreach ($dates as $d) {
            $clash = (int) db_val("SELECT COUNT(*) FROM booking WHERE tempat_id=$1 AND tanggal=$2 AND status IN ('pending','booked')
                                   AND NOT ($3::time >= jam_selesai OR $4::time <= jam_mulai)",
                                   [$tempat, $d, $j1, $j2]);
            if ($clash > 0) continue;
            db_exec("INSERT INTO booking(tempat_id,user_id,tanggal,jam_mulai,jam_selesai,status,recurring,recurring_until,catatan)
                     VALUES($1,$2,$3,$4,$5,'booked',$6,$7,$8)",
                     [$tempat,(int)$u['id'],$d,$j1,$j2,$rec,$until,substr($_POST['catatan'] ?? '',0,200)]);
        }
        notify_all('booking', '📅 Booking lapangan baru', "Tanggal $tgl jam $j1-$j2", '/tempat.php');
    } elseif ($a==='cancel') {
        db_exec("UPDATE booking SET status='canceled' WHERE id=$1", [(int)$_POST['id']]);
    }
    header('Location: tempat.php'); exit;
}

// Hanya tempat yang ditandai "tampil di booking" (default: Badminton, Futsal, Biliar)
// Revisi 22 Juni 2026 R5 — sertakan kolom peta (lat,lng,gpx_path,parkir_info) untuk modal "Lihat Peta".
$tempats = db_all("SELECT t.*, COALESCE(j.nama,'') AS jenis_nama
                   FROM tempat t LEFT JOIN jenis_olahraga j ON j.id=t.jenis_id
                   WHERE t.tampil_booking = true ORDER BY t.nama");
/* Revisi 22 Juni 2026 R10 — section "Tempat Hiking & Camping" DIPINDAH ke
   tempat_list.php (Daftar Tempat). Halaman ini fokus pada booking lapangan. */
$trails = []; // dikosongkan agar perubahan minimal di kode bawah
$selected = (int)($_GET['tempat'] ?? ($tempats[0]['id'] ?? 0));
$month = $_GET['m'] ?? date('Y-m');
$first = strtotime("$month-01"); $daysIn = (int) date('t', $first); $startDow = (int) date('w', $first);

$bookings = $selected ? db_all("SELECT b.*, u.nama FROM booking b JOIN users u ON u.id=b.user_id
                                WHERE b.tempat_id=$1 AND to_char(b.tanggal,'YYYY-MM')=$2 AND b.status<>'canceled'
                                ORDER BY b.tanggal,b.jam_mulai", [$selected, $month]) : [];
$byDate = [];
foreach ($bookings as $b) $byDate[$b['tanggal']][] = $b;

// Semua booking aktif (dapat dilihat seluruh member) — dengan pagination
$bookPerPage = 10;
$bookPage = max(1, (int)($_GET['bp'] ?? 1));
$bookTotal = (int) db_val("SELECT COUNT(*) FROM booking WHERE status<>'canceled' AND tanggal >= CURRENT_DATE - INTERVAL '7 days'");
$bookPages = max(1, (int)ceil($bookTotal / $bookPerPage));
if ($bookPage > $bookPages) $bookPage = $bookPages;
$bookOffset = ($bookPage - 1) * $bookPerPage;
$allBooks = db_all("SELECT b.*, t.nama AS tnama, u.nama AS uname FROM booking b
                    JOIN tempat t ON t.id=b.tempat_id LEFT JOIN users u ON u.id=b.user_id
                    WHERE b.status<>'canceled' AND b.tanggal >= CURRENT_DATE - INTERVAL '7 days'
                    ORDER BY b.tanggal DESC, b.jam_mulai DESC LIMIT $1 OFFSET $2",
                    [$bookPerPage, $bookOffset]);
include __DIR__.'/includes/header.php';
?>
<h2 class="mb-3"><i class="bi bi-calendar2-week text-primary"></i> Booking Lapangan</h2>

<div class="row g-3">
  <div class="col-lg-8">
    <div class="card shadow-sm mb-3"><div class="card-body">
      <form class="row g-2 align-items-end">
        <div class="col-md-5"><label class="small fw-semibold">Tempat</label>
          <select name="tempat" class="form-select" onchange="this.form.submit()">
          <?php foreach($tempats as $t): ?><option value="<?= $t['id'] ?>" <?= $t['id']==$selected?'selected':'' ?>><?= htmlspecialchars($t['nama']) ?></option><?php endforeach; ?>
          </select></div>
        <div class="col-md-3"><label class="small fw-semibold">Bulan</label>
          <input type="month" name="m" value="<?= htmlspecialchars($month) ?>" class="form-control" onchange="this.form.submit()"></div>
        <?php /* Revisi 22 Juni 2026 R5 — tombol Lihat Peta untuk tempat terpilih */ ?>
        <?php
          $selT = null; foreach($tempats as $tt) { if ((int)$tt['id']===$selected) { $selT = $tt; break; } }
          $hasMap = $selT && (!empty($selT['lat']) || !empty($selT['gpx_path']));
        ?>
        <div class="col-md-4 d-flex gap-2 flex-wrap">
          <?php if ($hasMap): ?>
            <button type="button" class="btn btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#tpUserMap<?= (int)$selT['id'] ?>">
              <i class="bi bi-map"></i> Lihat Peta
            </button>
          <?php endif; ?>
          <?php if ($selT && !empty($selT['kontak_wa'])): ?>
            <a class="btn btn-outline-success btn-sm" target="_blank" rel="noopener"
               href="https://wa.me/<?= preg_replace('/[^0-9]/','', $selT['kontak_wa']) ?>"><i class="bi bi-whatsapp"></i> WA</a>
          <?php endif; ?>
        </div>
      </form>
    </div></div>

    <div class="card shadow-sm"><div class="card-body">
      <table class="table table-bordered text-center align-middle small mb-0" data-paginate="10">
        <thead><tr><?php foreach(['Min','Sen','Sel','Rab','Kam','Jum','Sab'] as $h) echo "<th>$h</th>"; ?></tr></thead>
        <tbody><tr>
        <?php
          for ($i=0; $i<$startDow; $i++) echo '<td></td>';
          for ($d=1; $d<=$daysIn; $d++) {
            $date = sprintf('%s-%02d', $month, $d);
            $items = $byDate[$date] ?? [];
            $cnt = count($items);
            $bg = $cnt>0 ? 'background:#dbeafe;' : '';
            echo "<td style='height:70px;$bg'><div class='fw-semibold'>$d</div>";
            foreach (array_slice($items,0,3) as $it) {
              echo "<div class='badge bg-primary mt-1' style='font-size:.6rem'>".substr($it['jam_mulai'],0,5)." ".htmlspecialchars(mb_substr($it['nama'],0,8))."</div>";
            }
            if ($cnt>3) echo "<div class='small text-muted'>+".($cnt-3)."</div>";
            echo '</td>';
            if (($startDow + $d) % 7 == 0) echo "</tr><tr>";
          }
        ?>
        </tr></tbody></table>
    </div></div>
  </div>

  <div class="col-lg-4">
    <?php if($isAdmin): ?>
    <div class="card shadow-sm mb-3"><div class="card-header"><i class="bi bi-shield-lock text-primary"></i> Booking Baru <span class="badge bg-primary">Admin</span></div>
    <div class="card-body"><form method="post">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="_action" value="book">
      <input type="hidden" name="tempat_id" value="<?= $selected ?>">
      <label class="small fw-semibold">Tanggal</label><input type="date" name="tanggal" class="form-control mb-2" required>
      <div class="row g-2"><div class="col-6"><label class="small fw-semibold">Jam mulai</label><input type="time" name="jam_mulai" class="form-control" required></div>
      <div class="col-6"><label class="small fw-semibold">Jam selesai</label><input type="time" name="jam_selesai" class="form-control" required></div></div>
      <label class="small fw-semibold mt-2">Recurring</label>
      <select name="recurring" class="form-select"><option value="">Sekali saja</option><option value="weekly">Mingguan</option></select>
      <label class="small fw-semibold mt-2">Sampai (jika recurring)</label><input type="date" name="recurring_until" class="form-control">
      <label class="small fw-semibold mt-2">Catatan</label><input class="form-control" name="catatan" maxlength="200">
      <button class="btn btn-primary w-100 mt-3"><i class="bi bi-bookmark-plus"></i> Booking</button>
    </form></div></div>
    <?php else: ?>
    <div class="alert alert-info small"><i class="bi bi-info-circle"></i> Booking lapangan hanya dapat dilakukan oleh <strong>admin</strong>. Anda dapat melihat seluruh jadwal booking di bawah.</div>
    <?php endif; ?>

    <div class="card shadow-sm"><div class="card-header"><i class="bi bi-list-check"></i> Daftar Booking</div>
    <ul class="list-group list-group-flush" style="max-height:480px;overflow:auto">
    <?php foreach($allBooks as $b): ?>
      <li class="list-group-item d-flex justify-content-between align-items-center">
        <div><strong><?= htmlspecialchars($b['tnama']) ?></strong>
          <small class="text-muted">· oleh <?= htmlspecialchars($b['uname'] ?? '-') ?></small><br>
          <small><?= htmlspecialchars($b['tanggal']) ?> · <?= substr($b['jam_mulai'],0,5) ?>-<?= substr($b['jam_selesai'],0,5) ?></small><br>
          <span class="pill"><?= htmlspecialchars($b['status']) ?></span>
          <?php if(!empty($b['catatan'])): ?><span class="small text-muted">· <?= htmlspecialchars($b['catatan']) ?></span><?php endif; ?>
        </div>
        <?php if($isAdmin && $b['status']!=='canceled'): ?>
        <form method="post" onsubmit="return confirm('Batalkan booking ini?')"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="_action" value="cancel"><input type="hidden" name="id" value="<?= $b['id'] ?>">
          <button class="btn btn-sm btn-outline-danger"><i class="bi bi-x"></i></button></form>
        <?php endif; ?>
      </li>
    <?php endforeach; if(!$allBooks): ?><li class="list-group-item text-muted small text-center">Belum ada booking.</li><?php endif; ?>
    </ul>
    <?php if ($bookTotal > $bookPerPage):
      $bpQS = function($n){ $q = $_GET; $q['bp'] = $n; return '/tempat.php?'.http_build_query($q); };
    ?>
    <nav class="d-flex justify-content-between align-items-center p-2 border-top" aria-label="Navigasi booking">
      <?php if ($bookPage > 1): ?>
        <a class="btn btn-sm btn-outline-primary" href="<?= htmlspecialchars($bpQS($bookPage-1)) ?>"><i class="bi bi-chevron-left"></i> Sebelumnya</a>
      <?php else: ?><span class="btn btn-sm btn-outline-secondary disabled"><i class="bi bi-chevron-left"></i> Sebelumnya</span><?php endif; ?>
      <span class="small text-muted">Halaman <?= $bookPage ?> / <?= $bookPages ?> · Total <?= $bookTotal ?></span>
      <?php if ($bookPage < $bookPages): ?>
        <a class="btn btn-sm btn-outline-primary" href="<?= htmlspecialchars($bpQS($bookPage+1)) ?>">Berikutnya <i class="bi bi-chevron-right"></i></a>
      <?php else: ?><span class="btn btn-sm btn-outline-secondary disabled">Berikutnya <i class="bi bi-chevron-right"></i></span><?php endif; ?>
    </nav>
    <?php endif; ?>
    </div>
  </div>
</div>

<?php /* Revisi 22 Juni 2026 R10 — Section Tempat Hiking & Camping DIPINDAH
   ke tempat_list.php (Daftar Tempat). Tidak ada lagi blok di sini. */ ?>

<?php
/* ============================================================
   Revisi 22 Juni 2026 R5 — Modal "Lihat Peta" untuk halaman booking user.
   Memuat peta Leaflet (OSM). Bila tempat punya .gpx_path, gambar polyline GPX;
   selain itu pakai pin lat/lng. Tampil detail popup: nama, alamat, kontak WA,
   harga, parkir, status booking.
   ============================================================ */
?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="" onerror="this.onerror=null">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin="" defer></script>
<?php
// Revisi 22 Juni 2026 R7 — gabungkan tempat booking + trails (hiking/camping) untuk modal peta.
$__mapList = $tempats; $__seen = [];
foreach ($tempats as $tt) $__seen[(int)$tt['id']] = true;
foreach ($trails as $tt) if (empty($__seen[(int)$tt['id']])) { $__mapList[] = $tt; $__seen[(int)$tt['id']] = true; }
?>
<?php foreach ($__mapList as $t):
  $hasMapT = (!empty($t['lat']) && !empty($t['lng'])) || !empty($t['gpx_path']);
  if (!$hasMapT) continue;
?>
<div class="modal fade" id="tpUserMap<?= (int)$t['id'] ?>" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-xl"><div class="modal-content">
    <div class="modal-header">
      <h5 class="modal-title"><i class="bi bi-geo-alt-fill text-danger"></i> Peta — <?= htmlspecialchars($t['nama']) ?></h5>
      <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body p-0">
      <div id="userMap<?= (int)$t['id'] ?>" style="height:460px;width:100%"></div>
      <div class="p-3 small">
        <div class="row g-2">
          <div class="col-md-7">
            <div><strong><i class="bi bi-pin-map text-danger"></i> <?= htmlspecialchars($t['nama']) ?></strong></div>
            <?php if(!empty($t['alamat'])): ?><div class="text-muted"><i class="bi bi-signpost"></i> <?= htmlspecialchars($t['alamat']) ?></div><?php endif; ?>
            <?php if(!empty($t['jenis_nama'])): ?><div><span class="badge bg-primary-subtle text-primary"><i class="bi bi-tag"></i> <?= htmlspecialchars($t['jenis_nama']) ?></span></div><?php endif; ?>
            <?php if(!empty($t['parkir_info'])): ?><div class="mt-1"><i class="bi bi-p-square text-primary"></i> <?= nl2br(htmlspecialchars($t['parkir_info'])) ?></div><?php endif; ?>
          </div>
          <div class="col-md-5">
            <?php if(!empty($t['harga_lapang'])): ?><div>Harga lapang: <strong>Rp <?= number_format($t['harga_lapang'],0,',','.') ?></strong></div><?php endif; ?>
            <?php if(!empty($t['harga_per_jam'])): ?><div>Harga / jam: <strong>Rp <?= number_format($t['harga_per_jam'],0,',','.') ?></strong></div><?php endif; ?>
            <?php if(!empty($t['harga_tiket'])): ?><div>Tiket: <strong>Rp <?= number_format($t['harga_tiket'],0,',','.') ?></strong></div><?php endif; ?>
            <?php if(!empty($t['harga_parkir'])): ?><div>Parkir: <strong>Rp <?= number_format($t['harga_parkir'],0,',','.') ?></strong></div><?php endif; ?>
            <div class="mt-2 d-flex flex-wrap gap-1">
              <?php if(!empty($t['kontak_wa'])): ?>
                <a class="btn btn-sm btn-success" target="_blank" rel="noopener" href="https://wa.me/<?= preg_replace('/[^0-9]/','', $t['kontak_wa']) ?>"><i class="bi bi-whatsapp"></i> Hubungi</a>
              <?php endif; ?>
              <?php if(!empty($t['lat']) && !empty($t['lng'])): ?>
                <a class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener"
                   href="https://www.google.com/maps/dir/?api=1&destination=<?= (float)$t['lat'] ?>,<?= (float)$t['lng'] ?>&travelmode=driving"><i class="bi bi-google"></i> Rute</a>
                <a class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener"
                   href="https://www.google.com/maps/@?api=1&map_action=pano&viewpoint=<?= (float)$t['lat'] ?>,<?= (float)$t['lng'] ?>"><i class="bi bi-camera"></i> Street View</a>
              <?php endif; ?>
              <a class="btn btn-sm btn-outline-info" href="/tempat_detail.php?id=<?= (int)$t['id'] ?>"><i class="bi bi-info-circle"></i> Detail</a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div></div>
</div>
<script>
(function(){
  var TID = <?= (int)$t['id'] ?>;
  var LAT = <?= !empty($t['lat']) ? (float)$t['lat'] : 'null' ?>;
  var LNG = <?= !empty($t['lng']) ? (float)$t['lng'] : 'null' ?>;
  var GPX = <?= json_encode(!empty($t['gpx_path']) ? $t['gpx_path'] : '') ?>;
  var NAMA = <?= json_encode($t['nama']) ?>;
  var initialized = false, lmap = null;
  var modal = document.getElementById('tpUserMap'+TID);
  if (!modal) return;
  modal.addEventListener('shown.bs.modal', function(){
    if (typeof L === 'undefined') { setTimeout(function(){ modal.dispatchEvent(new Event('shown.bs.modal')); }, 200); return; }
    if (initialized) { setTimeout(function(){ lmap.invalidateSize(); }, 100); return; }
    initialized = true;
    var center = (LAT && LNG) ? [LAT,LNG] : [-6.9,107.6];
    lmap = L.map('userMap'+TID).setView(center, (LAT&&LNG)?16:12);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'© OpenStreetMap'}).addTo(lmap);
    setTimeout(function(){ lmap.invalidateSize(); }, 200);
    if (LAT && LNG) {
      L.marker([LAT,LNG]).addTo(lmap).bindPopup('<b>'+NAMA+'</b>').openPopup();
    }
    if (GPX) {
      fetch(GPX).then(function(r){return r.text();}).then(function(xml){
        var doc = new DOMParser().parseFromString(xml,'application/xml');
        var trkpts = doc.getElementsByTagName('trkpt'); var pts = [];
        for (var i=0;i<trkpts.length;i++) pts.push(L.latLng(parseFloat(trkpts[i].getAttribute('lat')), parseFloat(trkpts[i].getAttribute('lon'))));
        if (pts.length){
          var line = L.polyline(pts,{color:'#dc2626',weight:5,opacity:.85}).addTo(lmap);
          L.marker(pts[0]).addTo(lmap).bindPopup('Start');
          L.marker(pts[pts.length-1]).addTo(lmap).bindPopup('Finish');
          lmap.fitBounds(line.getBounds(),{padding:[20,20]});
        }
      }).catch(function(){});
    }
  });
})();
</script>
<?php endforeach; ?>
<?php include __DIR__.'/includes/footer.php'; ?>

