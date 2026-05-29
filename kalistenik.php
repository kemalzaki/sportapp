<?php
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
require __DIR__.'/includes/info_publik.php';
send_security_headers(); enforce_session_timeout();
$pageTitle = 'Paket Bugar Kalistenik';

// Katalog gerakan kalistenik + ilustrasi muslimah berhijab (asset lokal)
// + langkah detail untuk modal "Lihat Gerakan"
$GERAKAN = [
  'push_up'    => [
    'nama'=>'Push-up','icon'=>'bi-arrow-down-up','target'=>'Dada, trisep, bahu',
    'tips'=>'Tubuh lurus dari kepala ke tumit, siku ±45°.',
    'img'=>'assets/img/kalistenik/push_up.jpg',
    'langkah'=>[
      'Posisi plank tinggi: tangan selebar bahu, kaki rapat, tubuh lurus.',
      'Turunkan dada hingga ±5 cm dari lantai, siku ±45° dari tubuh.',
      'Dorong kembali ke atas dengan kuat tanpa mengunci siku.',
      'Jaga inti perut & glutes aktif agar pinggul tidak turun.',
    ],
    'yt'=>'https://www.youtube.com/results?search_query=cara+push+up+benar',
  ],
  'pull_up'    => [
    'nama'=>'Pull-up','icon'=>'bi-arrow-up','target'=>'Punggung, bisep',
    'tips'=>'Gantung penuh, tarik hingga dagu melewati bar.',
    'img'=>'assets/img/kalistenik/pull_up.jpg',
    'langkah'=>[
      'Gantung pada bar, telapak menghadap ke depan, lebar bahu.',
      'Tarik bahu ke bawah-belakang, lalu tarik tubuh hingga dagu melewati bar.',
      'Turunkan secara terkontrol hingga lengan lurus penuh.',
      'Hindari mengayun; gunakan band elastis jika belum kuat.',
    ],
    'yt'=>'https://www.youtube.com/results?search_query=cara+pull+up+pemula',
  ],
  'squat'      => [
    'nama'=>'Squat','icon'=>'bi-arrows-vertical','target'=>'Paha, bokong',
    'tips'=>'Lutut sejajar ujung kaki, punggung netral.',
    'img'=>'assets/img/kalistenik/squat.jpg',
    'langkah'=>[
      'Berdiri kaki selebar bahu, ujung kaki sedikit keluar.',
      'Tekuk lutut & pinggul bersamaan seperti hendak duduk.',
      'Turun sampai paha sejajar lantai (atau senyaman mungkin).',
      'Dorong kembali berdiri dengan tumit menapak penuh.',
    ],
    'yt'=>'https://www.youtube.com/results?search_query=cara+squat+benar',
  ],
  'lunge'      => [
    'nama'=>'Lunge','icon'=>'bi-shoe-prints','target'=>'Paha, glutes',
    'tips'=>'Langkah panjang, lutut depan 90°.',
    'img'=>'assets/img/kalistenik/lunge.jpg',
    'langkah'=>[
      'Langkahkan satu kaki ke depan cukup panjang.',
      'Turunkan pinggul sampai kedua lutut ±90°.',
      'Lutut depan tegak lurus pergelangan kaki, tidak melewati ujung jari.',
      'Dorong tumit depan untuk kembali berdiri. Ulangi sisi lain.',
    ],
    'yt'=>'https://www.youtube.com/results?search_query=cara+lunge+benar',
  ],
  'plank'      => [
    'nama'=>'Plank','icon'=>'bi-dash-lg','target'=>'Core (perut)',
    'tips'=>'Tahan posisi lurus, jangan turunkan pinggul.',
    'img'=>'assets/img/kalistenik/plank.jpg',
    'langkah'=>[
      'Tumpuan siku tepat di bawah bahu, lengan bawah menempel matras.',
      'Tubuh lurus satu garis dari kepala–pinggul–tumit.',
      'Kencangkan perut & glutes, tarik pusar ke arah tulang belakang.',
      'Tahan sambil bernapas normal. Hindari pinggul turun atau naik.',
    ],
    'yt'=>'https://www.youtube.com/results?search_query=cara+plank+benar',
  ],
  'dip'        => [
    'nama'=>'Dip','icon'=>'bi-arrow-down','target'=>'Trisep, dada bawah',
    'tips'=>'Pakai 2 kursi/bar paralel; turunkan badan terkontrol.',
    'img'=>'assets/img/kalistenik/dip.jpg',
    'langkah'=>[
      'Letakkan tangan di tepi kursi/bench di belakang Anda.',
      'Luruskan lengan, kaki ditekuk (atau lurus untuk versi sulit).',
      'Tekuk siku ke belakang sampai ±90°, jangan turun terlalu dalam.',
      'Dorong kembali ke atas tanpa mengunci siku.',
    ],
    'yt'=>'https://www.youtube.com/results?search_query=cara+tricep+dip',
  ],
  'burpee'     => [
    'nama'=>'Burpee','icon'=>'bi-lightning','target'=>'Full body + kardio',
    'tips'=>'Squat → plank → push-up → lompat.',
    'img'=>'assets/img/kalistenik/burpee.jpg',
    'langkah'=>[
      'Berdiri tegak, turun ke posisi squat dan letakkan tangan di lantai.',
      'Lompat kedua kaki ke belakang menjadi posisi plank.',
      'Lakukan satu push-up (opsional untuk pemula).',
      'Lompat kedua kaki kembali ke tangan, lalu lompat ke atas dengan tangan terangkat.',
    ],
    'yt'=>'https://www.youtube.com/results?search_query=cara+burpee+benar',
  ],
  'mountain'   => [
    'nama'=>'Mountain Climber','icon'=>'bi-speedometer','target'=>'Core + kardio',
    'tips'=>'Posisi plank, lari di tempat dengan lutut ke dada.',
    'img'=>'assets/img/kalistenik/mountain.jpg',
    'langkah'=>[
      'Mulai dari posisi plank tinggi dengan tubuh lurus.',
      'Tarik lutut kanan ke arah dada dengan cepat, lalu kembalikan.',
      'Bergantian dengan lutut kiri seperti berlari di tempat.',
      'Jaga pinggul tetap rendah dan inti aktif.',
    ],
    'yt'=>'https://www.youtube.com/results?search_query=cara+mountain+climber',
  ],
  'jumping'    => [
    'nama'=>'Jumping Jack','icon'=>'bi-arrows-fullscreen','target'=>'Pemanasan, kardio',
    'tips'=>'Lompat sambil buka-tutup kaki dan tangan.',
    'img'=>'assets/img/kalistenik/jumping.jpg',
    'langkah'=>[
      'Berdiri tegak, tangan di samping, kaki rapat.',
      'Lompat sambil membuka kaki selebar bahu dan angkat tangan ke atas.',
      'Lompat kembali ke posisi awal.',
      'Lakukan dengan irama stabil, mendarat dengan lembut.',
    ],
    'yt'=>'https://www.youtube.com/results?search_query=cara+jumping+jack',
  ],
  'leg_raise'  => [
    'nama'=>'Leg Raise','icon'=>'bi-arrow-up-short','target'=>'Perut bawah',
    'tips'=>'Berbaring, angkat kaki lurus 90°, turunkan perlahan.',
    'img'=>'assets/img/kalistenik/leg_raise.jpg',
    'langkah'=>[
      'Berbaring telentang, kaki lurus, tangan di samping atau di bawah pinggul.',
      'Angkat kedua kaki lurus hingga 90° terhadap lantai.',
      'Turunkan perlahan tanpa menyentuh lantai (jaga tegangan perut).',
      'Hindari mengangkat punggung bawah; kencangkan inti.',
    ],
    'yt'=>'https://www.youtube.com/results?search_query=cara+leg+raise',
  ],
];

