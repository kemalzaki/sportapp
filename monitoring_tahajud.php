<?php
/**
 * monitoring_tahajud.php — Revisi (Juli 2026)
 * - Tambah kolom "Evaluasi" (catatan harian bebas) per tanggal.
 * - Tabel bulan dibungkus wrapper scroll (max-height) agar tidak terlalu panjang.
 *
 * SQL tambahan yang diperlukan (PostgreSQL):
 *   CREATE TABLE IF NOT EXISTS shalat_evaluasi_harian (
 *     user_id  INT NOT NULL,
 *     tanggal  DATE NOT NULL,
 *     evaluasi TEXT,
 *     updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
 *     PRIMARY KEY (user_id, tanggal)
 *   );
 */
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
require __DIR__.'/includes/islami_helpers.php';
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

// Revisi Juli 2026 — simpan evaluasi harian.
if ($_SERVER['REQUEST_METHOD']==='POST' && $u && ($_POST['_action'] ?? '')==='save_evaluasi') {
    csrf_check();
    header('Content-Type: application/json');
    $tgl = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['tanggal'] ?? '') ? $_POST['tanggal'] : date('Y-m-d');
    $ev  = mb_substr(trim($_POST['evaluasi'] ?? ''), 0, 1000);
    try {
        if ($ev === '') {
            db_exec("DELETE FROM shalat_evaluasi_harian WHERE user_id=$1 AND tanggal=$2",
                [(int)$u['id'], $tgl]);
        } else {
            db_exec("INSERT INTO shalat_evaluasi_harian(user_id,tanggal,evaluasi) VALUES($1,$2,$3)
                     ON CONFLICT (user_id,tanggal) DO UPDATE SET evaluasi=EXCLUDED.evaluasi, updated_at=NOW()",
                [(int)$u['id'], $tgl, $ev]);
        }
        echo json_encode(['ok'=>true]); exit;
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

// Revisi Juli 2026 — muat evaluasi bulan berjalan.
$evalMap = [];
if ($u) {
    try {
        $rows = db_all("SELECT tanggal, evaluasi FROM shalat_evaluasi_harian
                        WHERE user_id=$1 AND tanggal BETWEEN $2 AND $3",
                       [(int)$u['id'], $ssStart, $ssEnd]);
        foreach ($rows as $r) { $evalMap[$r['tanggal']] = $r['evaluasi']; }
    } catch (Throwable $e) { /* tabel belum dibuat — abaikan */ }
}

include __DIR__.'/includes/header.php'; ?>

<nav aria-label="breadcrumb" class="mb-2">
  <ol class="breadcrumb small mb-0">
    <li class="breadcrumb-item"><a href="/islami.php">Hub Islami</a></li>
    <li class="breadcrumb-item active">Monitoring Tahajud &amp; Duha Bulanan</li>
  </ol>
</nav>

<h2 class="mb-3"><i class="bi bi-calendar2-check-fill text-info"></i> Monitoring Tahajud &amp; Duha Bulanan</h2>
<p class="text-muted small">Catat shalat sunnah harian Anda — Tahajud dan Duha — beserta evaluasi singkat agar terlihat progressnya per bulan.</p>

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
        Klik 🌙 untuk <b>Tahajud</b>, ☀️ untuk <b>Duha</b>. Isi kolom <b>Evaluasi</b> lalu keluar dari kotak untuk menyimpan otomatis.
      </div>
    </div>

    <!-- Revisi Juli 2026 (R2) — wrapper tabel scroll horizontal & vertikal, kolom evaluasi diperlebar. -->
    <div class="table-responsive ssmonth-scroll" style="max-height:600px; overflow:auto;">
      <table class="table table-sm table-bordered align-middle text-center mb-0 ssmonth-table" style="min-width:900px;">
        <thead class="table-light" style="position:sticky; top:0; z-index:2;">
          <tr>
            <th style="width:60px">Tgl</th>
            <th style="width:150px">Tgl Hijriyah</th>
            <th style="width:110px">Tahajud 🌙</th>
            <th style="width:90px">Duha ☀️</th>
            <th style="min-width:160px">Catatan</th>
            <th style="min-width:380px">Evaluasi</th>
          </tr>
        </thead>
        <tbody>
        <?php for ($d=1; $d<=$ssDays; $d++):
            $tgl = sprintf('%s-%02d', $ssBulan, $d);
            $tj  = $ssMap['tahajud'][$tgl] ?? null;
            $dh  = $ssMap['duha'][$tgl] ?? null;
            $isToday = $tgl === $ssToday;
            $isFuture= $tgl > $ssToday;
            $ev  = $evalMap[$tgl] ?? '';
            // Revisi Nov 2026 — tampilkan tanggal Hijriyah paralel per baris.
            $hij = masehi_ke_hijriyah(new DateTime($tgl));
            $hijLabel = $hij['hari'].' '.hijriyah_nama_bulan($hij['bulan']).' '.$hij['tahun'].' H';
        ?>
          <tr class="<?= $isToday?'table-warning':'' ?>">
            <td class="fw-semibold"><?= $d ?><?php if($isToday): ?><br><small class="badge bg-warning text-dark">Hari ini</small><?php endif; ?></td>
            <td class="small text-muted"><?= htmlspecialchars($hijLabel) ?></td>
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
            <td class="small text-muted text-start">
              <?php if($tj && !empty($tj['catatan'])): ?>🌙 <?= htmlspecialchars($tj['catatan']) ?><br><?php endif; ?>
              <?php if($dh && !empty($dh['catatan'])): ?>☀️ <?= htmlspecialchars($dh['catatan']) ?><?php endif; ?>
            </td>
            <td class="text-start">
              <textarea class="form-control form-control-sm ss-eval" rows="2" style="min-width:340px;"
                        data-tgl="<?= $tgl ?>" <?= $isFuture?'disabled':'' ?>
                        placeholder="Evaluasi hari ini (khusyuk? sempat qiyamul lail?)"><?= htmlspecialchars($ev) ?></textarea>
              <div class="small text-success ss-eval-status" data-for="<?= $tgl ?>" style="min-height:1em"></div>
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
      var jenisLabel = b.dataset.jenis === 'duha' ? 'Duha' : 'Tahajud';
      var html =
        '<div class="text-start">' +
          '<label class="form-label small mb-1">Rakaat</label>' +
          '<input id="ss_rakaat" type="number" min="0" max="20" value="2" class="swal2-input" style="margin:.25rem 0">' +
          '<label class="form-label small mb-1 mt-2">Keterangan (opsional)</label>' +
          '<textarea id="ss_catatan" class="swal2-textarea" placeholder="mis. di rumah, berjamaah, doa khusus…" style="margin:.25rem 0"></textarea>' +
          '<div class="form-text small">Kosongkan rakaat = hapus catatan tanggal ini.</div>' +
        '</div>';
      var rakaat = '', catatan = '';
      if (typeof Swal !== 'undefined') {
        var r = await Swal.fire({
          title: jenisLabel + ' • ' + b.dataset.tgl,
          html: html, showCancelButton: true,
          confirmButtonText: 'Simpan', cancelButtonText: 'Batal',
          confirmButtonColor: '#0ea5e9', focusConfirm: false,
          preConfirm: function(){
            return { rakaat: document.getElementById('ss_rakaat').value,
                     catatan: document.getElementById('ss_catatan').value };
          }
        });
        if (!r.isConfirmed) return;
        rakaat  = r.value.rakaat; catatan = r.value.catatan;
      } else {
        rakaat = prompt('Berapa rakaat ' + jenisLabel + ' ' + b.dataset.tgl + '? (kosongkan untuk hapus)', '2');
        if (rakaat === null) return;
        catatan = prompt('Keterangan (opsional):', '') || '';
      }
      var fd = new FormData();
      fd.append('csrf','<?= csrf_token() ?>');
      fd.append('_action','ssunnah_toggle');
      fd.append('jenis', b.dataset.jenis);
      fd.append('tanggal', b.dataset.tgl);
      fd.append('rakaat', rakaat || '2');
      fd.append('catatan', catatan || '');
      b.disabled = true;
      try {
        var r2 = await fetch('/monitoring_tahajud.php', {method:'POST', body:fd, credentials:'same-origin'});
        var j  = await r2.json();
        if (j.ok) location.reload();
        else if (typeof Swal !== 'undefined') Swal.fire('Gagal', j.err||'?', 'error');
        else alert('Gagal: '+(j.err||'?'));
      } catch(e){
        if (typeof Swal !== 'undefined') Swal.fire('Error', e.message, 'error');
        else alert('Error: '+e.message);
      }
      b.disabled = false;
    });
  });

  // Revisi Juli 2026 — auto-save Evaluasi saat blur / debounce ketik.
  var CSRF = '<?= csrf_token() ?>';
  document.querySelectorAll('.ss-eval').forEach(function(ta){
    var tgl = ta.dataset.tgl;
    var stat = document.querySelector('.ss-eval-status[data-for="'+tgl+'"]');
    var last = ta.value;
    var t = null;
    function save(){
      if (ta.value === last) return;
      last = ta.value;
      stat.textContent = 'menyimpan…'; stat.className = 'small text-muted ss-eval-status';
      var fd = new FormData();
      fd.append('csrf', CSRF);
      fd.append('_action', 'save_evaluasi');
      fd.append('tanggal', tgl);
      fd.append('evaluasi', ta.value);
      fetch('/monitoring_tahajud.php', {method:'POST', body:fd, credentials:'same-origin'})
        .then(function(r){ return r.json(); })
        .then(function(j){
          if (j && j.ok) { stat.textContent = 'tersimpan ✓'; stat.className='small text-success ss-eval-status'; }
          else { stat.textContent = 'gagal simpan'; stat.className='small text-danger ss-eval-status'; }
          setTimeout(function(){ stat.textContent=''; }, 1500);
        })
        .catch(function(){ stat.textContent = 'gagal simpan'; stat.className='small text-danger ss-eval-status'; });
    }
    ta.addEventListener('blur', save);
    ta.addEventListener('input', function(){ clearTimeout(t); t=setTimeout(save, 900); });
  });
})();
</script>

<?php include __DIR__.'/includes/footer.php'; ?>
