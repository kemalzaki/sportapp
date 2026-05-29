<?php
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
require __DIR__.'/includes/info_publik.php';
send_security_headers(); enforce_session_timeout();
$pageTitle = 'Paket Bugar Kalistenik';

// Katalog gerakan kalistenik
$GERAKAN = [
  'push_up'    => ['nama'=>'Push-up',       'icon'=>'bi-arrow-down-up','target'=>'Dada, trisep, bahu','tips'=>'Tubuh lurus dari kepala ke tumit, siku ±45°.'],
  'pull_up'    => ['nama'=>'Pull-up',       'icon'=>'bi-arrow-up',     'target'=>'Punggung, bisep',    'tips'=>'Gantung penuh, tarik hingga dagu melewati bar.'],
  'squat'      => ['nama'=>'Squat',         'icon'=>'bi-arrows-vertical','target'=>'Paha, bokong',    'tips'=>'Lutut sejajar ujung kaki, punggung netral.'],
  'lunge'      => ['nama'=>'Lunge',         'icon'=>'bi-shoe-prints',  'target'=>'Paha, glutes',       'tips'=>'Langkah panjang, lutut depan 90°.'],
  'plank'      => ['nama'=>'Plank',         'icon'=>'bi-dash-lg',      'target'=>'Core (perut)',       'tips'=>'Tahan posisi lurus, jangan turunkan pinggul.'],
  'dip'        => ['nama'=>'Dip',           'icon'=>'bi-arrow-down',   'target'=>'Trisep, dada bawah', 'tips'=>'Pakai 2 kursi/bar paralel; turunkan badan terkontrol.'],
  'burpee'     => ['nama'=>'Burpee',        'icon'=>'bi-lightning',    'target'=>'Full body + kardio', 'tips'=>'Squat → plank → push-up → lompat.'],
  'mountain'   => ['nama'=>'Mountain Climber','icon'=>'bi-speedometer','target'=>'Core + kardio',     'tips'=>'Posisi plank, lari di tempat dengan lutut ke dada.'],
  'jumping'    => ['nama'=>'Jumping Jack',  'icon'=>'bi-arrows-fullscreen','target'=>'Pemanasan, kardio','tips'=>'Lompat sambil buka-tutup kaki dan tangan.'],
  'leg_raise'  => ['nama'=>'Leg Raise',     'icon'=>'bi-arrow-up-short','target'=>'Perut bawah',       'tips'=>'Berbaring, angkat kaki lurus 90°, turunkan perlahan.'],
];

// Paket per level
$PAKET = [
  'pemula' => [
    'label' => 'Pemula',
    'durasi'=> '20 menit · 3x / minggu',
    'desc'  => 'Cocok untuk yang baru mulai berolahraga atau setelah lama vakum.',
    'badge' => 'success',
    'sesi'  => [
      ['gerakan'=>'jumping',  'set'=>2, 'rep'=>'30 detik',     'rest'=>'30 detik'],
      ['gerakan'=>'squat',    'set'=>3, 'rep'=>'10 reps',      'rest'=>'45 detik'],
      ['gerakan'=>'push_up',  'set'=>3, 'rep'=>'5–8 reps (boleh lutut menempel)', 'rest'=>'60 detik'],
      ['gerakan'=>'plank',    'set'=>3, 'rep'=>'20 detik',     'rest'=>'30 detik'],
      ['gerakan'=>'lunge',    'set'=>2, 'rep'=>'8 reps / kaki','rest'=>'45 detik'],
    ],
  ],
  'menengah' => [
    'label' => 'Menengah',
    'durasi'=> '30 menit · 4x / minggu',
    'desc'  => 'Sudah terbiasa olahraga ringan; siap meningkatkan volume dan intensitas.',
    'badge' => 'warning',
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
    'label' => 'Lanjutan',
    'durasi'=> '45 menit · 5x / minggu',
    'desc'  => 'Untuk yang sudah rutin berlatih dan ingin mengejar kekuatan + daya tahan.',
    'badge' => 'danger',
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
            <th>#</th><th>Gerakan</th><th>Target</th><th>Set</th><th>Repetisi/Durasi</th><th>Istirahat</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach($cur['sesi'] as $i=>$s):
          $g = $GERAKAN[$s['gerakan']] ?? ['nama'=>$s['gerakan'],'icon'=>'bi-dash','target'=>'-','tips'=>''];
        ?>
          <tr>
            <td><?= $i+1 ?></td>
            <td><i class="bi <?= $g['icon'] ?> text-<?= $cur['badge'] ?>"></i> <strong><?= htmlspecialchars($g['nama']) ?></strong></td>
            <td class="small text-muted"><?= htmlspecialchars($g['target']) ?></td>
            <td><?= (int)$s['set'] ?></td>
            <td><?= htmlspecialchars($s['rep']) ?></td>
            <td class="text-muted"><?= htmlspecialchars($s['rest']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Panduan tiap gerakan -->
<div class="card shadow-sm mb-3">
  <div class="card-header"><i class="bi bi-info-circle text-primary"></i> <strong>Panduan Gerakan</strong></div>
  <div class="card-body">
    <div class="row g-3">
      <?php foreach($GERAKAN as $g): ?>
        <div class="col-md-6 col-lg-4">
          <div class="border rounded p-3 h-100">
            <div class="fw-semibold mb-1"><i class="bi <?= $g['icon'] ?> text-primary"></i> <?= htmlspecialchars($g['nama']) ?></div>
            <div class="small text-muted mb-1"><i class="bi bi-bullseye"></i> <?= htmlspecialchars($g['target']) ?></div>
            <div class="small"><?= htmlspecialchars($g['tips']) ?></div>
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

<?php include __DIR__.'/includes/bottom_nav.php'; ?>
<?php include __DIR__.'/includes/footer.php'; ?>
