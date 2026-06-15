<?php
/** Revisi 15 Juni 2026 — Halaman Rukun Islam (5 Pilar) */
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
send_security_headers(); require_login();
$pageTitle = 'Rukun Islam';

$RUKUN_ISLAM = [
  [
    'judul' => '1. Syahadat', 'icon' => 'bi-patch-check-fill', 'warna' => 'success',
    'rukun' => ['Mengucapkan dua kalimat syahadat: <em>Asyhadu an laa ilaaha illallaah wa asyhadu anna Muhammadar rasuulullaah</em>.'],
    'syarat_sah' => ['Mengetahui makna dan kandungannya.','Yakin tanpa keraguan.','Menerima konsekuensinya, tidak menolaknya.','Tunduk (inqiyad) pada tuntutannya.','Jujur (shidq) dari hati, bukan sekadar lisan.','Ikhlas, bukan karena riya atau dunia.','Mencintai kalimat tauhid dan ahlinya.'],
    'syarat_wajib' => ['Berakal (mukallaf).','Baligh atau sudah mumayyiz.','Mendengar dakwah Islam.','Mampu mengucapkan (kecuali bisu, cukup dengan isyarat/hati).'],
  ],
  [
    'judul' => '2. Sholat', 'icon' => 'bi-person-arms-up', 'warna' => 'primary',
    'rukun' => ['Niat.','Berdiri bagi yang mampu.','Takbiratul ihram.','Membaca Al-Fatihah pada setiap rakaat.','Ruku dengan thuma\'ninah.','I\'tidal dengan thuma\'ninah.','Sujud dua kali dengan thuma\'ninah.','Duduk di antara dua sujud.','Duduk tasyahud akhir & membacanya.','Membaca shalawat atas Nabi ﷺ pada tasyahud akhir.','Salam pertama.','Tertib (urut).'],
    'syarat_sah' => ['Suci dari hadats kecil dan besar.','Suci badan, pakaian, dan tempat dari najis.','Menutup aurat.','Menghadap kiblat.','Telah masuk waktu sholat.','Mengetahui tata cara sholat.','Meninggalkan pembatal sholat.'],
    'syarat_wajib' => ['Islam.','Baligh.','Berakal.','Suci dari haid & nifas.','Telah sampai dakwah/seruan sholat.'],
  ],
  [
    'judul' => '3. Zakat', 'icon' => 'bi-coin', 'warna' => 'warning',
    'rukun' => ['Niat zakat karena Allah.','Memindahkan kepemilikan harta zakat kepada yang berhak (8 ashnaf).'],
    'syarat_sah' => ['Niat saat menunaikannya.','Diberikan kepada mustahik yang berhak (8 ashnaf).','Tamlik: menjadi milik penuh mustahik.','Harta zakat berasal dari sumber yang halal.'],
    'syarat_wajib' => ['Islam.','Merdeka.','Kepemilikan penuh atas harta.','Harta mencapai nishab.','Telah mencapai haul (1 tahun hijriyah) — kecuali zakat pertanian & rikaz.','Harta berkembang (produktif) atau berpotensi berkembang.','Lebih dari kebutuhan pokok.','Bebas dari hutang yang menggugurkan nishab.'],
  ],
  [
    'judul' => '4. Puasa Ramadhan', 'icon' => 'bi-moon-stars-fill', 'warna' => 'info',
    'rukun' => ['Niat puasa di malam hari (sebelum fajar) untuk puasa wajib.','Menahan diri dari segala pembatal puasa sejak terbit fajar sampai terbenam matahari.'],
    'syarat_sah' => ['Islam.','Berakal & mumayyiz.','Suci dari haid dan nifas.','Pada waktu yang dibolehkan berpuasa (bukan hari raya / tasyrik).','Niat (untuk puasa wajib: dilakukan sebelum fajar).'],
    'syarat_wajib' => ['Islam.','Baligh.','Berakal.','Mampu (sehat, tidak sakit berat).','Mukim (tidak dalam safar yang memberatkan).','Suci dari haid & nifas.'],
  ],
  [
    'judul' => '5. Haji (bagi yang mampu)', 'icon' => 'bi-geo-alt-fill', 'warna' => 'danger',
    'rukun' => ['Ihram (dengan niat haji).','Wukuf di Arafah (9 Dzulhijjah).','Thawaf Ifadhah.','Sa\'i antara Shafa dan Marwah.','Tahallul (mencukur/memendekkan rambut).','Tertib pada sebagian besar rukun.'],
    'syarat_sah' => ['Dilaksanakan pada waktunya (bulan-bulan haji: Syawal, Dzulqa\'dah, 10 hari Dzulhijjah).','Dilakukan di tempat-tempat manasik (Mekkah, Arafah, Muzdalifah, Mina).','Mengikuti urutan manasik sesuai tuntunan.','Berihram dari miqat.'],
    'syarat_wajib' => ['Islam.','Baligh.','Berakal.','Merdeka.','Mampu (istitha\'ah): fisik, bekal, kendaraan, & keamanan perjalanan.','Bagi wanita: disertai mahram atau rombongan yang aman menurut sebagian ulama.'],
  ],
];

