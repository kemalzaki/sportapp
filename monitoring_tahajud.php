<?php
/**
 * monitoring_tahajud.php — Revisi 27 Juni 2026
 * Halaman terpisah untuk Monitoring Tahajud & Duha Bulanan.
 * Dipindah dari panel inline islami.php agar islami.php tetap ringkas
 * dan menu monitoring menjadi 1 entry (di bawah "Shalat Duha & Tahajud").
 */
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
send_security_headers(); require_login();
$pageTitle = 'Monitoring Tahajud & Duha Bulanan';
$u = current_user();

if ($_SERVER['REQUEST_METHOD']==='POST' && $u && ($_POST['_action'] ?? '')==='ssunnah_toggle') {
    csrf_check();
    header('Content-Type: application/json');
    $jenis = ($_POST['jenis'] ?? '') === 'duha' ? 'duha' : 'tahajud';
    $tgl   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['tanggal'] ?? '') ? $_POST['tanggal'] : date('Y-m-d');
    $rakaat= max(0,min(20,(int)($_POST['rakaat'] ?? 2)));
    $cat   = mb_substr(trim($_POST['catatan'] ?? ''),0,500);
    try {
        $exists = (int)db_val("SELECT COUNT(*) FROM shalat_sunnah_log WHERE user_id=$1 AND jenis=$2 AND tanggal=$3",
            [(int)$u['id'],$jenis,$tgl]);
        if ($exists) {
            db_exec("DELETE FROM shalat_sunnah_log WHERE user_id=$1 AND jenis=$2 AND tanggal=$3",
                [(int)$u['id'],$jenis,$tgl]);
            echo json_encode(['ok'=>true,'state'=>'off']); exit;
        } else {
            db_exec("INSERT INTO shalat_sunnah_log(user_id,jenis,tanggal,rakaat,catatan) VALUES($1,$2,$3,$4,$5)",
                [(int)$u['id'],$jenis,$tgl,$rakaat,$cat ?: null]);
            echo json_encode(['ok'=>true,'state'=>'on']); exit;
        }
    } catch (Throwable $e) { echo json_encode(['ok'=>false,'err'=>'db']); exit; }
}

$ssBulan = isset($_GET['ssbulan']) && preg_match('/^\d{4}-\d{2}$/', $_GET['ssbulan']) ? $_GET['ssbulan'] : date('Y-m');
$ssStart = $ssBulan.'-01';
$ssEnd   = date('Y-m-t', strtotime($ssStart));
$ssLogs  = $u ? db_all("SELECT jenis,tanggal,rakaat,catatan FROM shalat_sunnah_log
                        WHERE user_id=$1 AND tanggal BETWEEN $2 AND $3",
                       [(int)$u['id'],$ssStart,$ssEnd]) : [];
$ssMap = ['tahajud'=>[], 'duha'=>[]];
foreach ($ssLogs as $r) { $ssMap[$r['jenis']][$r['tanggal']] = $r; }
$ssCount = ['tahajud'=>count($ssMap['tahajud']), 'duha'=>count($ssMap['duha'])];
$ssDays  = (int)date('t', strtotime($ssStart));
$ssToday = date('Y-m-d');
$ssPrev  = date('Y-m', strtotime($ssStart.' -1 month'));
$ssNext  = date('Y-m', strtotime($ssStart.' +1 month'));

include __DIR__.'/includes/header.php'; ?>

<nav aria-label="breadcrumb" class="mb-2">
  <ol class="breadcrumb small mb-0">
    <li class="breadcrumb-item"><a href="/islami.php">Hub Islami</a></li>
    <li class="breadcrumb-item active">Monitoring Tahajud &amp; Duha Bulanan</li>
  </ol>
</nav>

<h2 class="mb-3"><i class="bi bi-calendar2-check-fill text-info"></i> Monitoring Tahajud &amp; Duha Bulanan</h2>
<p class="text-muted small">Catat shalat sunnah harian Anda — Tahajud dan Duha — agar terlihat ringkasannya per bulan.</p>

