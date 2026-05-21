<?php
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
$pageTitle = 'Riwayat Olahraga';

$bulan = $_GET['bulan'] ?? '';
$jenis = $_GET['jenis'] ?? '';
$koord = $_GET['koord'] ?? '';

$totalMember = (int) db_val("SELECT COUNT(*) FROM users WHERE role IN ('member','admin')");

$sql = "SELECT j.*, u.nama AS koordinator,
        (SELECT COUNT(*) FROM absensi a WHERE a.jadwal_id=j.id AND a.hadir=1) AS hadir_internal,
        (SELECT COUNT(*) FROM absensi a WHERE a.jadwal_id=j.id) AS total_absen
        FROM jadwal j LEFT JOIN users u ON u.id=j.koordinator_id WHERE 1=1";
$p = []; $i = 1;
if ($bulan) { $sql .= " AND bulan = \$$i";        $p[] = $bulan; $i++; }
if ($jenis) { $sql .= " AND jenis::text = \$$i";  $p[] = $jenis; $i++; }
if ($koord) { $sql .= " AND u.nama ILIKE \$$i";   $p[] = "%$koord%"; $i++; }
$sql .= " ORDER BY tanggal DESC";
$rows = db_all($sql, $p);

// Ambil tamu eksternal per jadwal
$tamuMap = [];
if ($rows) {
    $ids = array_map(fn($r)=>(int)$r['id'], $rows);
    $tamus = db_all("SELECT me.jadwal_id, me.nama_tamu, u.nama AS dibawa FROM member_eksternal me
                     LEFT JOIN users u ON u.id=me.dibawa_oleh_id
                     WHERE me.jadwal_id = ANY($1::int[])", ['{'.implode(',', $ids).'}']);
    foreach ($tamus as $t) $tamuMap[$t['jadwal_id']][] = $t;
}

$koords = array_column(db_all("SELECT nama FROM users WHERE role='admin' ORDER BY nama"), 'nama');
$jenisList = array_column(db_all("SELECT nama FROM jenis_olahraga ORDER BY nama"), 'nama');
if (!$jenisList) $jenisList = ['Jogging','Badminton','Futsal','Senam','Renang','Lainnya'];

include __DIR__.'/includes/header.php'; ?>

<div class="d-flex flex-wrap align-items-center justify-content-between mb-3 gap-2">
  <h2 class="mb-0"><i class="bi bi-clock-history text-primary"></i> Riwayat Olahraga</h2>
  <span class="badge bg-primary rounded-pill"><?= count($rows) ?> sesi</span>
</div>

<div class="card shadow-sm mb-3"><div class="card-body">
<form class="row g-2" method="get">
  <div class="col-12 col-md-3">
    <label class="form-label small fw-semibold text-muted">Bulan</label>
    <input class="form-control" name="bulan" value="<?= htmlspecialchars($bulan) ?>" placeholder="cth: May">
  </div>
  <div class="col-6 col-md-3">
    <label class="form-label small fw-semibold text-muted">Jenis</label>
    <select class="form-select" name="jenis">
      <option value="">Semua</option>
      <?php foreach($jenisList as $j): ?><option <?= $jenis===$j?'selected':'' ?>><?= htmlspecialchars($j) ?></option><?php endforeach; ?>
    </select>
  </div>
  <div class="col-6 col-md-3">
    <label class="form-label small fw-semibold text-muted">Koordinator</label>
    <select class="form-select" name="koord">
      <option value="">Semua</option>
      <?php foreach($koords as $k): ?><option <?= $koord===$k?'selected':'' ?>><?= htmlspecialchars($k) ?></option><?php endforeach; ?>
    </select>
  </div>
  <div class="col-12 col-md-3 d-flex gap-2 align-items-end">
    <button class="btn btn-primary flex-grow-1"><i class="bi bi-funnel"></i> Filter</button>
    <a class="btn btn-outline-secondary" href="riwayat.php">Reset</a>
  </div>
</form>
</div></div>

<div class="card shadow-sm"><div class="table-responsive"><table class="table table-hover mb-0">
  <thead><tr>
    <th>#</th><th>Tanggal</th><th>Bulan / W</th><th>Jenis</th><th>Tempat</th>
    <th>Koordinator</th><th class="text-center">Hadir Internal</th><th>Tamu Eksternal</th>
  </tr></thead>
  <tbody>
  <?php foreach($rows as $i=>$r):
      $tot = (int)$r['total_absen'] ?: $totalMember;
      $tamus = $tamuMap[$r['id']] ?? [];
  ?>
    <tr>
      <td class="text-muted"><?= $i+1 ?></td>
      <td><?= htmlspecialchars($r['tanggal']) ?></td>
      <td><span class="pill"><?= htmlspecialchars($r['bulan']) ?> &middot; <?= htmlspecialchars($r['minggu_ke']) ?></span></td>
      <td><?= htmlspecialchars($r['jenis']) ?></td>
      <td><i class="bi bi-geo-alt text-muted"></i> <?= htmlspecialchars($r['tempat']) ?></td>
      <td><?= htmlspecialchars($r['koordinator'] ?? '-') ?></td>
      <td class="text-center">
        <span class="badge bg-success rounded-pill"><?= (int)$r['hadir_internal'] ?> / <?= $tot ?></span>
      </td>
      <td>
        <?php if ($tamus): ?>
          <div class="kv">
          <?php foreach ($tamus as $t): ?>
            <span class="pill" title="Dibawa oleh: <?= htmlspecialchars($t['dibawa'] ?? '-') ?>">
              <i class="bi bi-person-badge"></i> <?= htmlspecialchars($t['nama_tamu']) ?>
              <?php if (!empty($t['dibawa'])): ?><span class="text-muted">· <?= htmlspecialchars($t['dibawa']) ?></span><?php endif; ?>
            </span>
          <?php endforeach; ?>
          </div>
        <?php else: ?>
          <span class="text-muted small">—</span>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; if(!$rows): ?>
    <tr><td colspan="8" class="text-center text-muted py-4">Belum ada data sesuai filter.</td></tr>
  <?php endif; ?>
  </tbody>
</table></div></div>

<?php if ($rows): ?>
<div class="row g-3 mt-3">
  <div class="col-md-6"><div class="card shadow-sm"><div class="card-header"><i class="bi bi-chat-dots text-primary me-1"></i> Konten Obrolan</div><ul class="list-group list-group-flush">
    <?php $any=false; foreach($rows as $r): if(!trim($r['konten_obrolan']??'')) continue; $any=true; ?>
      <li class="list-group-item"><small class="text-muted"><?= $r['tanggal'] ?> &middot; <?= htmlspecialchars($r['jenis']) ?></small><br><?= nl2br(htmlspecialchars($r['konten_obrolan'])) ?></li>
    <?php endforeach; if(!$any): ?><li class="list-group-item text-muted text-center small">Belum ada konten.</li><?php endif; ?>
  </ul></div></div>
  <div class="col-md-6"><div class="card shadow-sm"><div class="card-header"><i class="bi bi-journal-text text-primary me-1"></i> Catatan Kondisi</div><ul class="list-group list-group-flush">
    <?php $any=false; foreach($rows as $r): if(!trim($r['catatan']??'')) continue; $any=true; ?>
      <li class="list-group-item"><small class="text-muted"><?= $r['tanggal'] ?></small><br><?= nl2br(htmlspecialchars($r['catatan'])) ?></li>
    <?php endforeach; if(!$any): ?><li class="list-group-item text-muted text-center small">Belum ada catatan.</li><?php endif; ?>
  </ul></div></div>
</div>
<?php endif; ?>

<?php include __DIR__.'/includes/footer.php'; ?>