// Paket per level
$PAKET = [
  'pemula' => [
    'label' => 'Pemula','durasi'=> '20 menit · 3x / minggu',
    'desc'  => 'Cocok untuk yang baru mulai berolahraga atau setelah lama vakum.','badge' => 'success',
    'sesi'  => [
      ['gerakan'=>'jumping',  'set'=>2, 'rep'=>'30 detik',     'rest'=>'30 detik'],
      ['gerakan'=>'squat',    'set'=>3, 'rep'=>'10 reps',      'rest'=>'45 detik'],
      ['gerakan'=>'push_up',  'set'=>3, 'rep'=>'5–8 reps (boleh lutut menempel)', 'rest'=>'60 detik'],
      ['gerakan'=>'plank',    'set'=>3, 'rep'=>'20 detik',     'rest'=>'30 detik'],
      ['gerakan'=>'lunge',    'set'=>2, 'rep'=>'8 reps / kaki','rest'=>'45 detik'],
    ],
  ],
  'menengah' => [
    'label' => 'Menengah','durasi'=> '30 menit · 4x / minggu',
    'desc'  => 'Sudah terbiasa olahraga ringan; siap meningkatkan volume dan intensitas.','badge' => 'warning',
    'sesi'  => [
      ['gerakan'=>'jumping',  'set'=>2, 'rep'=>'45 detik',     'rest'=>'20 detik'],
      ['gerakan'=>'push_up',  'set'=>4, 'rep'=>'12 reps',      'rest'=>'45 detik'],
      ['gerakan'=>'squat',    'set'=>4, 'rep'=>'20 reps',      'rest'=>'45 detik'],
      ['gerakan'=>'lunge',    'set'=>3, 'rep'=>'12 reps / kaki','rest'=>'45 detik'],
      ['gerakan'=>'dip',      'set'=>3, 'rep'=>'8 reps',       'rest'=>'45 detik'],
      ['gerakan'=>'plank',    'set'=>3, 'rep'=>'45 detik',     'rest'=>'30 detik'],
      ['gerakan'=>'mountain', 'set'=>3, 'rep'=>'30 detik',     'rest'=>'30 detik'],
    ],
  ],
  'lanjutan' => [
    'label' => 'Lanjutan','durasi'=> '45 menit · 5x / minggu',
    'desc'  => 'Untuk yang sudah rutin berlatih dan ingin mengejar kekuatan + daya tahan.','badge' => 'danger',
    'sesi'  => [
      ['gerakan'=>'burpee',   'set'=>3, 'rep'=>'15 reps',      'rest'=>'60 detik'],
      ['gerakan'=>'pull_up',  'set'=>4, 'rep'=>'6–8 reps',     'rest'=>'90 detik'],
      ['gerakan'=>'push_up',  'set'=>4, 'rep'=>'20 reps',      'rest'=>'60 detik'],
      ['gerakan'=>'squat',    'set'=>4, 'rep'=>'25 reps (boleh jump squat)', 'rest'=>'60 detik'],
      ['gerakan'=>'dip',      'set'=>4, 'rep'=>'12 reps',      'rest'=>'60 detik'],
      ['gerakan'=>'leg_raise','set'=>3, 'rep'=>'15 reps',      'rest'=>'45 detik'],
      ['gerakan'=>'plank',    'set'=>3, 'rep'=>'60 detik',     'rest'=>'30 detik'],
      ['gerakan'=>'mountain', 'set'=>3, 'rep'=>'45 detik',     'rest'=>'30 detik'],
    ],
  ],
];

