<?php
// gaya_hidup.php — Kalkulator Gaya Hidup
// Revisi 12 Juni 2026: mekanisme diubah menjadi Pola Makan, Pola Tidur,
// Mood & Aspek Psikologi. Tren diperbaiki (spanGaps, multi-axis bermakna,
// format tanggal, empty-state, kategori warna).
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
send_security_headers(); enforce_session_timeout();
$pageTitle = 'Kalkulator Gaya Hidup';
$u = current_user();
if (!$u) { header('Location: /login.php'); exit; }
$uid = (int)$u['id'];

// --- Pastikan kolom baru tersedia (idempotent). Data lama TIDAK dihapus. ---
try {
  db_exec("ALTER TABLE gaya_hidup_log
           ADD COLUMN IF NOT EXISTS pola_makan VARCHAR(20),
           ADD COLUMN IF NOT EXISTS porsi_makan SMALLINT,
           ADD COLUMN IF NOT EXISTS minum_air_gelas SMALLINT,
           ADD COLUMN IF NOT EXISTS pola_tidur VARCHAR(20),
           ADD COLUMN IF NOT EXISTS kualitas_tidur SMALLINT,
           ADD COLUMN IF NOT EXISTS mood_skor SMALLINT,
           ADD COLUMN IF NOT EXISTS kecemasan SMALLINT,
           ADD COLUMN IF NOT EXISTS motivasi SMALLINT,
           ADD COLUMN IF NOT EXISTS fokus SMALLINT,
           ADD COLUMN IF NOT EXISTS catatan_psikologi TEXT");
} catch (Throwable $e) { /* abaikan jika belum punya hak ALTER */ }

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    $a = $_POST['_action'] ?? '';
    if ($a==='save') {
        $tgl   = $_POST['tanggal'] ?: date('Y-m-d');
        $pi    = function($k){ return isset($_POST[$k]) && $_POST[$k]!=='' ? (int)$_POST[$k] : null; };
        $ps    = function($k){ return isset($_POST[$k]) && $_POST[$k]!=='' ? trim($_POST[$k]) : null; };
        $clamp = function($v,$lo,$hi){ if($v===null) return null; $v=(int)$v; return max($lo,min($hi,$v)); };

        $pola_makan       = $ps('pola_makan');
        $porsi_makan      = $clamp($pi('porsi_makan'), 0, 12);
        $minum_air_gelas  = $clamp($pi('minum_air_gelas'), 0, 30);
        $pola_tidur       = $ps('pola_tidur');
        $kualitas_tidur   = $clamp($pi('kualitas_tidur'), 1, 5);
        $tidur_menit      = $clamp($pi('tidur_menit'), 0, 24*60);
        $mood             = $ps('mood');
        $mood_skor        = $clamp($pi('mood_skor'), 1, 10);
        $stres            = $clamp($pi('stres'), 0, 100);
        $kecemasan        = $clamp($pi('kecemasan'), 1, 10);
        $motivasi         = $clamp($pi('motivasi'), 1, 10);
        $fokus            = $clamp($pi('fokus'), 1, 10);
        $catatan          = $ps('catatan');
        $catatan_psi      = $ps('catatan_psikologi');

        db_exec("INSERT INTO gaya_hidup_log
                 (user_id,tanggal,pola_makan,porsi_makan,minum_air_gelas,
                  pola_tidur,kualitas_tidur,tidur_menit,
                  mood,mood_skor,stres_skor,kecemasan,motivasi,fokus,
                  catatan,catatan_psikologi)
                 VALUES($1,$2,$3,$4,$5,$6,$7,$8,$9,$10,$11,$12,$13,$14,$15,$16)
                 ON CONFLICT (user_id,tanggal) DO UPDATE SET
                   pola_makan=EXCLUDED.pola_makan,
                   porsi_makan=EXCLUDED.porsi_makan,
                   minum_air_gelas=EXCLUDED.minum_air_gelas,
                   pola_tidur=EXCLUDED.pola_tidur,
                   kualitas_tidur=EXCLUDED.kualitas_tidur,
                   tidur_menit=EXCLUDED.tidur_menit,
                   mood=EXCLUDED.mood,
                   mood_skor=EXCLUDED.mood_skor,
                   stres_skor=EXCLUDED.stres_skor,
                   kecemasan=EXCLUDED.kecemasan,
                   motivasi=EXCLUDED.motivasi,
                   fokus=EXCLUDED.fokus,
                   catatan=EXCLUDED.catatan,
                   catatan_psikologi=EXCLUDED.catatan_psikologi,
                   updated_at=now()",
            [$uid,$tgl,$pola_makan,$porsi_makan,$minum_air_gelas,
             $pola_tidur,$kualitas_tidur,$tidur_menit,
             $mood,$mood_skor,$stres,$kecemasan,$motivasi,$fokus,
             $catatan,$catatan_psi]);
        $_SESSION['flash_ok'] = "Data $tgl tersimpan.";
    } elseif ($a==='delete') {
        db_exec("DELETE FROM gaya_hidup_log WHERE id=$1 AND user_id=$2", [(int)$_POST['id'],$uid]);
    }
    header('Location: gaya_hidup.php'); exit;
}

