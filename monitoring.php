<?php
// Monitoring lanjutan: VO2, pace trend, calories, consistency, fatigue, heatmap
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
send_security_headers(); enforce_session_timeout();
require_login();
$u = current_user();
$pageTitle = 'Monitoring Performa';

// Range default: 365 hari ke belakang
$uploads = db_all("SELECT * FROM upload_harian WHERE user_id=$1 AND tanggal >= CURRENT_DATE - INTERVAL '365 days' ORDER BY tanggal", [(int)$u['id']]);

// ---- VO2 estimation (Cooper test ish): jika ada jarak_km >= 1.6 di durasi <=15 menit utk lari, atau pakai HR cadangan ----
$vo2 = null;
foreach (array_reverse($uploads) as $r) {
    if (!empty($r['jarak_km']) && (float)$r['jarak_km'] >= 1.6 && !empty($r['durasi_menit'])) {
        // Cooper: (jarak_meter - 504.9) / 44.73
        $meters = (float)$r['jarak_km'] * 1000;
        $vo2 = max(0, ($meters - 504.9) / 44.73);
        break;
    }
}

// ---- Pace trend (detik per km, lebih rendah lebih bagus) ----
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

// ---- Consistency score: % minggu dengan minimal 1 aktivitas, 12 minggu terakhir ----
$weeks12 = [];
for ($i=11; $i>=0; $i--) $weeks12[date('o-\WW', strtotime("-$i week"))] = 0;
foreach ($uploads as $r) {
    $w = date('o-\WW', strtotime($r['tanggal']));
    if (isset($weeks12[$w])) $weeks12[$w] = 1;
}
$consistency = (int) round(array_sum($weeks12) / count($weeks12) * 100);

// ---- Fatigue indicator: rata-rata RPE 7 hari vs 28 hari ----
$rpe7=[]; $rpe28=[];
$today = time();
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

// ---- Heatmap 53 minggu x 7 hari ----
$heat = [];
foreach ($uploads as $r) {
    $heat[$r['tanggal']] = ($heat[$r['tanggal']] ?? 0) + 1;
}

include __DIR__.'/includes/header.php';
?>
<h2 class="mb-3"><i class="bi bi-graph-up-arrow text-primary"></i> Monitoring Performa</h2>

<div class="row g-3 mb-3">
  <div class="col-6 col-md-3"><div class="card card-stat shadow-sm"><div class="card-body">
    <div class="stat-label">VO₂ Estimasi</div>
    <div class="stat-value"><?= $vo2 ? number_format($vo2,1) : '—' ?></div>
    <small class="text-muted">ml/kg/min</small></div></div></div>
  <div class="col-6 col-md-3"><div class="card card-stat shadow-sm"><div class="card-body">
    <div class="stat-label">Consistency</div>
    <div class="stat-value"><?= $consistency ?>%</div>
    <small class="text-muted">12 minggu</small></div></div></div>
  <div class="col-6 col-md-3"><div class="card card-stat shadow-sm"><div class="card-body">
    <div class="stat-label">Fatigue Index</div>
    <div class="stat-value"><?= $fatigue ?>%</div>
    <small class="text-muted"><?= $fatigueLabel ?></small></div></div></div>
  <div class="col-6 col-md-3"><div class="card card-stat shadow-sm"><div class="card-body">
    <div class="stat-label">Kalori (mgg ini)</div>
    <div class="stat-value"><?= number_format(end($calVals) ?: 0) ?></div>
    <small class="text-muted">kkal</small></div></div></div>
</div>

<div class="row g-3">
  <div class="col-lg-6"><div class="card shadow-sm"><div class="card-header">Pace Trend (detik/km, lower = better)</div>
    <div class="card-body"><canvas id="paceChart" height="160"></canvas></div></div></div>
  <div class="col-lg-6"><div class="card shadow-sm"><div class="card-header">Kalori per Minggu</div>
    <div class="card-body"><canvas id="calChart" height="160"></canvas></div></div></div>
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
    echo '<div class="cell '.$cls.'" title="'.$date.': '.$cnt.'"></div>';
  }
}
?>
</div></div></div>

<script>
const paceData = <?= json_encode($pacePoints) ?>;
const calLabels = <?= json_encode($calLabels) ?>;
const calVals = <?= json_encode($calVals) ?>;
new Chart(document.getElementById('paceChart'), {
  type:'line',
  data:{ labels: paceData.map(p=>p.t), datasets:[{ label:'pace (s/km)', data: paceData.map(p=>p.v), tension:.3, borderColor:'#0ea5e9'}]},
  options:{ scales:{ y:{ reverse:true } } }
});
new Chart(document.getElementById('calChart'), {
  type:'bar',
  data:{ labels: calLabels, datasets:[{ label:'kalori', data: calVals, backgroundColor:'#6366f1' }]}
});
</script>
<?php include __DIR__.'/includes/footer.php'; ?>
