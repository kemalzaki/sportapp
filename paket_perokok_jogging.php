<?php
/**
 * paket_perokok_jogging.php — Revisi Juli 2026 R10
 *
 * Paket Bugar khusus perokok (aktif / mantan). Fokus: kembalikan kapasitas
 * paru & jantung lewat program JOGGING bertahap. Struktur halaman &
 * monitoring dibuat mirip halaman /kalistenik.php (nav pill level,
 * tabel sesi, gerakan/tips, monitoring 30 hari, akses PRO/komunitas).
 *
 * SQL tambahan (PostgreSQL):
 *   CREATE TABLE IF NOT EXISTS perokok_jogging_log (
 *     user_id  INT  NOT NULL,
 *     tanggal  DATE NOT NULL,
 *     level    VARCHAR(20) NOT NULL,
 *     catatan  TEXT,
 *     created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
 *     PRIMARY KEY (user_id, tanggal, level)
 *   );
 */
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
require __DIR__.'/includes/info_publik.php';
require __DIR__.'/includes/paket_helpers.php';
send_security_headers(); enforce_session_timeout();
require_login();
$u = current_user();
$pageTitle = 'Paket Perokok — Jogging';

paket_require_or_lock('pro', $u, 'Paket Perokok — Jogging',
    'Program jogging bertahap untuk perokok / mantan perokok tersedia untuk paket PRO atau Komunitas.');
$pageSkeleton = 'grid';

// ============ Referensi gerakan / komponen sesi ============
$GERAKAN = [
  'warmup_walk' => [
    'nama'=>'Jalan Pemanasan','icon'=>'bi-person-walking','target'=>'Sirkulasi & pemanasan sendi',
    'tips'=>'Jalan santai 5 menit, ayun lengan, tarik napas dalam.',
    'langkah'=>[
      'Jalan santai selama 5 menit dengan langkah stabil.',
      'Ayunkan lengan lembut, buka bahu, longgarkan pinggul.',
      'Tarik napas dalam 4 hitungan – buang 6 hitungan.',
      'Jangan langsung joging tanpa pemanasan (paru perokok sensitif).',
    ],
    'yt_id'=>'gPIib9cgmU8',
  ],
  'brisk_walk' => [
    'nama'=>'Jalan Cepat','icon'=>'bi-speedometer2','target'=>'Kardio ringan',
    'tips'=>'Kecepatan cukup untuk sedikit ngos-ngosan tapi masih bisa bicara.',
    'langkah'=>[
      'Langkah lebih panjang & lebih cepat dari jalan santai.',
      'Pertahankan ritme napas hidung-mulut.',
      'Bila batuk / dada berat: turunkan ritme, JANGAN berhenti mendadak.',
    ],
    'yt_id'=>'enYITYwvPAQ',
  ],
  'jog_slow' => [
    'nama'=>'Jog Lambat','icon'=>'bi-heart-pulse','target'=>'Kapasitas paru',
    'tips'=>'Lari sangat pelan; kalau tidak bisa bicara → terlalu cepat.',
    'langkah'=>[
      'Lari perlahan (10–11 menit/km) dengan langkah pendek.',
      'Bahu rileks, jangan menggenggam tangan.',
      'Bernapas ritmis: 3 langkah tarik, 2 langkah buang.',
    ],
    'yt_id'=>'brFHyOtTwH4',
  ],
  'jog_easy' => [
    'nama'=>'Jog Ringan','edit'=>true,'icon'=>'bi-person-arms-up','target'=>'Efisiensi kardio',
    'tips'=>'Tempo santai berkelanjutan (7–8 menit/km).',
    'langkah'=>[
      'Pertahankan pace stabil; hindari akselerasi tajam.',
      'Fokus pada napas panjang – batuk normal untuk perokok, lakukan pull-back.',
      'Selesai sesi: JANGAN merokok minimal 60 menit.',
    ],
    'yt_id'=>'brFHyOtTwH4',
  ],
  'cooldown' => [
    'nama'=>'Pendinginan & Peregangan','icon'=>'bi-stopwatch','target'=>'Pemulihan',
    'tips'=>'Jalan 5 menit + peregangan betis, hamstring, dada.',
    'langkah'=>[
      'Jalan santai 5 menit hingga detak jantung turun.',
      'Peregangan betis, paha belakang, dada & bahu (masing-masing 20 detik).',
      'Minum air hangat, hindari rokok / kafein tinggi selama 1 jam.',
    ],
    'yt_id'=>'g_tea8ZNk5A',
  ],
  'breath_drill' => [
    'nama'=>'Latihan Napas Diafragma','icon'=>'bi-wind','target'=>'Kapasitas paru',
    'tips'=>'Tarik napas lewat hidung, kembungkan perut; buang panjang.',
    'langkah'=>[
      'Duduk / berdiri tegak, satu tangan di perut.',
      'Tarik napas hidung 4 detik → tahan 2 detik → buang mulut 6 detik.',
      'Ulangi 8–12 siklus. Sangat baik dilakukan pagi & sebelum jog.',
    ],
    'yt_id'=>'acUZdGd_3Dg',
  ],
];