$level = $_GET['lvl'] ?? 'pemula';
if (!isset($PAKET[$level])) $level = 'pemula';
$cur = $PAKET[$level];

include __DIR__.'/includes/header.php'; ?>

<link rel="stylesheet" href="assets/css/sport-islami.css">

<div class="hero-sport-islami mb-3" style="background-image:linear-gradient(135deg, rgba(15,98,72,.78), rgba(7,59,76,.78)), url('assets/img/kalistenik/hero.jpg');background-size:cover;background-position:center;">
  <div class="hero-overlay">
    <h1 class="h4 mb-1"><i class="bi bi-person-arms-up"></i> Paket Bugar Kalistenik</h1>
    <p class="small mb-0 opacity-85">Latihan beban tubuh sendiri — sehat lahir, kuat batin.</p>
  </div>
</div>

<?php ip_card_open('Paket Bugar Kalistenik', 'bi-person-arms-up'); ?>

<p class="text-muted small mb-3">
  Latihan <strong>kalistenik</strong> (beban tubuh sendiri) tanpa alat. Pilih paket sesuai level kebugaran Anda.
  Mulailah dengan pemanasan 3–5 menit dan akhiri dengan peregangan.
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
            <th>#</th><th>Gerakan</th><th>Target</th><th>Set</th><th>Repetisi/Durasi</th><th>Istirahat</th><th></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach($cur['sesi'] as $i=>$s):
          $gk = $s['gerakan'];
          $g = $GERAKAN[$gk] ?? ['nama'=>$gk,'icon'=>'bi-dash','target'=>'-','tips'=>'','img'=>'','langkah'=>[],'yt'=>''];
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
                <i class="bi bi-eye"></i> Lihat Gerakan
              </button>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Panduan tiap gerakan (kartu dengan gambar) -->