<div class="card shadow-sm mb-3 border-info" id="ssMonitor">
  <div class="card-header bg-info-subtle text-info-emphasis d-flex flex-wrap justify-content-between align-items-center gap-2">
    <span><i class="bi bi-calendar2-check-fill"></i> <strong>Rekap Bulan: <?= htmlspecialchars(date('F Y', strtotime($ssStart))) ?></strong></span>
    <form method="get" class="d-flex align-items-center gap-1">
      <a class="btn btn-sm btn-outline-secondary" href="?ssbulan=<?= $ssPrev ?>">&laquo;</a>
      <input type="month" name="ssbulan" value="<?= htmlspecialchars($ssBulan) ?>" class="form-control form-control-sm" onchange="this.form.submit()" style="width:auto">
      <a class="btn btn-sm btn-outline-secondary" href="?ssbulan=<?= $ssNext ?>">&raquo;</a>
    </form>
  </div>
  <div class="card-body">
    <div class="row g-2 mb-2">
      <div class="col-6 col-md-3">
        <div class="border rounded p-2 text-center">
          <div class="small text-muted">Tahajud bulan ini</div>
          <div class="h4 mb-0 text-primary"><?= $ssCount['tahajud'] ?> / <?= $ssDays ?></div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="border rounded p-2 text-center">
          <div class="small text-muted">Duha bulan ini</div>
          <div class="h4 mb-0 text-warning"><?= $ssCount['duha'] ?> / <?= $ssDays ?></div>
        </div>
      </div>
      <div class="col-12 col-md-6 small text-muted align-self-center">
        Klik 🌙 untuk <b>Tahajud</b>, ☀️ untuk <b>Duha</b>. Klik ulang untuk hapus catatan.
      </div>
    </div>
    <div class="table-responsive">
      <table class="table table-sm table-bordered align-middle text-center mb-0 ssmonth-table">
        <thead class="table-light"><tr><th>Tgl</th><th>Tahajud 🌙</th><th>Duha ☀️</th><th class="d-none d-md-table-cell">Catatan</th></tr></thead>
        <tbody>
        <?php for ($d=1; $d<=$ssDays; $d++):
            $tgl = sprintf('%s-%02d', $ssBulan, $d);
            $tj  = $ssMap['tahajud'][$tgl] ?? null;
            $dh  = $ssMap['duha'][$tgl] ?? null;
            $isToday = $tgl === $ssToday;
            $isFuture= $tgl > $ssToday;
        ?>
          <tr class="<?= $isToday?'table-warning':'' ?>">
            <td class="fw-semibold"><?= $d ?><?php if($isToday): ?> <small class="badge bg-warning text-dark">Hari ini</small><?php endif; ?></td>
            <td>
              <button type="button" class="btn btn-sm <?= $tj?'btn-primary':'btn-outline-secondary' ?> ss-btn"
                      data-jenis="tahajud" data-tgl="<?= $tgl ?>" <?= $isFuture?'disabled':'' ?>
                      title="<?= $tj?'Sudah dicatat (klik untuk hapus)':'Catat shalat Tahajud' ?>">
                <?= $tj ? '<i class="bi bi-check-circle-fill"></i> '.((int)$tj['rakaat']).' rkt' : '<i class="bi bi-moon-stars"></i>' ?>
              </button>
            </td>
            <td>
              <button type="button" class="btn btn-sm <?= $dh?'btn-warning text-dark':'btn-outline-secondary' ?> ss-btn"
                      data-jenis="duha" data-tgl="<?= $tgl ?>" <?= $isFuture?'disabled':'' ?>
                      title="<?= $dh?'Sudah dicatat (klik untuk hapus)':'Catat shalat Duha' ?>">
                <?= $dh ? '<i class="bi bi-check-circle-fill"></i> '.((int)$dh['rakaat']).' rkt' : '<i class="bi bi-sun"></i>' ?>
              </button>
            </td>
            <td class="d-none d-md-table-cell small text-muted text-start">
              <?php if($tj && !empty($tj['catatan'])): ?>🌙 <?= htmlspecialchars($tj['catatan']) ?><br><?php endif; ?>
              <?php if($dh && !empty($dh['catatan'])): ?>☀️ <?= htmlspecialchars($dh['catatan']) ?><?php endif; ?>
            </td>
          </tr>
        <?php endfor; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<script>
(function(){
  document.querySelectorAll('.ss-btn').forEach(function(b){
    b.addEventListener('click', async function(){
      var rakaat = prompt('Berapa rakaat ' + b.dataset.jenis + ' tanggal ' + b.dataset.tgl + '? (kosongkan untuk hapus catatan)', '2');
      if (rakaat === null) return;
      var fd = new FormData();
      fd.append('csrf','<?= csrf_token() ?>');
      fd.append('_action','ssunnah_toggle');
      fd.append('jenis', b.dataset.jenis);
      fd.append('tanggal', b.dataset.tgl);
      fd.append('rakaat', rakaat || '2');
      b.disabled = true;
      try {
        var r = await fetch('/monitoring_tahajud.php', {method:'POST', body:fd, credentials:'same-origin'});
        var j = await r.json();
        if (j.ok) location.reload();
        else alert('Gagal: '+(j.err||'?'));
      } catch(e){ alert('Error: '+e.message); }
      b.disabled = false;
    });
  });
})();
</script>

<?php include __DIR__.'/includes/footer.php'; ?>