// ============ Paket per level ============
$PAKET = [
  'pemula' => [
    'label' => 'Pemula (Baru Berhenti / Masih Merokok)',
    'durasi'=> '20 menit · 3x / minggu (Run-Walk)',
    'desc'  => 'Metode Run-Walk yang ramah untuk paru perokok. Fokus membangun kebiasaan tanpa memaksa napas.',
    'badge' => 'success',
    'sesi'  => [
      ['gerakan'=>'breath_drill','set'=>1,'rep'=>'2 menit',      'rest'=>'-'],
      ['gerakan'=>'warmup_walk', 'set'=>1,'rep'=>'5 menit',      'rest'=>'-'],
      ['gerakan'=>'brisk_walk',  'set'=>4,'rep'=>'2 menit',      'rest'=>'-'],
      ['gerakan'=>'jog_slow',    'set'=>4,'rep'=>'1 menit',      'rest'=>'2 menit jalan'],
      ['gerakan'=>'cooldown',    'set'=>1,'rep'=>'5 menit',      'rest'=>'-'],
    ],
  ],
  'menengah' => [
    'label' => 'Menengah (3–6 Bulan Konsisten)',
    'durasi'=> '30 menit · 4x / minggu',
    'desc'  => 'Perpanjang durasi jog dan kurangi jeda jalan. Perhatikan gejala (pusing, dada sesak) — turunkan intensitas bila perlu.',
    'badge' => 'warning',
    'sesi'  => [
      ['gerakan'=>'breath_drill','set'=>1,'rep'=>'3 menit',      'rest'=>'-'],
      ['gerakan'=>'warmup_walk', 'set'=>1,'rep'=>'5 menit',      'rest'=>'-'],
      ['gerakan'=>'brisk_walk',  'set'=>1,'rep'=>'3 menit',      'rest'=>'-'],
      ['gerakan'=>'jog_slow',    'set'=>3,'rep'=>'3 menit',      'rest'=>'1 menit jalan'],
      ['gerakan'=>'jog_easy',    'set'=>2,'rep'=>'4 menit',      'rest'=>'1 menit jalan'],
      ['gerakan'=>'cooldown',    'set'=>1,'rep'=>'5 menit',      'rest'=>'-'],
    ],
  ],
  'lanjutan' => [
    'label' => 'Lanjutan (>6 Bulan Bebas Rokok Aktif)',
    'durasi'=> '45 menit · 5x / minggu',
    'desc'  => 'Jog kontinu untuk paru yang sudah pulih sebagian. Hindari sprint eksplosif; fokus daya tahan.',
    'badge' => 'danger',
    'sesi'  => [
      ['gerakan'=>'breath_drill','set'=>1,'rep'=>'3 menit',      'rest'=>'-'],
      ['gerakan'=>'warmup_walk', 'set'=>1,'rep'=>'5 menit',      'rest'=>'-'],
      ['gerakan'=>'jog_easy',    'set'=>1,'rep'=>'25–30 menit',  'rest'=>'-'],
      ['gerakan'=>'brisk_walk',  'set'=>1,'rep'=>'3 menit',      'rest'=>'-'],
      ['gerakan'=>'cooldown',    'set'=>1,'rep'=>'5–7 menit',    'rest'=>'-'],
    ],
  ],
];

