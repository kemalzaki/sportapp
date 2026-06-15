<?php
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
send_security_headers(); enforce_session_timeout();
$pageTitle = 'Pencarian Global';
$q = trim($_GET['q'] ?? '');
$like = '%'.str_replace(['%','_'],['\\%','\\_'],$q).'%';

$members = $jadwal = $tempat = $aktivitas = [];
if ($q !== '') {
    $members = db_all("SELECT id,nama,email,foto_url,role FROM users WHERE nama ILIKE $1 OR email ILIKE $1 ORDER BY nama LIMIT 20",[$like]);
    $jadwal  = db_all("SELECT id,tanggal,jenis,tempat FROM jadwal WHERE jenis ILIKE $1 OR tempat ILIKE $1 ORDER BY tanggal DESC LIMIT 20",[$like]);
    $tempat  = db_all("SELECT id,nama,alamat FROM tempat WHERE nama ILIKE $1 OR alamat ILIKE $1 ORDER BY nama LIMIT 20",[$like]);
    $aktivitas = db_all("SELECT uh.id,uh.tanggal,uh.jenis,uh.deskripsi,u.nama AS user FROM upload_harian uh JOIN users u ON u.id=uh.user_id
                         WHERE uh.jenis ILIKE $1 OR uh.deskripsi ILIKE $1 OR u.nama ILIKE $1 ORDER BY uh.tanggal DESC LIMIT 20",[$like]);
}
require_once __DIR__.'/includes/htmx.php'; htmx_layout_start($pageTitle ?? 'Search'); ?>
<h2 class="mb-3"><i class="bi bi-search text-primary"></i> Pencarian Global</h2>
<form class="mb-3"><div class="input-group">
  <input name="q" class="form-control" placeholder="Cari member, jadwal, aktivitas, tempat..." value="<?= htmlspecialchars($q) ?>" autofocus>
  <button class="btn btn-primary">Cari</button>
</div></form>

<?php if($q===''): ?><p class="text-muted">Ketik kata kunci untuk mulai mencari.</p><?php else: ?>
<div class="row g-3">
  <div class="col-md-6"><div class="card shadow-sm"><div class="card-header"><i class="bi bi-people"></i> Member (<?= count($members) ?>)</div>
    <ul class="list-group list-group-flush"><?php foreach($members as $m): ?>
      <li class="list-group-item"><a href="/user.php?id=<?= (int)$m['id'] ?>" class="text-decoration-none"><?= user_name_with_avatar($m['foto_url'],$m['nama'],false,28) ?></a> <small class="text-muted">· <?= htmlspecialchars($m['role']) ?></small></li>
    <?php endforeach; if(!$members): ?><li class="list-group-item text-muted small">Tidak ada hasil.</li><?php endif; ?></ul>
  </div></div>
  <div class="col-md-6"><div class="card shadow-sm"><div class="card-header"><i class="bi bi-calendar-event"></i> Jadwal (<?= count($jadwal) ?>)</div>
    <ul class="list-group list-group-flush"><?php foreach($jadwal as $j): ?>
      <li class="list-group-item"><a href="/calendar.php" class="text-decoration-none"><strong><?= htmlspecialchars($j['jenis']) ?></strong> · <?= htmlspecialchars($j['tanggal']) ?> · <small class="text-muted"><?= htmlspecialchars($j['tempat']) ?></small></a></li>
    <?php endforeach; if(!$jadwal): ?><li class="list-group-item text-muted small">Tidak ada hasil.</li><?php endif; ?></ul>
  </div></div>
  <div class="col-md-6"><div class="card shadow-sm"><div class="card-header"><i class="bi bi-geo-alt"></i> Tempat (<?= count($tempat) ?>)</div>
    <ul class="list-group list-group-flush"><?php foreach($tempat as $t): ?>
      <li class="list-group-item"><a href="/tempat.php?id=<?= (int)$t['id'] ?>" class="text-decoration-none"><strong><?= htmlspecialchars($t['nama']) ?></strong></a><br><small class="text-muted"><?= htmlspecialchars($t['alamat'] ?? '') ?></small></li>
    <?php endforeach; if(!$tempat): ?><li class="list-group-item text-muted small">Tidak ada hasil.</li><?php endif; ?></ul>
  </div></div>
  <div class="col-md-6"><div class="card shadow-sm"><div class="card-header"><i class="bi bi-activity"></i> Aktivitas (<?= count($aktivitas) ?>)</div>
    <ul class="list-group list-group-flush"><?php foreach($aktivitas as $a): ?>
      <li class="list-group-item"><a href="/riwayat.php" class="text-decoration-none"><strong><?= htmlspecialchars($a['jenis']) ?></strong> · <?= htmlspecialchars($a['tanggal']) ?> · <small class="text-muted">oleh <?= htmlspecialchars($a['user']) ?></small></a><br><small><?= htmlspecialchars(mb_strimwidth($a['deskripsi'] ?? '',0,120,'...')) ?></small></li>
    <?php endforeach; if(!$aktivitas): ?><li class="list-group-item text-muted small">Tidak ada hasil.</li><?php endif; ?></ul>
  </div></div>
</div>
<?php endif; ?>

<?php htmx_layout_end(); ?>
