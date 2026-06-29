<?php
/**
 * panduan_adzan.php — Revisi (28 Juni 2026).
 * Menu baru di Hub Islami: Panduan Adzan (lafadz Arab + latin + terjemah Indonesia),
 * cara menjawab adzan oleh masyarakat (la haula wala quwwata illa billah, dst),
 * serta panduan adzan saat di lapangan / safar.
 *
 * Halaman statis (tidak memerlukan tabel baru).
 */
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
require __DIR__.'/includes/paket_helpers.php';
send_security_headers(); require_login();
$pageTitle = 'Panduan Adzan';
$u = current_user();
$USER_PAKET = paket_user($u);
$IS_KOMUNITAS = ($USER_PAKET === 'komunitas');

include __DIR__.'/includes/header.php';
?>
<nav aria-label="breadcrumb" class="mb-2"><ol class="breadcrumb small mb-0">
  <li class="breadcrumb-item"><a href="/index.php">Beranda</a></li>
  <li class="breadcrumb-item"><a href="/islami.php">Hub Islami</a></li>
  <li class="breadcrumb-item active">Panduan Adzan</li>
</ol></nav>

<?php if (!$IS_KOMUNITAS): ?>
  <div class="alert alert-warning small">
    Halaman ini bagian dari <b>Hub Islami</b> yang eksklusif untuk paket <b>KOMUNITAS</b>.
    Paket Anda saat ini: <b><?= htmlspecialchars(strtoupper($USER_PAKET)) ?></b>.
    <a href="/paket_upgrade.php" class="alert-link">Upgrade paket →</a>
  </div>
  <?php include __DIR__.'/includes/footer.php'; exit; ?>
<?php endif; ?>

<div class="hero-sport-islami hero-islami mb-3">
  <div class="hero-overlay">
    <span class="badge bg-light text-primary mb-2"><i class="bi bi-megaphone-fill"></i> ADZAN &amp; IQAMAH</span>
    <h1 class="h3 mb-1 fw-bold">Panduan Adzan — Lafadz, Terjemah &amp; Cara Menjawabnya</h1>
    <p class="small mb-0 opacity-85">
      Lengkap dengan tuntunan adzan saat di lapangan / safar (perjalanan) dan
      adab muadzin maupun pendengar.
    </p>
  </div>
</div>

<?php
/* ---- DATA LAFADZ ADZAN ---- */
$adzan = [
  ['Allāhu Akbar (4x)', 'الله أكبر، الله أكبر، الله أكبر، الله أكبر',
   'Allah Maha Besar',
   'Allāhu Akbar (mengikuti lafadz muadzin)'],
  ['Asyhadu allā ilāha illallāh (2x)', 'أشهد أن لا إله إلا الله',
   'Aku bersaksi tiada Tuhan yang berhak disembah selain Allah',
   'Asyhadu allā ilāha illallāh (mengikuti lafadz muadzin)'],
  ['Asyhadu anna Muhammadar Rasūlullāh (2x)', 'أشهد أن محمداً رسول الله',
   'Aku bersaksi bahwa Muhammad adalah utusan Allah',
   'Asyhadu anna Muhammadar Rasūlullāh (mengikuti lafadz muadzin)'],
  ['Hayya \'ala-sh-shalāh (2x)', 'حي على الصلاة',
   'Marilah menunaikan shalat',
   '<b>Lā haula walā quwwata illā billāh</b> — tiada daya &amp; kekuatan kecuali dengan pertolongan Allah (HR. Bukhari no. 613, Muslim no. 385)'],
  ['Hayya \'ala-l-falāh (2x)', 'حي على الفلاح',
   'Marilah menuju kemenangan (kebahagiaan)',
   '<b>Lā haula walā quwwata illā billāh</b>'],
  ['Allāhu Akbar (2x)', 'الله أكبر، الله أكبر',
   'Allah Maha Besar',
   'Allāhu Akbar, Allāhu Akbar (mengikuti)'],
  ['Lā ilāha illallāh (1x)', 'لا إله إلا الله',
   'Tiada Tuhan yang berhak disembah selain Allah',
   'Lā ilāha illallāh (mengikuti)'],
];
$adzanSubuh = ['Ash-shalātu khairum minan-naum (2x — khusus Subuh)','الصلاة خير من النوم',
  'Shalat itu lebih baik daripada tidur',
  '<b>Shadaqta wa bararta</b> (engkau benar dan berbuat baik) — riwayat Asy-Syafi\'i'];

