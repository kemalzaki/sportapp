<?php
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/helpers.php';
$pageTitle = 'Hidup Sehat';
include __DIR__.'/includes/header.php';

/* Revisi: tiap kartu panduan dibungkus dalam collapse (spoiler) agar tampilan
   awal ringkas. User bisa membuka bagian yang diminati saja. */
$sections = [
  [
    'id'    => 'hs-hindari',
    'title' => 'Hindari / Kurangi',
    'icon'  => 'bi-x-circle text-danger',
    'items' => [
      'Gula tambahan: minuman manis kemasan, sirup, kental manis.',
      'Tepung halus: roti putih, mie instan, kue pabrik, biskuit.',
      'Makanan ultra-proses pabrik: nugget, sosis, makanan kaleng tinggi natrium.',
      'Gorengan berulang &amp; minyak trans (margarin murah, minyak bekas).',
      'MSG berlebih, penyedap bubuk instan.',
      'Rokok, vape, alkohol.',
      'Tidur larut &amp; begadang tanpa alasan.',
    ],
  ],
  [
    'id'    => 'hs-saran',
    'title' => 'Saran Pola Makan',
    'icon'  => 'bi-check-circle text-success',
    'items' => [
      'Karbohidrat kompleks: nasi merah, ubi, kentang rebus, oat.',
      'Protein utuh: telur, ikan, ayam kampung, tahu, tempe.',
      'Sayur warna-warni minimal 2 porsi sehari (bayam, brokoli, wortel, kangkung).',
      'Buah segar: pisang, apel, pepaya, jeruk, semangka.',
      'Lemak baik: alpukat, kacang almond, minyak zaitun, ikan laut.',
      'Air putih &plusmn;2 liter/hari; kurangi soda &amp; kopi sachet.',
      'Rempah alami: kunyit, jahe, bawang putih, kayu manis.',
    ],
  ],
  [
    'id'    => 'hs-pola',
    'title' => 'Pola &amp; Jadwal',
    'icon'  => 'bi-clock-history text-primary',
    'items' => [
      'Sarapan secukupnya (jangan dilewatkan jika beraktivitas berat).',
      'Makan malam maksimal 3 jam sebelum tidur.',
      'Coba puasa intermiten 14:10 atau 16:8 sesuai kondisi.',
      'Tidur 7&ndash;8 jam, tidur sebelum jam 23.00.',
      'Olahraga ringan minimal 30 menit, 3&ndash;5x seminggu.',
    ],
  ],
  [
    'id'    => 'hs-tips',
    'title' => 'Tips Praktis',
    'icon'  => 'bi-lightbulb text-warning',
    'items' => [
      'Masak sendiri minimal 1x sehari supaya tahu komposisinya.',
      'Baca label gizi: cek gula, natrium, lemak trans.',
      'Jalan kaki 7.000&ndash;10.000 langkah/hari.',
      'Kelola stres: dzikir, journaling, jeda layar tiap 1 jam.',
      'Cek tekanan darah &amp; berat badan rutin.',
    ],
  ],
];
?>
<div class="container py-3">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-heart-fill text-success"></i> Hidup Sehat</h1>
    <a href="/index.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Beranda</a>
  </div>
  <p class="text-muted small">Panduan ringkas kebiasaan dan pola makan sehat ala KawanKeringat. Klik setiap panel untuk membuka isinya.</p>

  <!-- Revisi: tampilan spoiler (accordion) supaya halaman tidak overwhelming. -->
  <div class="accordion" id="hidupSehatAcc">
    <?php foreach ($sections as $i => $sec): ?>
      <div class="accordion-item">
        <h2 class="accordion-header" id="head-<?= $sec['id'] ?>">
          <button class="accordion-button <?= $i===0?'':'collapsed' ?>" type="button"
                  data-bs-toggle="collapse" data-bs-target="#body-<?= $sec['id'] ?>"
                  aria-expanded="<?= $i===0?'true':'false' ?>" aria-controls="body-<?= $sec['id'] ?>">
            <i class="bi <?= $sec['icon'] ?> me-2"></i> <strong><?= $sec['title'] ?></strong>
          </button>
        </h2>
        <div id="body-<?= $sec['id'] ?>" class="accordion-collapse collapse <?= $i===0?'show':'' ?>"
             aria-labelledby="head-<?= $sec['id'] ?>" data-bs-parent="#hidupSehatAcc">
          <div class="accordion-body">
            <ul class="mb-0">
              <?php foreach ($sec['items'] as $it): ?>
                <li><?= $it ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="alert alert-info mt-3 small mb-0">
    <i class="bi bi-info-circle"></i> Konten edukatif umum, bukan pengganti konsultasi dokter. Bila ada kondisi medis khusus, konsultasikan dengan tenaga kesehatan.
  </div>
</div>
<?php include __DIR__.'/includes/footer.php'; ?>
