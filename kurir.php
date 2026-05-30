<?php
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
send_security_headers(); require_login();
$pageTitle = 'Kurir Jajanan';
$u = current_user();

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    $a = $_POST['_action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    if ($a==='take' && $id) {
        db_exec("UPDATE jajanan_pesanan SET kurir_user_id=$1, status=CASE WHEN status='baru' THEN 'diproses' ELSE status END, updated_at=now()
                 WHERE id=$2 AND (kurir_user_id IS NULL OR kurir_user_id=$1)", [(int)$u['id'],$id]);
    } elseif ($a==='deliver' && $id) {
        db_exec("UPDATE jajanan_pesanan SET status='diantar', updated_at=now() WHERE id=$1 AND kurir_user_id=$2",[$id,(int)$u['id']]);
    } elseif ($a==='done' && $id) {
        db_exec("UPDATE jajanan_pesanan SET status='selesai', updated_at=now() WHERE id=$1 AND kurir_user_id=$2",[$id,(int)$u['id']]);
    }
    header('Location: kurir.php'); exit;
}

$avail = db_all("SELECT * FROM jajanan_pesanan WHERE kurir_user_id IS NULL AND status IN ('baru') ORDER BY created_at ASC");
$mine  = db_all("SELECT * FROM jajanan_pesanan WHERE kurir_user_id=$1 AND status IN ('diproses','diantar') ORDER BY created_at ASC",[(int)$u['id']]);
$hist  = db_all("SELECT * FROM jajanan_pesanan WHERE kurir_user_id=$1 AND status IN ('selesai','batal') ORDER BY updated_at DESC LIMIT 20",[(int)$u['id']]);

/**
 * Revisi #5: link Google Maps dari pickup_lat / pickup_lng pesanan.
 */
function jjn_maps_link($r){
    if (empty($r['pickup_lat']) || empty($r['pickup_lng'])) return '';
    $lat = (float)$r['pickup_lat']; $lng = (float)$r['pickup_lng'];
    return 'https://www.google.com/maps?q='.$lat.','.$lng;
}

include __DIR__.'/includes/header.php';
?>
<h4 class="mb-3"><i class="bi bi-scooter text-warning"></i> Kurir Jajanan</h4>
<p class="text-muted small">Sebagai member terdaftar, kamu bisa mengambil order pengantaran jajanan dari masyarakat umum. Lokasi pemesan tampil sebagai link Google Maps (klik untuk navigasi).</p>

<h6 class="mt-3"><i class="bi bi-inbox"></i> Order Tersedia (<?= count($avail) ?>)</h6>
<div class="row g-2">
<?php foreach($avail as $r):
  $items = db_all("SELECT * FROM jajanan_pesanan_item WHERE pesanan_id=$1",[(int)$r['id']]);
  $maps = jjn_maps_link($r); ?>
  <div class="col-md-6">
    <div class="card border-warning"><div class="card-body">
      <div class="d-flex justify-content-between"><strong><?= htmlspecialchars($r['kode']) ?></strong>
        <span class="badge bg-success">Rp <?= number_format((int)$r['total'],0,',','.') ?></span></div>
      <div class="small mt-1"><i class="bi bi-person"></i> <?= htmlspecialchars($r['nama_pemesan']) ?> · <a href="https://wa.me/<?= preg_replace('/\D/','',$r['no_wa']) ?>" target="_blank"><i class="bi bi-whatsapp text-success"></i> <?= htmlspecialchars($r['no_wa']) ?></a></div>
      <div class="small text-muted mt-1"><i class="bi bi-geo-alt"></i> <?= nl2br(htmlspecialchars($r['alamat'])) ?></div>
      <?php if ($maps): ?>
        <div class="small mt-1">
          <i class="bi bi-map text-danger"></i>
          <a href="<?= htmlspecialchars($maps) ?>" target="_blank" rel="noopener" class="fw-semibold">
            Buka Lokasi di Google Maps
          </a>
          <span class="text-muted">(<?= htmlspecialchars($r['pickup_lat']) ?>, <?= htmlspecialchars($r['pickup_lng']) ?>)</span>
        </div>
      <?php else: ?>
        <div class="small text-muted mt-1"><i class="bi bi-geo"></i> Lat/Lng tidak tersedia</div>
      <?php endif; ?>
      <ul class="small mt-2 mb-2"><?php foreach($items as $it): ?><li><?= (int)$it['qty'] ?>× <?= htmlspecialchars($it['nama']) ?></li><?php endforeach; ?></ul>
      <form method="post"><input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="_action" value="take"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
        <button class="btn btn-sm btn-warning w-100"><i class="bi bi-hand-thumbs-up"></i> Saya Ambil Order Ini</button>
      </form>
    </div></div>
  </div>
