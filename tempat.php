<?php
// Booking lapangan pintar (user-facing): calendar, status, recurring, reminder DP
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/notifications.php';
send_security_headers(); enforce_session_timeout();
require_login();
$u = current_user();
$pageTitle = 'Booking Lapangan';

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

$tempats = db_all("SELECT * FROM tempat ORDER BY nama");
$selected = (int)($_GET['tempat'] ?? ($tempats[0]['id'] ?? 0));
$month = $_GET['m'] ?? date('Y-m');
$first = strtotime("$month-01"); $daysIn = (int) date('t', $first); $startDow = (int) date('w', $first);

$bookings = $selected ? db_all("SELECT b.*, u.nama FROM booking b JOIN users u ON u.id=b.user_id
                                WHERE b.tempat_id=$1 AND to_char(b.tanggal,'YYYY-MM')=$2 AND b.status<>'canceled'
                                ORDER BY b.tanggal,b.jam_mulai", [$selected, $month]) : [];
$byDate = [];
foreach ($bookings as $b) $byDate[$b['tanggal']][] = $b;

// Semua booking aktif (dapat dilihat seluruh member)
$allBooks = db_all("SELECT b.*, t.nama AS tnama, u.nama AS uname FROM booking b
                    JOIN tempat t ON t.id=b.tempat_id LEFT JOIN users u ON u.id=b.user_id
                    WHERE b.status<>'canceled' AND b.tanggal >= CURRENT_DATE - INTERVAL '7 days'
                    ORDER BY b.tanggal DESC, b.jam_mulai DESC LIMIT 50");
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
      </form>
    </div></div>

    <div class="card shadow-sm"><div class="card-body">
      <table class="table table-bordered text-center align-middle small mb-0">
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
    </ul></div>
  </div>
</div>
<?php include __DIR__.'/includes/footer.php'; ?>
