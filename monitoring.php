<?php
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require_login();
$pageTitle='Monitoring Performa';

$totalSesi = (int) db_val("SELECT COUNT(*) FROM jadwal");

$rank = db_all("
  SELECT u.id, u.nama,
         COUNT(a.id) FILTER (WHERE a.hadir=1) AS hadir,
         CASE WHEN $totalSesi>0
              THEN ROUND(COUNT(a.id) FILTER (WHERE a.hadir=1)::numeric * 100 / $totalSesi, 1)
              ELSE 0 END AS persen
  FROM users u
  LEFT JOIN absensi a ON a.user_id=u.id
  WHERE u.role IN ('member','admin')
  GROUP BY u.id, u.nama
  ORDER BY hadir DESC
");

$trend = db_all("
  SELECT bulan, minggu_ke,
         SUM((SELECT COUNT(*) FROM absensi a WHERE a.jadwal_id=j.id AND a.hadir=1)) AS total
  FROM jadwal j
  GROUP BY bulan, minggu_ke
  ORDER BY MIN(tanggal)
");

$me = current_user();
$s = db_one("SELECT COALESCE(SUM(durasi_menit),0) dm, COALESCE(SUM(jarak_km),0) jk, COALESCE(SUM(kalori),0) kl
             FROM upload_harian WHERE user_id=$1", [$me['id']]);

include __DIR__.'/includes/header.php'; ?>

<h2 class="mb-3"><i class="bi bi-graph-up-arrow text-primary"></i> Monitoring Performa</h2>

<div class="alert alert-info py-2 small d-flex align-items-center gap-2">
  <i class="bi bi-info-circle fs-5"></i>
  <div>
    <strong>Keterangan Jogging:</strong> data durasi, jarak, dan kalori berikut bersumber dari upload aktivitas
    <u>jogging</u> harian masing-masing member. Pastikan rutin upload minimal 1× seminggu agar tren akurat.
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-6 col-lg-4">
    <div class="card card-stat shadow-sm h-100"><div class="card-body">
      <div class="stat-icon"><i class="bi bi-stopwatch"></i></div>
      <div class="stat-label">Total Durasi Saya</div>
      <div class="stat-value"><?= (int)$s['dm'] ?><small class="fs-6 fw-normal text-muted"> mnt</small></div>
      <div class="stat-foot">Akumulasi semua sesi jogging</div>
    </div></div>
  </div>
  <div class="col-6 col-lg-4">
    <div class="card card-stat shadow-sm h-100"><div class="card-body">
      <div class="stat-icon"><i class="bi bi-signpost-split"></i></div>
      <div class="stat-label">Total Jarak Saya</div>
      <div class="stat-value"><?= $s['jk'] ?><small class="fs-6 fw-normal text-muted"> km</small></div>
      <div class="stat-foot">Jarak tempuh kumulatif</div>
    </div></div>
  </div>
  <div class="col-12 col-lg-4">
    <div class="card card-stat shadow-sm h-100"><div class="card-body">
      <div class="stat-icon"><i class="bi bi-fire"></i></div>
      <div class="stat-label">Total Kalori Saya</div>
      <div class="stat-value"><?= (int)$s['kl'] ?></div>
      <div class="stat-foot">Estimasi kalori terbakar</div>
    </div></div>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="card shadow-sm"><div class="card-header"><i class="bi bi-activity text-primary me-1"></i> Tren Total Kehadiran Mingguan</div>
      <div class="card-body"><canvas id="trendChart" height="140"></canvas></div></div>
  </div>
  <div class="col-lg-5">
    <div class="card shadow-sm"><div class="card-header"><i class="bi bi-trophy text-primary me-1"></i> Leaderboard Kehadiran</div>
      <div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>#</th><th>Nama</th><th class="text-center">Hadir</th><th class="text-end">%</th></tr></thead><tbody>
      <?php foreach($rank as $i=>$r): $medal = $i===0?'🥇':($i===1?'🥈':($i===2?'🥉':'')); ?>
        <tr><td><?= $medal ?: ($i+1) ?></td><td><?= htmlspecialchars($r['nama']) ?></td>
          <td class="text-center"><span class="badge bg-success rounded-pill"><?= (int)$r['hadir'] ?></span></td>
          <td class="text-end fw-semibold"><?= $r['persen'] ?>%</td></tr>
      <?php endforeach; ?></tbody></table></div>
    </div>
  </div>
</div>

<script>
const labels = <?= json_encode(array_map(fn($t)=>$t['bulan'].' '.$t['minggu_ke'], $trend)) ?>;
const data   = <?= json_encode(array_map(fn($t)=>(int)$t['total'], $trend)) ?>;
const ctx = document.getElementById('trendChart');
const grad = ctx.getContext('2d').createLinearGradient(0,0,0,200);
grad.addColorStop(0,'rgba(14,165,233,.45)'); grad.addColorStop(1,'rgba(14,165,233,0)');
new Chart(ctx,{type:'line',data:{labels,datasets:[{label:'Total Hadir',data,borderColor:'#0ea5e9',backgroundColor:grad,fill:true,tension:.35,pointBackgroundColor:'#0ea5e9'}]},options:{plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,ticks:{precision:0}}}}});
</script>

<?php include __DIR__.'/includes/footer.php'; ?>