<?php endforeach; if(!$avail): ?><div class="col-12"><p class="text-muted small">Tidak ada order tersedia saat ini.</p></div><?php endif; ?>
</div>

<h6 class="mt-4"><i class="bi bi-truck"></i> Order Saya Aktif (<?= count($mine) ?>)</h6>
<div class="row g-2">
<?php foreach($mine as $r):
  $items = db_all("SELECT * FROM jajanan_pesanan_item WHERE pesanan_id=$1",[(int)$r['id']]);
  $maps = jjn_maps_link($r); ?>
  <div class="col-md-6">
    <div class="card border-info"><div class="card-body">
      <div class="d-flex justify-content-between"><strong><?= htmlspecialchars($r['kode']) ?></strong>
        <span class="badge bg-info"><?= htmlspecialchars($r['status']) ?></span></div>
      <div class="small mt-1"><?= htmlspecialchars($r['nama_pemesan']) ?> · <a href="https://wa.me/<?= preg_replace('/\D/','',$r['no_wa']) ?>" target="_blank"><i class="bi bi-whatsapp text-success"></i> <?= htmlspecialchars($r['no_wa']) ?></a></div>
      <div class="small text-muted mt-1"><i class="bi bi-geo-alt"></i> <?= nl2br(htmlspecialchars($r['alamat'])) ?></div>
      <?php if ($maps): ?>
        <div class="small mt-1">
          <i class="bi bi-map text-danger"></i>
          <a href="<?= htmlspecialchars($maps) ?>" target="_blank" rel="noopener" class="fw-semibold">
            Navigasi di Google Maps
          </a>
        </div>
      <?php endif; ?>
      <ul class="small mt-2 mb-2"><?php foreach($items as $it): ?><li><?= (int)$it['qty'] ?>× <?= htmlspecialchars($it['nama']) ?></li><?php endforeach; ?></ul>
      <div class="d-flex gap-1">
        <?php if($r['status']==='diproses'): ?>
        <form method="post" class="flex-grow-1"><input type="hidden" name="csrf" value="<?= csrf_token() ?>">
          <input type="hidden" name="_action" value="deliver"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
          <button class="btn btn-sm btn-info w-100"><i class="bi bi-bicycle"></i> Sedang Diantar</button>
        </form>
        <?php endif; ?>
        <form method="post" class="flex-grow-1"><input type="hidden" name="csrf" value="<?= csrf_token() ?>">
          <input type="hidden" name="_action" value="done"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
          <button class="btn btn-sm btn-success w-100"><i class="bi bi-check2-all"></i> Selesai (COD diterima)</button>
        </form>
      </div>
    </div></div>
  </div>
<?php endforeach; if(!$mine): ?><div class="col-12"><p class="text-muted small">Belum ada order aktif yang kamu ambil.</p></div><?php endif; ?>
</div>

<h6 class="mt-4"><i class="bi bi-clock-history"></i> Riwayat Antar Saya (20 terakhir)</h6>
<ul class="list-group">
<?php foreach($hist as $r): ?>
  <li class="list-group-item d-flex justify-content-between small">
    <span><?= htmlspecialchars($r['kode']) ?> · <?= htmlspecialchars($r['nama_pemesan']) ?></span>
    <span class="badge bg-secondary"><?= htmlspecialchars($r['status']) ?></span>
  </li>
<?php endforeach; if(!$hist): ?><li class="list-group-item small text-muted">Belum ada riwayat.</li><?php endif; ?>
</ul>

<?php include __DIR__.'/includes/footer.php'; ?>
