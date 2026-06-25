<?php
/** Revisi R14 (25 Juni 2026)
 *  - Tambah gambar AI (pollinations.ai) untuk masing-masing gerakan.
 *  - Tambah breadcrumb & tombol kembali ke Wudhu.
 */
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
require __DIR__.'/includes/shalat_data.php';
send_security_headers(); require_login();
$pageTitle = 'Tata Cara Shalat';

// Mapping prompt gambar AI untuk tiap nomor langkah (#4 R14)
$SHALAT_AI_PROMPTS = [
  0  => 'muslim man standing facing qibla, hands at sides, making sincere intention for shalat (niat), modest white attire, peaceful expression, instructional illustration, soft daylight',
  1  => 'muslim man raising both hands beside ears performing takbiratul ihram Allahu Akbar at start of shalat, side view, prayer mat, instructional illustration',
  2  => 'muslim man standing in shalat with hands folded on chest reciting doa iftitah, peaceful expression, prayer mat, mosque interior background, instructional illustration',
  3  => 'muslim man standing in shalat reciting Al-Fatihah, hands folded on chest, prayer mat, calm expression, instructional illustration',
  4  => 'muslim man standing in shalat reciting short surah, hands folded on chest, prayer mat, peaceful, instructional illustration',
  5  => 'muslim man performing ruku in shalat, back parallel to ground, hands on knees, side view, prayer mat, instructional illustration',
  6  => 'muslim man standing straight in itidal after ruku, hands at sides, prayer mat, instructional illustration',
  7  => 'muslim man performing sujud (prostration) in shalat, forehead nose hands knees toes on prayer mat, instructional illustration',
  8  => 'muslim man sitting between two sujud (duduk iftirasy) in shalat, hands on thighs, prayer mat, side view, instructional illustration',
  9  => 'muslim man sitting tasyahud awal in shalat (iftirasy), index finger pointing, calm expression, prayer mat, instructional illustration',
  10 => 'muslim man sitting tasyahud akhir (tawarruk) reciting shalawat, index finger pointing, prayer mat, instructional illustration',
  11 => 'muslim man turning head right giving salam at the end of shalat, prayer mat, instructional illustration',
];
function ai_img_url(string $prompt, int $w=640, int $h=420, int $seed=1): string {
    return 'https://image.pollinations.ai/prompt/'.rawurlencode($prompt).'?width='.$w.'&height='.$h.'&nologo=true&seed='.$seed;
}

include __DIR__.'/includes/header.php';
?>
<nav aria-label="breadcrumb" class="mb-2"><ol class="breadcrumb small mb-0">
  <li class="breadcrumb-item"><a href="/islami.php">Hub Islami</a></li>
  <li class="breadcrumb-item"><a href="/wudhu_tatacara.php">Tata Cara Wudhu</a></li>
  <li class="breadcrumb-item active">Tata Cara Shalat</li>
</ol></nav>

<div class="card shadow-sm mb-3 border-primary">
  <div class="card-header bg-primary text-white d-flex align-items-center justify-content-between">
    <span><i class="bi bi-person-arms-up"></i> <strong>TATA CARA SHALAT</strong> — Urutan, Bacaan Arab, Latin, Terjemah &amp; Ilustrasi AI</span>
    <small class="opacity-75 d-none d-md-inline">Gambar di-generate oleh AI</small>
  </div>
  <div class="card-body">
    <div class="alert alert-info small py-2 mb-3"><i class="bi bi-info-circle"></i> Pastikan sudah berwudhu. Lihat <a href="/wudhu_tatacara.php" class="alert-link">Tata Cara Wudhu</a> jika perlu.</div>
    <div class="accordion" id="accTataCaraShalat">
      <?php foreach ($SHALAT_TATA_CARA as $i=>$t):
        $prompt = $SHALAT_AI_PROMPTS[$i] ?? ('muslim man in shalat, instructional illustration, step '.($i+1)); ?>
      <div class="accordion-item">
        <h2 class="accordion-header" id="hTC<?= $i ?>">
          <button class="accordion-button <?= $i>0?'collapsed':'' ?>" type="button" data-bs-toggle="collapse"
                  data-bs-target="#cTC<?= $i ?>" aria-expanded="<?= $i===0?'true':'false' ?>" aria-controls="cTC<?= $i ?>">
            <strong><?= htmlspecialchars($t['judul']) ?></strong>
          </button>
        </h2>
        <div id="cTC<?= $i ?>" class="accordion-collapse collapse <?= $i===0?'show':'' ?>"
             aria-labelledby="hTC<?= $i ?>" data-bs-parent="#accTataCaraShalat">
          <div class="accordion-body">
            <div class="row g-3">
              <div class="col-md-5">
                <img loading="lazy" class="img-fluid rounded border" alt="Ilustrasi: <?= htmlspecialchars($t['judul']) ?>"
                     src="<?= htmlspecialchars(ai_img_url($prompt, 640, 420, $i+1)) ?>">
                <div class="small text-muted mt-1"><i class="bi bi-stars"></i> Ilustrasi AI (pollinations.ai)</div>
              </div>
              <div class="col-md-7">
                <div class="mb-2 text-end" dir="rtl" lang="ar" style="font-size:1.5rem;line-height:2.2;font-family:'Scheherazade New','Amiri','Times New Roman',serif;"><?= htmlspecialchars($t['arab']) ?></div>
                <div class="mb-2"><span class="badge bg-primary-subtle text-primary me-1">Latin</span><em><?= htmlspecialchars($t['latin']) ?></em></div>
                <div class="small"><span class="badge bg-success-subtle text-success me-1">Arti</span><?= htmlspecialchars($t['arti']) ?></div>
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