$doaSesudahAdzan = [
  'Membaca shalawat: <em>Allāhumma shalli \'alā Muhammad wa \'alā āli Muhammad</em>',
  'Membaca doa wasilah: <em>Allāhumma rabba hādzihid-da\'watit-tāmmah wash-shalātil qā\'imah, āti Muhammadanil wasīlata wal fadhīlah, wab\'atshu maqāmam mahmūdanil-ladzī wa\'adtah</em> — <b>(HR. Bukhari no. 614)</b>',
  'Berdoa pribadi antara adzan dan iqamah — termasuk waktu mustajab (HR. Tirmidzi no. 212, Abu Dawud no. 521).',
];
?>

<!-- Revisi (29 Juni 2026) — Spoiler / collapse keseluruhan konten. -->
<div class="d-flex justify-content-end mb-2">
  <button class="btn btn-sm btn-outline-primary" type="button"
          data-bs-toggle="collapse" data-bs-target="#spoilerPanduanAdzan"
          aria-expanded="true" aria-controls="spoilerPanduanAdzan">
    <i class="bi bi-eye-slash"></i> Sembunyikan / Tampilkan Panduan
  </button>
</div>
<div class="collapse show" id="spoilerPanduanAdzan">
<div class="row g-3">
  <!-- ===== LAFADZ + JAWABAN ===== -->
  <div class="col-12">
    <div class="card shadow-sm border-primary">
      <div class="card-header bg-primary-subtle text-primary-emphasis">
        <i class="bi bi-soundwave"></i> <strong>Lafadz Adzan, Terjemah &amp; Cara Menjawab</strong>
      </div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-bordered align-middle small mb-0">
            <thead class="table-light">
              <tr>
                <th style="width:24%">Lafadz Muadzin (Latin)</th>
                <th style="width:22%" class="text-end">Arab</th>
                <th style="width:24%">Terjemah</th>
                <th>Jawaban Pendengar / Masyarakat</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($adzan as $row): ?>
              <tr>
                <td><b><?= htmlspecialchars($row[0]) ?></b></td>
                <td class="text-end fs-5" dir="rtl" lang="ar"><?= $row[1] ?></td>
                <td><?= htmlspecialchars($row[2]) ?></td>
                <td><?= $row[3] ?></td>
              </tr>
            <?php endforeach; ?>
              <tr class="table-warning">
                <td><b><?= htmlspecialchars($adzanSubuh[0]) ?></b></td>
                <td class="text-end fs-5" dir="rtl" lang="ar"><?= $adzanSubuh[1] ?></td>
                <td><?= htmlspecialchars($adzanSubuh[2]) ?></td>
                <td><?= $adzanSubuh[3] ?></td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="alert alert-info small mt-3 mb-0">
          <i class="bi bi-info-circle"></i>
          Dasar: Hadits Umar bin Khattab radhiyallahu \'anhu — Rasulullah ﷺ memerintahkan kita
          menjawab adzan dengan lafadz yang sama, kecuali pada <em>Hayya 'alas-shalāh</em> dan
          <em>Hayya 'alal-falāh</em>, kita menjawab dengan
          <b>Lā haula walā quwwata illā billāh</b> (HR. Muslim no. 385).
        </div>
      </div>
    </div>
  </div>

  <!-- ===== DOA SETELAH ADZAN ===== -->
  <div class="col-md-7">
    <div class="card shadow-sm border-success h-100">
      <div class="card-header bg-success-subtle text-success-emphasis">
        <i class="bi bi-stars"></i> <strong>Doa Setelah Adzan &amp; Antara Adzan–Iqamah</strong>
      </div>
      <div class="card-body">
        <ol class="small mb-2">
          <?php foreach ($doaSesudahAdzan as $d): ?>
            <li class="mb-2"><?= $d ?></li>
          <?php endforeach; ?>
        </ol>
        <div class="alert alert-success small mb-0">
          <i class="bi bi-check2-circle"></i>
          "Barangsiapa membaca doa wasilah setelah mendengar adzan, ia berhak mendapat syafa'atku
          pada hari kiamat." (HR. Bukhari no. 614)
        </div>
      </div>
    </div>
  </div>

  <!-- ===== ADAB MUADZIN & PENDENGAR ===== -->
  <div class="col-md-5">
    <div class="card shadow-sm h-100">
      <div class="card-header"><i class="bi bi-list-check text-primary"></i> <strong>Adab Pendengar &amp; Muadzin</strong></div>
      <div class="card-body">
        <ul class="small mb-0">
          <li>Diam, hentikan obrolan, jawab tiap kalimat adzan.</li>
          <li>Jangan keluar masjid setelah adzan dikumandangkan kecuali ada uzur (HR. Muslim no. 655).</li>
          <li>Muadzin sebaiknya suci dari hadats, menghadap kiblat, memasukkan jari ke telinga, suara lantang &amp; tartil.</li>
          <li>Muadzin tidak mengambil upah dari adzan (HR. Abu Dawud no. 531, Tirmidzi no. 209).</li>
          <li>Antara adzan dan iqamah ada jeda secukupnya agar jamaah berkumpul (± 5–15 menit).</li>
        </ul>
      </div>
    </div>
  </div>

  <!-- ===== ADZAN DI LAPANGAN / SAFAR ===== -->
  <div class="col-12">
    <div class="card shadow-sm border-warning">
      <div class="card-header bg-warning-subtle text-warning-emphasis">
        <i class="bi bi-geo-alt-fill"></i> <strong>Panduan Adzan Saat di Lapangan / Safar (Perjalanan)</strong>
      </div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-6">
            <h6 class="fw-bold mb-2"><i class="bi bi-1-circle-fill text-warning"></i> Wajib Adzan Walau di Lapangan?</h6>
            <p class="small mb-2">
              Adzan adalah <b>fardhu kifayah</b> bagi komunitas muslim. Rasulullah ﷺ bersabda:
              "Apabila waktu shalat telah tiba, hendaklah salah seorang dari kalian mengumandangkan
              adzan, lalu yang paling tua menjadi imam." (HR. Bukhari no. 628)
            </p>
            <p class="small mb-0">
              Saat di lapangan (camping, kerja proyek, perjalanan, mendaki, latihan survival),
              meskipun hanya 2 orang, <b>tetap dianjurkan adzan &amp; iqamah</b> sebelum shalat.
            </p>
          </div>
          <div class="col-md-6">
            <h6 class="fw-bold mb-2"><i class="bi bi-2-circle-fill text-warning"></i> Praktis di Lapangan</h6>
            <ul class="small mb-0">
              <li>Suara tidak harus lantang seperti di masjid — cukup terdengar oleh rombongan.</li>
              <li>Menghadap kiblat (gunakan kompas / aplikasi qibla).</li>
              <li>Jika musafir, boleh menjamak shalat (lihat <a href="/panduan_shalat_jama.php">Panduan Shalat Jama'</a>) — tetap satu adzan + dua iqamah (satu untuk tiap shalat).</li>
              <li>Bila waktu sempit / kondisi darurat (hujan deras, dingin ekstrem, di kendaraan), cukup iqamah saja juga sah.</li>
              <li>Boleh menggunakan pengeras suara portabel selama tidak mengganggu warga sekitar.</li>
            </ul>
          </div>
          <div class="col-12">
            <div class="alert alert-warning small mb-0">
              <i class="bi bi-info-circle"></i>
              <b>Catatan musafir:</b> Dalam perjalanan jauh (≥ 80 km), jika hanya sendirian
              boleh tidak adzan tetapi tetap iqamah. Dianjurkan adzan bila bersama jamaah agar
              syiar Islam tetap tegak walau di tengah hutan / gunung / lapangan kerja.
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
</div><!-- /#spoilerPanduanAdzan -->

<?php include __DIR__.'/includes/footer.php'; ?>