include __DIR__.'/includes/header.php';
?>
<nav aria-label="breadcrumb" class="mb-2"><ol class="breadcrumb small mb-0">
  <li class="breadcrumb-item"><a href="/islami.php">Hub Islami</a></li>
  <li class="breadcrumb-item active">Rukun Islam</li>
</ol></nav>

<div class="card shadow-sm mb-3 border-success">
  <div class="card-header bg-success text-white d-flex align-items-center justify-content-between">
    <span><i class="bi bi-stars"></i> <strong>RUKUN ISLAM</strong> — 5 Pilar Beserta Rukun, Syarat Sah &amp; Syarat Wajib</span>
    <small class="opacity-75 d-none d-md-inline">Ringkasan ringkas</small>
  </div>
  <div class="card-body">
    <div class="accordion" id="accRukunIslam">
      <?php foreach ($RUKUN_ISLAM as $i=>$r): ?>
      <div class="accordion-item">
        <h2 class="accordion-header" id="hRI<?= $i ?>">
          <button class="accordion-button <?= $i>0?'collapsed':'' ?>" type="button" data-bs-toggle="collapse"
                  data-bs-target="#cRI<?= $i ?>" aria-expanded="<?= $i===0?'true':'false' ?>" aria-controls="cRI<?= $i ?>">
            <i class="bi <?= $r['icon'] ?> text-<?= $r['warna'] ?> me-2 fs-5"></i>
            <strong><?= $r['judul'] ?></strong>
          </button>
        </h2>
        <div id="cRI<?= $i ?>" class="accordion-collapse collapse <?= $i===0?'show':'' ?>"
             aria-labelledby="hRI<?= $i ?>" data-bs-parent="#accRukunIslam">
          <div class="accordion-body">
            <div class="row g-3">
              <div class="col-md-4">
                <h6 class="text-success"><i class="bi bi-check2-square"></i> Rukun</h6>
                <ol class="ps-3 small mb-0">
                  <?php foreach ($r['rukun'] as $x): ?><li><?= $x ?></li><?php endforeach; ?>
                </ol>
              </div>
              <div class="col-md-4">
                <h6 class="text-primary"><i class="bi bi-shield-check"></i> Syarat Sah</h6>
                <ol class="ps-3 small mb-0">
                  <?php foreach ($r['syarat_sah'] as $x): ?><li><?= $x ?></li><?php endforeach; ?>
                </ol>
              </div>
              <div class="col-md-4">
                <h6 class="text-danger"><i class="bi bi-exclamation-octagon"></i> Syarat Wajib</h6>
                <ol class="ps-3 small mb-0">
                  <?php foreach ($r['syarat_wajib'] as $x): ?><li><?= $x ?></li><?php endforeach; ?>
                </ol>
              </div>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<?php include __DIR__.'/includes/footer.php'; ?>
