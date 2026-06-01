<?php
require __DIR__.'/../config/db.php';
require __DIR__.'/../includes/auth.php';
require __DIR__.'/../includes/helpers.php';
require_role('admin');
$pageTitle = 'Pesanan Jajanan';

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    $a = $_POST['_action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    if ($a==='set_status' && $id) {
        $s = in_array($_POST['status'] ?? '', ['baru','diproses','diantar','selesai','batal'], true) ? $_POST['status'] : 'baru';
        db_exec("UPDATE jajanan_pesanan SET status=$1, updated_at=now() WHERE id=$2",[$s,$id]);
    } elseif ($a==='set_kurir' && $id) {
        $kid = (int)($_POST['kurir_user_id'] ?? 0) ?: null;
        db_exec("UPDATE jajanan_pesanan SET kurir_user_id=$1, status=CASE WHEN status='baru' THEN 'diproses' ELSE status END, updated_at=now() WHERE id=$2",[$kid,$id]);
    } elseif ($a==='delete' && $id) {
        db_exec("DELETE FROM jajanan_pesanan WHERE id=$1",[$id]);
    }
    header('Location: jajanan_pesanan.php'); exit;
}
$rows = db_all("SELECT p.*, u.nama AS kurir_nama FROM jajanan_pesanan p LEFT JOIN users u ON u.id=p.kurir_user_id ORDER BY p.created_at DESC LIMIT 200");
$members = db_all("SELECT id, nama FROM users WHERE role IN ('member','admin') ORDER BY nama");
include __DIR__.'/../includes/header.php';
?>
<h2 class="mb-3"><i class="bi bi-bag-heart text-warning"></i> Pesanan Jajanan</h2>
<p class="text-muted small">Tetapkan kurir dari member terdaftar dan ubah status pesanan. Kolom <strong>Toko &amp; Produk</strong> menampilkan toko/pedagang asal tiap item pesanan.</p>

<div class="card"><div class="table-responsive"><table class="table table-sm align-middle mb-0">
  <thead><tr><th>Kode</th><th>Pemesan</th><th>Alamat</th><th>Toko &amp; Produk</th><th class="text-end">Total</th><th>Status</th><th>Kurir (member)</th><th class="text-end">Aksi</th></tr></thead>
  <tbody>
  <?php foreach($rows as $r):
    $items = db_all("SELECT i.*, j.toko_id, t.nama AS toko_nama
                     FROM jajanan_pesanan_item i
                     LEFT JOIN jajanan j ON j.id = i.jajanan_id
                     LEFT JOIN toko t    ON t.id = j.toko_id
                     WHERE i.pesanan_id=$1
                     ORDER BY t.nama NULLS LAST, i.id",[(int)$r['id']]);
    // group items by toko
    $grouped = [];
    foreach($items as $it){
      $key = $it['toko_nama'] ?: '— Tanpa Toko —';
      $grouped[$key][] = $it;
    }
  ?>
    <tr>
      <td class="small"><strong><?= htmlspecialchars($r['kode']) ?></strong><br><span class="text-muted"><?= date('d M H:i', strtotime($r['created_at'])) ?></span></td>
      <td class="small"><?= htmlspecialchars($r['nama_pemesan']) ?><br><a href="https://wa.me/<?= htmlspecialchars(preg_replace('/\D/','',$r['no_wa'])) ?>" target="_blank" class="text-success"><i class="bi bi-whatsapp"></i> <?= htmlspecialchars($r['no_wa']) ?></a></td>
      <td class="small" style="max-width:220px"><?= nl2br(htmlspecialchars($r['alamat'])) ?>
        <?php if(!empty($r['catatan'])): ?><div class="text-muted"><em><?= htmlspecialchars($r['catatan']) ?></em></div><?php endif; ?>
      </td>
      <td class="small" style="min-width:220px">
        <?php foreach($grouped as $tokoNama => $its): ?>
          <div class="mb-1">
            <span class="badge bg-warning-subtle text-warning-emphasis"><i class="bi bi-shop"></i> <?= htmlspecialchars($tokoNama) ?></span>
            <?php foreach($its as $it): ?>
              <div class="ms-2"><i class="bi bi-dot"></i><?= (int)$it['qty'] ?>× <?= htmlspecialchars($it['nama']) ?> <span class="text-muted">@<?= number_format((int)$it['harga'],0,',','.') ?></span></div>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
      </td>
      <td class="text-end">Rp <?= number_format((int)$r['total'],0,',','.') ?>
        <div class="small text-muted">ongkir <?= number_format((int)$r['ongkir'],0,',','.') ?></div>
      </td>
      <td>
        <form method="post" class="d-flex gap-1">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
          <input type="hidden" name="_action" value="set_status">
          <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
          <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
            <?php foreach(['baru','diproses','diantar','selesai','batal'] as $s): ?>
              <option value="<?= $s ?>" <?= $r['status']===$s?'selected':'' ?>><?= $s ?></option>
            <?php endforeach; ?>
          </select>
        </form>
      </td>
      <td>
        <form method="post" class="d-flex gap-1">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
          <input type="hidden" name="_action" value="set_kurir">
          <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
          <select name="kurir_user_id" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="0">— belum ditentukan —</option>
            <?php foreach($members as $m): ?>
              <option value="<?= (int)$m['id'] ?>" <?= (int)$r['kurir_user_id']===(int)$m['id']?'selected':'' ?>><?= htmlspecialchars($m['nama']) ?></option>
            <?php endforeach; ?>
          </select>
        </form>
      </td>
      <td class="text-end">
        <form method="post" onsubmit="return confirm('Hapus pesanan ini?')">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
          <input type="hidden" name="_action" value="delete">
          <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
          <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
        </form>
      </td>
    </tr>
  <?php endforeach; if(!$rows): ?><tr><td colspan="8" class="text-center text-muted small">Belum ada pesanan.</td></tr><?php endif; ?>
  </tbody>
</table></div></div>

<?php include __DIR__.'/../includes/footer.php'; ?>
