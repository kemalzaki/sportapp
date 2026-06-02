<?php
/**
 * privasi.php — halaman publik kebijakan privasi (UU PDP).
 * Diakses dari tombol di /login.php dan /register.php.
 */
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/helpers.php';
$pageTitle = 'Kebijakan Privasi';

$k = db_one("SELECT * FROM kebijakan_privasi WHERE aktif=true ORDER BY id DESC LIMIT 1");
if (!$k) {
    $k = ['judul'=>'Kebijakan Privasi','versi'=>'-','konten'=>'<p class="text-muted">Belum ada kebijakan privasi yang dipublikasikan.</p>','updated_at'=>'-'];
}
include __DIR__.'/includes/header.php';
?>
<article class="container py-4" style="max-width:860px;">
  <h1 class="mb-1"><?= htmlspecialchars($k['judul']) ?></h1>
  <p class="text-muted small mb-3">Versi <?= htmlspecialchars($k['versi']) ?> · Diperbarui: <?= htmlspecialchars($k['updated_at']) ?></p>
  <div class="card shadow-sm"><div class="card-body" style="line-height:1.7;">
    <?= $k['konten'] /* HTML sudah disanitize di admin */ ?>
  </div></div>
  <div class="mt-3">
    <a href="javascript:history.back()" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Kembali</a>
  </div>
</article>
<?php include __DIR__.'/includes/footer.php'; ?>
