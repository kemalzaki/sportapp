<?php
/**
 * admin/toko_olahraga.php — Revisi R23 (27 Juni 2026)
 * CRUD Toko Perlengkapan Olahraga Terdekat.
 * Skema: lihat migrations_r23_27jun2026.sql (tabel `toko_olahraga`).
 */
require __DIR__.'/../config/db.php';
require __DIR__.'/../includes/auth.php';
require __DIR__.'/../includes/helpers.php';
require_role('admin');
$pageTitle = 'CRUD Toko Perlengkapan Olahraga';

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    $a = $_POST['_action'] ?? '';
    $fields = function() {
        return [
            'nama'      => trim($_POST['nama'] ?? ''),
            'alamat'    => trim($_POST['alamat'] ?? ''),
            'kota'      => trim($_POST['kota'] ?? ''),
            'kategori'  => trim($_POST['kategori'] ?? ''),
            'deskripsi' => trim($_POST['deskripsi'] ?? ''),
            'foto_url'  => trim($_POST['foto_url'] ?? ''),
            'wa_nomor'  => preg_replace('/\D+/', '', $_POST['wa_nomor'] ?? ''),
            'telp'      => trim($_POST['telp'] ?? ''),
            'jam_buka'  => trim($_POST['jam_buka'] ?? ''),
            'lat'       => ($_POST['lat'] ?? '') === '' ? null : (float)$_POST['lat'],
            'lng'       => ($_POST['lng'] ?? '') === '' ? null : (float)$_POST['lng'],
            'map_url'   => trim($_POST['map_url'] ?? ''),
            'aktif'     => !empty($_POST['aktif']) ? 't' : 'f',
            'sort_order'=> (int)($_POST['sort_order'] ?? 0),
        ];
    };
    if ($a === 'create') {
        $f = $fields();
        if ($f['nama'] !== '') {
            db_exec("INSERT INTO toko_olahraga
                (nama,alamat,kota,kategori,deskripsi,foto_url,wa_nomor,telp,jam_buka,lat,lng,map_url,aktif,sort_order)
                VALUES($1,$2,$3,$4,$5,$6,$7,$8,$9,$10,$11,$12,$13,$14)",
                [$f['nama'],$f['alamat'],$f['kota'],$f['kategori'],$f['deskripsi'],$f['foto_url'],
                 $f['wa_nomor'],$f['telp'],$f['jam_buka'],$f['lat'],$f['lng'],$f['map_url'],
                 $f['aktif'],$f['sort_order']]);
            $_SESSION['flash_ok'] = 'Toko berhasil ditambahkan.';
        }
    } elseif ($a === 'edit') {
        $id = (int)$_POST['id'];
        $f = $fields();
        db_exec("UPDATE toko_olahraga SET
                nama=$1,alamat=$2,kota=$3,kategori=$4,deskripsi=$5,foto_url=$6,
                wa_nomor=$7,telp=$8,jam_buka=$9,lat=$10,lng=$11,map_url=$12,
                aktif=$13,sort_order=$14,updated_at=now()
                WHERE id=$15",
            [$f['nama'],$f['alamat'],$f['kota'],$f['kategori'],$f['deskripsi'],$f['foto_url'],
             $f['wa_nomor'],$f['telp'],$f['jam_buka'],$f['lat'],$f['lng'],$f['map_url'],
             $f['aktif'],$f['sort_order'],$id]);
        $_SESSION['flash_ok'] = 'Toko diperbarui.';
    } elseif ($a === 'toggle') {
        db_exec("UPDATE toko_olahraga SET aktif = NOT aktif, updated_at=now() WHERE id=$1",
                [(int)$_POST['id']]);
    } elseif ($a === 'delete') {
        db_exec("DELETE FROM toko_olahraga WHERE id=$1", [(int)$_POST['id']]);
        $_SESSION['flash_ok'] = 'Toko dihapus.';
    }
    header('Location: toko_olahraga.php'); exit;
}

try {
    $rows = db_all("SELECT * FROM toko_olahraga ORDER BY aktif DESC, sort_order, LOWER(nama)");
} catch (Throwable $e) {
    $rows = [];
    $tableMissing = true;
}
$ok  = $_SESSION['flash_ok']  ?? null; unset($_SESSION['flash_ok']);
$err = $_SESSION['flash_err'] ?? null; unset($_SESSION['flash_err']);
include __DIR__.'/../includes/header.php';
?>
<h2 class="mb-3"><i class="bi bi-shop text-primary"></i> Toko Perlengkapan Olahraga Terdekat</h2>
<p class="text-muted small">CRUD daftar toko perlengkapan olahraga. Data tampil di halaman user
  <a href="/toko_olahraga.php">Toko Perlengkapan Olahraga</a> (menu Info &amp; Wawasan).</p>