<div class="card shadow-sm mb-3">
  <div class="card-header"><i class="bi bi-info-circle text-primary"></i> <strong>Panduan Gerakan</strong></div>
  <div class="card-body">
    <div class="row g-3">
      <?php foreach($GERAKAN as $key=>$g): ?>
        <div class="col-md-6 col-lg-4">
          <div class="border rounded h-100 overflow-hidden gerakan-card">
            <?php if (!empty($g['img'])): ?>
              <img src="<?= htmlspecialchars($g['img']) ?>" alt="<?= htmlspecialchars($g['nama']) ?>" loading="lazy"
                   class="w-100" style="height:160px;object-fit:cover;background:#f4f4f4;"
                   onerror="this.style.display='none'">
            <?php endif; ?>
            <div class="p-3">
              <div class="fw-semibold mb-1"><i class="bi <?= $g['icon'] ?> text-primary"></i> <?= htmlspecialchars($g['nama']) ?></div>
              <div class="small text-muted mb-1"><i class="bi bi-bullseye"></i> <?= htmlspecialchars($g['target']) ?></div>
              <div class="small mb-2"><?= htmlspecialchars($g['tips']) ?></div>
              <button type="button" class="btn btn-sm btn-primary btn-lihat-gerakan" data-key="<?= htmlspecialchars($key) ?>">
                <i class="bi bi-play-circle"></i> Lihat Gerakan
              </button>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<div class="alert alert-info small">
  <i class="bi bi-lightbulb"></i>
  <strong>Tips:</strong> minum air cukup, jaga form di atas jumlah repetisi, dan istirahat 1 hari penuh tiap minggu untuk pemulihan otot.
  Hentikan latihan jika terasa nyeri tajam — konsultasikan ke dokter bila ragu.
</div>

<!-- Modal "Lihat Gerakan" -->
<div class="modal fade" id="modalGerakan" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="mgTitle"><i class="bi bi-person-arms-up"></i> Gerakan</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body">
        <img id="mgImg" src="" alt="" class="img-fluid rounded mb-3 d-none" style="width:100%;max-height:360px;object-fit:cover;background:#f4f4f4;">
        <div class="mb-2"><span class="badge bg-primary-subtle text-primary"><i class="bi bi-bullseye"></i> Target: <span id="mgTarget">-</span></span></div>
        <h6 class="mt-3">Langkah-langkah:</h6>
        <ol id="mgLangkah" class="ps-3"></ol>
        <div class="alert alert-warning small mb-0">
          <i class="bi bi-shield-exclamation"></i>
          <strong>Tips form:</strong> <span id="mgTips">-</span>
        </div>
      </div>
      <div class="modal-footer">
        <a id="mgYT" href="#" target="_blank" rel="noopener" class="btn btn-outline-danger">
          <i class="bi bi-youtube"></i> Cari Video Tutorial
        </a>
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
  document.querySelectorAll('.btn-lihat-gerakan').forEach(function(btn){
    btn.addEventListener('click', function(){
      var key = this.getAttribute('data-key');
      var g = window.__GERAKAN__[key];
      if (!g) return;
      document.getElementById('mgTitle').innerHTML = '<i class="bi '+(g.icon||'bi-person-arms-up')+'"></i> '+ g.nama;
      document.getElementById('mgTarget').textContent = g.target || '-';
      document.getElementById('mgTips').textContent   = g.tips   || '-';
      var ol = document.getElementById('mgLangkah'); ol.innerHTML='';
      (g.langkah||[]).forEach(function(s){ var li=document.createElement('li'); li.textContent=s; ol.appendChild(li); });
      var img = document.getElementById('mgImg');
      if (g.img) { img.src=g.img; img.classList.remove('d-none'); } else { img.classList.add('d-none'); img.src=''; }
      document.getElementById('mgYT').href = g.yt || '#';
      modal.show();
    });
  });
});
</script>

<?php include __DIR__.'/includes/bottom_nav.php'; ?>
<?php include __DIR__.'/includes/footer.php'; ?>
