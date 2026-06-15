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
require_once __DIR__.'/includes/htmx.php'; htmx_layout_start($pageTitle ?? 'Privasi');
?>
<article class="container py-4" style="max-width:860px;">
  <h1 class="mb-1"><?= htmlspecialchars($k['judul']) ?></h1>
  <p class="text-muted small mb-3">Versi <?= htmlspecialchars($k['versi']) ?> · Diperbarui: <?= htmlspecialchars($k['updated_at']) ?></p>
  <div class="card shadow-sm"><div class="card-body" style="line-height:1.7;">
    <?= $k['konten'] /* HTML sudah disanitize di admin */ ?>
  </div></div>
  <div class="mt-3">
    <a href="/login.php" id="btnPrivasiBack" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Kembali</a>
  <script>
    (function(){
      var b=document.getElementById("btnPrivasiBack");
      if(!b)return;
      b.addEventListener("click",function(e){
        var ref=document.referrer||"";
        if(ref && history.length>1 && ref.indexOf(location.host)!==-1){
          e.preventDefault(); history.back();
        }
      });
    })();
  </script>
  </div>
</article>
<?php htmx_layout_end(); ?>
