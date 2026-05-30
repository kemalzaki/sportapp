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

/** Link Google Maps dari pasangan lat/lng */
function jjn_maps_link_ll($lat, $lng){
    if ($lat===null || $lng===null || $lat==='' || $lng==='') return '';
    return 'https://www.google.com/maps?q='.((float)$lat).','.((float)$lng);
}
/** Link maps untuk pemesan */
function jjn_maps_pemesan($r){
    return jjn_maps_link_ll($r['pickup_lat'] ?? null, $r['pickup_lng'] ?? null);
}
/**
 * Revisi 1 Jun 2026 (Lanjutan) #6: ambil lokasi pedagang dari item pesanan.
 * Pakai lat/lng pertama yang tersedia di tabel jajanan (kolom lat/lng yang ditambahkan
 * pada migrasi 1 Jun 2026).
 * Return ['lat'=>..,'lng'=>..,'nama'=>..] atau null.
 */
function jjn_pedagang_loc($pesanan_id){
    $row = db_one(
        "SELECT j.lat, j.lng, j.nama
         FROM jajanan_pesanan_item i
         JOIN jajanan j ON j.id = i.jajanan_id
         WHERE i.pesanan_id = $1
           AND j.lat IS NOT NULL AND j.lng IS NOT NULL
         ORDER BY i.id ASC LIMIT 1",
        [(int)$pesanan_id]
    );
    if (!$row) return null;
    return ['lat'=>(float)$row['lat'],'lng'=>(float)$row['lng'],'nama'=>$row['nama']];
}

/** Render dua badge Maps (Pemesan & Pedagang) yang konsisten dipakai di semua card */
function jjn_render_maps_block($r){
    $mp = jjn_maps_pemesan($r);
    $ped = jjn_pedagang_loc((int)$r['id']);
    $mpe = $ped ? jjn_maps_link_ll($ped['lat'],$ped['lng']) : '';
    ob_start(); ?>
    <div class="d-flex flex-wrap gap-2 mt-2">
      <?php if ($mp): ?>
        <a href="<?= htmlspecialchars($mp) ?>" target="_blank" rel="noopener"
           class="btn btn-sm btn-outline-danger">
          <i class="bi bi-geo-alt-fill"></i> Maps Pemesan
        </a>
      <?php else: ?>
        <span class="badge text-bg-light"><i class="bi bi-geo"></i> Maps Pemesan: -</span>
      <?php endif; ?>

      <?php if ($mpe): ?>
        <a href="<?= htmlspecialchars($mpe) ?>" target="_blank" rel="noopener"
           class="btn btn-sm btn-outline-warning" title="<?= htmlspecialchars($ped['nama']) ?>">
          <i class="bi bi-shop"></i> Maps Pedagang
        </a>
      <?php else: ?>
        <span class="badge text-bg-light" title="Tambahkan lat/lng pada produk di CRUD Jajanan">
          <i class="bi bi-shop"></i> Maps Pedagang: -
        </span>
      <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

include __DIR__.'/includes/header.php';
?>
<h4 class="mb-3"><i class="bi bi-scooter text-warning"></i> Kurir Jajanan</h4>
<p class="text-muted small">
  Sebagai member terdaftar, kamu bisa mengambil order pengantaran jajanan. Tiap pesanan menampilkan
  dua link Google Maps: <strong>Maps Pemesan</strong> (lokasi tujuan antar) dan
  <strong>Maps Pedagang</strong> (lokasi pengambilan jajanan dari pedagang).
</p>

<h6 class="mt-3"><i class="bi bi-inbox"></i> Order Tersedia (<?= count($avail) ?>)</h6>
<div class="row g-2">
<?php foreach($avail as $r):
  $items = db_all("SELECT * FROM jajanan_pesanan_item WHERE pesanan_id=$1",[(int)$r['id']]); ?>
  <div class="col-md-6">
    <div class="card border-warning"><div class="card-body">
      <div class="d-flex justify-content-between"><strong><?= htmlspecialchars($r['kode']) ?></strong>
        <span class="badge bg-success">Rp <?= number_format((int)$r['total'],0,',','.') ?></span></div>
      <div class="small mt-1"><i class="bi bi-person"></i> <?= htmlspecialchars($r['nama_pemesan']) ?> · <a href="https://wa.me/<?= preg_replace('/\D/','',$r['no_wa']) ?>" target="_blank"><i class="bi bi-whatsapp text-success"></i> <?= htmlspecialchars($r['no_wa']) ?></a></div>
      <div class="small text-muted mt-1"><i class="bi bi-geo-alt"></i> <?= nl2br(htmlspecialchars($r['alamat'])) ?></div>
      <?= jjn_render_maps_block($r) ?>
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
  $items = db_all("SELECT * FROM jajanan_pesanan_item WHERE pesanan_id=$1",[(int)$r['id']]); ?>
  <div class="col-md-6">
    <div class="card border-info"><div class="card-body">
      <div class="d-flex justify-content-between"><strong><?= htmlspecialchars($r['kode']) ?></strong>
        <span class="badge bg-info"><?= htmlspecialchars($r['status']) ?></span></div>
      <div class="small mt-1"><?= htmlspecialchars($r['nama_pemesan']) ?> · <a href="https://wa.me/<?= preg_replace('/\D/','',$r['no_wa']) ?>" target="_blank"><i class="bi bi-whatsapp text-success"></i> <?= htmlspecialchars($r['no_wa']) ?></a></div>
      <div class="small text-muted mt-1"><i class="bi bi-geo-alt"></i> <?= nl2br(htmlspecialchars($r['alamat'])) ?></div>
      <?= jjn_render_maps_block($r) ?>
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
