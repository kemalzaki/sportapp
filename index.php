<?php
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/helpers.php';
$pageTitle = 'Beranda';
$u = current_user();

// ---- Handle forum chat (post / delete) ----
if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    $a = $_POST['_action'] ?? '';
    if ($a === 'chat_post' && $u) {
        $pesan = trim($_POST['pesan'] ?? '');
        if ($pesan !== '') db_exec("INSERT INTO chat_forum(user_id,pesan) VALUES($1,$2)", [(int)$u['id'], $pesan]);
    } elseif ($a === 'chat_delete' && $u && $u['role']==='admin') {
        db_exec("DELETE FROM chat_forum WHERE id=$1", [(int)$_POST['id']]);
    } elseif ($a === 'chat_edit' && $u && $u['role']==='admin') {
        $pesan = trim($_POST['pesan'] ?? '');
        if ($pesan !== '') db_exec("UPDATE chat_forum SET pesan=$1 WHERE id=$2", [$pesan, (int)$_POST['id']]);
    }
    header('Location: /index.php#forum'); exit;
}

$totalSesi    = (int) db_val("SELECT COUNT(*) FROM jadwal");
$totalHadir   = (int) db_val("SELECT COUNT(*) FROM absensi WHERE hadir=1");
$totalMember  = (int) db_val("SELECT COUNT(*) FROM users WHERE role IN ('member','admin')");
$totalEksternal = (int) db_val("SELECT COUNT(*) FROM member_eksternal");

$jadwalTerdekat = db_all("SELECT j.*, u.nama AS koordinator, u.foto_url AS koord_foto FROM jadwal j LEFT JOIN users u ON u.id=j.koordinator_id WHERE tanggal >= CURRENT_DATE ORDER BY tanggal ASC LIMIT 5");
$jadwalLalu     = db_all("SELECT j.*, u.nama AS koordinator FROM jadwal j LEFT JOIN users u ON u.id=j.koordinator_id WHERE tanggal < CURRENT_DATE ORDER BY tanggal DESC LIMIT 5");
$topTempat      = db_all("SELECT tempat, COUNT(*) c FROM jadwal GROUP BY tempat ORDER BY c DESC LIMIT 5");

$onlineMembers = db_all("SELECT id, nama, foto_url, last_seen FROM users WHERE last_seen IS NOT NULL AND last_seen >= NOW() - INTERVAL '2 minutes' ORDER BY nama");
$berita = db_all("SELECT * FROM berita ORDER BY created_at DESC LIMIT 8");
$chats = db_all("SELECT c.*, u.nama, u.foto_url FROM chat_forum c LEFT JOIN users u ON u.id=c.user_id ORDER BY c.created_at DESC LIMIT 30");

include __DIR__.'/includes/header.php'; ?>

<section class="hero mb-4">
  <span class="badge-soft mb-3"><i class="bi bi-stars me-1"></i> Komunitas Olahraga HapFam</span>
  <h1 class="display-6">Dashboard Olahraga Komunitas</h1>
  <p class="lead mb-0">Pantau kegiatan, jadwal, lokasi, dan partisipasi member secara transparan.</p>
</section>

<?php if($berita): ?>
<div id="newsSlider" class="carousel slide news-slider mb-4" data-bs-ride="carousel">
  <div class="carousel-indicators">
    <?php foreach($berita as $i=>$b): ?>
      <button data-bs-target="#newsSlider" data-bs-slide-to="<?= $i ?>" <?= $i===0?'class="active"':'' ?>></button>
    <?php endforeach; ?>
  </div>
  <div class="carousel-inner rounded-3 shadow-sm">
    <?php foreach($berita as $i=>$b): ?>
      <div class="carousel-item <?= $i===0?'active':'' ?>">
        <?php if($b['gambar_url']): ?>
          <img src="<?= htmlspecialchars($b['gambar_url']) ?>" alt="<?= htmlspecialchars($b['judul']) ?>">
        <?php else: ?>
          <div style="height:300px;background:linear-gradient(135deg,#0ea5e9,#6366f1);"></div>
        <?php endif; ?>
        <div class="news-caption">
          <h5><?= htmlspecialchars($b['judul']) ?></h5>
          <div class="small"><?= strip_tags($b['isi']) ?></div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <button class="carousel-control-prev" data-bs-target="#newsSlider" data-bs-slide="prev"><span class="carousel-control-prev-icon"></span></button>
  <button class="carousel-control-next" data-bs-target="#newsSlider" data-bs-slide="next"><span class="carousel-control-next-icon"></span></button>
