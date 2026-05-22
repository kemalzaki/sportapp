<?php
// Monitoring lanjutan: VO2, pace trend, calories, consistency, fatigue, heatmap, kehadiran & jogging tren
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
send_security_headers(); enforce_session_timeout();
require_login();
$u = current_user();
$pageTitle = 'Monitoring Performa';

$uploads = db_all("SELECT * FROM upload_harian WHERE user_id=$1 AND tanggal >= CURRENT_DATE - INTERVAL '365 days' ORDER BY tanggal", [(int)$u['id']]);

// ---- VO2 ----
$vo2 = null;
foreach (array_reverse($uploads) as $r) {
    if (!empty($r['jarak_km']) && (float)$r['jarak_km'] >= 1.6 && !empty($r['durasi_menit'])) {
        $meters = (float)$r['jarak_km'] * 1000;
        $vo2 = max(0, ($meters - 504.9) / 44.73);
        break;
    }
}

// ---- Pace trend ----
$pacePoints = [];
foreach ($uploads as $r) {
    if (!empty($r['pace_detik'])) $pacePoints[] = ['t'=>$r['tanggal'], 'v'=>(int)$r['pace_detik']];
    elseif (!empty($r['jarak_km']) && !empty($r['durasi_menit']) && (float)$r['jarak_km']>0) {
        $pacePoints[] = ['t'=>$r['tanggal'], 'v'=> (int) round(((int)$r['durasi_menit']*60)/(float)$r['jarak_km'])];
    }
}

// ---- Calories weekly ----
$calMap = [];
foreach ($uploads as $r) {
    if (!empty($r['kalori'])) {
        $w = date('o-\WW', strtotime($r['tanggal']));
        $calMap[$w] = ($calMap[$w] ?? 0) + (int)$r['kalori'];
    }
}
ksort($calMap);
$calLabels = array_keys($calMap); $calVals = array_values($calMap);

// ---- Consistency ----
$weeks12 = [];
for ($i=11; $i>=0; $i--) $weeks12[date('o-\WW', strtotime("-$i week"))] = 0;
foreach ($uploads as $r) {
    $w = date('o-\WW', strtotime($r['tanggal']));
    if (isset($weeks12[$w])) $weeks12[$w] = 1;
}
$consistency = (int) round(array_sum($weeks12) / max(1,count($weeks12)) * 100);

// ---- Fatigue ----
$rpe7=[]; $rpe28=[]; $today = time();
foreach ($uploads as $r) {
    if (empty($r['rpe'])) continue;
    $age = ($today - strtotime($r['tanggal'])) / 86400;
    if ($age <= 7) $rpe7[] = (int)$r['rpe'];
    if ($age <= 28) $rpe28[] = (int)$r['rpe'];
}
$avg7  = $rpe7  ? array_sum($rpe7)/count($rpe7) : 0;
$avg28 = $rpe28 ? array_sum($rpe28)/count($rpe28) : 0;
$fatigue = $avg28 > 0 ? round(($avg7/$avg28 - 1)*100) : 0;
$fatigueLabel = $fatigue > 30 ? '🔥 Overload' : ($fatigue > 10 ? '⚠️ Cukup berat' : ($fatigue < -10 ? '🟢 Recovery' : '✅ Seimbang'));

// ---- Heatmap ----
$heat = [];
foreach ($uploads as $r) { $heat[$r['tanggal']] = ($heat[$r['tanggal']] ?? 0) + 1; }

