<?php
// gaya_hidup.php — Pencatatan Gaya Hidup (Garmin-style)
// Metrik: langkah, tidur, hidrasi, stres, body battery, berat badan, mood
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
send_security_headers(); enforce_session_timeout();
$pageTitle = 'Gaya Hidup';
$u = current_user();
if (!$u) { header('Location: /login.php'); exit; }
$uid = (int)$u['id'];

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    $a = $_POST['_action'] ?? '';
    if ($a==='save') {
        $tgl   = $_POST['tanggal'] ?: date('Y-m-d');
        $lang  = isset($_POST['langkah']) ? (int)$_POST['langkah'] : null;
        $tidur = isset($_POST['tidur_menit']) && $_POST['tidur_menit']!=='' ? (int)$_POST['tidur_menit'] : null;
        $hid   = isset($_POST['hidrasi_ml']) && $_POST['hidrasi_ml']!=='' ? (int)$_POST['hidrasi_ml'] : null;
        $stres = isset($_POST['stres']) && $_POST['stres']!=='' ? (int)$_POST['stres'] : null;
        $bb    = isset($_POST['body_battery']) && $_POST['body_battery']!=='' ? (int)$_POST['body_battery'] : null;
        $berat = isset($_POST['berat_kg']) && $_POST['berat_kg']!=='' ? (float)$_POST['berat_kg'] : null;
        $mood  = trim($_POST['mood'] ?? '');
        $cat   = trim($_POST['catatan'] ?? '');
        db_exec("INSERT INTO gaya_hidup_log(user_id,tanggal,langkah,tidur_menit,hidrasi_ml,stres_skor,body_battery,berat_kg,mood,catatan)
                 VALUES($1,$2,$3,$4,$5,$6,$7,$8,$9,$10)
                 ON CONFLICT (user_id,tanggal) DO UPDATE SET
                   langkah=EXCLUDED.langkah, tidur_menit=EXCLUDED.tidur_menit, hidrasi_ml=EXCLUDED.hidrasi_ml,
                   stres_skor=EXCLUDED.stres_skor, body_battery=EXCLUDED.body_battery, berat_kg=EXCLUDED.berat_kg,
                   mood=EXCLUDED.mood, catatan=EXCLUDED.catatan, updated_at=now()",
            [$uid,$tgl,$lang,$tidur,$hid,$stres,$bb,$berat,$mood,$cat]);
        $_SESSION['flash_ok'] = "Data $tgl tersimpan.";
    } elseif ($a==='delete') {
        db_exec("DELETE FROM gaya_hidup_log WHERE id=$1 AND user_id=$2", [(int)$_POST['id'],$uid]);
    }
    header('Location: gaya_hidup.php'); exit;
}

