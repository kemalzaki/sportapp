<?php
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
require __DIR__.'/includes/scope.php'; // Revisi R7 #5
send_security_headers(); enforce_session_timeout();
require_login();
$pageTitle = 'Kalender Jadwal';
$u = current_user();
$isAdmin = $u && in_array($u['role'], ['admin','superadmin'], true);
$__isSuper = scope_is_super() ? 1 : 0;
$__vkoms   = scope_kom_ids_sql_array();

if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['_action'] ?? '')==='move' && $isAdmin) {
    csrf_check();
    $id = (int)$_POST['id']; $tgl = $_POST['tanggal'];
    if ($id && preg_match('/^\d{4}-\d{2}-\d{2}$/',$tgl)) {
        // Revisi R7 #5 — cegah admin memindah jadwal komunitas lain
        $k = db_one("SELECT komunitas_id FROM jadwal WHERE id=$1", [$id]);
        if ($k) scope_require_kom($k['komunitas_id']===null?null:(int)$k['komunitas_id']);
        db_exec("UPDATE jadwal SET tanggal=$1, bulan=$2, minggu_ke=$3 WHERE id=$4",
                [$tgl, date('F',strtotime($tgl)), 'W'.(int)ceil(date('j',strtotime($tgl))/7), $id]);
    }
    header('Content-Type: application/json'); echo json_encode(['ok'=>true]); exit;
}

$events = db_all("SELECT j.id,j.tanggal,j.jenis,j.tempat,j.jam_mulai,j.jam_selesai,j.durasi_menit,j.catatan,j.konten_obrolan, u.nama AS koord
                  FROM jadwal j LEFT JOIN users u ON u.id=j.koordinator_id
                  WHERE ($1 = 1 OR j.komunitas_id = ANY($2::int[]))
                  ORDER BY j.tanggal", [$__isSuper, $__vkoms]);
$upcoming = db_all("SELECT j.id,j.tanggal,j.jenis,j.tempat,j.jam_mulai,j.jam_selesai
                    FROM jadwal j
                    WHERE j.tanggal >= CURRENT_DATE
                      AND ($1 = 1 OR j.komunitas_id = ANY($2::int[]))
                    ORDER BY j.tanggal ASC LIMIT 8", [$__isSuper, $__vkoms]);
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
        <?php foreach($upcoming as $e):
          $jm = $e['jam_mulai'] ? substr($e['jam_mulai'],0,5) : null;
          $js = $e['jam_selesai'] ? substr($e['jam_selesai'],0,5) : null;
        ?>
          <li class="list-group-item">
            <div class="small text-muted"><?= htmlspecialchars($e['tanggal']) ?> · <?= hari_id($e['tanggal']) ?><?= $jm ? ' · '.htmlspecialchars($jm).($js ? '–'.htmlspecialchars($js):'') : '' ?></div>
            <strong><?= htmlspecialchars($e['jenis']) ?></strong><br>
            <small><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($e['tempat']) ?></small>
          </li>
        <?php endforeach; if(!$upcoming): ?><li class="list-group-item small text-muted">Tidak ada event mendatang.</li><?php endif; ?>
      </ul>
    </div>
  </div>
</div>

<!-- Detail event popup -->
<div class="modal fade" id="evModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title"><i class="bi bi-calendar-event"></i> <span id="evTitle"></span></h5>
        <button class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <p class="mb-1"><i class="bi bi-calendar3"></i> <span id="evDate"></span></p>
        <p class="mb-1"><i class="bi bi-clock"></i> <span id="evTime"></span></p>
        <p class="mb-1"><i class="bi bi-geo-alt"></i> <span id="evPlace"></span></p>
        <p class="mb-1"><i class="bi bi-person"></i> Koordinator: <span id="evKoord"></span></p>
        <hr>
        <h6 class="text-primary small">Konten Obrolan</h6>
        <div id="evKonten" class="border rounded p-2 mb-2 small"></div>
        <h6 class="text-warning small">Catatan Kondisi Kegiatan</h6>
        <div id="evCatatan" class="border rounded p-2 small"></div>
      </div>
    </div>
  </div>
</div>

<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
<script>
const CSRF = '<?= csrf_token() ?>';
const IS_ADMIN = <?= $isAdmin ? 'true':'false' ?>;
const RAW = <?= json_encode($events) ?>;
const EVENTS = RAW.map(e => ({
  id:e.id,
  title:(e.jenis||'') + ' @ ' + (e.tempat||''),
  start: e.jam_mulai ? (e.tanggal+'T'+e.jam_mulai) : e.tanggal,
  extendedProps:e
}));
let _evM=null;
document.addEventListener('DOMContentLoaded', () => {
  _evM = new bootstrap.Modal(document.getElementById('evModal'));
  const cal = new FullCalendar.Calendar(document.getElementById('cal'), {
    initialView:'dayGridMonth', height:'auto', editable: IS_ADMIN, events: EVENTS,
    eventColor:'#0ea5e9',
    eventClick: (info) => {
      const e = info.event.extendedProps;
      document.getElementById('evTitle').textContent = e.jenis || '-';
      document.getElementById('evDate').textContent  = e.tanggal || '-';
      const jm = e.jam_mulai ? e.jam_mulai.substring(0,5) : '';
      const js = e.jam_selesai ? e.jam_selesai.substring(0,5) : '';
      document.getElementById('evTime').textContent  = (jm? jm+(js?' – '+js:'') : '—') + (e.durasi_menit? ' ('+e.durasi_menit+' mnt)':'');
      document.getElementById('evPlace').textContent = e.tempat || '-';
      document.getElementById('evKoord').textContent = e.koord || '-';
      document.getElementById('evKonten').innerHTML  = e.konten_obrolan || '<span class="text-muted">—</span>';
      document.getElementById('evCatatan').innerHTML = e.catatan || '<span class="text-muted">—</span>';
      _evM.show();
    },
    eventDrop: async (info) => {
      if (!IS_ADMIN) { info.revert(); return; }
      const fd = new FormData(); fd.append('_action','move'); fd.append('csrf',CSRF);
      fd.append('id', info.event.id); fd.append('tanggal', info.event.startStr.substring(0,10));
      const r = await fetch('/calendar.php',{method:'POST',body:fd});
      if (!r.ok) info.revert();
    }
  });
  cal.render();
});
</script>
<?php include __DIR__.'/includes/footer.php'; ?>
