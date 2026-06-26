<?php
// kalkulator_kesehatan.php — Revisi 11 Juni 2026
// Menentukan ritme lari yang disarankan berdasarkan kondisi sakit yang dialami.
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
send_security_headers(); enforce_session_timeout();
$pageTitle = 'Kalkulator Kesehatan — Ritme Lari';

/* Revisi 26 Juni 2026 — Gating Paket PRO & KOMUNITAS.
   Paket Gratis dikunci, ditampilkan banner upgrade + tombol pesan via WA. */
require_once __DIR__.'/includes/paket_helpers.php';
if (!isset($u) || !$u) { require_login(); $u = current_user(); }
$USER_PAKET = paket_user($u);
if (!in_array($USER_PAKET, ['pro','komunitas'], true)) {
    $__lockTitle = isset($pageTitle) && $pageTitle ? $pageTitle : 'Fitur PRO';
    include __DIR__.'/includes/header.php';
    echo '<h2 class="mb-3"><i class="bi bi-lock-fill text-warning"></i> '.htmlspecialchars($__lockTitle).'</h2>';
    echo paket_pro_lock_banner($__lockTitle,
        'Fitur ini hanya tersedia untuk paket PRO & KOMUNITAS. Paket Gratis tidak dapat mengakses fitur ini. Status paket Anda saat ini: '.strtoupper($USER_PAKET).'. Silakan upgrade untuk membuka akses.');
    include __DIR__.'/includes/footer.php';
    exit;
}

$u = current_user();

/* Pengetahuan dasar (rules-of-thumb) “neck check”:
   - Gejala di atas leher (pilek ringan, bersin, hidung tersumbat, sakit kepala ringan) → boleh lari ringan.
   - Gejala di bawah leher (batuk dada, sesak, demam, badan ngilu, mual) → tidak boleh lari, istirahat.
*/
$KONDISI = [
  'pilek'         => ['label'=>'Pilek (hidung meler, bersin)',  'level'=>'ringan'],
  'flu'           => ['label'=>'Flu / Demam',                   'level'=>'berat'],
  'batuk_kering'  => ['label'=>'Batuk kering (atas leher)',     'level'=>'ringan'],
  'batuk_dada'    => ['label'=>'Batuk berdahak / dada',         'level'=>'berat'],
  'sakit_kepala'  => ['label'=>'Sakit kepala ringan',           'level'=>'ringan'],
  'migrain'       => ['label'=>'Migrain berat',                 'level'=>'berat'],
  'sakit_perut'   => ['label'=>'Sakit perut / mual',            'level'=>'sedang'],
  'diare'         => ['label'=>'Diare',                         'level'=>'berat'],
  'nyeri_otot'    => ['label'=>'DOMS / nyeri otot ringan',      'level'=>'ringan'],
  'cedera_lutut'  => ['label'=>'Cedera lutut/kaki',             'level'=>'sangat_berat'],
  'asma'          => ['label'=>'Asma sedang aktif',             'level'=>'berat'],
  'haid'          => ['label'=>'Haid (PMS/Dismenore)',          'level'=>'sedang'],
  'kurang_tidur'  => ['label'=>'Kurang tidur (<5 jam)',         'level'=>'sedang'],
  'sehat'         => ['label'=>'Tidak ada keluhan',             'level'=>'sehat'],
];

$pilihan = $_GET['kondisi'] ?? [];
if (!is_array($pilihan)) $pilihan = [];
$pace_normal = (int)($_GET['pace'] ?? 360); // detik/km, default 6:00

// Tentukan level tertinggi yang dipilih
$rank = ['sehat'=>0,'ringan'=>1,'sedang'=>2,'berat'=>3,'sangat_berat'=>4];
$level = 'sehat';
foreach ($pilihan as $k) {
  if (isset($KONDISI[$k]) && $rank[$KONDISI[$k]['level']] > $rank[$level]) $level = $KONDISI[$k]['level'];
}

$rekom = null;
switch ($level) {
  case 'sehat':
    $rekom = [
      'label'=>'Boleh lari normal',
      'warna'=>'success',
      'intensitas'=>'70–85% HRmax (zona 3-4)',
      'pace'=>['mult'=>1.00, 'desc'=>'Pace normal Anda'],
      'durasi'=>'30–60 menit',
      'jarak'=>'5–10 km',
      'catatan'=>['Hidrasi cukup','Pemanasan 5-10 menit','Boleh tempo / interval bila terlatih'],
    ];
    break;
  case 'ringan':
    $rekom = [
      'label'=>'Lari ringan (Zona 1-2)',
      'warna'=>'info',
      'intensitas'=>'50–65% HRmax',
      'pace'=>['mult'=>1.20, 'desc'=>'+20% lebih lambat dari pace normal'],
      'durasi'=>'20–30 menit',
      'jarak'=>'3–5 km',
      'catatan'=>['Aturan "neck check": gejala di atas leher umumnya aman','Berhenti bila pusing/sesak','Hindari kerumunan'],
    ];
    break;
  case 'sedang':
    $rekom = [
      'label'=>'Jalan cepat / jog sangat ringan',
      'warna'=>'warning',
      'intensitas'=>'40–55% HRmax (Zona 1)',
      'pace'=>['mult'=>1.40, 'desc'=>'+40% lebih lambat (mode brisk walk / jog)'],
      'durasi'=>'15–20 menit',
      'jarak'=>'1–3 km',
      'catatan'=>['Hindari intensitas tinggi','Cukup minum & elektrolit','Stop bila gejala memburuk'],
    ];
    break;
  case 'berat':
    $rekom = [
      'label'=>'TIDAK DISARANKAN LARI — Istirahat',
      'warna'=>'danger',
      'intensitas'=>'Istirahat / peregangan ringan saja',
      'pace'=>['mult'=>0, 'desc'=>'—'],
      'durasi'=>'0 menit (rest day)',
      'jarak'=>'0 km',
      'catatan'=>[
        'Demam, batuk dada, atau gejala di bawah leher → istirahat total.',
        'Lari saat demam berisiko miokarditis (radang otot jantung).',
        'Boleh kembali bertahap 1-2 hari setelah bebas gejala/demam.',
      ],
    ];
    break;
  case 'sangat_berat':
    $rekom = [
      'label'=>'STOP — Konsultasi Dokter / Fisioterapi',
      'warna'=>'danger',
      'intensitas'=>'Hindari aktivitas memicu nyeri',
      'pace'=>['mult'=>0, 'desc'=>'—'],
      'durasi'=>'—',
      'jarak'=>'—',
      'catatan'=>['Cedera kaki/lutut perlu RICE','Cross-training non-impact: berenang/bersepeda statis bila tidak nyeri','Periksa ke profesional sebelum kembali berlari'],
    ];
    break;
}

