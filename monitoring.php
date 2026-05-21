<?php
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/helpers.php';
require_login();
$pageTitle='Monitoring Performa';

$totalSesi = (int) db_val("SELECT COUNT(*) FROM jadwal");

$trend = db_all("
  SELECT j.bulan, j.minggu_ke, MIN(j.tanggal) AS tgl,
         COALESCE(SUM(CASE WHEN a.hadir=1 THEN 1 ELSE 0 END),0) AS total
  FROM jadwal j
  LEFT JOIN absensi a ON a.jadwal_id=j.id
  GROUP BY j.bulan, j.minggu_ke
  ORDER BY tgl
");

// Tren performa jogging harian (akumulasi semua user)
$trendJog = db_all("
  SELECT tanggal,
         COALESCE(SUM(durasi_menit),0) AS durasi,
         COALESCE(SUM(jarak_km),0) AS jarak,
         COALESCE(SUM(kalori),0) AS kalori
  FROM upload_harian
  WHERE jenis='Jogging'
  GROUP BY tanggal
  ORDER BY tanggal
");

$me = current_user();
$s = db_one("SELECT COALESCE(SUM(durasi_menit),0) dm, COALESCE(SUM(jarak_km),0) jk, COALESCE(SUM(kalori),0) kl
             FROM upload_harian WHERE user_id=$1", [$me['id']]);

include __DIR__.'/includes/header.php'; ?>

<h2 class="mb-3"><i class="bi bi-graph-up-arrow text-primary"></i> Monitoring Performa</h2>

<div class="alert alert-info py-2 small d-flex align-items-center gap-2">
  <i class="bi bi-info-circle fs-5"></i>
  <div>
    <strong>Keterangan Jogging:</strong> data durasi, jarak, dan kalori bersumber dari upload aktivitas
    <u>jogging</u> harian masing-masing member.
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-6 col-lg-4">
    <div class="card card-stat shadow-sm h-100"><div class="card-body">
      <div class="stat-icon"><i class="bi bi-stopwatch"></i></div>
      <div class="stat-label">Total Durasi Saya</div>
      <div class="stat-value"><?= (int)$s['dm'] ?><small class="fs-6 fw-normal text-muted"> mnt</small></div>
    </div></div>
  </div>
  <div class="col-6 col-lg-4">
    <div class="card card-stat shadow-sm h-100"><div class="card-body">
      <div class="stat-icon"><i class="bi bi-signpost-split"></i></div>
      <div class="stat-label">Total Jarak Saya</div>
      <div class="stat-value"><?= $s['jk'] ?><small class="fs-6 fw-normal text-muted"> km</small></div>
    </div></div>
  </div>
  <div class="col-12 col-lg-4">
    <div class="card card-stat shadow-sm h-100"><div class="card-body">
      <div class="stat-icon"><i class="bi bi-fire"></i></div>
      <div class="stat-label">Total Kalori Saya</div>
      <div class="stat-value"><?= (int)$s['kl'] ?></div>
    </div></div>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-6">
    <div class="card shadow-sm"><div class="card-header"><i class="bi bi-activity text-primary me-1"></i> Tren Total Kehadiran Mingguan</div>
      <div class="card-body"><canvas id="trendChart" height="160"></canvas></div></div>
  </div>
  <div class="col-lg-6">
    <div class="card shadow-sm"><div class="card-header"><i class="bi bi-graph-up text-primary me-1"></i> Tren Performa Jogging Harian</div>
      <div class="card-body"><canvas id="jogChart" height="160"></canvas></div></div>
  </div>
</div>

<script>
window.addEventListener('load', function(){
  if (typeof Chart === 'undefined') { console.warn('Chart.js belum termuat'); return; }
  const trendLabels = <?= json_encode(array_map(fn($t)=>$t['bulan'].' '.$t['minggu_ke'], $trend)) ?>;
  const trendData   = <?= json_encode(array_map(fn($t)=>(int)$t['total'], $trend)) ?>;
  const ctx = document.getElementById('trendChart');
  if (ctx) {
    if (trendLabels.length === 0) {
      ctx.parentNode.innerHTML = '<p class="text-muted text-center py-3 mb-0">Belum ada data jadwal.</p>';
    } else {
      const grad = ctx.getContext('2d').createLinearGradient(0,0,0,200);
      grad.addColorStop(0,'rgba(14,165,233,.45)'); grad.addColorStop(1,'rgba(14,165,233,0)');
      new Chart(ctx,{type:'line',data:{labels:trendLabels,datasets:[{label:'Total Hadir',data:trendData,borderColor:'#0ea5e9',backgroundColor:grad,fill:true,tension:.35,pointBackgroundColor:'#0ea5e9'}]},options:{responsive:true,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,ticks:{precision:0}}}}});
    }
  }

  const jogLabels = <?= json_encode(array_map(fn($t)=>$t['tanggal'], $trendJog)) ?>;
  const jogDur    = <?= json_encode(array_map(fn($t)=>(int)$t['durasi'], $trendJog)) ?>;
  const jogJrk    = <?= json_encode(array_map(fn($t)=>(float)$t['jarak'], $trendJog)) ?>;
  const jogKal    = <?= json_encode(array_map(fn($t)=>(int)$t['kalori'], $trendJog)) ?>;
  const jc = document.getElementById('jogChart');
  if (jc) {
    if (jogLabels.length === 0) {
      jc.parentNode.innerHTML = '<p class="text-muted text-center py-3 mb-0">Belum ada upload jogging.</p>';
    } else {
      new Chart(jc,{type:'line',data:{labels:jogLabels,datasets:[
        {label:'Durasi (mnt)',data:jogDur,borderColor:'#0ea5e9',tension:.3,fill:false},
        {label:'Jarak (km)',data:jogJrk,borderColor:'#22c55e',tension:.3,fill:false},
        {label:'Kalori',data:jogKal,borderColor:'#f59e0b',tension:.3,fill:false}
      ]},options:{responsive:true,plugins:{legend:{display:true}},scales:{y:{beginAtZero:true}}}});
    }
  }
});
</script>

<?php include __DIR__.'/includes/footer.php'; ?>
