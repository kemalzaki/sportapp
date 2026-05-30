<?php
require __DIR__.'/../config/db.php';
require __DIR__.'/../includes/auth.php';
require __DIR__.'/../includes/helpers.php';
require_role('admin');
$pageTitle = 'CRUD Jajanan';

/**
 * Helper upload foto jajanan ke ImageKit.
 * Mengembalikan ['url'=>..., 'fileId'=>...] atau null jika gagal/tidak ada file.
 */
function jjn_upload_imagekit($fileField, $namaPrefix) {
    if (empty($_FILES[$fileField]['name']) || !is_uploaded_file($_FILES[$fileField]['tmp_name'])) return null;
    $ext = strtolower(pathinfo($_FILES[$fileField]['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','webp','gif'], true)) return null;
    if (filesize($_FILES[$fileField]['tmp_name']) > 5*1024*1024) return null; // 5MB cap
    require_once __DIR__.'/../config/imagekit.php';
    global $imageKit;
    $safe = preg_replace('/[^a-z0-9]/i','_', $namaPrefix ?: 'jajanan') . '-' . time() . '-' . bin2hex(random_bytes(3)) . '.' . $ext;
    try {
        $resp = $imageKit->uploadFile([
            'file'     => base64_encode(file_get_contents($_FILES[$fileField]['tmp_name'])),
            'fileName' => $safe,
            'folder'   => '/sportapp/jajanan/' . date('Y/m'),
        ]);
        if (!$resp->error && !empty($resp->result->url)) {
            return ['url'=>$resp->result->url, 'fileId'=>$resp->result->fileId ?? null];
        }
    } catch (Throwable $e) { /* swallow */ }
    return null;
}

// Helper validasi lat/lng (revisi #6)
function jjn_parse_latlng($v, $min, $max) {
    if ($v === null || $v === '') return null;
    $f = (float)str_replace(',', '.', trim($v));
    if ($f < $min || $f > $max) return null;
    return $f;
}

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    $a = $_POST['_action'] ?? '';
    $nama = substr(trim($_POST['nama'] ?? ''),0,160);
    $des  = trim($_POST['deskripsi'] ?? '');
    $harga= max(0,(int)($_POST['harga'] ?? 0));
    $stok = max(0,(int)($_POST['stok'] ?? 0));
    $kat  = substr(trim($_POST['kategori'] ?? ''),0,60);
    $foto = substr(trim($_POST['foto_url'] ?? ''),0,500);
    $fotoFileId = null;
    $aktif= !empty($_POST['aktif']);
    // === Revisi #6: lat/lng lokasi jajanan ===
    $lat  = jjn_parse_latlng($_POST['lat'] ?? '', -90, 90);
    $lng  = jjn_parse_latlng($_POST['lng'] ?? '', -180, 180);

    $upl = jjn_upload_imagekit('foto', $nama);
    if ($upl) { $foto = $upl['url']; $fotoFileId = $upl['fileId']; }

    if ($a==='add' && $nama!=='') {
        db_exec("INSERT INTO jajanan(nama,deskripsi,harga,stok,foto_url,foto_file_id,kategori,aktif,lat,lng)
                 VALUES($1,$2,$3,$4,$5,$6,$7,$8,$9,$10)",
          [$nama,$des?:null,$harga,$stok,$foto?:null,$fotoFileId,$kat?:null,$aktif?'t':'f',$lat,$lng]);
    } elseif ($a==='edit') {
        $id=(int)$_POST['id'];
        $cur = db_one("SELECT foto_url, foto_file_id FROM jajanan WHERE id=$1",[$id]);
        if (!$upl && $foto==='') {
            $foto = $cur['foto_url'] ?? null;
            $fotoFileId = $cur['foto_file_id'] ?? null;
        } elseif ($upl) {
            if (!empty($cur['foto_file_id'])) {
                require_once __DIR__.'/../config/imagekit.php'; global $imageKit;
                try { $imageKit->deleteFile($cur['foto_file_id']); } catch(Throwable $e){}
            }
        }
        db_exec("UPDATE jajanan SET nama=$1,deskripsi=$2,harga=$3,stok=$4,foto_url=$5,foto_file_id=$6,kategori=$7,aktif=$8,lat=$9,lng=$10 WHERE id=$11",
          [$nama,$des?:null,$harga,$stok,$foto?:null,$fotoFileId,$kat?:null,$aktif?'t':'f',$lat,$lng,$id]);
    } elseif ($a==='delete') {
        $id = (int)$_POST['id'];
        $cur = db_one("SELECT foto_file_id FROM jajanan WHERE id=$1",[$id]);
        if (!empty($cur['foto_file_id'])) {
            require_once __DIR__.'/../config/imagekit.php'; global $imageKit;
            try { $imageKit->deleteFile($cur['foto_file_id']); } catch(Throwable $e){}
        }
        db_exec("DELETE FROM jajanan WHERE id=$1",[$id]);
    }
    header('Location: jajanan.php'); exit;
}
$rows = db_all("SELECT * FROM jajanan ORDER BY aktif DESC, nama");
include __DIR__.'/../includes/header.php';
?>
<h2 class="mb-3"><i class="bi bi-shop text-warning"></i> CRUD Jajanan (Gojek-style)</h2>
<p class="text-muted small">Jajanan yang aktif akan tampil di <a href="/jajanan.php">halaman pesan jajan</a> untuk umum/guest.
Foto otomatis di-upload &amp; disimpan ke <strong>ImageKit</strong>. Lokasi (lat/lng) opsional — bila diisi akan dipakai untuk peta &amp; perhitungan jarak.</p>