function fmt_pace($detik){
  $detik = max(0,(int)$detik);
  return sprintf('%d:%02d /km', intdiv($detik,60), $detik%60);
}

include __DIR__.'/includes/header.php';
?>
<nav aria-label="breadcrumb" class="mb-2">
  <ol class="breadcrumb small mb-0">
    <li class="breadcrumb-item"><a href="/index.php">Beranda</a></li>
    <li class="breadcrumb-item active">Kalkulator Kesehatan — Ritme Lari</li>
  </ol>
</nav>

<h2 class="mb-1"><i class="bi bi-clipboard2-pulse text-primary"></i> Kalkulator Ritme Lari Berdasarkan Kondisi</h2>
<p class="text-muted small mb-3">Pilih kondisi yang sedang dialami. Sistem akan menyarankan <strong>pace, durasi, jarak</strong> dan intensitas yang aman. Tidak menggantikan saran dokter.</p>

<form method="get" class="row g-3">
  <div class="col-lg-5">
    <div class="card shadow-sm h-100"><div class="card-body">
      <h3 class="h6"><i class="bi bi-clipboard-check"></i> Kondisi saat ini</h3>
      <div class="mb-3">
        <?php foreach($KONDISI as $k=>$v): ?>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="kondisi[]" id="k_<?= $k ?>" value="<?= $k ?>" <?= in_array($k,$pilihan,true)?'checked':'' ?>>
            <label class="form-check-label small" for="k_<?= $k ?>"><?= htmlspecialchars($v['label']) ?>
              <span class="badge bg-<?= ['sehat'=>'success','ringan'=>'info','sedang'=>'warning','berat'=>'danger','sangat_berat'=>'danger'][$v['level']] ?> ms-1"><?= $v['level'] ?></span>
            </label>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="mb-3"><label class="small fw-semibold">Pace normal Anda (detik/km)</label>
        <input type="number" name="pace" min="180" max="900" value="<?= (int)$pace_normal ?>" class="form-control form-control-sm">
        <div class="small text-muted">Contoh: 360 = 6:00 /km. Gunakan untuk menghitung pace yang disesuaikan.</div>
      </div>
      <button class="btn btn-primary w-100"><i class="bi bi-calculator"></i> Hitung Rekomendasi</button>
    </div></div>
  </div>

  <div class="col-lg-7">
    <div class="card shadow-sm border-<?= $rekom['warna'] ?>">
      <div class="card-header bg-<?= $rekom['warna'] ?>-subtle text-<?= $rekom['warna'] ?>-emphasis">
        <i class="bi bi-megaphone"></i> Rekomendasi: <strong><?= htmlspecialchars($rekom['label']) ?></strong>
      </div>
      <div class="card-body">
        <div class="row g-3 mb-3">
          <div class="col-6 col-md-3"><div class="border rounded p-2 text-center"><div class="small text-muted">Intensitas</div><div class="fw-bold small"><?= htmlspecialchars($rekom['intensitas']) ?></div></div></div>
          <div class="col-6 col-md-3"><div class="border rounded p-2 text-center"><div class="small text-muted">Pace</div><div class="fw-bold"><?= $rekom['pace']['mult']>0 ? htmlspecialchars(fmt_pace($pace_normal*$rekom['pace']['mult'])) : '—' ?></div><div class="small text-muted"><?= htmlspecialchars($rekom['pace']['desc']) ?></div></div></div>
          <div class="col-6 col-md-3"><div class="border rounded p-2 text-center"><div class="small text-muted">Durasi</div><div class="fw-bold"><?= htmlspecialchars($rekom['durasi']) ?></div></div></div>
          <div class="col-6 col-md-3"><div class="border rounded p-2 text-center"><div class="small text-muted">Jarak</div><div class="fw-bold"><?= htmlspecialchars($rekom['jarak']) ?></div></div></div>
        </div>
        <div class="small"><strong>Catatan:</strong>
          <ul class="mb-0">
            <?php foreach($rekom['catatan'] as $c): ?><li><?= htmlspecialchars($c) ?></li><?php endforeach; ?>
          </ul>
        </div>
        <div class="alert alert-warning small mt-3 mb-0">
          <i class="bi bi-exclamation-triangle"></i> Gunakan <em>aturan neck check</em>: gejala di <strong>atas leher</strong> umumnya aman untuk lari ringan; gejala di <strong>bawah leher</strong> (batuk dada, demam, mual, sesak) → <strong>istirahat</strong>.
        </div>
      </div>
    </div>
  </div>
</form>

<?php include __DIR__.'/includes/footer.php'; ?>
