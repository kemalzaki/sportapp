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

// ---- Pace trend (Revisi 16 Juni 2026: sinkron dgn statistik jogging) ----
// Hanya hitung pace untuk aktivitas LARI/JOGGING dan minimal 1 km supaya konsisten
// dengan kartu "Statistik Tren Performa Jogging" & rumus VO2.
$pacePoints = [];
foreach ($uploads as $r) {
    $jenis = strtolower((string)($r['jenis'] ?? ''));
    $isRun = (strpos($jenis,'jogging')!==false || strpos($jenis,'lari')!==false || strpos($jenis,'run')!==false);
    if (!$isRun) continue;
    if (empty($r['jarak_km']) || (float)$r['jarak_km'] < 1) continue;
    if (!empty($r['pace_detik']) && (int)$r['pace_detik']>0) {
        $pacePoints[] = ['t'=>$r['tanggal'], 'v'=>(int)$r['pace_detik']];
    } elseif (!empty($r['durasi_menit'])) {
        $pace = (int) round(((int)$r['durasi_menit']*60)/(float)$r['jarak_km']);
        // Buang outlier kasar (< 2'/km atau > 15'/km) supaya tren tidak nge-skew.
        if ($pace >= 120 && $pace <= 900) $pacePoints[] = ['t'=>$r['tanggal'], 'v'=>$pace];
    }
}
// Sort by tanggal supaya garis tren tidak meloncat.
usort($pacePoints, fn($a,$b)=>strcmp($a['t'],$b['t']));

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

