<?php
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
require __DIR__.'/includes/islami_helpers.php';
require __DIR__.'/includes/hadist_data.php';
send_security_headers(); require_login();
$pageTitle = 'Ensiklopedia Hadist';

$tema = $_GET['tema'] ?? 'semua';
$kitab = $_GET['kitab'] ?? 'semua';
$q = trim($_GET['q'] ?? '');

$data = array_filter($HADITHS, function($h) use ($tema,$kitab,$q){
    if ($tema!=='semua' && ($h['tema']??'')!==$tema) return false;
    if ($kitab!=='semua' && ($h['kitab']??'')!==$kitab) return false;
    if ($q!=='') {
        $hay = mb_strtolower($h['id'].' '.$h['arab'].' '.$h['perawi'].' '.$h['kitab']);
        if (strpos($hay, mb_strtolower($q)) === false) return false;
    }
    return true;
});

include __DIR__.'/includes/header.php';
?>
<h4 class="mb-3"><i class="bi bi-book-half text-success"></i> Ensiklopedia Hadist</h4>
<p class="text-muted small">Koleksi hadits dari <strong>Sahih Bukhari</strong> &amp; <strong>Sahih Muslim</strong> bernuansa <em>Perjuangan</em> dan <em>Olahraga</em>.</p>

<form class="row g-2 mb-3" method="get">
  <div class="col-md-3"><select name="tema" class="form-select" onchange="this.form.submit()">
    <option value="semua" <?= $tema==='semua'?'selected':'' ?>>Semua tema</option>
    <option value="perjuangan" <?= $tema==='perjuangan'?'selected':'' ?>>Perjuangan</option>
    <option value="olahraga"   <?= $tema==='olahraga'?'selected':'' ?>>Olahraga</option>
  </select></div>
  <div class="col-md-3"><select name="kitab" class="form-select" onchange="this.form.submit()">
    <option value="semua" <?= $kitab==='semua'?'selected':'' ?>>Semua kitab</option>
    <option value="Sahih Bukhari" <?= $kitab==='Sahih Bukhari'?'selected':'' ?>>Sahih Bukhari</option>
    <option value="Sahih Muslim"  <?= $kitab==='Sahih Muslim'?'selected':'' ?>>Sahih Muslim</option>
  </select></div>
  <div class="col-md-5"><input class="form-control" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Cari kata kunci..."></div>
  <div class="col-md-1"><button class="btn btn-success w-100"><i class="bi bi-search"></i></button></div>
</form>

<div class="small text-muted mb-2"><?= count($data) ?> hadits</div>

<?php foreach ($data as $h):
  $temaColor = $h['tema']==='olahraga' ? 'primary' : 'danger'; ?>
<div class="card mb-3 border-<?= $temaColor ?>"><div class="card-body">
  <div class="d-flex justify-content-between align-items-start mb-2">
    <div>
      <span class="badge bg-<?= $temaColor ?> me-1"><?= ucfirst($h['tema']) ?></span>
      <span class="badge bg-success"><?= htmlspecialchars($h['kitab']) ?> No. <?= (int)$h['no'] ?></span>
    </div>
    <div class="small text-muted">Perawi: <?= htmlspecialchars($h['perawi']) ?></div>
  </div>
  <div class="text-end fs-4 mb-2" dir="rtl" style="font-family:'Amiri','Scheherazade New',serif;line-height:2"><?= htmlspecialchars($h['arab']) ?></div>
  <div><?= htmlspecialchars($h['id']) ?></div>
</div></div>
<?php endforeach; if (!$data): ?>
<div class="alert alert-warning">Tidak ada hadits yang cocok.</div>
<?php endif; ?>

<?php include __DIR__.'/includes/footer.php'; ?>
