<?php
// cedera_olahraga.php — Revisi (video YouTube diperbarui)
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
send_security_headers(); enforce_session_timeout();
$pageTitle = 'Cedera Olahraga & Penanganan';
$u = current_user();

$ytId = function($s){
  $s = trim((string)$s);
  if ($s === '') return '';
  if (preg_match('/^[A-Za-z0-9_-]{11}$/', $s)) return $s;
  if (preg_match('~(?:youtu\.be/|v=|embed/|shorts/)([A-Za-z0-9_-]{11})~', $s, $m)) return $m[1];
  return '';
};

$CEDERA = [
  [
    'nama'=>'Keseleo / Sprain (Pergelangan Kaki/Tangan)',
    'icon'=>'bi-bandaid', 'warna'=>'warning',
    'gejala'=>['Nyeri tajam saat bergerak','Bengkak, memar','Sulit menahan beban'],
    'penanganan'=>[
      'Prinsip RICE: Rest (istirahatkan), Ice (kompres es 15-20 menit per 2 jam, 1-2 hari pertama).',
      'Compression: balut dengan elastic bandage (jangan terlalu kencang).',
      'Elevation: angkat bagian cedera lebih tinggi dari jantung.',
      'Jangan dipijat keras pada 48 jam pertama.',
      'Konsultasi dokter bila tidak bisa menumpu berat badan / nyeri parah > 3 hari.',
    ],
    'mitigasi'=>['Pemanasan dinamis 5-10 menit','Gunakan sepatu sesuai aktivitas','Latihan keseimbangan & propriosepsi','Hindari permukaan tidak rata'],
    'videos'=>[
      ['Penanganan Keseleo (RICE)','https://www.youtube.com/watch?v=pdBX5lKW-hg'],
      ['Latihan Rehab Ankle Sprain','https://www.youtube.com/watch?v=C5ettc0onck'],
    ],
  ],
  [
    'nama'=>'Kram Otot',
    'icon'=>'bi-lightning-charge', 'warna'=>'danger',
    'gejala'=>['Otot mengeras tiba-tiba','Nyeri menusuk','Sering di betis/paha belakang'],
    'penanganan'=>[
      'Hentikan aktivitas, regangkan otot perlahan ke arah berlawanan kontraksi.',
      'Pijat lembut, kompres hangat (atau dingin bila baru terjadi).',
      'Minum air + elektrolit (oralit/isotonik).',
    ],
    'mitigasi'=>['Hidrasi cukup sebelum & selama olahraga','Pemanasan & peregangan','Cukup elektrolit (natrium, kalium, magnesium)','Tidak overtraining'],
    'videos'=>[
      ['Cara Mengatasi Kram Otot','https://www.youtube.com/watch?v=rjxEfbBwj30'],
      ['Stretching Anti Kram Betis','https://www.youtube.com/watch?v=9twPWWIGu-o'],
    ],
  ],
  [
    'nama'=>'Strain Otot (Tarikan/Robekan Ringan)',
    'icon'=>'bi-activity', 'warna'=>'warning',
    'gejala'=>['Nyeri saat kontraksi','Bengkak/memar lokal','Kelemahan otot'],
    'penanganan'=>['RICE 48-72 jam pertama','Hindari aktivitas memicu nyeri','Setelah 3 hari mulai mobilisasi ringan & peregangan'],
    'mitigasi'=>['Pemanasan dinamis','Tingkatkan beban latihan bertahap (≤10%/minggu)','Latihan kekuatan rutin'],
    'videos'=>[
      ['Muscle Strain: Penyebab & Penanganan','https://www.youtube.com/watch?v=sGKHzfdzs2A'],
      ['Rehab Hamstring Strain','https://www.youtube.com/watch?v=hBcmkInzZKE'],
    ],
  ],
  [
    'nama'=>"Cedera Lutut (Runner's Knee)",
    'icon'=>'bi-person-walking', 'warna'=>'info',
    'gejala'=>['Nyeri di sekitar tempurung lutut','Bertambah sakit saat naik/turun tangga'],
    'penanganan'=>['Istirahat & kurangi beban','Ice 15-20 menit setelah aktivitas','Latihan penguat quadriceps & glutes','Konsultasi fisioterapi bila menetap'],
    'mitigasi'=>['Ganti sepatu lari tiap 500-800 km','Hindari menambah jarak >10%/minggu','Latihan core & hip strength'],
    'videos'=>[
      ["Apa itu Runner's Knee?",'https://www.youtube.com/watch?v=q59peAoaCSo'],
      ['5 Latihan Penguat Lutut','https://www.youtube.com/watch?v=ekdpK5FsqiY'],
    ],
  ],
  [
    'nama'=>'Lecet / Blister',
    'icon'=>'bi-droplet-half', 'warna'=>'secondary',
    'gejala'=>['Gelembung berisi cairan','Nyeri saat ditekan'],
    'penanganan'=>['Jangan dipecahkan kecuali sangat besar','Tutup dengan plester blister/hydrocolloid','Jaga kebersihan, ganti plester rutin'],
    'mitigasi'=>['Pakai kaus kaki olahraga (anti gesek)','Sepatu pas, tidak longgar','Gunakan vaseline pada titik gesekan'],
    'videos'=>[
      ['Cara Merawat Blister di Kaki','https://www.youtube.com/watch?v=s4Qj8w5wxDo'],
    ],
  ],
  [
    'nama'=>'Heat Exhaustion (Kelelahan Akibat Panas)',
    'icon'=>'bi-thermometer-sun', 'warna'=>'danger',
    'gejala'=>['Pusing, mual','Keringat berlebih','Kulit dingin & lembap','Denyut nadi cepat'],
    'penanganan'=>[
      'Pindah ke tempat sejuk/teduh, longgarkan pakaian.',
      'Minum air dingin / oralit perlahan.',
      'Kompres dingin di leher, ketiak, selangkangan.',
      'Bila tidak membaik 30 menit / suhu >40°C → segera ke IGD (waspada heat stroke).',
    ],
    'mitigasi'=>['Olahraga di pagi/sore','Hidrasi 500 ml 1 jam sebelumnya','Pakaian ringan & breathable','Aklimatisasi bertahap di cuaca panas'],
    'videos'=>[
      ['Heat Exhaustion vs Heat Stroke','https://www.youtube.com/watch?v=oynSAL8v8aY'],
      ['Pertolongan Pertama Kelelahan Panas','https://www.youtube.com/watch?v=Ev78iB4PE40'],
    ],
  ],
  [
    'nama'=>'Pingsan (Sinkop) Saat Olahraga',
    'icon'=>'bi-emoji-dizzy', 'warna'=>'danger',
    'gejala'=>['Pusing, pandangan gelap','Berkeringat dingin','Kehilangan kesadaran sesaat'],
    'penanganan'=>[
      'Baringkan korban telentang, angkat kaki ~30 cm (posisi syok).',
      'Longgarkan pakaian, pastikan jalan napas bebas.',
      'Jangan beri makan/minum saat masih belum sadar.',
      'Setelah sadar, beri air manis perlahan. Istirahatkan minimal 15-30 menit.',
      'Panggil bantuan medis bila tidak sadar >1 menit, kejang, dada nyeri, sesak napas, atau cedera kepala.',
      'CPR bila tidak ada napas/nadi (30 kompresi : 2 napas) — hubungi 119/118.',
    ],
    'mitigasi'=>[
      'Tidak olahraga berat saat sakit/demam/dehidrasi.',
      'Makan ringan 1-2 jam sebelum olahraga.',
      'Pemanasan & pendinginan bertahap (hindari berhenti mendadak).',
      'Periksa tensi/gula darah rutin bila punya riwayat hipotensi atau hipoglikemia.',
      'Awasi tanda kelelahan: hentikan bila pusing, dada berdebar, atau pandangan kabur.',
    ],
    'videos'=>[
      ['Pertolongan Pertama Pingsan','https://www.youtube.com/watch?v=wMiXQeV84AY'],
      ['CPR Dewasa (BHD)','https://www.youtube.com/watch?v=Rn6c6F88vc4'],
    ],
  ],
  [
    'nama'=>'Cedera Punggung Bawah',
    'icon'=>'bi-person-arms-up', 'warna'=>'info',
    'gejala'=>['Nyeri tumpul di pinggang','Sulit membungkuk'],
    'penanganan'=>['Istirahat aktif (tetap bergerak ringan)','Kompres dingin 48 jam pertama lalu hangat','Penguatan core & peregangan hamstring','Konsultasi bila menjalar ke kaki'],
    'mitigasi'=>['Teknik mengangkat yang benar (jongkok, bukan membungkuk)','Latihan core (plank, bird-dog)','Hindari beban berlebih'],
    'videos'=>[
      ['Latihan Aman untuk Nyeri Punggung Bawah','https://www.youtube.com/watch?v=e91iGG39KLM'],
      ['Stretching Low Back Pain','https://www.youtube.com/watch?v=ZpBOwQaxBZc'],
    ],
  ],
];

