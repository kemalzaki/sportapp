<?php
require __DIR__.'/config/db.php';
$pageTitle = 'Beranda';

$totalSesi    = (int) db_val("SELECT COUNT(*) FROM jadwal");
$totalHadir   = (int) db_val("SELECT COUNT(*) FROM absensi WHERE hadir=1");
$totalMember  = (int) db_val("SELECT COUNT(*) FROM users WHERE role IN ('member','admin')");
$totalEksternal = (int) db_val("SELECT COUNT(*) FROM member_eksternal");

$jadwalTerdekat = db_all("SELECT j.*, u.nama AS koordinator FROM jadwal j LEFT JOIN users u ON u.id=j.koordinator_id WHERE tanggal >= CURRENT_DATE ORDER BY tanggal ASC LIMIT 5");
$jadwalLalu     = db_all("SELECT j.*, u.nama AS koordinator FROM jadwal j LEFT JOIN users u ON u.id=j.koordinator_id WHERE tanggal < CURRENT_DATE ORDER BY tanggal DESC LIMIT 5");
$topTempat      = db_all("SELECT tempat, COUNT(*) c FROM jadwal GROUP BY tempat ORDER BY c DESC LIMIT 5");

include __DIR__.'/includes/header.php'; ?>

<section class="hero mb-4">
  <span class="badge-soft mb-3"><i class="bi bi-stars me-1"></i> Komunitas Olahraga HapFam</span>
  <h1 class="display-6">Dashboard Olahraga Komunitas</h1>
  <p class="lead mb-0">Pantau kegiatan, jadwal, lokasi, dan partisipasi member secara transparan dari mana saja.</p>
</section>

<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3">
    <div class="card card-stat shadow-sm h-100"><div class="card-body">
      <div class="stat-icon"><i class="bi bi-calendar-event"></i></div>
      <div class="stat-label">Total Sesi</div>
      <div class="stat-value"><?= $totalSesi ?></div>
      <div class="stat-foot">Sesi olahraga tercatat</div>
    </div></div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card card-stat shadow-sm h-100"><div class="card-body">
      <div class="stat-icon"><i class="bi bi-check2-circle"></i></div>
      <div class="stat-label">Total Kehadiran</div>
      <div class="stat-value"><?= $totalHadir ?></div>
      <div class="stat-foot">Hadir internal kumulatif</div>
    </div></div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card card-stat shadow-sm h-100"><div class="card-body">
      <div class="stat-icon"><i class="bi bi-people-fill"></i></div>
      <div class="stat-label">Member Internal</div>
      <div class="stat-value"><?= $totalMember ?></div>
      <div class="stat-foot">Member &amp; admin aktif</div>
    </div></div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card card-stat shadow-sm h-100"><div class="card-body">
      <div class="stat-icon"><i class="bi bi-person-plus"></i></div>
      <div class="stat-label">Member Eksternal</div>
      <div class="stat-value"><?= $totalEksternal ?></div>
      <div class="stat-foot">Tamu yang pernah hadir</div>
    </div></div>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="card shadow-sm"><div class="card-header d-flex align-items-center justify-content-between">
      <span><i class="bi bi-calendar3 me-1 text-primary"></i> Jadwal Terdekat</span>
      <a href="/riwayat.php" class="small text-decoration-none">Lihat semua <i class="bi bi-arrow-right"></i></a>
    </div>
      <div class="table-responsive"><table class="table table-hover mb-0">
        <thead><tr><th>Tanggal</th><th>Jenis</th><th>Tempat</th><th>Koordinator</th></tr></thead>
        <tbody>
        <?php foreach($jadwalTerdekat as $j): ?>
          <tr>
            <td><?= htmlspecialchars($j['tanggal']) ?></td>
            <td><span class="pill"><?= htmlspecialchars($j['jenis']) ?></span></td>
            <td><i class="bi bi-geo-alt text-muted"></i> <?= htmlspecialchars($j['tempat']) ?></td>
            <td><?= htmlspecialchars($j['koordinator'] ?? '-') ?></td>
          </tr>
        <?php endforeach; if(!$jadwalTerdekat): ?>
          <tr><td colspan="4" class="text-center text-muted py-3">Belum ada jadwal mendatang.</td></tr>
        <?php endif; ?>
        </tbody>
      </table></div>
    </div>

    <?php if($jadwalLalu): ?>
    <div class="card shadow-sm mt-3"><div class="card-header"><i class="bi bi-clock-history me-1 text-primary"></i> Sesi Terakhir</div>
      <ul class="list-group list-group-flush">
        <?php foreach($jadwalLalu as $j): ?>
          <li class="list-group-item d-flex justify-content-between align-items-center">
            <div>
              <div class="fw-semibold"><?= htmlspecialchars($j['jenis']) ?> &middot; <?= htmlspecialchars($j['tempat']) ?></div>
              <small class="text-muted"><?= $j['tanggal'] ?> &middot; <?= htmlspecialchars($j['koordinator'] ?? '-') ?></small>
            </div>
            <span class="pill"><?= htmlspecialchars($j['bulan']) ?> <?= htmlspecialchars($j['minggu_ke']) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>
  </div>

  <div class="col-lg-5">
    <div class="card shadow-sm"><div class="card-header"><i class="bi bi-geo-alt-fill me-1 text-primary"></i> Lokasi Tersering</div>
      <ul class="list-group list-group-flush">
      <?php foreach($topTempat as $t): ?>
        <li class="list-group-item d-flex justify-content-between align-items-center">
          <span><i class="bi bi-pin-map text-muted me-1"></i> <?= htmlspecialchars($t['tempat']) ?></span>
          <span class="badge bg-primary rounded-pill"><?= $t['c'] ?>x</span>
        </li>
      <?php endforeach; if(!$topTempat): ?>
        <li class="list-group-item text-muted text-center">Belum ada data.</li>
      <?php endif; ?>
      </ul>
    </div>
  </div>
</div>

<?php include __DIR__.'/includes/footer.php'; ?>