<?php if (!empty($tableMissing)): ?>
  <div class="alert alert-warning small">
    <i class="bi bi-exclamation-triangle"></i>
    Tabel <code>toko_olahraga</code> belum ada. Jalankan migrasi
    <code>migrations_r23_27jun2026.sql</code> pada PostgreSQL Anda.
  </div>
<?php endif; ?>

<?php if($ok): ?><div class="alert alert-success py-2"><?= htmlspecialchars($ok) ?></div><?php endif; ?>
<?php if($err): ?><div class="alert alert-danger  py-2"><?= htmlspecialchars($err) ?></div><?php endif; ?>

<div class="card shadow-sm mb-3">
  <div class="card-header"><i class="bi bi-plus-circle text-success"></i> Tambah Toko Baru</div>
  <div class="card-body">
    <form method="post" class="row g-2">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="_action" value="create">
      <div class="col-md-6"><input class="form-control" name="nama" placeholder="Nama toko *" required></div>
      <div class="col-md-3"><input class="form-control" name="kota" placeholder="Kota"></div>
      <div class="col-md-3"><input class="form-control" name="kategori" placeholder="Kategori (Sepatu / Bola / Umum)"></div>
      <div class="col-md-8"><input class="form-control" name="alamat" placeholder="Alamat lengkap"></div>
      <div class="col-md-4"><input class="form-control" name="jam_buka" placeholder="Jam buka (cth: 09.00–21.00)"></div>
      <div class="col-12"><textarea class="form-control" name="deskripsi" rows="2" placeholder="Deskripsi singkat toko"></textarea></div>
      <div class="col-md-4"><input class="form-control" name="wa_nomor" placeholder="No. WhatsApp (cth: 6281234567890)"></div>
      <div class="col-md-4"><input class="form-control" name="telp" placeholder="No. telepon (opsional)"></div>
      <div class="col-md-4"><input class="form-control" name="foto_url" placeholder="URL foto (opsional)"></div>
      <div class="col-md-3"><input class="form-control" name="lat" placeholder="Latitude (opsional)"></div>
      <div class="col-md-3"><input class="form-control" name="lng" placeholder="Longitude (opsional)"></div>
      <div class="col-md-4"><input class="form-control" name="map_url" placeholder="URL Google Maps (opsional)"></div>
      <div class="col-md-2"><input class="form-control" type="number" name="sort_order" value="0" title="Urutan"></div>
      <div class="col-md-2 form-check ms-2 mt-2"><input class="form-check-input" type="checkbox" name="aktif" id="ac" value="1" checked><label class="form-check-label small" for="ac">Aktif</label></div>
      <div class="col-12"><button class="btn btn-success"><i class="bi bi-plus-lg"></i> Tambah Toko</button></div>
    </form>
  </div>
</div>

<div class="card shadow-sm">
  <div class="card-header d-flex justify-content-between">
    <span><i class="bi bi-list-ul"></i> Daftar Toko (<?= count($rows) ?>)</span>
  </div>
  <div class="table-responsive">
    <table class="table table-hover mb-0 align-middle">
      <thead class="table-light"><tr>
        <th>#</th><th>Nama</th><th>Kategori</th><th>Kota</th><th>WhatsApp</th><th>Status</th><th class="text-end">Aksi</th>
      </tr></thead>
      <tbody>
      <?php foreach($rows as $i=>$r):
        $isAct = ($r['aktif']==='t'||$r['aktif']===true||$r['aktif']==='1'); ?>
        <tr>
          <td class="text-muted"><?= $i+1 ?></td>
          <td>
            <div class="fw-semibold"><?= htmlspecialchars($r['nama']) ?></div>
            <?php if(!empty($r['alamat'])): ?><div class="small text-muted"><?= htmlspecialchars($r['alamat']) ?></div><?php endif; ?>
          </td>
          <td><?php if(!empty($r['kategori'])): ?><span class="badge bg-light text-secondary border"><?= htmlspecialchars($r['kategori']) ?></span><?php endif; ?></td>
          <td class="small"><?= htmlspecialchars($r['kota'] ?? '') ?></td>
          <td class="small"><?= htmlspecialchars($r['wa_nomor'] ?? '') ?></td>
          <td>
            <form method="post" class="d-inline">
              <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
              <input type="hidden" name="_action" value="toggle">
              <input type="hidden" name="id" value="<?= $r['id'] ?>">
              <button class="btn btn-sm <?= $isAct?'btn-success':'btn-outline-secondary' ?>"><?= $isAct?'Aktif':'Nonaktif' ?></button>
            </form>
          </td>
          <td class="text-end text-nowrap">
            <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#edit<?= $r['id'] ?>"><i class="bi bi-pencil"></i></button>
            <form method="post" class="d-inline" onsubmit="return confirm('Hapus toko ini?')">
              <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
              <input type="hidden" name="_action" value="delete">
              <input type="hidden" name="id" value="<?= $r['id'] ?>">
              <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
            </form>
          </td>
        </tr>
      <?php endforeach; if(!$rows): ?>
        <tr><td colspan="7" class="text-center text-muted py-3">Belum ada toko. Tambahkan via form di atas.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php foreach($rows as $r): $isAct = ($r['aktif']==='t'||$r['aktif']===true||$r['aktif']==='1'); ?>