require_once __DIR__.'/includes/htmx.php'; htmx_layout_start($pageTitle ?? 'Cedera Olahraga');
?>
<nav aria-label="breadcrumb" class="mb-2">
  <ol class="breadcrumb small mb-0">
    <li class="breadcrumb-item"><a href="/index.php">Beranda</a></li>
    <li class="breadcrumb-item active">Cedera Olahraga &amp; Penanganan</li>
  </ol>
</nav>

<h2 class="mb-1"><i class="bi bi-bandaid text-danger"></i> Cedera Olahraga &amp; Penanganan</h2>
<p class="text-muted small mb-3">Panduan ringkas — termasuk <strong>pingsan</strong> dan <strong>mitigasi sebelum cedera</strong>. Tidak menggantikan saran medis profesional.</p>

<div class="alert alert-warning small d-flex gap-2 align-items-start">
  <i class="bi bi-shield-exclamation fs-4"></i>
  <div><strong>Mitigasi Umum:</strong> pemanasan 5–10 menit, hidrasi cukup, sepatu sesuai aktivitas, peningkatan intensitas bertahap (≤10%/minggu), dengarkan tubuh — berhenti bila nyeri tajam atau pusing.</div>
</div>

<div class="row g-3">
  <?php foreach($CEDERA as $c): ?>
    <div class="col-md-6">
      <div class="card h-100 shadow-sm border-<?= $c['warna'] ?>">
        <div class="card-header bg-<?= $c['warna'] ?>-subtle text-<?= $c['warna'] ?>-emphasis">
          <i class="bi <?= $c['icon'] ?>"></i> <strong><?= htmlspecialchars($c['nama']) ?></strong>
        </div>
        <div class="card-body">
          <div class="small mb-2"><strong>Gejala:</strong>
            <ul class="mb-2"><?php foreach($c['gejala'] as $g): ?><li><?= htmlspecialchars($g) ?></li><?php endforeach; ?></ul>
          </div>
          <div class="small mb-2"><strong class="text-success">Penanganan:</strong>
            <ol class="mb-2"><?php foreach($c['penanganan'] as $g): ?><li><?= htmlspecialchars($g) ?></li><?php endforeach; ?></ol>
          </div>
          <div class="small mb-3"><strong class="text-primary">Mitigasi (sebelum cedera):</strong>
            <ul class="mb-0"><?php foreach($c['mitigasi'] as $g): ?><li><?= htmlspecialchars($g) ?></li><?php endforeach; ?></ul>
          </div>

          <?php if (!empty($c['videos'])): ?>
            <div class="small mb-1"><strong class="text-danger"><i class="bi bi-youtube"></i> Video Edukasi:</strong></div>
            <div class="row g-2">
              <?php foreach($c['videos'] as $v): $vid = $ytId($v[1]); if(!$vid) continue; ?>
                <div class="col-12 col-sm-6">
                  <div class="ratio ratio-16x9 rounded overflow-hidden border">
                    <iframe loading="lazy" src="https://www.youtube-nocookie.com/embed/<?= htmlspecialchars($vid) ?>"
                      title="<?= htmlspecialchars($v[0]) ?>"
                      allow="accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                      referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>
                  </div>
                  <div class="small text-muted mt-1 text-truncate" title="<?= htmlspecialchars($v[0]) ?>"><?= htmlspecialchars($v[0]) ?></div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<div class="alert alert-danger mt-4 small">
  <i class="bi bi-telephone-fill"></i> <strong>Darurat medis:</strong> hubungi <strong>119</strong> (Layanan Gawat Darurat) atau <strong>118</strong> (Ambulans) bila terjadi tidak sadar &gt; 1 menit, sesak napas berat, nyeri dada, atau perdarahan tidak berhenti.
</div>

<?php htmx_layout_end(); ?>