$level = $_GET['lvl'] ?? 'pemula';
if (!isset($PAKET[$level])) $level = 'pemula';
$cur = $PAKET[$level];

// ============ Handler monitoring toggle ============
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['_action'] ?? '')==='pjog_log_toggle') {
    csrf_check();
    header('Content-Type: application/json');
    $lv  = in_array($_POST['level'] ?? '', array_keys($PAKET), true) ? $_POST['level'] : 'pemula';
    $tgl = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['tanggal'] ?? '') ? $_POST['tanggal'] : date('Y-m-d');
    $cat = mb_substr(trim($_POST['catatan'] ?? ''), 0, 300);
    try {
        // Auto-create tabel jika belum ada (aman untuk instal baru / lokal).
        db_exec("CREATE TABLE IF NOT EXISTS perokok_jogging_log (
                    user_id INT NOT NULL,
                    tanggal DATE NOT NULL,
                    level VARCHAR(20) NOT NULL,
                    catatan TEXT,
                    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                    PRIMARY KEY(user_id, tanggal, level)
                 )");
        $has = (int)db_val("SELECT COUNT(*) FROM perokok_jogging_log WHERE user_id=$1 AND tanggal=$2 AND level=$3",
            [(int)$u['id'], $tgl, $lv]);
        if ($has) {
            db_exec("DELETE FROM perokok_jogging_log WHERE user_id=$1 AND tanggal=$2 AND level=$3",
                [(int)$u['id'], $tgl, $lv]);
            echo json_encode(['ok'=>true,'state'=>'off']); exit;
        }
        db_exec("INSERT INTO perokok_jogging_log(user_id,tanggal,level,catatan) VALUES($1,$2,$3,$4)",
            [(int)$u['id'], $tgl, $lv, $cat ?: null]);
        echo json_encode(['ok'=>true,'state'=>'on']); exit;
    } catch (Throwable $e) {
        echo json_encode(['ok'=>false,'err'=>'db']); exit;
    }
}

$pjStart = date('Y-m-d', strtotime('-29 days'));
$pjToday = date('Y-m-d');
$pjLogs = [];
try {
    db_exec("CREATE TABLE IF NOT EXISTS perokok_jogging_log (
                user_id INT NOT NULL, tanggal DATE NOT NULL,
                level VARCHAR(20) NOT NULL, catatan TEXT,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                PRIMARY KEY(user_id, tanggal, level)
             )");
    $pjLogs = db_all("SELECT tanggal::text AS tanggal, level, catatan
                      FROM perokok_jogging_log
                      WHERE user_id=$1 AND tanggal BETWEEN $2 AND $3
                      ORDER BY tanggal DESC, level",
                     [(int)$u['id'], $pjStart, $pjToday]);
} catch (Throwable $e) { $pjLogs = []; }
$pjDoneToday = [];
foreach ($pjLogs as $r) {
    if ($r['tanggal'] === $pjToday) $pjDoneToday[$r['level']] = $r['catatan'];
}
$pjCountLevel = ['pemula'=>0,'menengah'=>0,'lanjutan'=>0];
foreach ($pjLogs as $r) { if(isset($pjCountLevel[$r['level']])) $pjCountLevel[$r['level']]++; }

include __DIR__.'/includes/header.php'; ?>

<link rel="stylesheet" href="assets/css/sport-islami.css">

