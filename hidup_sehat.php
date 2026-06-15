<?php
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/helpers.php';
$pageTitle = 'Hidup Sehat';
include __DIR__.'/includes/header.php';
?>
<div class="container py-3">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-heart-fill text-success"></i> Hidup Sehat</h1>
    <a href="/index.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Beranda</a>
  </div>
  <p class="text-muted small">Panduan ringkas kebiasaan dan pola makan sehat ala HapFam. Mulai dari hal kecil yang konsisten.</p>

  <div class="row g-3">
    <div class="col-md-6">
      <div class="card shadow-sm h-100"><div class="card-body">
        <h5 class="card-title"><i class="bi bi-x-circle text-danger"></i> Hindari / Kurangi</h5>
        <ul class="mb-0">
          <li>Gula tambahan: minuman manis kemasan, sirup, kental manis.</li>
          <li>Tepung halus: roti putih, mie instan, kue pabrik, biskuit.</li>
          <li>Makanan ultra-proses pabrik: nugget, sosis, makanan kaleng tinggi natrium.</li>
          <li>Gorengan berulang & minyak trans (margarin murah, minyak bekas).</li>
          <li>MSG berlebih, penyedap bubuk instan.</li>
          <li>Rokok, vape, alkohol.</li>
          <li>Tidur larut & begadang tanpa alasan.</li>
        </ul>
      </div></div>
    </div>
    <div class="col-md-6">
      <div class="card shadow-sm h-100"><div class="card-body">
        <h5 class="card-title"><i class="bi bi-check-circle text-success"></i> Saran Pola Makan</h5>
        <ul class="mb-0">
          <li>Karbohidrat kompleks: nasi merah, ubi, kentang rebus, oat.</li>
          <li>Protein utuh: telur, ikan, ayam kampung, tahu, tempe.</li>
          <li>Sayur warna-warni minimal 2 porsi sehari (bayam, brokoli, wortel, kangkung).</li>
          <li>Buah segar: pisang, apel, pepaya, jeruk, semangka.</li>
          <li>Lemak baik: alpukat, kacang almond, minyak zaitun, ikan laut.</li>
          <li>Air putih ±2 liter/hari; kurangi soda &amp; kopi sachet.</li>
          <li>Rempah alami: kunyit, jahe, bawang putih, kayu manis.</li>
        </ul>
      </div></div>
    </div>

    <div class="col-md-6">
      <div class="card shadow-sm h-100"><div class="card-body">
        <h5 class="card-title"><i class="bi bi-clock-history text-primary"></i> Pola &amp; Jadwal</h5>
        <ul class="mb-0">
          <li>Sarapan secukupnya (jangan dilewatkan jika beraktivitas berat).</li>
          <li>Makan malam maksimal 3 jam sebelum tidur.</li>
          <li>Coba puasa intermiten 14:10 atau 16:8 sesuai kondisi.</li>
          <li>Tidur 7–8 jam, tidur sebelum jam 23.00.</li>
          <li>Olahraga ringan minimal 30 menit, 3–5x seminggu.</li>
        </ul>
      </div></div>
    </div>
    <div class="col-md-6">
      <div class="card shadow-sm h-100"><div class="card-body">
        <h5 class="card-title"><i class="bi bi-lightbulb text-warning"></i> Tips Praktis</h5>
        <ul class="mb-0">
          <li>Masak sendiri minimal 1x sehari supaya tahu komposisinya.</li>
          <li>Baca label gizi: cek gula, natrium, lemak trans.</li>
          <li>Jalan kaki 7.000–10.000 langkah/hari.</li>
          <li>Kelola stres: dzikir, journaling, jeda layar tiap 1 jam.</li>
          <li>Cek tekanan darah &amp; berat badan rutin.</li>
        </ul>
      </div></div>
    </div>
  </div>

  <div class="alert alert-info mt-3 small mb-0">
    <i class="bi bi-info-circle"></i> Konten edukatif umum, bukan pengganti konsultasi dokter. Bila ada kondisi medis khusus, konsultasikan dengan tenaga kesehatan.
  </div>
</div>
<?php include __DIR__.'/includes/footer.php'; ?>
