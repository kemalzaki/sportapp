<?php
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
send_security_headers(); enforce_session_timeout();
$id = (int)($_GET['id'] ?? 0);
$r = db_one("SELECT t.*, jo.nama AS jenis_nama, u.nama AS pic_nama, u.foto_url AS pic_foto, u.nomor_wa AS pic_wa
             FROM tempat t LEFT JOIN jenis_olahraga jo ON jo.id=t.jenis_id
             LEFT JOIN users u ON u.id=t.pic_user_id WHERE t.id=$1", [$id]);
if (!$r) { http_response_code(404); die('Tempat tidak ditemukan.'); }
$pageTitle = 'Tempat: '.$r['nama'];
$u = current_user();
$isAdmin = $u && $u['role']==='admin';
$picWa = preg_replace('/^0/','62', preg_replace('/\D+/','', $r['kontak_wa'] ?: ($r['pic_wa'] ?? '')));
include __DIR__.'/includes/header.php';
?>
<nav aria-label="breadcrumb"><ol class="breadcrumb small"><li class="breadcrumb-item"><a href="/tempat_list.php">Daftar Tempat</a></li><li class="breadcrumb-item active"><?= htmlspecialchars($r['nama']) ?></li></ol></nav>
<div class="card shadow-sm mb-3"><div class="card-body">
  <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
    <div>
      <h3 class="mb-1"><?= htmlspecialchars($r['nama']) ?></h3>
      <div class="small text-muted"><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($r['alamat'] ?? '—') ?></div>
      <?php if($r['jenis_nama']): ?><div class="mt-1"><span class="pill"><?= htmlspecialchars($r['jenis_nama']) ?></span></div><?php endif; ?>
    </div>
    <div class="d-flex gap-2">
      <?php if($picWa): ?><a href="https://wa.me/<?= htmlspecialchars($picWa) ?>" target="_blank" class="btn btn-outline-success"><i class="bi bi-whatsapp"></i> Hubungi</a><?php endif; ?>
    </div>
  </div>
  <hr>
  <div class="row g-3">
    <div class="col-12">
      <table class="table table-sm mb-0">
        <tr><th>Status</th><td><span class="badge bg-info-subtle text-info"><?= htmlspecialchars($r['status_booking']) ?></span></td></tr>
        <tr><th>Harga Lapang</th><td>Rp <?= number_format((float)$r['harga_lapang'],0,',','.') ?></td></tr>
        <tr><th>Harga / Jam</th><td>Rp <?= number_format((float)$r['harga_per_jam'],0,',','.') ?></td></tr>
        <tr><th>Harga Tiket</th><td>Rp <?= number_format((float)($r['harga_tiket']??0),0,',','.') ?></td></tr>
        <tr><th>Harga Parkir</th><td>Rp <?= number_format((float)($r['harga_parkir']??0),0,',','.') ?></td></tr>
        <?php if($r['pic_nama']): ?><tr><th>PIC</th><td><?= user_name_with_avatar($r['pic_foto']??null,$r['pic_nama'],false,24) ?></td></tr><?php endif; ?>
        <?php if($isAdmin && $r['kontak_wa']): ?><tr><th>Kontak</th><td><?= htmlspecialchars($r['kontak_wa']) ?></td></tr><?php endif; ?>
      </table>
      <?php if($r['catatan']): ?><div class="mt-3"><strong>Catatan:</strong><p class="small text-muted mb-0" style="white-space:pre-wrap"><?= htmlspecialchars($r['catatan']) ?></p></div><?php endif; ?>
    </div>
  </div>
</div></div>
<?php include __DIR__.'/includes/footer.php'; ?>