<div class="hero-sport-islami mb-3" style="background:linear-gradient(135deg,#7f1d1d,#0f172a);color:#fff;padding:1.25rem;border-radius:12px;">
  <div class="hero-overlay">
    <h1 class="h4 mb-1"><i class="bi bi-lungs-fill"></i> Paket Perokok — Jogging</h1>
    <p class="small mb-0 opacity-85">Program joging bertahap untuk perokok / mantan perokok. Pulihkan paru & jantung secara aman.</p>
  </div>
</div>

<div class="alert alert-warning small">
  <i class="bi bi-shield-exclamation"></i> <strong>Perhatian:</strong>
  Bila muncul <strong>nyeri dada, pusing berat, mual, atau napas seperti tersedak</strong>, hentikan sesi & konsultasikan ke dokter. Rekomendasi kuat: kurangi / berhenti merokok agar hasil program maksimal.
</div>

<?php ip_card_open('Paket Perokok — Jogging', 'bi-lungs-fill'); ?>

<p class="text-muted small mb-3">
  Program <strong>jogging bertahap</strong> khusus untuk perokok aktif maupun mantan perokok.
  Pilih level sesuai kondisi paru dan pengalaman berlari Anda. Sesi selalu diawali pemanasan &
  latihan napas diafragma untuk membantu efisiensi oksigen.
</p>

<ul class="nav nav-pills mb-3 gap-2 flex-wrap">
  <?php foreach($PAKET as $k=>$p): ?>
    <li class="nav-item">
      <a class="nav-link <?= $k===$level?'active':'' ?>" href="?lvl=<?= $k ?>">
        <?= htmlspecialchars($p['label']) ?>
      </a>
    </li>
  <?php endforeach; ?>
</ul>

<div class="card shadow-sm mb-4 border-<?= $cur['badge'] ?>">
  <div class="card-header bg-<?= $cur['badge'] ?> text-white d-flex justify-content-between align-items-center flex-wrap gap-2">
    <span><i class="bi bi-trophy"></i> <strong>Paket <?= htmlspecialchars($cur['label']) ?></strong></span>
    <small class="opacity-75"><?= htmlspecialchars($cur['durasi']) ?></small>
  </div>
  <div class="card-body">
    <p class="small text-muted mb-3"><?= htmlspecialchars($cur['desc']) ?></p>
    <div class="table-responsive">
      <table class="table table-sm align-middle">
        <thead class="table-light">
          <tr>
            <th>#</th><th>Komponen</th><th>Target</th><th>Set</th><th>Durasi/Repetisi</th><th>Istirahat</th><th></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach($cur['sesi'] as $i=>$s):
          $gk = $s['gerakan'];
          $g = $GERAKAN[$gk] ?? ['nama'=>$gk,'icon'=>'bi-dash','target'=>'-','tips'=>'','langkah'=>[],'yt_id'=>''];
        ?>
          <tr>
            <td><?= $i+1 ?></td>
            <td><i class="bi <?= $g['icon'] ?> text-<?= $cur['badge'] ?>"></i> <strong><?= htmlspecialchars($g['nama']) ?></strong></td>
            <td class="small text-muted"><?= htmlspecialchars($g['target']) ?></td>
            <td><?= (int)$s['set'] ?></td>
            <td><?= htmlspecialchars($s['rep']) ?></td>
            <td class="text-muted"><?= htmlspecialchars($s['rest']) ?></td>
            <td>
              <button type="button" class="btn btn-sm btn-outline-<?= $cur['badge'] ?> btn-lihat-gerakan" data-key="<?= htmlspecialchars($gk) ?>">
                <i class="bi bi-eye"></i> Lihat
              </button>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Monitoring 30 Hari (mirip kalistenik) -->