<div class="modal fade" id="edit<?= $r['id'] ?>" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <form method="post" class="modal-content">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="_action" value="edit">
      <input type="hidden" name="id" value="<?= $r['id'] ?>">
      <div class="modal-header"><h5 class="modal-title">Edit Toko</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="row g-2">
          <div class="col-md-6"><label class="form-label small">Nama *</label><input class="form-control" name="nama" value="<?= htmlspecialchars($r['nama']) ?>" required></div>
          <div class="col-md-3"><label class="form-label small">Kota</label><input class="form-control" name="kota" value="<?= htmlspecialchars($r['kota']??'') ?>"></div>
          <div class="col-md-3"><label class="form-label small">Kategori</label><input class="form-control" name="kategori" value="<?= htmlspecialchars($r['kategori']??'') ?>"></div>
          <div class="col-md-8"><label class="form-label small">Alamat</label><input class="form-control" name="alamat" value="<?= htmlspecialchars($r['alamat']??'') ?>"></div>
          <div class="col-md-4"><label class="form-label small">Jam Buka</label><input class="form-control" name="jam_buka" value="<?= htmlspecialchars($r['jam_buka']??'') ?>"></div>
          <div class="col-12"><label class="form-label small">Deskripsi</label><textarea class="form-control" name="deskripsi" rows="2"><?= htmlspecialchars($r['deskripsi']??'') ?></textarea></div>
          <div class="col-md-4"><label class="form-label small">No. WhatsApp</label><input class="form-control" name="wa_nomor" value="<?= htmlspecialchars($r['wa_nomor']??'') ?>"></div>
          <div class="col-md-4"><label class="form-label small">No. Telepon</label><input class="form-control" name="telp" value="<?= htmlspecialchars($r['telp']??'') ?>"></div>
          <div class="col-md-4"><label class="form-label small">Foto URL</label><input class="form-control" name="foto_url" value="<?= htmlspecialchars($r['foto_url']??'') ?>"></div>
          <div class="col-md-3"><label class="form-label small">Latitude</label><input class="form-control" name="lat" value="<?= htmlspecialchars($r['lat']??'') ?>"></div>
          <div class="col-md-3"><label class="form-label small">Longitude</label><input class="form-control" name="lng" value="<?= htmlspecialchars($r['lng']??'') ?>"></div>
          <div class="col-md-4"><label class="form-label small">URL Google Maps</label><input class="form-control" name="map_url" value="<?= htmlspecialchars($r['map_url']??'') ?>"></div>
          <div class="col-md-2"><label class="form-label small">Urutan</label><input class="form-control" type="number" name="sort_order" value="<?= (int)($r['sort_order']??0) ?>"></div>
          <div class="col-md-2 form-check align-self-end ms-2"><input class="form-check-input" type="checkbox" name="aktif" id="ae<?= $r['id'] ?>" value="1" <?= $isAct?'checked':'' ?>><label class="form-check-label small" for="ae<?= $r['id'] ?>">Aktif</label></div>
        </div>
      </div>
      <div class="modal-footer"><button class="btn btn-primary">Simpan</button></div>
    </form>
  </div>
</div>
<?php endforeach; ?>

<?php include __DIR__.'/../includes/footer.php'; ?>
