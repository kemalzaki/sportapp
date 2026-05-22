<?php
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
send_security_headers(); enforce_session_timeout();
$pageTitle = 'Kalender Jadwal';
$u = current_user();
$isAdmin = $u && $u['role']==='admin';

// AJAX: drag & drop reschedule (admin)
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['_action'] ?? '')==='move' && $isAdmin) {
    csrf_check();
    $id = (int)$_POST['id']; $tgl = $_POST['tanggal'];
    if ($id && preg_match('/^\d{4}-\d{2}-\d{2}$/',$tgl)) {
        db_exec("UPDATE jadwal SET tanggal=$1, bulan=$2, minggu_ke=$3 WHERE id=$4",
                [$tgl, date('F',strtotime($tgl)), 'W'.(int)ceil(date('j',strtotime($tgl))/7), $id]);
    }
    header('Content-Type: application/json'); echo json_encode(['ok'=>true]); exit;
}

$events = db_all("SELECT id,tanggal,jenis,tempat FROM jadwal ORDER BY tanggal");
$upcoming = db_all("SELECT id,tanggal,jenis,tempat FROM jadwal WHERE tanggal >= CURRENT_DATE ORDER BY tanggal ASC LIMIT 8");
include __DIR__.'/includes/header.php'; ?>

<h2 class="mb-3"><i class="bi bi-calendar3 text-primary"></i> Kalender Jadwal</h2>
<div class="row g-3">
  <div class="col-lg-9">
    <div class="card shadow-sm"><div class="card-body">
      <div id="cal"></div>
    </div></div>
  </div>
  <div class="col-lg-3">
    <div class="card shadow-sm"><div class="card-header"><i class="bi bi-bell text-warning"></i> Upcoming</div>
      <ul class="list-group list-group-flush">
        <?php foreach($upcoming as $e): ?>
          <li class="list-group-item">
            <div class="small text-muted"><?= htmlspecialchars($e['tanggal']) ?> · <?= hari_id($e['tanggal']) ?></div>
            <strong><?= htmlspecialchars($e['jenis']) ?></strong><br>
            <small><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($e['tempat']) ?></small>
          </li>
        <?php endforeach; if(!$upcoming): ?><li class="list-group-item small text-muted">Tidak ada event mendatang.</li><?php endif; ?>
      </ul>
    </div>
  </div>
</div>

<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
<script>
const CSRF = '<?= csrf_token() ?>';
const IS_ADMIN = <?= $isAdmin ? 'true':'false' ?>;
const EVENTS = <?= json_encode(array_map(function($e){
  return ['id'=>$e['id'],'title'=>$e['jenis'].' @ '.$e['tempat'],'start'=>$e['tanggal']];
},$events)) ?>;
document.addEventListener('DOMContentLoaded', () => {
  const cal = new FullCalendar.Calendar(document.getElementById('cal'), {
    initialView:'dayGridMonth', height:'auto', editable: IS_ADMIN, events: EVENTS,
    eventColor:'#0ea5e9',
    eventDrop: async (info) => {
      if (!IS_ADMIN) { info.revert(); return; }
      const fd = new FormData(); fd.append('_action','move'); fd.append('csrf',CSRF);
      fd.append('id', info.event.id); fd.append('tanggal', info.event.startStr);
      const r = await fetch('/calendar.php',{method:'POST',body:fd});
      if (!r.ok) info.revert();
    }
  });
  cal.render();
});
</script>
<?php include __DIR__.'/includes/footer.php'; ?>