<div class="card shadow-sm mb-3 border-success" id="pjMonitor">
  <div class="card-header bg-success-subtle text-success-emphasis d-flex flex-wrap justify-content-between align-items-center gap-2">
    <span><i class="bi bi-clipboard2-check-fill"></i> <strong>Monitoring Jogging (30 Hari Terakhir)</strong></span>
    <small class="text-muted">Tandai sesi yang sudah Anda selesaikan hari ini.</small>
  </div>
  <div class="card-body">
    <div class="row g-2 mb-3">
      <?php foreach ($PAKET as $lk => $pv):
        $doneToday = array_key_exists($lk, $pjDoneToday);
      ?>
        <div class="col-12 col-md-4">
          <div class="border rounded p-2 h-100">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <div class="fw-semibold">Paket <?= htmlspecialchars($pv['label']) ?></div>
                <div class="small text-muted"><?= (int)$pjCountLevel[$lk] ?> sesi / 30 hari</div>
              </div>
              <button type="button"
                      class="btn btn-sm <?= $doneToday?'btn-success':'btn-outline-success' ?> pj-log-btn"
                      data-level="<?= $lk ?>" data-tgl="<?= $pjToday ?>">
                <?= $doneToday ? '<i class="bi bi-check-circle-fill"></i> Selesai' : '<i class="bi bi-plus-circle"></i> Selesai hari ini' ?>
              </button>
            </div>
            <?php if ($doneToday && !empty($pjDoneToday[$lk])): ?>
              <div class="small text-muted mt-1"><i class="bi bi-chat-left-text"></i> <?= htmlspecialchars($pjDoneToday[$lk]) ?></div>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="table-responsive" style="max-height:320px; overflow-y:auto;">
      <table class="table table-sm table-bordered align-middle mb-0">
        <thead class="table-light" style="position:sticky;top:0;z-index:2;">
          <tr><th style="width:130px">Tanggal</th><th style="width:200px">Level</th><th>Catatan</th></tr>
        </thead>
        <tbody>
        <?php if (!$pjLogs): ?>
          <tr><td colspan="3" class="text-center text-muted small">Belum ada catatan latihan. Mulai hari ini!</td></tr>
        <?php else: foreach ($pjLogs as $r): ?>
          <tr>
            <td><?= htmlspecialchars($r['tanggal']) ?></td>
            <td><span class="badge bg-<?= $PAKET[$r['level']]['badge'] ?? 'secondary' ?>"><?= htmlspecialchars($PAKET[$r['level']]['label'] ?? $r['level']) ?></span></td>
            <td class="small"><?= htmlspecialchars($r['catatan'] ?? '') ?: '<span class="text-muted">–</span>' ?></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<script>
(function(){
  var CSRF = '<?= csrf_token() ?>';
  document.querySelectorAll('.pj-log-btn').forEach(function(b){
    b.addEventListener('click', async function(){
      var cat = '';
      if (typeof Swal !== 'undefined') {
        var r = await Swal.fire({
          title: 'Selesai Sesi ' + b.dataset.level.toUpperCase(),
          input: 'textarea',
          inputLabel: 'Catatan (opsional)',
          inputPlaceholder: 'mis. 20 menit run-walk, batuk 2x, napas mulai lebih ringan…',
          showCancelButton: true, confirmButtonText: 'Simpan',
          cancelButtonText: 'Batal', confirmButtonColor: '#198754'
        });
        if (!r.isConfirmed) return;
        cat = r.value || '';
      } else {
        cat = prompt('Catatan latihan (opsional):','') || '';
      }
      var fd = new FormData();
      fd.append('csrf', CSRF);
      fd.append('_action', 'pjog_log_toggle');
      fd.append('level', b.dataset.level);
      fd.append('tanggal', b.dataset.tgl);
      fd.append('catatan', cat);
      b.disabled = true;
      try {
        var r2 = await fetch('/paket_perokok_jogging.php?lvl=' + encodeURIComponent(b.dataset.level),
          { method:'POST', body:fd, credentials:'same-origin' });
        var j  = await r2.json();
        if (j.ok) location.reload();
        else alert('Gagal: ' + (j.err || '?'));
      } catch(e){ alert('Error: ' + e.message); }
      b.disabled = false;
    });
  });
})();
</script>