// Revisi 24 Juni 2026 — Tren Kehadiran Mingguan PERSONAL (per user yang login).
// Versi "semua anggota" telah dipindah ke /riwayat.php.
$wkRows = db_all("SELECT to_char(date_trunc('week', j.tanggal), 'IYYY-\"W\"IW') AS wk, COUNT(*) AS c
                  FROM absensi a JOIN jadwal j ON j.id=a.jadwal_id
                  WHERE a.hadir=1 AND a.user_id=$1
                    AND j.tanggal >= CURRENT_DATE - INTERVAL '12 weeks'
                  GROUP BY 1 ORDER BY 1", [(int)$u['id']]);
$wkLabels=[]; $wkVals=[];
foreach($wkRows as $r){ $wkLabels[]=$r['wk']; $wkVals[]=(int)$r['c']; }

// ---- Tren Performa Jogging Harian saya (30 hari) ----
// Revisi 19 Juni 2026 Part Q — perlonggar match jenis (Jogging/Lari/Run, case-insensitive, partial)
// dan hitung pace fallback dari durasi/jarak bila pace_detik kosong supaya chart & statistik tidak nihil.
$jogRows = db_all("SELECT tanggal, jarak_km, pace_detik, durasi_menit, pace FROM upload_harian
                   WHERE user_id=$1
                     AND (jenis ILIKE '%jogging%' OR jenis ILIKE '%lari%' OR jenis ILIKE '%run%')
                     AND tanggal >= CURRENT_DATE - INTERVAL '30 days'
                   ORDER BY tanggal", [(int)$u['id']]);
$jogLabels=[]; $jogDist=[]; $jogPace=[]; $jogDur=[];
foreach($jogRows as $r){
    $jogLabels[]=$r['tanggal'];
    $jogDist[]=(float)$r['jarak_km'];
    $pd = (int)$r['pace_detik'];
    if ($pd <= 0) {
        // 1) coba parse string "6'30"/km" → 390 detik
        if (!empty($r['pace']) && preg_match('/(\d+)\D+(\d{1,2})/', (string)$r['pace'], $m)) {
            $pd = (int)$m[1]*60 + (int)$m[2];
        }
        // 2) fallback dari durasi & jarak
        if ($pd <= 0 && (float)$r['jarak_km'] > 0 && (int)$r['durasi_menit'] > 0) {
            $pd = (int) round(((int)$r['durasi_menit']*60) / (float)$r['jarak_km']);
        }
        // Buang outlier kasar agar tren tidak skew
        if ($pd < 120 || $pd > 900) $pd = 0;
    }
    $jogPace[]= $pd;
    $jogDur[]=(int)$r['durasi_menit'];
}
// ---- Revisi 15 Juni 2026: Statistik ringkas jogging (pace / durasi / jarak) ----
$jogStat = ['count'=>count($jogRows), 'distAvg'=>0,'distTot'=>0,'distBest'=>0,
            'paceAvg'=>0,'paceBest'=>0,'paceTrend'=>0,
            'durAvg'=>0,'durTot'=>0,'durBest'=>0];
if ($jogRows) {
    $validPace = array_values(array_filter($jogPace, fn($v)=>$v>0));
    $jogStat['distTot']  = array_sum($jogDist);
    $jogStat['distAvg']  = $jogStat['distTot'] / max(1,count($jogDist));
    $jogStat['distBest'] = max($jogDist);
    $jogStat['durTot']   = array_sum($jogDur);
    $jogStat['durAvg']   = $jogStat['durTot'] / max(1,count($jogDur));
    $jogStat['durBest']  = max($jogDur);
    if ($validPace) {
        $jogStat['paceAvg']  = (int) round(array_sum($validPace)/count($validPace));
        $jogStat['paceBest'] = (int) min($validPace);
    }
    // Tren: bandingkan paruh awal vs paruh akhir (turun = lebih cepat = lebih baik)
    if (count($validPace) >= 4) {
        $half = (int) floor(count($validPace)/2);
        $earlyP = array_sum(array_slice($validPace,0,$half))/max(1,$half);
        $lateP  = array_sum(array_slice($validPace,$half))/max(1,(count($validPace)-$half));
        $jogStat['paceTrend'] = (int) round($lateP - $earlyP); // detik/km
    }
}
$fmtPace = function($s){ if($s<=0) return '—'; $m=intdiv($s,60); $ss=$s%60; return $m."'".str_pad((string)$ss,2,'0',STR_PAD_LEFT).'"'; };
$fmtDur  = function($menit){ if($menit<=0) return '—'; $h=intdiv($menit,60); $m=$menit%60; return $h>0?($h.'j '.$m.'m'):($m.' m'); };

// Revisi 6 Juni 2026 — Rekomendasi kesehatan dari hasil statistik
$rekomendasi = [];
// 1) Pace trend
if (count($pacePoints) >= 4) {
    $half = (int) floor(count($pacePoints)/2);
    $earlyAvg = array_sum(array_map(fn($p)=>$p['v'], array_slice($pacePoints,0,$half))) / max(1,$half);
    $lateAvg  = array_sum(array_map(fn($p)=>$p['v'], array_slice($pacePoints,$half))) / max(1,(count($pacePoints)-$half));
    if ($lateAvg < $earlyAvg - 5) $rekomendasi[] = ['success','speedometer2','Pace Anda makin cepat 🚀','Pace lari membaik ('.round($earlyAvg).'s/km → '.round($lateAvg).'s/km). Lanjutkan latihan & jaga recovery.'];
    elseif ($lateAvg > $earlyAvg + 10) $rekomendasi[] = ['warning','exclamation-triangle','Pace melambat ⚠️','Pace cenderung melambat. Tambah variasi interval & cek pola istirahat / nutrisi.'];
    else $rekomendasi[] = ['info','arrow-left-right','Pace stabil','Tambahkan 1 sesi interval/minggu untuk progres lebih lanjut.'];
}
// 2) Kalori per minggu
if (!empty($calVals)) {
    $lastCal = (int)end($calVals);
    if ($lastCal < 1000)   $rekomendasi[] = ['warning','fire','Pembakaran kalori rendah','Total kalori minggu ini hanya '.$lastCal.' kkal. Target ideal 1500-3000 kkal/minggu untuk pemeliharaan kebugaran.'];
    elseif ($lastCal > 4500) $rekomendasi[] = ['danger','exclamation-octagon','Latihan berlebih','Kalori terbakar '.$lastCal.' kkal/minggu — pastikan asupan & istirahat cukup untuk hindari overtraining.'];
    else $rekomendasi[] = ['success','check-circle','Pembakaran kalori sehat','Pembakaran '.$lastCal.' kkal/minggu berada di rentang ideal.'];
}
// 3) Tren kehadiran komunitas
if (count($wkVals) >= 4) {
    $lateWk  = array_sum(array_slice($wkVals,-2)) / 2;
    $earlyWk = array_sum(array_slice($wkVals,0,2)) / 2;
    if ($lateWk > $earlyWk * 1.1) $rekomendasi[] = ['success','people-fill','Komunitas makin aktif','Tren kehadiran naik — momentum bagus untuk ajakan event bersama.'];
    elseif ($lateWk < $earlyWk * 0.9) $rekomendasi[] = ['warning','people','Kehadiran komunitas turun','Coba kirim pengingat & buat event ringan untuk menarik partisipasi.'];
}
// 4) Tren performa jogging (jarak)
if (count($jogDist) >= 4) {
    $earlyD = array_sum(array_slice($jogDist,0,(int)floor(count($jogDist)/2))) / max(1,floor(count($jogDist)/2));
    $lateD  = array_sum(array_slice($jogDist,(int)floor(count($jogDist)/2))) / max(1,ceil(count($jogDist)/2));
    if ($lateD > $earlyD * 1.15) $rekomendasi[] = ['success','graph-up-arrow','Endurance meningkat','Rata-rata jarak jogging Anda naik '.round(($lateD/$earlyD-1)*100).'%. Pertahankan progressive overload (+10%/minggu).'];
    elseif ($lateD < $earlyD * 0.85) $rekomendasi[] = ['warning','graph-down-arrow','Endurance menurun','Jarak rata-rata turun. Periksa kualitas tidur, hidrasi & beban kerja harian.'];
}
// 5) VO2 advisory
if ($vo2) {
    if     ($vo2 < 30) $rekomendasi[] = ['danger','heart-pulse','VO₂ rendah','VO₂ '.number_format($vo2,1).' (di bawah rata-rata). Mulai program aerobik ringan 3×/minggu (jalan cepat / jogging zona 2).'];
    elseif ($vo2 < 40) $rekomendasi[] = ['info','heart-pulse','VO₂ rata-rata','VO₂ '.number_format($vo2,1).' — tambahkan latihan interval & long run mingguan.'];
    else               $rekomendasi[] = ['success','heart-pulse','VO₂ baik','VO₂ '.number_format($vo2,1).' — kebugaran aerobik bagus, jaga konsistensi.'];
}
if (!$rekomendasi) $rekomendasi[] = ['info','info-circle','Data belum cukup','Upload aktivitas & hadiri sesi olahraga beberapa minggu agar rekomendasi muncul.'];

include __DIR__.'/includes/header.php';
?>
<h2 class="mb-3"><i class="bi bi-graph-up-arrow text-primary"></i> Monitoring Performa</h2>
<!-- Revisi 17 Juni 2026 — anchor target untuk pindah AI Coach ke atas di mobile -->
<div id="aiCoachAnchor"></div>

<!-- Revisi 23 Juni 2026 — Rekomendasi Kesehatan dibungkus <details> agar bisa diciutkan -->
<details class="card shadow-sm mb-3 border-success spoiler-card">
  <summary class="card-header bg-success-subtle text-success-emphasis d-flex justify-content-between align-items-center" style="cursor:pointer;list-style:revert">
    <span><i class="bi bi-clipboard2-pulse"></i> <strong>Rekomendasi Kesehatan</strong> (otomatis dari statistik Anda) <span class="text-muted small">(klik untuk buka/tutup)</span></span>
    <small class="text-muted d-none d-md-inline">Pace · Kalori · Kehadiran · Performa Jogging · VO₂</small>
  </summary>
  <div class="card-body">
    <div class="row g-2">
      <?php foreach($rekomendasi as $r): ?>
        <div class="col-md-6">
          <div class="d-flex align-items-start gap-2 p-2 border rounded">
            <span class="badge bg-<?= $r[0] ?>-subtle text-<?= $r[0] ?>-emphasis"><i class="bi bi-<?= $r[1] ?>"></i></span>
            <div class="small">
              <div class="fw-semibold"><?= htmlspecialchars($r[2]) ?></div>
              <div class="text-muted"><?= htmlspecialchars($r[3]) ?></div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="small text-muted mt-2"><i class="bi bi-info-circle"></i> Rekomendasi ini bersifat umum, bukan pengganti konsultasi tenaga medis.</div>
  </div>
</details>

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
<!-- Revisi 22 Juni 2026 R12 — Penjelasan Metrik dibungkus <details> (spoiler)
     supaya halaman tidak memanjang ke bawah. Klik untuk membuka penjelasan. -->
<details class="card shadow-sm mb-3 border-info">
  <summary class="card-header bg-info-subtle text-info-emphasis" style="cursor:pointer;list-style:revert">
    <i class="bi bi-book"></i> Penjelasan Metrik <span class="text-muted small">(klik untuk buka/tutup)</span>
  </summary>
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
</details>

<!-- Revisi 23 Juni 2026 — Pace Trend & Kalori per Minggu dibungkus <details> -->
<div class="row g-3">
  <div class="col-lg-6"><details class="card shadow-sm spoiler-card">
    <summary class="card-header" style="cursor:pointer;list-style:revert"><i class="bi bi-speedometer text-primary"></i> Pace Trend (detik/km, lower = better) <span class="text-muted small">(klik buka/tutup)</span></summary>
    <div class="card-body"><canvas id="paceChart" height="160"></canvas></div>
  </details></div>
  <div class="col-lg-6"><details class="card shadow-sm spoiler-card">
    <summary class="card-header" style="cursor:pointer;list-style:revert"><i class="bi bi-fire text-danger"></i> Kalori per Minggu <span class="text-muted small">(klik buka/tutup)</span></summary>
    <div class="card-body"><canvas id="calChart" height="160"></canvas></div>
  </details></div>
</div>

<!-- Revisi 23 Juni 2026 — Tren Kehadiran & Tren Performa Jogging dibungkus <details> -->
<div class="row g-3 mt-1">
  <div class="col-lg-6"><details class="card shadow-sm spoiler-card">
    <summary class="card-header" style="cursor:pointer;list-style:revert"><i class="bi bi-person-check text-primary"></i> Tren Kehadiran Mingguan (Personal) <span class="text-muted small">(klik buka/tutup)</span></summary>
    <div class="card-body"><canvas id="wkChart" height="160"></canvas>
      <small class="text-muted d-block mt-2">Total kehadiran <b>saya</b> per minggu (12 minggu terakhir). Versi seluruh anggota dipindah ke halaman <a href="/riwayat.php" class="text-decoration-none">Riwayat</a>.</small></div>
  </details></div>
  <div class="col-lg-6"><details class="card shadow-sm spoiler-card">
    <summary class="card-header" style="cursor:pointer;list-style:revert"><i class="bi bi-activity text-success"></i> Tren Performa Jogging Harian (saya) <span class="text-muted small">(klik buka/tutup)</span></summary>
    <div class="card-body"><canvas id="jogChart" height="160"></canvas>
      <small class="text-muted d-block mt-2">Jarak (km) dan pace (detik/km) tiap sesi jogging — 30 hari terakhir.</small></div>
  </details></div>
</div>

<!-- Revisi 23 Juni 2026 — Statistik Tren Performa Jogging dibungkus <details> -->
<div class="row g-3 mt-1">
  <div class="col-12">
    <details class="card shadow-sm border-success spoiler-card">
      <summary class="card-header bg-success-subtle text-success-emphasis d-flex justify-content-between align-items-center" style="cursor:pointer;list-style:revert">
        <span><i class="bi bi-speedometer2"></i> <strong>Statistik Tren Performa Jogging (30 hari)</strong> <span class="text-muted small">(klik buka/tutup)</span></span>
        <small class="text-muted"><?= (int)$jogStat['count'] ?> sesi</small>
      </summary>
      <div class="card-body">
        <div class="row g-2 mb-3">
          <!-- Pace -->
          <div class="col-md-4">
            <div class="border rounded p-3 h-100">
              <div class="small text-muted mb-1"><i class="bi bi-stopwatch text-danger"></i> PACE</div>
              <div class="d-flex justify-content-between"><span class="small">Rata-rata</span><strong><?= $fmtPace($jogStat['paceAvg']) ?>/km</strong></div>
              <div class="d-flex justify-content-between"><span class="small">Tercepat</span><strong class="text-success"><?= $fmtPace($jogStat['paceBest']) ?>/km</strong></div>
              <div class="d-flex justify-content-between"><span class="small">Tren</span>
                <strong class="text-<?= $jogStat['paceTrend']<-3?'success':($jogStat['paceTrend']>3?'danger':'muted') ?>">
                  <?= $jogStat['paceTrend']>0?'+':'' ?><?= (int)$jogStat['paceTrend'] ?> s/km
                  <?= $jogStat['paceTrend']<-3?'⬇ lebih cepat':($jogStat['paceTrend']>3?'⬆ melambat':'≈ stabil') ?>
                </strong>
              </div>
            </div>
          </div>
          <!-- Durasi -->
          <div class="col-md-4">
            <div class="border rounded p-3 h-100">
              <div class="small text-muted mb-1"><i class="bi bi-hourglass-split text-warning"></i> DURASI</div>
              <div class="d-flex justify-content-between"><span class="small">Total</span><strong><?= $fmtDur((int)$jogStat['durTot']) ?></strong></div>
              <div class="d-flex justify-content-between"><span class="small">Rata-rata/sesi</span><strong><?= $fmtDur((int)round($jogStat['durAvg'])) ?></strong></div>
              <div class="d-flex justify-content-between"><span class="small">Sesi terlama</span><strong class="text-primary"><?= $fmtDur((int)$jogStat['durBest']) ?></strong></div>
            </div>
          </div>
          <!-- Jarak -->
          <div class="col-md-4">
            <div class="border rounded p-3 h-100">
              <div class="small text-muted mb-1"><i class="bi bi-rulers text-info"></i> JARAK</div>
              <div class="d-flex justify-content-between"><span class="small">Total</span><strong><?= number_format($jogStat['distTot'],2) ?> km</strong></div>
              <div class="d-flex justify-content-between"><span class="small">Rata-rata/sesi</span><strong><?= number_format($jogStat['distAvg'],2) ?> km</strong></div>
              <div class="d-flex justify-content-between"><span class="small">Terjauh</span><strong class="text-success"><?= number_format($jogStat['distBest'],2) ?> km</strong></div>
            </div>
          </div>
        </div>

        <div class="alert alert-info small mb-0">
          <strong><i class="bi bi-question-circle"></i> Cara Baca Pace Trend:</strong>
          <ul class="mb-0 ps-3">
            <li><strong>Pace</strong> = waktu (detik) untuk menempuh 1 km. <em>Angka lebih kecil = lebih cepat.</em>
                Contoh: <code>6'30"</code> = 6 menit 30 detik per km.</li>
            <li>Pada grafik di atas, sumbu pace dibalik (reverse) — <strong>garis NAIK = makin cepat</strong>,
                <strong>garis TURUN = makin lambat</strong>.</li>
            <li><strong>Tren</strong> di kartu ini membandingkan rata-rata pace paruh pertama vs paruh kedua periode 30 hari:
                negatif (mis. <code>-8 s/km</code>) berarti Anda <span class="text-success">membaik</span>,
                positif berarti <span class="text-danger">melambat</span>.</li>
            <li>Rentang umum pace jogging rekreasi: <code>6'30"–8'00"</code>/km · pelari berpengalaman: <code>5'00"–6'00"</code>/km.</li>
            <li>Bila pace stabil/melambat, coba latihan <em>interval</em> 1×/minggu (lari cepat 1–2 menit, jeda 1–2 menit, ulang 6–8×) dan jaga tidur ≥7 jam.</li>
          </ul>
        </div>
      </div>
    </details>
  </div>
</div>


<!-- Revisi 17 Juni 2026 — anchor untuk memindahkan AI Coach ke paling atas di mobile -->

<!-- Revisi 16 Juni 2026 — AI Running Coach (Google Gemini 2.5 Flash) -->
<div id="aiCoachCard" class="card shadow-sm mt-3 border-primary">
  <div class="card-header bg-primary-subtle text-primary-emphasis d-flex justify-content-between align-items-center">
    <span><i class="bi bi-robot"></i> <strong>AI Running Coach</strong> — analisa &amp; rekomendasi pribadi</span>
    <button type="button" id="btnAICoach" class="btn btn-primary btn-sm"><i class="bi bi-stars"></i> Minta Saran AI</button>
  </div>
  <div class="card-body">
    <p class="text-muted small mb-2">AI akan membaca statistik 30 hari terakhir (pace · durasi · jarak · fatigue · konsistensi · VO2) lalu memberi rekomendasi latihan minggu depan.</p>
    <div id="aiCoachOut" class="border rounded p-3 bg-body-tertiary small text-muted">Klik "Minta Saran AI" untuk memulai.</div>
  </div>
</div>
<script>
// Pindahkan card AI Coach ke paling atas di tampilan mobile (<768px)
(function(){
  function reposition(){
    var card = document.getElementById('aiCoachCard');
    var anchor = document.getElementById("aiCoachAnchor");
    if (!card || !anchor) return;
    if (window.innerWidth < 768 && card.previousElementSibling !== anchor) {
      anchor.parentNode.insertBefore(card, anchor.nextSibling);
    }
  }
  document.addEventListener('DOMContentLoaded', reposition);
  window.addEventListener('resize', reposition);
})();
</script>
<script>
(function(){
  var btn = document.getElementById('btnAICoach'), out = document.getElementById('aiCoachOut');
  if (!btn) return;
  btn.addEventListener('click', async function(){
    btn.disabled = true; var oh = btn.innerHTML;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Menghitung...';
    out.className = 'border rounded p-3 bg-body-tertiary small text-muted';
    out.textContent = 'AI sedang menganalisis statistik Anda...';
    try {
      var ctx = "VO2: " + (<?= $vo2 ? number_format($vo2,1) : 'null' ?>) + " ml/kg/min\n" +
                "Consistency 12 minggu: <?= $consistency ?>%\n" +
                "Fatigue Index: <?= $fatigue ?>% (<?= $fatigueLabel ?>)\n" +
                "Kalori minggu ini: <?= number_format(end($calVals) ?: 0) ?> kkal\n" +
                "JOGGING 30 HARI: <?= (int)$jogStat['count'] ?> sesi, " +
                "jarak total <?= number_format($jogStat['distTot'],2) ?> km (rerata <?= number_format($jogStat['distAvg'],2) ?> km/sesi, terjauh <?= number_format($jogStat['distBest'],2) ?> km), " +
                "durasi total <?= (int)$jogStat['durTot'] ?> menit (rerata <?= (int)round($jogStat['durAvg']) ?> menit), " +
                "pace rerata <?= (int)$jogStat['paceAvg'] ?> s/km, tercepat <?= (int)$jogStat['paceBest'] ?> s/km, " +
                "tren pace <?= (int)$jogStat['paceTrend'] ?> s/km (negatif = lebih cepat).";
      var fd = new FormData();
      fd.append('csrf', '<?= csrf_token() ?>');
      fd.append('task', 'coach');
      fd.append('ctx', ctx);
      var r = await fetch('/api_ai.php', {method:'POST', body:fd, credentials:'same-origin'});
      var j = await r.json();
      if (!j.ok) { out.className='border rounded p-3 bg-warning-subtle small'; out.textContent='Gagal: '+(j.err||'?'); return; }
      out.className = 'border rounded p-3 bg-body-tertiary';
      // simple markdown-ish render
      var html = (j.text||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
                  .replace(/\*\*(.+?)\*\*/g,'<strong>$1</strong>')
                  .replace(/^[-*] (.+)$/gm,'<li>$1</li>')
                  .replace(/(<li>[\s\S]+?<\/li>)/,'<ul>$1</ul>')
                  .replace(/\n/g,'<br>');
      out.innerHTML = html;
    } catch(e){
      out.className='border rounded p-3 bg-warning-subtle small'; out.textContent='Error: '+e.message;
    } finally {
      btn.disabled=false; btn.innerHTML = oh;
    }
  });
})();
</script>

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
<!-- Revisi 23 Juni 2026 — saat <details> dibuka, resize Chart.js agar ukuran benar -->
<script>
(function(){
  document.querySelectorAll('details.spoiler-card').forEach(function(d){
    d.addEventListener('toggle', function(){
      if (!d.open) return;
      d.querySelectorAll('canvas').forEach(function(c){
        try {
          var ch = (window.Chart && Chart.getChart) ? Chart.getChart(c) : null;
          if (ch) ch.resize();
        } catch(e){}
      });
    });
  });
})();
</script>

<?php include __DIR__.'/includes/footer.php'; ?>