// ---- Tren Kehadiran Mingguan (12 minggu) — semua user ----
$wkRows = db_all("SELECT to_char(date_trunc('week', j.tanggal), 'IYYY-\"W\"IW') AS wk, COUNT(*) AS c
                  FROM absensi a JOIN jadwal j ON j.id=a.jadwal_id
                  WHERE a.hadir=1 AND j.tanggal >= CURRENT_DATE - INTERVAL '12 weeks'
                  GROUP BY 1 ORDER BY 1");
$wkLabels=[]; $wkVals=[];
foreach($wkRows as $r){ $wkLabels[]=$r['wk']; $wkVals[]=(int)$r['c']; }

// ---- Tren Performa Jogging Harian saya (30 hari) ----
$jogRows = db_all("SELECT tanggal, jarak_km, pace_detik FROM upload_harian
                   WHERE user_id=$1 AND jenis ILIKE 'jogging' AND tanggal >= CURRENT_DATE - INTERVAL '30 days'
                   ORDER BY tanggal", [(int)$u['id']]);
$jogLabels=[]; $jogDist=[]; $jogPace=[];
foreach($jogRows as $r){ $jogLabels[]=$r['tanggal']; $jogDist[]=(float)$r['jarak_km']; $jogPace[]=(int)$r['pace_detik']; }

include __DIR__.'/includes/header.php';
?>
<h2 class="mb-3"><i class="bi bi-graph-up-arrow text-primary"></i> Monitoring Performa</h2>

<!-- KPI cards -->
<div class="row g-3 mb-3">
  <div class="col-6 col-md-3"><div class="card card-stat shadow-sm"><div class="card-body">
    <div class="stat-label">VO₂ Estimasi <i class="bi bi-info-circle" data-bs-toggle="tooltip" title="Perkiraan VO₂max (ml/kg/min) — kapasitas aerobik. Dihitung dari aktivitas lari ≥ 1.6 km dengan rumus Cooper."></i></div>
    <div class="stat-value"><?= $vo2 ? number_format($vo2,1) : '—' ?></div>
    <small class="text-muted">ml/kg/min</small></div></div></div>
  <div class="col-6 col-md-3"><div class="card card-stat shadow-sm"><div class="card-body">
    <div class="stat-label">Consistency <i class="bi bi-info-circle" data-bs-toggle="tooltip" title="% minggu yang punya minimal 1 aktivitas dalam 12 minggu terakhir."></i></div>
    <div class="stat-value"><?= $consistency ?>%</div>
    <small class="text-muted">12 minggu</small></div></div></div>
  <div class="col-6 col-md-3"><div class="card card-stat shadow-sm"><div class="card-body">
    <div class="stat-label">Fatigue Index <i class="bi bi-info-circle" data-bs-toggle="tooltip" title="Rasio rata-rata RPE 7 hari vs 28 hari. Positif = beban naik, negatif = recovery."></i></div>
    <div class="stat-value"><?= $fatigue ?>%</div>
    <small class="text-muted"><?= $fatigueLabel ?></small></div></div></div>
  <div class="col-6 col-md-3"><div class="card card-stat shadow-sm"><div class="card-body">
    <div class="stat-label">Kalori (mgg ini)</div>
    <div class="stat-value"><?= number_format(end($calVals) ?: 0) ?></div>
    <small class="text-muted">kkal</small></div></div></div>
</div>

<!-- Penjelasan metrik -->
<div class="card shadow-sm mb-3 border-info">
  <div class="card-header bg-info-subtle text-info-emphasis"><i class="bi bi-book"></i> Penjelasan Metrik</div>
  <div class="card-body small">
    <div class="row g-3">
      <div class="col-md-6"><strong>🫁 VO₂ Estimasi</strong><br>
        Perkiraan VO₂max (ml/kg/min) — kapasitas tubuh menyerap oksigen saat olahraga.
        Dihitung dari rumus Cooper: <code>(jarak_meter − 504.9) / 44.73</code> pada lari ≥ 1.6 km.
        Makin tinggi makin bagus (rata-rata dewasa: 30-40, atlet: 50+).</div>
      <div class="col-md-6"><strong>📅 Consistency</strong><br>
        Persentase minggu (dari 12 minggu terakhir) yang memiliki minimal 1 aktivitas. 100% = aktif setiap minggu.</div>
      <div class="col-md-6"><strong>🔥 Fatigue Index</strong><br>
        Membandingkan rata-rata RPE (perceived exertion) 7 hari terakhir vs 28 hari.
        <b>&gt; +30%</b>: overload (rawan cedera). <b>+10% s/d +30%</b>: cukup berat.
        <b>−10% s/d +10%</b>: seimbang. <b>&lt; −10%</b>: recovery.</div>
      <div class="col-md-6"><strong>🗓️ Heatmap Aktivitas</strong><br>
        Grid 53 minggu × 7 hari (mirip GitHub contribution graph). Tiap sel mewakili 1 hari.
        Warna makin gelap = jumlah aktivitas hari itu makin banyak.
        Hover tiap kotak untuk melihat tanggal & jumlah.</div>
    </div>
  </div>
</div>

<!-- Tren Pace + Kalori per Minggu -->
<div class="row g-3">
  <div class="col-lg-6"><div class="card shadow-sm"><div class="card-header">Pace Trend (detik/km, lower = better)</div>
    <div class="card-body"><canvas id="paceChart" height="160"></canvas></div></div></div>
  <div class="col-lg-6"><div class="card shadow-sm"><div class="card-header">Kalori per Minggu</div>
    <div class="card-body"><canvas id="calChart" height="160"></canvas></div></div></div>
</div>

<!-- Tren Kehadiran Mingguan + Tren Performa Jogging Harian -->
<div class="row g-3 mt-1">
  <div class="col-lg-6"><div class="card shadow-sm"><div class="card-header"><i class="bi bi-people text-primary"></i> Tren Total Kehadiran Mingguan</div>
    <div class="card-body"><canvas id="wkChart" height="160"></canvas>
      <small class="text-muted d-block mt-2">Total kehadiran semua anggota per minggu (12 minggu terakhir).</small></div></div></div>
  <div class="col-lg-6"><div class="card shadow-sm"><div class="card-header"><i class="bi bi-activity text-success"></i> Tren Performa Jogging Harian (saya)</div>
    <div class="card-body"><canvas id="jogChart" height="160"></canvas>
      <small class="text-muted d-block mt-2">Jarak (km) dan pace (detik/km) tiap sesi jogging — 30 hari terakhir.</small></div></div></div>
</div>

<div class="card shadow-sm mt-3"><div class="card-header">Heatmap Aktivitas (53 minggu)</div>
<div class="card-body"><div class="heatmap">
<?php
$start = strtotime('sunday -52 week');
for ($w=0; $w<53; $w++) {
  for ($d=0; $d<7; $d++) {
    $date = date('Y-m-d', strtotime("+".($w*7+$d)." day", $start));
    $cnt = $heat[$date] ?? 0;
    $cls = $cnt<=0?'':($cnt==1?'l1':($cnt==2?'l2':($cnt==3?'l3':'l4')));
    echo '<div class="cell '.$cls.'" title="'.$date.': '.$cnt.' aktivitas"></div>';
  }
}
?>
</div>
<div class="small text-muted mt-2">Tiap sel = 1 hari. Warna lebih gelap = lebih banyak aktivitas.</div>
</div></div>

<script>
const paceData = <?= json_encode($pacePoints ?: []) ?>;
const calLabels = <?= json_encode($calLabels ?: []) ?>;
const calVals   = <?= json_encode($calVals ?: []) ?>;
const wkLabels  = <?= json_encode($wkLabels ?: []) ?>;
const wkVals    = <?= json_encode($wkVals ?: []) ?>;
const jogLabels = <?= json_encode($jogLabels ?: []) ?>;
const jogDist   = <?= json_encode($jogDist ?: []) ?>;
const jogPace   = <?= json_encode($jogPace ?: []) ?>;

function _renderMonitoringCharts(){
  if (typeof Chart === 'undefined') { return setTimeout(_renderMonitoringCharts, 120); }
  // Helper untuk dataset kosong: tampilkan minimal 1 titik 0 agar grid tren tetap kelihatan
  function ensure(arr, fallbackLabel){ if(arr && arr.length) return arr; return [fallbackLabel || '—']; }

  new Chart(document.getElementById('paceChart'), {
    type:'line',
    data:{ labels: paceData.length? paceData.map(p=>p.t):['—'], datasets:[{ label:'pace (s/km)', data: paceData.length? paceData.map(p=>p.v):[0], tension:.3, borderColor:'#0ea5e9', backgroundColor:'rgba(14,165,233,.15)', fill:true }]},
    options:{ scales:{ y:{ reverse:true, beginAtZero:false } } }
  });
  new Chart(document.getElementById('calChart'), {
    type:'bar',
    data:{ labels: calLabels.length? calLabels:['—'], datasets:[{ label:'kalori', data: calVals.length? calVals:[0], backgroundColor:'#6366f1' }]}
  });
  new Chart(document.getElementById('wkChart'), {
    type:'line',
    data:{ labels: wkLabels.length? wkLabels:['—'], datasets:[{ label:'Total hadir', data: wkVals.length? wkVals:[0], tension:.3, borderColor:'#10b981', backgroundColor:'rgba(16,185,129,.15)', fill:true }]}
  });
  new Chart(document.getElementById('jogChart'), {
    type:'line',
    data:{ labels: jogLabels.length? jogLabels:['—'], datasets:[
      { label:'Jarak (km)', data: jogDist.length? jogDist:[0], yAxisID:'y',  borderColor:'#f59e0b', backgroundColor:'rgba(245,158,11,.15)', tension:.3 },
      { label:'Pace (s/km)',data: jogPace.length? jogPace:[0], yAxisID:'y1', borderColor:'#ef4444', tension:.3 }
    ]},
    options:{ scales:{ y:{ position:'left', title:{display:true,text:'km'} }, y1:{ position:'right', reverse:true, grid:{drawOnChartArea:false}, title:{display:true,text:'s/km'} } } }
  });
  if (window.bootstrap) document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));
}
document.addEventListener('DOMContentLoaded', _renderMonitoringCharts);
</script>
<?php include __DIR__.'/includes/footer.php'; ?>