<!-- Panduan komponen sesi -->
<div class="card shadow-sm mb-3">
  <div class="card-header"><i class="bi bi-info-circle text-primary"></i> <strong>Panduan Komponen Sesi</strong></div>
  <div class="card-body">
    <div class="row g-3">
      <?php foreach($GERAKAN as $key=>$g): ?>
        <div class="col-md-6 col-lg-4">
          <div class="border rounded h-100 p-3">
            <div class="fw-semibold mb-1"><i class="bi <?= $g['icon'] ?> text-primary"></i> <?= htmlspecialchars($g['nama']) ?></div>
            <div class="small text-muted mb-1"><i class="bi bi-bullseye"></i> <?= htmlspecialchars($g['target']) ?></div>
            <div class="small mb-2"><?= htmlspecialchars($g['tips']) ?></div>
            <button type="button" class="btn btn-sm btn-primary btn-lihat-gerakan" data-key="<?= htmlspecialchars($key) ?>">
              <i class="bi bi-play-circle"></i> Lihat Video
            </button>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<div class="alert alert-info small">
  <i class="bi bi-lightbulb"></i>
  <strong>Tips untuk perokok:</strong> minum air banyak, jangan merokok minimal 60 menit sebelum & sesudah sesi, dan naikkan durasi hanya bila Anda bisa menyelesaikan 2 minggu tanpa nyeri dada.
</div>

<!-- Modal Lihat Gerakan (video YouTube) -->
<div class="modal fade" id="modalGerakan" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="mgTitle"><i class="bi bi-lungs-fill"></i> Komponen Sesi</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body">
        <div class="ratio ratio-16x9 rounded overflow-hidden border mb-3">
          <iframe id="mgVideo" width="100%" height="100%" src="" title="YouTube video player"
                  frameborder="0"
                  allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                  referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>
        </div>
        <div class="mb-2"><span class="badge bg-primary-subtle text-primary"><i class="bi bi-bullseye"></i> Target: <span id="mgTarget">-</span></span></div>
        <h6 class="mt-3">Langkah-langkah:</h6>
        <ol id="mgLangkah" class="ps-3"></ol>
        <div class="alert alert-warning small mb-0">
          <i class="bi bi-shield-exclamation"></i>
          <strong>Tips form:</strong> <span id="mgTips">-</span>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

<script>
window.__GERAKAN__ = <?= json_encode($GERAKAN, JSON_UNESCAPED_UNICODE) ?>;
window.addEventListener('load', function(){
  var el = document.getElementById('modalGerakan');
  if (!el || typeof bootstrap === 'undefined') return;
  var modal = new bootstrap.Modal(el);
  var iframe = document.getElementById('mgVideo');
  document.querySelectorAll('.btn-lihat-gerakan').forEach(function(btn){
    btn.addEventListener('click', function(){
      var key = this.getAttribute('data-key');
      var g = window.__GERAKAN__[key]; if (!g) return;
      document.getElementById('mgTitle').innerHTML = '<i class="bi '+(g.icon||'bi-lungs-fill')+'"></i> '+ g.nama;
      document.getElementById('mgTarget').textContent = g.target || '-';
      document.getElementById('mgTips').textContent   = g.tips   || '-';
      var ol = document.getElementById('mgLangkah'); ol.innerHTML='';
      (g.langkah||[]).forEach(function(s){ var li=document.createElement('li'); li.textContent=s; ol.appendChild(li); });
      iframe.src = g.yt_id ? ('https://www.youtube.com/embed/' + encodeURIComponent(g.yt_id)) : '';
      modal.show();
    });
  });
  el.addEventListener('hidden.bs.modal', function(){ iframe.src=''; });
});
</script>

<?php include __DIR__.'/includes/bottom_nav.php'; ?>
<?php include __DIR__.'/includes/footer.php'; ?>
