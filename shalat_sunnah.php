<?php
/** Revisi R14 (25 Juni 2026)
 *  #5 — Tambah terjemah bahasa Indonesia untuk doa setelah Shalat Duha & Tahajud.
 */
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
require __DIR__.'/includes/shalat_data.php';
send_security_headers(); require_login();
$pageTitle = 'Shalat Sunnah Duha & Tahajud';

// Terjemah Indonesia untuk doa setelah Shalat Duha & Tahajud (R14 #5)
$DOA_TERJEMAH = [
  'Shalat Duha' => 'Ya Allah, sesungguhnya waktu Duha adalah waktu Duha-Mu, keagungan adalah keagungan-Mu, keindahan adalah keindahan-Mu, kekuatan adalah kekuatan-Mu, kekuasaan adalah kekuasaan-Mu, dan perlindungan adalah perlindungan-Mu. Ya Allah, jika rezekiku berada di langit maka turunkanlah, jika berada di bumi maka keluarkanlah, jika sulit maka mudahkanlah, jika haram maka sucikanlah, jika jauh maka dekatkanlah. Dengan kebenaran waktu Duha-Mu, keagungan-Mu, keindahan-Mu, kekuatan-Mu, dan kekuasaan-Mu, berikanlah kepadaku apa yang telah Engkau berikan kepada hamba-hamba-Mu yang shalih.',

  'Shalat Tahajud' => 'Ya Allah, segala puji bagi-Mu, Engkaulah cahaya langit dan bumi serta semua yang ada di dalamnya. Segala puji bagi-Mu, Engkaulah penegak langit dan bumi serta semua yang ada di dalamnya. Segala puji bagi-Mu, Engkaulah Yang Maha Benar, janji-Mu benar, pertemuan dengan-Mu benar, firman-Mu benar, surga itu benar, neraka itu benar, para Nabi itu benar, Nabi Muhammad ﷺ itu benar, dan hari Kiamat itu benar.',
];

include __DIR__.'/includes/header.php';
?>
<nav aria-label="breadcrumb" class="mb-2"><ol class="breadcrumb small mb-0">
  <li class="breadcrumb-item"><a href="/islami.php">Hub Islami</a></li>
  <li class="breadcrumb-item active">Shalat Sunnah Duha &amp; Tahajud</li>
</ol></nav>

<?php if (!empty($SHALAT_SUNNAH_LAIN)): ?>
<div class="card shadow-sm mb-3 border-info">
  <div class="card-header bg-info-subtle text-info-emphasis d-flex align-items-center justify-content-between">
    <span><i class="bi bi-sun"></i> <strong>SHALAT SUNNAH DUHA &amp; TAHAJUD</strong></span>
    <small class="opacity-75 d-none d-md-inline">Penambah pahala &amp; pintu rezeki</small>
  </div>
  <div class="card-body">
    <div class="row g-3">
      <?php foreach ($SHALAT_SUNNAH_LAIN as $sh):
        $terjemah = $DOA_TERJEMAH[$sh['nama']] ?? null; ?>
      <div class="col-md-6">
        <div class="border rounded p-3 h-100 bg-light-subtle">
          <h6 class="fw-bold text-info-emphasis mb-2"><i class="bi bi-moon-stars"></i> <?= htmlspecialchars($sh['nama']) ?></h6>
          <div class="small mb-2"><strong>Waktu:</strong> <?= htmlspecialchars($sh['waktu']) ?></div>
          <div class="small mb-2"><strong>Jumlah Rakaat:</strong> <?= htmlspecialchars($sh['rakaat']) ?></div>
          <div class="small mb-2"><strong>Tata Cara:</strong>
            <ol class="mb-1 ps-3">
              <?php foreach ($sh['tata_cara'] as $tc): ?><li><?= htmlspecialchars($tc) ?></li><?php endforeach; ?>
            </ol>
          </div>
          <div class="small mb-2"><strong>Doa Setelah Shalat (Arab):</strong>
            <div class="p-2 bg-white border rounded mt-1" style="font-family:'Scheherazade New','Amiri',serif;font-size:1.1rem;line-height:1.9;direction:rtl;text-align:right"><?= htmlspecialchars($sh['doa']) ?></div>
          </div>
          <?php if ($terjemah): ?>
          <!-- R14 #5: terjemah Indonesia -->
          <div class="small mb-2"><strong class="text-success">Terjemah (Bahasa Indonesia):</strong>
            <div class="p-2 bg-success-subtle border border-success-subtle rounded mt-1 fst-italic" style="line-height:1.7"><?= htmlspecialchars($terjemah) ?></div>
          </div>
          <?php endif; ?>
          <div class="small text-muted"><strong>Fadhilah:</strong> <?= htmlspecialchars($sh['fadhilah']) ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<?php include __DIR__.'/includes/footer.php'; ?>