$today = date('Y-m-d');
$todayRow = db_one("SELECT * FROM gaya_hidup_log WHERE user_id=$1 AND tanggal=$2",[$uid,$today]);
$rows = db_all("SELECT * FROM gaya_hidup_log WHERE user_id=$1 AND tanggal >= (CURRENT_DATE - INTERVAL '30 days') ORDER BY tanggal DESC",[$uid]);
$stats = db_one("SELECT
   ROUND(AVG(langkah)::numeric,0) AS avg_lang,
   ROUND(AVG(tidur_menit)::numeric/60.0,1) AS avg_tidur,
   ROUND(AVG(hidrasi_ml)::numeric,0) AS avg_hid,
   ROUND(AVG(stres_skor)::numeric,1) AS avg_stres,
   ROUND(AVG(body_battery)::numeric,0) AS avg_bb,
   COUNT(*) AS days
   FROM gaya_hidup_log WHERE user_id=$1 AND tanggal >= (CURRENT_DATE - INTERVAL '7 days')",[$uid]);
$ok = $_SESSION['flash_ok'] ?? null; unset($_SESSION['flash_ok']);
include __DIR__.'/includes/header.php';

// Data chart (30 hari, urut asc)
$chart = db_all("SELECT tanggal, langkah, tidur_menit, hidrasi_ml, stres_skor, body_battery, berat_kg
                 FROM gaya_hidup_log WHERE user_id=$1 AND tanggal >= (CURRENT_DATE - INTERVAL '30 days')
                 ORDER BY tanggal ASC",[$uid]);
$labels = []; $dLang=[]; $dTidur=[]; $dHid=[]; $dStres=[]; $dBB=[]; $dBerat=[];
foreach($chart as $r){
  $labels[]=$r['tanggal'];
  $dLang[]=(int)($r['langkah']??0);
  $dTidur[]=round(($r['tidur_menit']??0)/60.0,1);
  $dHid[]=(int)($r['hidrasi_ml']??0);
  $dStres[]=$r['stres_skor']!==null?(int)$r['stres_skor']:null;
  $dBB[]=$r['body_battery']!==null?(int)$r['body_battery']:null;
  $dBerat[]=$r['berat_kg']!==null?(float)$r['berat_kg']:null;
}
?>
<nav aria-label="breadcrumb" class="mb-2"><ol class="breadcrumb small mb-0">
  <li class="breadcrumb-item"><a href="/index.php">Beranda</a></li>
  <li class="breadcrumb-item active">Gaya Hidup</li>
</ol></nav>

<h2 class="mb-1"><i class="bi bi-heart-pulse text-danger"></i> Pencatatan Gaya Hidup</h2>
<p class="text-muted small">Lacak metrik harian ala Garmin: langkah, tidur, hidrasi, stres, body battery, berat & mood.</p>

<?php if($ok): ?><div class="alert alert-success py-2"><?= htmlspecialchars($ok) ?></div><?php endif; ?>

<div class="row g-2 mb-3">
  <?php
  $cards=[
    ['Rata Langkah/hari',(int)($stats['avg_lang']??0),'bi-shoe-prints','primary'],
    ['Rata Tidur (jam)',($stats['avg_tidur']??0),'bi-moon-stars','indigo'],
    ['Rata Hidrasi (ml)',(int)($stats['avg_hid']??0),'bi-droplet','info'],
    ['Rata Stres',($stats['avg_stres']??'-'),'bi-emoji-frown','warning'],
    ['Rata Body Battery',($stats['avg_bb']??'-'),'bi-battery-charging','success'],
    ['Hari tercatat (7h)',(int)($stats['days']??0),'bi-calendar-check','secondary'],
  ];
  foreach($cards as $c): ?>
    <div class="col-6 col-md-4 col-lg-2">
      <div class="card border-0 shadow-sm h-100"><div class="card-body p-2 text-center">
        <i class="bi <?= $c[2] ?> fs-4 text-<?= $c[3] ?>"></i>
        <div class="fw-bold"><?= htmlspecialchars((string)$c[1]) ?></div>
        <div class="small text-muted"><?= $c[0] ?></div>
      </div></div>
    </div>
  <?php endforeach; ?>
</div>

<div class="card shadow-sm mb-3">
  <div class="card-header"><i class="bi bi-pencil-square"></i> Catat Hari Ini / Tanggal Tertentu</div>
  <div class="card-body">
    <form method="post" class="row g-2">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="_action" value="save">
      <div class="col-md-3"><label class="form-label small">Tanggal</label>
        <input class="form-control" type="date" name="tanggal" value="<?= htmlspecialchars($todayRow['tanggal'] ?? $today) ?>" required></div>
      <div class="col-md-3"><label class="form-label small">Langkah</label>
        <input class="form-control" type="number" min="0" name="langkah" placeholder="cth 8000" value="<?= htmlspecialchars($todayRow['langkah'] ?? '') ?>"></div>
      <div class="col-md-3"><label class="form-label small">Tidur (menit)</label>
        <input class="form-control" type="number" min="0" name="tidur_menit" placeholder="cth 420 (7 jam)" value="<?= htmlspecialchars($todayRow['tidur_menit'] ?? '') ?>"></div>
      <div class="col-md-3"><label class="form-label small">Hidrasi (ml)</label>
        <input class="form-control" type="number" min="0" name="hidrasi_ml" placeholder="cth 2000" value="<?= htmlspecialchars($todayRow['hidrasi_ml'] ?? '') ?>"></div>
      <div class="col-md-3"><label class="form-label small">Stres (0-100)</label>
        <input class="form-control" type="number" min="0" max="100" name="stres" value="<?= htmlspecialchars($todayRow['stres_skor'] ?? '') ?>"></div>
      <div class="col-md-3"><label class="form-label small">Body Battery (0-100)</label>
        <input class="form-control" type="number" min="0" max="100" name="body_battery" value="<?= htmlspecialchars($todayRow['body_battery'] ?? '') ?>"></div>
      <div class="col-md-3"><label class="form-label small">Berat (kg)</label>
        <input class="form-control" type="number" step="0.1" name="berat_kg" value="<?= htmlspecialchars($todayRow['berat_kg'] ?? '') ?>"></div>
      <div class="col-md-3"><label class="form-label small">Mood</label>
        <select class="form-select" name="mood">
          <?php foreach(['','Senang','Biasa','Lelah','Sedih','Marah','Cemas'] as $m): ?>
            <option value="<?= $m ?>" <?= ($todayRow['mood']??'')===$m?'selected':'' ?>><?= $m?:'(pilih)' ?></option>
          <?php endforeach; ?>
        </select></div>
      <div class="col-12"><label class="form-label small">Catatan</label>
        <textarea class="form-control" name="catatan" rows="2" placeholder="opsional"><?= htmlspecialchars($todayRow['catatan'] ?? '') ?></textarea></div>
      <div class="col-12"><button class="btn btn-primary"><i class="bi bi-save"></i> Simpan</button></div>
    </form>
  </div>
</div>

<div class="card shadow-sm mb-3">
  <div class="card-header"><i class="bi bi-graph-up"></i> Tren 30 Hari</div>
  <div class="card-body">
    <canvas id="ghChart" height="120"></canvas>
  </div>
</div>

<div class="card shadow-sm">
  <div class="card-header"><i class="bi bi-clock-history"></i> Riwayat (30 hari terakhir)</div>
  <div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
      <thead class="table-light"><tr><th>Tanggal</th><th>Langkah</th><th>Tidur</th><th>Hidrasi</th><th>Stres</th><th>BB</th><th>Berat</th><th>Mood</th><th></th></tr></thead>
      <tbody>
      <?php foreach($rows as $r): ?>
        <tr>
          <td><?= htmlspecialchars($r['tanggal']) ?></td>
          <td><?= htmlspecialchars($r['langkah']??'-') ?></td>
          <td><?= $r['tidur_menit']?floor($r['tidur_menit']/60).'j '.($r['tidur_menit']%60).'m':'-' ?></td>
          <td><?= htmlspecialchars($r['hidrasi_ml']??'-') ?> ml</td>
          <td><?= htmlspecialchars($r['stres_skor']??'-') ?></td>
          <td><?= htmlspecialchars($r['body_battery']??'-') ?></td>
          <td><?= htmlspecialchars($r['berat_kg']??'-') ?></td>
          <td><?= htmlspecialchars($r['mood']??'-') ?></td>
          <td class="text-end">
            <form method="post" class="d-inline" onsubmit="return confirm('Hapus?')">
              <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
              <input type="hidden" name="_action" value="delete">
              <input type="hidden" name="id" value="<?= $r['id'] ?>">
              <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
            </form>
          </td>
        </tr>
      <?php endforeach; if(!$rows): ?><tr><td colspan="9" class="text-center text-muted py-3">Belum ada catatan.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const ctx = document.getElementById('ghChart');
new Chart(ctx, {
  type:'line',
  data:{
    labels: <?= json_encode($labels) ?>,
    datasets:[
      {label:'Langkah', data: <?= json_encode($dLang) ?>, borderColor:'#0d6efd', yAxisID:'y1', tension:.3},
      {label:'Tidur (jam)', data: <?= json_encode($dTidur) ?>, borderColor:'#6610f2', yAxisID:'y2', tension:.3},
      {label:'Hidrasi (ml)', data: <?= json_encode($dHid) ?>, borderColor:'#0dcaf0', yAxisID:'y1', tension:.3},
      {label:'Stres', data: <?= json_encode($dStres) ?>, borderColor:'#ffc107', yAxisID:'y2', tension:.3},
      {label:'Body Battery', data: <?= json_encode($dBB) ?>, borderColor:'#198754', yAxisID:'y2', tension:.3},
      {label:'Berat (kg)', data: <?= json_encode($dBerat) ?>, borderColor:'#dc3545', yAxisID:'y2', tension:.3},
    ]
  },
  options:{responsive:true, interaction:{mode:'index',intersect:false},
    scales:{
      y1:{type:'linear', position:'left', title:{display:true,text:'Langkah / Hidrasi'}},
      y2:{type:'linear', position:'right', grid:{drawOnChartArea:false}, title:{display:true,text:'Jam / Skor / kg'}}
    }
  }
});
</script>
<?php include __DIR__.'/includes/footer.php'; ?>