</div>
<?php endif; ?>

<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3"><div class="card card-stat shadow-sm h-100"><div class="card-body">
    <div class="stat-icon"><i class="bi bi-calendar-event"></i></div>
    <div class="stat-label">Total Sesi</div><div class="stat-value"><?= $totalSesi ?></div></div></div></div>
  <div class="col-6 col-lg-3"><div class="card card-stat shadow-sm h-100"><div class="card-body">
    <div class="stat-icon"><i class="bi bi-check2-circle"></i></div>
    <div class="stat-label">Total Kehadiran</div><div class="stat-value"><?= $totalHadir ?></div></div></div></div>
  <div class="col-6 col-lg-3"><div class="card card-stat shadow-sm h-100"><div class="card-body">
    <div class="stat-icon"><i class="bi bi-people-fill"></i></div>
    <div class="stat-label">Member Internal</div><div class="stat-value"><?= $totalMember ?></div></div></div></div>
  <div class="col-6 col-lg-3"><div class="card card-stat shadow-sm h-100"><div class="card-body">
    <div class="stat-icon"><i class="bi bi-broadcast"></i></div>
    <div class="stat-label">Member Online</div><div class="stat-value"><?= count($onlineMembers) ?></div></div></div></div>
</div>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="card shadow-sm"><div class="card-header d-flex justify-content-between align-items-center">
      <span><i class="bi bi-calendar3 me-1 text-primary"></i> Jadwal Terdekat</span>
      <a href="/riwayat.php" class="small text-decoration-none">Lihat semua <i class="bi bi-arrow-right"></i></a>
    </div>
      <div class="table-responsive"><table class="table table-hover mb-0">
        <thead><tr><th>Tanggal</th><th>Hari</th><th>Jenis</th><th>Tempat</th><th>Koordinator</th></tr></thead>
        <tbody>
        <?php foreach($jadwalTerdekat as $j): ?>
          <tr>
            <td><?= htmlspecialchars($j['tanggal']) ?></td>
            <td><span class="pill"><?= hari_id($j['tanggal']) ?></span></td>
            <td><span class="pill"><?= htmlspecialchars($j['jenis']) ?></span></td>
            <td><i class="bi bi-geo-alt text-muted"></i> <?= htmlspecialchars($j['tempat']) ?></td>
            <td><?= user_name_with_avatar($j['koord_foto'] ?? null, $j['koordinator'] ?? '-', false, 24) ?></td>
          </tr>
        <?php endforeach; if(!$jadwalTerdekat): ?>
          <tr><td colspan="5" class="text-center text-muted py-3">Belum ada jadwal mendatang.</td></tr>
        <?php endif; ?>
        </tbody></table></div>
    </div>

    <div class="card shadow-sm mt-3" id="forum"><div class="card-header d-flex justify-content-between align-items-center">
      <span><i class="bi bi-chat-square-text text-primary me-1"></i> Forum Komunitas</span>
      <span class="badge bg-primary rounded-pill"><?= count($chats) ?></span>
    </div>
    <div class="card-body">
      <?php if($u): ?>
      <form method="post" class="d-flex gap-2 mb-3">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="_action" value="chat_post">
        <input class="form-control" name="pesan" placeholder="Tulis pesan untuk komunitas..." required>
        <button class="btn btn-primary"><i class="bi bi-send"></i></button>
      </form>
      <?php else: ?>
        <div class="alert alert-info py-2 small mb-3"><a href="/login.php">Login</a> untuk ikut diskusi.</div>
      <?php endif; ?>
      <div style="max-height:380px;overflow-y:auto;">
      <?php foreach($chats as $c): ?>
        <div class="chat-bubble">
          <div class="d-flex justify-content-between align-items-start gap-2">
            <div class="flex-grow-1">
              <div class="d-flex align-items-center gap-2">
                <?= user_avatar($c['foto_url'] ?? null, $c['nama'] ?? '?', 24) ?>
                <strong><?= htmlspecialchars($c['nama'] ?? 'Anon') ?></strong>
                <span class="chat-meta"><?= $c['created_at'] ?></span>
              </div>
              <div class="mt-1"><?= nl2br(htmlspecialchars($c['pesan'])) ?></div>
            </div>
            <?php if($u && $u['role']==='admin'): ?>
            <div class="d-flex gap-1">
              <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#cedit<?= $c['id'] ?>"><i class="bi bi-pencil"></i></button>
              <form method="post" onsubmit="return confirm('Hapus pesan?')">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="_action" value="chat_delete"><input type="hidden" name="id" value="<?= $c['id'] ?>">
                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
              </form>
            </div>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; if(!$chats): ?><p class="text-muted text-center mb-0">Belum ada pesan. Sapa komunitas dulu!</p><?php endif; ?>
      </div>
    </div></div>
  </div>

  <div class="col-lg-5">
    <div class="card shadow-sm mb-3"><div class="card-header"><i class="bi bi-broadcast text-success me-1"></i> Member Sedang Online (<?= count($onlineMembers) ?>)</div>
      <ul class="list-group list-group-flush">
        <?php foreach($onlineMembers as $om): ?>
          <li class="list-group-item d-flex align-items-center justify-content-between">
            <?= user_name_with_avatar($om['foto_url'] ?? null, $om['nama'], true, 28) ?>
            <small class="text-muted"><?= date('H:i', strtotime($om['last_seen'])) ?></small>
          </li>
        <?php endforeach; if(!$onlineMembers): ?><li class="list-group-item text-muted text-center small">Tidak ada yang online.</li><?php endif; ?>
      </ul>
    </div>

    <div class="card shadow-sm"><div class="card-header"><i class="bi bi-geo-alt-fill me-1 text-primary"></i> Lokasi Tersering</div>
      <ul class="list-group list-group-flush">
      <?php foreach($topTempat as $t): ?>
        <li class="list-group-item d-flex justify-content-between align-items-center">
          <span><i class="bi bi-pin-map text-muted me-1"></i> <?= htmlspecialchars($t['tempat']) ?></span>
          <span class="badge bg-primary rounded-pill"><?= $t['c'] ?>x</span>
        </li>
      <?php endforeach; if(!$topTempat): ?><li class="list-group-item text-muted text-center">Belum ada data.</li><?php endif; ?>
      </ul>
    </div>
  </div>
</div>

<?php if($u && $u['role']==='admin'): foreach($chats as $c): ?>
<div class="modal fade" id="cedit<?= $c['id'] ?>" tabindex="-1"><div class="modal-dialog"><form method="post" class="modal-content">
  <input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="_action" value="chat_edit"><input type="hidden" name="id" value="<?= $c['id'] ?>">
  <div class="modal-header"><h5 class="modal-title">Edit Pesan</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body"><textarea name="pesan" class="form-control" rows="3" required><?= htmlspecialchars($c['pesan']) ?></textarea></div>
  <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button><button class="btn btn-primary"><i class="bi bi-save"></i> Simpan</button></div>
</form></div></div>
<?php endforeach; endif; ?>

<?php include __DIR__.'/includes/footer.php'; ?>