$today = date('Y-m-d');
$todayRow = db_one("SELECT * FROM gaya_hidup_log WHERE user_id=$1 AND tanggal=$2",[$uid,$today]);
$rows = db_all("SELECT * FROM gaya_hidup_log WHERE user_id=$1
                AND tanggal >= (CURRENT_DATE - INTERVAL '30 days')
                ORDER BY tanggal DESC",[$uid]);
$stats = db_one("SELECT
   ROUND(AVG(mood_skor)::numeric,1) AS avg_mood,
   ROUND(AVG(kualitas_tidur)::numeric,1) AS avg_tidur_q,
   ROUND(AVG(tidur_menit)::numeric/60.0,1) AS avg_tidur_jam,
   ROUND(AVG(stres_skor)::numeric,1) AS avg_stres,
   ROUND(AVG(kecemasan)::numeric,1) AS avg_cemas,
   ROUND(AVG(motivasi)::numeric,1) AS avg_motiv,
   ROUND(AVG(fokus)::numeric,1) AS avg_fokus,
   COUNT(*) AS days
   FROM gaya_hidup_log
   WHERE user_id=$1 AND tanggal >= (CURRENT_DATE - INTERVAL '7 days')",[$uid]);
$ok = $_SESSION['flash_ok'] ?? null; unset($_SESSION['flash_ok']);
require_once __DIR__.'/includes/htmx.php'; htmx_layout_start($pageTitle ?? 'Gaya Hidup');

// Data chart 30 hari (asc) — fokus pada Pola Tidur, Mood & Psikologi
$chart = db_all("SELECT tanggal, kualitas_tidur, tidur_menit, mood_skor,
                        stres_skor, kecemasan, motivasi, fokus
                 FROM gaya_hidup_log
                 WHERE user_id=$1 AND tanggal >= (CURRENT_DATE - INTERVAL '30 days')
                 ORDER BY tanggal ASC",[$uid]);
$labels=[]; $dMood=[]; $dTidurQ=[]; $dTidurJam=[]; $dStres=[]; $dCemas=[]; $dMotiv=[]; $dFokus=[];
foreach($chart as $r){
  $labels[]    = date('d M', strtotime($r['tanggal']));
  $dMood[]     = isset($r['mood_skor'])     && $r['mood_skor']     !==null ? (int)$r['mood_skor']     : null;
  $dTidurQ[]   = isset($r['kualitas_tidur'])&& $r['kualitas_tidur']!==null ? (int)$r['kualitas_tidur']: null;
  $dTidurJam[] = isset($r['tidur_menit'])   && $r['tidur_menit']   !==null ? round($r['tidur_menit']/60.0,1) : null;
  $dStres[]    = isset($r['stres_skor'])    && $r['stres_skor']    !==null ? (int)$r['stres_skor']    : null;
  $dCemas[]    = isset($r['kecemasan'])     && $r['kecemasan']     !==null ? (int)$r['kecemasan']     : null;
  $dMotiv[]    = isset($r['motivasi'])      && $r['motivasi']      !==null ? (int)$r['motivasi']      : null;
  $dFokus[]    = isset($r['fokus'])         && $r['fokus']         !==null ? (int)$r['fokus']         : null;
}
$hasChartData = count(array_filter($chart, fn($r) =>
  $r['mood_skor']!==null || $r['kualitas_tidur']!==null || $r['stres_skor']!==null ||
  $r['kecemasan']!==null || $r['motivasi']!==null || $r['fokus']!==null || $r['tidur_menit']!==null
)) > 0;
?>
<nav aria-label="breadcrumb" class="mb-2"><ol class="breadcrumb small mb-0">
  <li class="breadcrumb-item"><a href="/index.php">Beranda</a></li>
  <li class="breadcrumb-item active">Kalkulator Gaya Hidup</li>
</ol></nav>

<h2 class="mb-1"><i class="bi bi-heart-pulse text-danger"></i> Kalkulator Gaya Hidup</h2>
<p class="text-muted small mb-3">Pencatatan harian: <b>Pola Makan</b>, <b>Pola Tidur</b>, <b>Mood</b>, dan <b>Aspek Psikologi</b> lainnya (stres, kecemasan, motivasi, fokus).</p>

<?php if($ok): ?><div class="alert alert-success py-2"><?= htmlspecialchars($ok) ?></div><?php endif; ?>

<div class="row g-2 mb-3">
  <?php
  $cards=[
    ['Rata Mood (1-10)',($stats['avg_mood']??'-'),'bi-emoji-smile','success'],
    ['Rata Kualitas Tidur (1-5)',($stats['avg_tidur_q']??'-'),'bi-moon-stars','indigo'],
    ['Rata Durasi Tidur (jam)',($stats['avg_tidur_jam']??'-'),'bi-clock-history','primary'],
    ['Rata Stres (0-100)',($stats['avg_stres']??'-'),'bi-emoji-frown','warning'],
    ['Rata Kecemasan (1-10)',($stats['avg_cemas']??'-'),'bi-emoji-dizzy','danger'],
    ['Rata Motivasi (1-10)',($stats['avg_motiv']??'-'),'bi-lightning-charge','info'],
    ['Rata Fokus (1-10)',($stats['avg_fokus']??'-'),'bi-bullseye','secondary'],
    ['Hari tercatat (7h)',(int)($stats['days']??0),'bi-calendar-check','dark'],
  ];
  foreach($cards as $c): ?>
    <div class="col-6 col-md-4 col-lg-3">
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
    <form method="post" class="row g-3">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="_action" value="save">

      <div class="col-md-3"><label class="form-label small">Tanggal</label>
        <input class="form-control" type="date" name="tanggal" value="<?= htmlspecialchars($todayRow['tanggal'] ?? $today) ?>" required></div>

      <!-- ============ POLA MAKAN ============ -->
      <div class="col-12"><h6 class="text-primary mb-1"><i class="bi bi-egg-fried"></i> Pola Makan</h6><hr class="mt-1 mb-2"></div>
      <div class="col-md-4"><label class="form-label small">Pola makan hari ini</label>
        <select class="form-select" name="pola_makan">
          <?php foreach(['','teratur','tidak_teratur','berlebih','kurang','puasa'] as $m): ?>
            <option value="<?= $m ?>" <?= ($todayRow['pola_makan']??'')===$m?'selected':'' ?>><?= $m?:'(pilih)' ?></option>
          <?php endforeach; ?>
        </select></div>
      <div class="col-md-4"><label class="form-label small">Jumlah porsi (kali makan)</label>
        <input class="form-control" type="number" min="0" max="12" name="porsi_makan" value="<?= htmlspecialchars($todayRow['porsi_makan'] ?? '') ?>"></div>
      <div class="col-md-4"><label class="form-label small">Minum air (gelas)</label>
        <input class="form-control" type="number" min="0" max="30" name="minum_air_gelas" value="<?= htmlspecialchars($todayRow['minum_air_gelas'] ?? '') ?>"></div>

      <!-- ============ POLA TIDUR ============ -->
      <div class="col-12"><h6 class="text-indigo text-primary mb-1"><i class="bi bi-moon-stars"></i> Pola Tidur</h6><hr class="mt-1 mb-2"></div>
      <div class="col-md-4"><label class="form-label small">Kategori pola tidur</label>
        <select class="form-select" name="pola_tidur">
          <?php foreach(['','cukup','kurang','berlebih','tidak_teratur'] as $m): ?>
            <option value="<?= $m ?>" <?= ($todayRow['pola_tidur']??'')===$m?'selected':'' ?>><?= $m?:'(pilih)' ?></option>
          <?php endforeach; ?>
        </select></div>
      <div class="col-md-4"><label class="form-label small">Kualitas tidur (1-5)</label>
        <input class="form-control" type="number" min="1" max="5" name="kualitas_tidur" value="<?= htmlspecialchars($todayRow['kualitas_tidur'] ?? '') ?>"></div>
      <div class="col-md-4"><label class="form-label small">Durasi tidur (menit)</label>
        <input class="form-control" type="number" min="0" name="tidur_menit" placeholder="cth 420 (7 jam)" value="<?= htmlspecialchars($todayRow['tidur_menit'] ?? '') ?>"></div>

      <!-- ============ MOOD & ASPEK PSIKOLOGI ============ -->
      <div class="col-12"><h6 class="text-danger mb-1"><i class="bi bi-emoji-smile"></i> Mood &amp; Aspek Psikologi</h6><hr class="mt-1 mb-2"></div>
      <div class="col-md-3"><label class="form-label small">Mood</label>
        <select class="form-select" name="mood">
          <?php foreach(['','Senang','Biasa','Lelah','Sedih','Marah','Cemas','Bersyukur'] as $m): ?>
            <option value="<?= $m ?>" <?= ($todayRow['mood']??'')===$m?'selected':'' ?>><?= $m?:'(pilih)' ?></option>
          <?php endforeach; ?>
        </select></div>
      <div class="col-md-3"><label class="form-label small">Skor Mood (1-10)</label>
        <input class="form-control" type="number" min="1" max="10" name="mood_skor" value="<?= htmlspecialchars($todayRow['mood_skor'] ?? '') ?>"></div>
      <div class="col-md-3"><label class="form-label small">Stres (0-100)</label>
        <input class="form-control" type="number" min="0" max="100" name="stres" value="<?= htmlspecialchars($todayRow['stres_skor'] ?? '') ?>"></div>
      <div class="col-md-3"><label class="form-label small">Kecemasan (1-10)</label>
        <input class="form-control" type="number" min="1" max="10" name="kecemasan" value="<?= htmlspecialchars($todayRow['kecemasan'] ?? '') ?>"></div>
      <div class="col-md-3"><label class="form-label small">Motivasi (1-10)</label>
        <input class="form-control" type="number" min="1" max="10" name="motivasi" value="<?= htmlspecialchars($todayRow['motivasi'] ?? '') ?>"></div>
      <div class="col-md-3"><label class="form-label small">Fokus (1-10)</label>
        <input class="form-control" type="number" min="1" max="10" name="fokus" value="<?= htmlspecialchars($todayRow['fokus'] ?? '') ?>"></div>

      <div class="col-md-6"><label class="form-label small">Catatan harian</label>
        <textarea class="form-control" name="catatan" rows="2" placeholder="opsional"><?= htmlspecialchars($todayRow['catatan'] ?? '') ?></textarea></div>
      <div class="col-md-6"><label class="form-label small">Catatan psikologis (refleksi)</label>
        <textarea class="form-control" name="catatan_psikologi" rows="2" placeholder="apa yang dirasakan, pemicu stres/cemas, hal yang disyukuri…"><?= htmlspecialchars($todayRow['catatan_psikologi'] ?? '') ?></textarea></div>

      <div class="col-12"><button class="btn btn-primary"><i class="bi bi-save"></i> Simpan</button></div>
    </form>
  </div>
</div>

<div class="card shadow-sm mb-3">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span><i class="bi bi-graph-up"></i> Tren 30 Hari — Pola Tidur, Mood &amp; Psikologi</span>
    <small class="text-muted">Sumbu kiri: skor 0-10 · Sumbu kanan: jam tidur &amp; stres</small>
  </div>
  <div class="card-body">
    <?php if(!$hasChartData): ?>
      <div class="text-center text-muted py-4">
        <i class="bi bi-bar-chart fs-1 d-block mb-2"></i>
        Belum ada data tren. Catat minimal 2 hari untuk melihat grafik.
      </div>
    <?php else: ?>
      <canvas id="ghChart" height="120"></canvas>
    <?php endif; ?>
  </div>
</div>

<div class="card shadow-sm">
  <div class="card-header"><i class="bi bi-clock-history"></i> Riwayat (30 hari terakhir)</div>
  <div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
      <thead class="table-light"><tr>
        <th>Tanggal</th><th>Pola Makan</th><th>Porsi</th><th>Air</th>
        <th>Pola Tidur</th><th>Kual.</th><th>Durasi</th>
        <th>Mood</th><th>Stres</th><th>Cemas</th><th>Motiv.</th><th>Fokus</th><th></th>
      </tr></thead>
      <tbody>
      <?php foreach($rows as $r): ?>
        <tr>
          <td><?= htmlspecialchars($r['tanggal']) ?></td>
          <td><?= htmlspecialchars($r['pola_makan']??'-') ?></td>
          <td><?= htmlspecialchars($r['porsi_makan']??'-') ?></td>
          <td><?= htmlspecialchars($r['minum_air_gelas']??'-') ?></td>
          <td><?= htmlspecialchars($r['pola_tidur']??'-') ?></td>
          <td><?= htmlspecialchars($r['kualitas_tidur']??'-') ?></td>
          <td><?= $r['tidur_menit']?floor($r['tidur_menit']/60).'j '.($r['tidur_menit']%60).'m':'-' ?></td>
          <td><?= htmlspecialchars($r['mood']??'-') ?><?php if($r['mood_skor']!==null): ?> <span class="badge bg-success-subtle text-success"><?= (int)$r['mood_skor'] ?>/10</span><?php endif; ?></td>
          <td><?= htmlspecialchars($r['stres_skor']??'-') ?></td>
          <td><?= htmlspecialchars($r['kecemasan']??'-') ?></td>
          <td><?= htmlspecialchars($r['motivasi']??'-') ?></td>
          <td><?= htmlspecialchars($r['fokus']??'-') ?></td>
          <td class="text-end">
            <form method="post" class="d-inline" onsubmit="return confirm('Hapus?')">
              <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
              <input type="hidden" name="_action" value="delete">
              <input type="hidden" name="id" value="<?= $r['id'] ?>">
              <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
            </form>
          </td>
        </tr>
      <?php endforeach; if(!$rows): ?>
        <tr><td colspan="13" class="text-center text-muted py-3">Belum ada catatan.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if($hasChartData): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function(){
  var ctx = document.getElementById('ghChart');
  if(!ctx) return;
  new Chart(ctx, {
    type:'line',
    data:{
      labels: <?= json_encode($labels) ?>,
      datasets:[
        {label:'Mood (1-10)',         data: <?= json_encode($dMood) ?>,    borderColor:'#16a34a', backgroundColor:'rgba(22,163,74,.12)', yAxisID:'y1', tension:.3, spanGaps:true, pointRadius:3},
        {label:'Kualitas Tidur (1-5)',data: <?= json_encode($dTidurQ) ?>,  borderColor:'#6366f1', backgroundColor:'rgba(99,102,241,.12)', yAxisID:'y1', tension:.3, spanGaps:true, pointRadius:3},
        {label:'Kecemasan (1-10)',    data: <?= json_encode($dCemas) ?>,   borderColor:'#dc2626', backgroundColor:'rgba(220,38,38,.10)',  yAxisID:'y1', tension:.3, spanGaps:true, pointRadius:3},
        {label:'Motivasi (1-10)',     data: <?= json_encode($dMotiv) ?>,   borderColor:'#0ea5e9', backgroundColor:'rgba(14,165,233,.10)', yAxisID:'y1', tension:.3, spanGaps:true, pointRadius:3},
        {label:'Fokus (1-10)',        data: <?= json_encode($dFokus) ?>,   borderColor:'#7c3aed', backgroundColor:'rgba(124,58,237,.10)', yAxisID:'y1', tension:.3, spanGaps:true, pointRadius:3},
        {label:'Durasi Tidur (jam)',  data: <?= json_encode($dTidurJam) ?>,borderColor:'#0d6efd', borderDash:[6,4], yAxisID:'y2', tension:.3, spanGaps:true, pointRadius:2},
        {label:'Stres (0-100)',       data: <?= json_encode($dStres) ?>,   borderColor:'#f59e0b', borderDash:[2,3], yAxisID:'y2', tension:.3, spanGaps:true, pointRadius:2}
      ]
    },
    options:{
      responsive:true,
      maintainAspectRatio:false,
      interaction:{mode:'index', intersect:false},
      plugins:{
        legend:{position:'bottom', labels:{boxWidth:12, font:{size:11}}},
        tooltip:{enabled:true}
      },
      scales:{
        y1:{type:'linear', position:'left',  min:0, max:10,  title:{display:true, text:'Skor 0-10 / 1-5'}, grid:{color:'rgba(0,0,0,.05)'}},
        y2:{type:'linear', position:'right', min:0, max:100, title:{display:true, text:'Jam tidur / Skor stres'}, grid:{drawOnChartArea:false}}
      }
    }
  });
})();
</script>
<?php endif; ?>
<?php htmx_layout_end(); ?>