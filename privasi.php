<?php
/**
 * privasi.php — Kebijakan Privasi (UU PDP).
 * Revisi 2 Juli 2026 #6: mendukung mode ?embed=1 supaya bisa dimuat di dalam
 * modal popup pada halaman login (tanpa header/footer aplikasi).
 */
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/helpers.php';
$pageTitle = 'Kebijakan Privasi';
$embed = !empty($_GET['embed']);

$k = db_one("SELECT * FROM kebijakan_privasi WHERE aktif=true ORDER BY id DESC LIMIT 1");
if (!$k) {
    $k = ['judul'=>'Kebijakan Privasi & UU PDP','versi'=>'-','konten'=>'<p class="text-muted">Belum ada kebijakan privasi yang dipublikasikan.</p>','updated_at'=>'-'];
}

if ($embed) {
?><!doctype html>
<html lang="id"><head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= htmlspecialchars($k['judul']) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
body{font-family:system-ui,'Plus Jakarta Sans',sans-serif;margin:0;padding:1.25rem 1.4rem;color:#0f172a;line-height:1.7}
h1{font-size:1.4rem;margin:0 0 .2rem}
.meta{color:#64748b;font-size:.8rem;margin-bottom:1rem}
.konten :is(h2,h3){margin-top:1.2rem}
</style>
</head><body>
<h1><?= htmlspecialchars($k['judul']) ?></h1>
<div class="meta">Versi <?= htmlspecialchars($k['versi']) ?> · Diperbarui: <?= htmlspecialchars($k['updated_at']) ?></div>
<div class="konten"><?= $k['konten'] ?></div>
</body></html>
<?php
    exit;
}

include __DIR__.'/includes/header.php';
?>
<article class="container py-4" style="max-width:860px;">
  <h1 class="mb-1"><?= htmlspecialchars($k['judul']) ?></h1>
  <p class="text-muted small mb-3">Versi <?= htmlspecialchars($k['versi']) ?> · Diperbarui: <?= htmlspecialchars($k['updated_at']) ?></p>
  <div class="card shadow-sm"><div class="card-body" style="line-height:1.7;">
    <?= $k['konten'] ?>
  </div></div>
  <div class="mt-3">
    <a href="/login.php" id="btnPrivasiBack" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Kembali</a>
  </div>
  <script>
    (function(){
      var b=document.getElementById("btnPrivasiBack"); if(!b)return;
      b.addEventListener("click",function(e){
        var ref=document.referrer||"";
        if(ref && history.length>1 && ref.indexOf(location.host)!==-1){ e.preventDefault(); history.back(); }
      });
    })();
  </script>
</article>
<?php include __DIR__.'/includes/footer.php'; ?>
