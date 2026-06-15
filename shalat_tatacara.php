<?php
/** Revisi 15 Juni 2026 — Halaman Tata Cara Shalat (dipindah dari islami.php) */
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
require __DIR__.'/includes/shalat_data.php';
send_security_headers(); require_login();
$pageTitle = 'Tata Cara Shalat';
include __DIR__.'/includes/header.php';
?>
<nav aria-label="breadcrumb" class="mb-2"><ol class="breadcrumb small mb-0">
  <li class="breadcrumb-item"><a href="/islami.php">Hub Islami</a></li>
  <li class="breadcrumb-item active">Tata Cara Shalat</li>
</ol></nav>

<div class="card shadow-sm mb-3 border-primary">
  <div class="card-header bg-primary text-white d-flex align-items-center justify-content-between">
    <span><i class="bi bi-person-arms-up"></i> <strong>TATA CARA SHALAT</strong> — Urutan, Bacaan Arab, Latin &amp; Terjemah</span>
    <small class="opacity-75 d-none d-md-inline">Panduan ringkas</small>
  </div>
  <div class="card-body">
    <div class="accordion" id="accTataCaraShalat">
      <?php foreach ($SHALAT_TATA_CARA as $i=>$t): ?>
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
            <div class="mb-2 text-end" dir="rtl" lang="ar" style="font-size:1.5rem;line-height:2.2;font-family:'Scheherazade New','Amiri','Times New Roman',serif;"><?= htmlspecialchars($t['arab']) ?></div>
            <div class="mb-2"><span class="badge bg-primary-subtle text-primary me-1">Latin</span><em><?= htmlspecialchars($t['latin']) ?></em></div>
            <div class="small"><span class="badge bg-success-subtle text-success me-1">Arti</span><?= htmlspecialchars($t['arti']) ?></div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<?php include __DIR__.'/includes/footer.php'; ?>