<div class="card mb-3"><div class="card-header"><i class="bi bi-plus-circle"></i> Tambah Jajanan</div>
<div class="card-body">
  <form method="post" enctype="multipart/form-data" class="row g-2">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <input type="hidden" name="_action" value="add">
    <div class="col-md-4"><label class="small">Nama Jajanan</label><input class="form-control form-control-sm" name="nama" required></div>
    <div class="col-md-2"><label class="small">Harga (Rp)</label><input type="number" class="form-control form-control-sm" name="harga" min="0" step="500" required></div>
    <div class="col-md-2"><label class="small">Stok</label><input type="number" class="form-control form-control-sm" name="stok" min="0" value="10"></div>
    <div class="col-md-2"><label class="small">Kategori</label><input class="form-control form-control-sm" name="kategori" placeholder="Minuman/Makanan/Snack"></div>
    <div class="col-md-2 d-flex align-items-end"><div class="form-check"><input class="form-check-input" type="checkbox" name="aktif" id="ak2" checked><label class="form-check-label small" for="ak2">Aktif</label></div></div>
    <div class="col-md-8"><label class="small">Deskripsi</label><textarea class="form-control form-control-sm" name="deskripsi" rows="2"></textarea></div>
    <div class="col-md-2"><label class="small">URL Foto (opsional)</label><input class="form-control form-control-sm" name="foto_url" placeholder="https://..."></div>
    <div class="col-md-2"><label class="small">Upload ke ImageKit</label><input type="file" class="form-control form-control-sm" name="foto" accept="image/*"></div>
    <!-- Revisi #6: lat/lng -->
    <div class="col-md-3"><label class="small">Lat lokasi jajanan</label><input class="form-control form-control-sm" name="lat" placeholder="-6.926263" inputmode="decimal"></div>
    <div class="col-md-3"><label class="small">Lng lokasi jajanan</label><input class="form-control form-control-sm" name="lng" placeholder="107.717553" inputmode="decimal"></div>
    <div class="col-12"><button class="btn btn-sm btn-primary"><i class="bi bi-cloud-upload"></i> Simpan &amp; Upload</button></div>
  </form>
</div></div>

<div class="row g-2">
<?php foreach($rows as $r): ?>
  <div class="col-md-4">
    <div class="card h-100 <?= ($r['aktif']==='t'||$r['aktif']===true)?'':'opacity-50' ?>">
      <?php if(!empty($r['foto_url'])): ?><img src="<?= htmlspecialchars($r['foto_url']) ?>" class="card-img-top" style="height:140px;object-fit:cover"><?php endif; ?>
      <div class="card-body">
        <form method="post" enctype="multipart/form-data" class="row g-1">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
          <input type="hidden" name="_action" value="edit">
          <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
          <div class="col-12"><input class="form-control form-control-sm" name="nama" value="<?= htmlspecialchars($r['nama']) ?>"></div>
          <div class="col-6"><input type="number" class="form-control form-control-sm" name="harga" value="<?= (int)$r['harga'] ?>"></div>
          <div class="col-6"><input type="number" class="form-control form-control-sm" name="stok" value="<?= (int)$r['stok'] ?>"></div>
          <div class="col-12"><input class="form-control form-control-sm" name="kategori" value="<?= htmlspecialchars($r['kategori'] ?? '') ?>" placeholder="Kategori"></div>
          <div class="col-12"><textarea class="form-control form-control-sm" name="deskripsi" rows="2"><?= htmlspecialchars($r['deskripsi'] ?? '') ?></textarea></div>
          <div class="col-12"><input class="form-control form-control-sm" name="foto_url" value="<?= htmlspecialchars($r['foto_url'] ?? '') ?>" placeholder="URL foto"></div>
          <div class="col-12"><input type="file" class="form-control form-control-sm" name="foto" accept="image/*"><div class="form-text small">Pilih file → tergantikan di ImageKit.</div></div>
          <!-- Revisi #6: lat/lng per item -->
          <div class="col-6"><input class="form-control form-control-sm" name="lat" value="<?= htmlspecialchars(isset($r['lat'])?(string)$r['lat']:'') ?>" placeholder="Lat" inputmode="decimal"></div>
          <div class="col-6"><input class="form-control form-control-sm" name="lng" value="<?= htmlspecialchars(isset($r['lng'])?(string)$r['lng']:'') ?>" placeholder="Lng" inputmode="decimal"></div>
          <?php if(!empty($r['lat']) && !empty($r['lng'])): ?>
            <div class="col-12 small">
              <a href="https://www.google.com/maps?q=<?= htmlspecialchars($r['lat']) ?>,<?= htmlspecialchars($r['lng']) ?>" target="_blank" rel="noopener">
                <i class="bi bi-geo-alt-fill text-danger"></i> Lihat lokasi di Google Maps
              </a>
            </div>
          <?php endif; ?>
          <div class="col-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="aktif" id="a<?= $r['id'] ?>" <?= ($r['aktif']==='t'||$r['aktif']===true)?'checked':'' ?>><label class="form-check-label small" for="a<?= $r['id'] ?>">Aktif</label></div></div>
          <div class="col-6 text-end"><button class="btn btn-sm btn-outline-primary"><i class="bi bi-check2"></i> Simpan</button></div>
        </form>
        <form method="post" class="mt-1" onsubmit="return confirm('Hapus jajanan ini? Foto di ImageKit juga akan dihapus.')">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
          <input type="hidden" name="_action" value="delete">
          <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
          <button class="btn btn-sm btn-outline-danger w-100"><i class="bi bi-trash"></i> Hapus</button>
        </form>
      </div>
    </div>
  </div>
<?php endforeach; if(!$rows): ?><div class="col-12"><p class="text-muted small">Belum ada jajanan.</p></div><?php endif; ?>
</div>

<?php include __DIR__.'/../includes/footer.php'; ?>
