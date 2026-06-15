<?php
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
require __DIR__.'/includes/islami_data.php';
require __DIR__.'/includes/islami_helpers.php';
require __DIR__.'/includes/surah_meta.php';
send_security_headers(); require_login();
$pageTitle = 'Al-Qur\'an Digital';
$u = current_user();

$bookmarks = [];
$lastRead = null;
if ($u) {
    $bookmarks = db_all("SELECT * FROM quran_bookmarks WHERE user_id=$1 ORDER BY surah, ayat", [(int)$u['id']]);
    $lastRead = db_one("SELECT * FROM quran_last_read WHERE user_id=$1", [(int)$u['id']]);
}
include __DIR__.'/includes/header.php';
?>
<h4 class="mb-3"><i class="bi bi-book text-success"></i> Al-Qur'an Digital</h4>

<div class="row g-2 mb-3">
  <div class="col-md-4"><a href="/quran_search.php?mode=ayat" class="btn btn-outline-success w-100"><i class="bi bi-search"></i> Cari Ayat (Arab)</a></div>
  <div class="col-md-4"><a href="/quran_search.php?mode=terjemah" class="btn btn-outline-primary w-100"><i class="bi bi-translate"></i> Cari Terjemah (Indonesia)</a></div>
  <div class="col-md-4"><a href="/quran_kata.php" class="btn btn-outline-warning w-100"><i class="bi bi-hash"></i> Jumlah Kata Arab (Rabb, Malik, Diin…)</a></div>
</div>

<div class="alert alert-secondary py-2 small mb-3">
  <span class="badge bg-warning text-dark">Makkiyah</span> = surah turun di Makkah sebelum hijrah ·
  <span class="badge bg-info text-dark">Madaniyah</span> = surah turun di Madinah sesudah hijrah.
</div>

<?php if ($lastRead): ?>
  <div class="alert alert-success py-2 small">
    <i class="bi bi-bookmark-check"></i> Terakhir dibaca:
    <a href="/quran_surah.php?s=<?= (int)$lastRead['surah'] ?>#a<?= (int)$lastRead['ayat'] ?>" class="fw-bold">
      <?= htmlspecialchars($ISLAMI_SURAH[(int)$lastRead['surah']][0] ?? '?') ?> ayat <?= (int)$lastRead['ayat'] ?></a>
  </div>
<?php endif; ?>

<?php if ($bookmarks): ?>
<div class="card shadow-sm mb-3"><div class="card-header"><i class="bi bi-bookmark-star text-warning"></i> Bookmark Ayat Favorit</div>
<div class="list-group list-group-flush">
  <?php foreach ($bookmarks as $b):
    $sn = $ISLAMI_SURAH[(int)$b['surah']][0] ?? '?'; ?>
    <a href="/quran_surah.php?s=<?= (int)$b['surah'] ?>#a<?= (int)$b['ayat'] ?>" class="list-group-item">
      <strong><?= htmlspecialchars($sn) ?></strong> ayat <?= (int)$b['ayat'] ?>
      <?php if (!empty($b['catatan'])): ?><span class="text-muted small">— <?= htmlspecialchars($b['catatan']) ?></span><?php endif; ?>
    </a>
  <?php endforeach; ?>
</div></div>
<?php endif; ?>

<div class="card shadow-sm">
  <div class="card-header d-flex justify-content-between flex-wrap gap-2">
    <span><i class="bi bi-list-ol"></i> Daftar 114 Surah</span>
    <div class="d-flex gap-2">
      <select id="tempatFilter" class="form-select form-select-sm" style="max-width:160px">
        <option value="">Semua tempat turun</option>
        <option value="Makkiyah">Makkiyah saja</option>
        <option value="Madaniyah">Madaniyah saja</option>
      </select>
      <input id="surahFilter" class="form-control form-control-sm" style="max-width:220px" placeholder="Cari surah...">
    </div>
  </div>
  <div class="card-body">
    <div class="row g-2" id="surahList">
      <?php foreach ($ISLAMI_SURAH as $no => $info):
        $tempat = surah_tempat_turun((int)$no); ?>
        <div class="col-md-4 col-sm-6 surah-item" data-name="<?= mb_strtolower($info[0]) ?>" data-tempat="<?= $tempat ?>">
          <a href="/quran_surah.php?s=<?= $no ?>" class="d-block border rounded p-2 text-decoration-none">
            <span class="badge bg-success me-1"><?= $no ?></span>
            <strong><?= htmlspecialchars($info[0]) ?></strong>
            <span class="text-muted small">· <?= $info[1] ?> ayat</span>
            <div class="mt-1"><?= surah_tempat_badge((int)$no) ?></div>
          </a>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<script>
function applyFilter(){
  var q = (document.getElementById('surahFilter').value||'').toLowerCase();
  var t = document.getElementById('tempatFilter').value||'';
  document.querySelectorAll('#surahList .surah-item').forEach(function(el){
    var okName = el.dataset.name.indexOf(q) >= 0;
    var okTempat = !t || el.dataset.tempat === t;
    el.style.display = (okName && okTempat) ? '' : 'none';
  });
}
document.getElementById('surahFilter').addEventListener('input', applyFilter);
document.getElementById('tempatFilter').addEventListener('change', applyFilter);
</script>
<?php include __DIR__.'/includes/footer.php'; ?>
