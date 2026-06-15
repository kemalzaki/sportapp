<?php
/** Revisi 15 Juni 2026 — Halaman Shalat Sunnah Rawatib (dipindah dari islami.php) */
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
require __DIR__.'/includes/shalat_data.php';
send_security_headers(); require_login();
$pageTitle = 'Shalat Sunnah Rawatib';
include __DIR__.'/includes/header.php';
?>
<nav aria-label="breadcrumb" class="mb-2"><ol class="breadcrumb small mb-0">
  <li class="breadcrumb-item"><a href="/islami.php">Hub Islami</a></li>
  <li class="breadcrumb-item active">Shalat Sunnah Rawatib</li>
</ol></nav>

<div class="card shadow-sm mb-3 border-warning">
  <div class="card-header bg-warning-subtle text-warning-emphasis d-flex align-items-center justify-content-between">
    <span><i class="bi bi-stars"></i> <strong>SHALAT SUNNAH RAWATIB</strong> — Sunnah Mengiringi Shalat Fardhu</span>
    <small class="opacity-75 d-none d-md-inline">Muakkad = sangat ditekankan</small>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-striped mb-0">
        <thead><tr><th>Shalat Fardhu</th><th>Qabliyah (Sebelum)</th><th>Ba‘diyah (Sesudah)</th><th>Catatan</th></tr></thead>
        <tbody>
          <?php foreach ($SHALAT_RAWATIB as $r): ?>
            <tr>
              <td><strong><?= htmlspecialchars($r['waktu']) ?></strong></td>
              <td><?= htmlspecialchars($r['qabliyah']) ?></td>
              <td><?= htmlspecialchars($r['badiyah']) ?></td>
              <td class="small text-muted"><?= htmlspecialchars($r['catatan']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="p-3 small text-muted border-top">
      <strong>Ringkasan rawatib muakkad (12 rakaat):</strong>
      2 sebelum Subuh · 4 sebelum Zhuhur · 2 sesudah Zhuhur · 2 sesudah Maghrib · 2 sesudah Isya.
      Nabi ﷺ bersabda: "Barangsiapa shalat 12 rakaat dalam sehari semalam, Allah bangunkan baginya rumah di surga." (HR. Muslim)
    </div>
  </div>
</div>

<?php include __DIR__.'/includes/footer.php'; ?>
