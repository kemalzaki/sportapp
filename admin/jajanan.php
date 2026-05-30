<?php
/**
 * Revisi 2 Jun 2026
 *   #6 Upload foto ke ImageKit ditampilkan errornya bila gagal (debug + flash)
 *   #7 Tampilan produk → tabel + pagination (10/halaman). Edit via modal.
 */
require __DIR__.'/../config/db.php';
require __DIR__.'/../includes/auth.php';
require __DIR__.'/../includes/helpers.php';
require_role('admin');
$pageTitle = 'CRUD Jajanan';

/** Upload foto ke ImageKit. Return ['url','fileId'] atau lempar exception kalau gagal. */
function jjn_upload_imagekit_strict($fileField, $namaPrefix) {
    if (empty($_FILES[$fileField]['name'])) return null; // tidak diisi
    $err = $_FILES[$fileField]['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($err === UPLOAD_ERR_NO_FILE) return null;
    if ($err !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload gagal (kode error PHP: '.$err.'). Cek upload_max_filesize / post_max_size di php.ini.');
    }
    if (!is_uploaded_file($_FILES[$fileField]['tmp_name'])) {
        throw new RuntimeException('File tidak ter-upload dengan benar.');
    }
    $ext = strtolower(pathinfo($_FILES[$fileField]['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','webp','gif'], true)) {
        throw new RuntimeException('Format foto tidak didukung: .'.$ext);
    }
    if (filesize($_FILES[$fileField]['tmp_name']) > 5*1024*1024) {
        throw new RuntimeException('Foto > 5 MB.');
    }
    if (!file_exists(__DIR__.'/../config/imagekit.php')) {
        throw new RuntimeException('config/imagekit.php tidak ditemukan.');
    }
    require_once __DIR__.'/../config/imagekit.php';
    global $imageKit;
    if (!isset($imageKit) || !is_object($imageKit)) {
        throw new RuntimeException('Variabel $imageKit tidak terdefinisi. Periksa config/imagekit.php & autoload composer.');
    }
    $safe = preg_replace('/[^a-z0-9]/i','_', $namaPrefix ?: 'jajanan') . '-' . time() . '-' . bin2hex(random_bytes(3)) . '.' . $ext;
    $resp = $imageKit->uploadFile([
        'file'     => base64_encode(file_get_contents($_FILES[$fileField]['tmp_name'])),
        'fileName' => $safe,
        'folder'   => '/sportapp/jajanan/' . date('Y/m'),
    ]);
    if (!empty($resp->error)) {
        $m = is_object($resp->error) ? json_encode($resp->error) : (string)$resp->error;
        throw new RuntimeException('ImageKit error: '.$m);
    }
    if (empty($resp->result->url)) {
        throw new RuntimeException('ImageKit tidak mengembalikan URL.');
    }
    return ['url'=>$resp->result->url, 'fileId'=>$resp->result->fileId ?? null];
}

function jjn_parse_latlng($v, $min, $max) {
    if ($v === null || $v === '') return null;
    $f = (float)str_replace(',', '.', trim($v));
    if ($f < $min || $f > $max) return null;
    return $f;
}

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    $a    = $_POST['_action'] ?? '';
    $nama = substr(trim($_POST['nama'] ?? ''),0,160);
    $des  = trim($_POST['deskripsi'] ?? '');
    $harga= max(0,(int)($_POST['harga'] ?? 0));
    $stok = max(0,(int)($_POST['stok'] ?? 0));
    $kat  = substr(trim($_POST['kategori'] ?? ''),0,60);
    $foto = substr(trim($_POST['foto_url'] ?? ''),0,500);
    $fotoFileId = null;
    $aktif= !empty($_POST['aktif']);
    $lat  = jjn_parse_latlng($_POST['lat'] ?? '', -90, 90);
    $lng  = jjn_parse_latlng($_POST['lng'] ?? '', -180, 180);

    try {
        if ($a === 'add' || $a === 'edit') {
            $upl = jjn_upload_imagekit_strict('foto', $nama);
            if ($upl) { $foto = $upl['url']; $fotoFileId = $upl['fileId']; }
        }

        if ($a==='add' && $nama!=='') {
            db_exec("INSERT INTO jajanan(nama,deskripsi,harga,stok,foto_url,foto_file_id,kategori,aktif,lat,lng)
                     VALUES($1,$2,$3,$4,$5,$6,$7,$8,$9,$10)",
              [$nama,$des?:null,$harga,$stok,$foto?:null,$fotoFileId,$kat?:null,$aktif?'t':'f',$lat,$lng]);
            $_SESSION['flash'] = 'Jajanan ditambahkan.'.($foto?' Foto ter-upload.':'');
        } elseif ($a==='edit') {
            $id=(int)$_POST['id'];
            $cur = db_one("SELECT foto_url, foto_file_id FROM jajanan WHERE id=$1",[$id]);
            if (!isset($upl) || !$upl) {
                if ($foto==='') { $foto = $cur['foto_url'] ?? null; $fotoFileId = $cur['foto_file_id'] ?? null; }
            } else {
                if (!empty($cur['foto_file_id'])) {
                    require_once __DIR__.'/../config/imagekit.php'; global $imageKit;
                    try { $imageKit->deleteFile($cur['foto_file_id']); } catch(Throwable $e){}
                }
            }
            db_exec("UPDATE jajanan SET nama=$1,deskripsi=$2,harga=$3,stok=$4,foto_url=$5,foto_file_id=$6,kategori=$7,aktif=$8,lat=$9,lng=$10 WHERE id=$11",
              [$nama,$des?:null,$harga,$stok,$foto?:null,$fotoFileId,$kat?:null,$aktif?'t':'f',$lat,$lng,$id]);
            $_SESSION['flash'] = 'Jajanan diperbarui.'.($upl?' Foto baru ter-upload.':'');
        } elseif ($a==='delete') {
            $id = (int)$_POST['id'];
            $cur = db_one("SELECT foto_file_id FROM jajanan WHERE id=$1",[$id]);
            if (!empty($cur['foto_file_id'])) {
                require_once __DIR__.'/../config/imagekit.php'; global $imageKit;
                try { $imageKit->deleteFile($cur['foto_file_id']); } catch(Throwable $e){}
            }
            db_exec("DELETE FROM jajanan WHERE id=$1",[$id]);
            $_SESSION['flash'] = 'Jajanan dihapus.';
        }
    } catch (Throwable $e) {
        $_SESSION['flash_err'] = 'Gagal: '.$e->getMessage();
    }
    header('Location: jajanan.php'.(isset($_GET['page'])?'?page='.(int)$_GET['page']:'')); exit;
}

/* ---------- Pagination ---------- */
$PER_PAGE = 10;
$total = (int) db_val("SELECT COUNT(*) FROM jajanan");
$totalPage = max(1, (int)ceil($total / $PER_PAGE));
$page = max(1, (int)($_GET['page'] ?? 1));
if ($page > $totalPage) $page = $totalPage;
$offset = ($page-1) * $PER_PAGE;
$rows = db_all("SELECT * FROM jajanan ORDER BY aktif DESC, id DESC LIMIT $PER_PAGE OFFSET $offset");

include __DIR__.'/../includes/header.php';
?>
<h2 class="mb-3"><i class="bi bi-shop text-warning"></i> CRUD Jajanan</h2>
<?php if (!empty($_SESSION['flash'])): ?><div class="alert alert-success py-2 small"><?= htmlspecialchars($_SESSION['flash']) ?></div><?php unset($_SESSION['flash']); endif; ?>
<?php if (!empty($_SESSION['flash_err'])): ?><div class="alert alert-danger py-2 small"><?= htmlspecialchars($_SESSION['flash_err']) ?></div><?php unset($_SESSION['flash_err']); endif; ?>

<p class="text-muted small">Foto otomatis diunggah ke <strong>ImageKit</strong>. Klik "Edit" pada baris produk untuk mengubah.
Lokasi (lat/lng) dipakai untuk peta &amp; perhitungan jarak.</p>

<div class="card mb-3"><div class="card-header"><i class="bi bi-plus-circle"></i> Tambah Jajanan</div>
<div class="card-body">
  <form method="post" enctype="multipart/form-data" class="row g-2">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <input type="hidden" name="_action" value="add">
    <div class="col-md-4"><label class="small">Nama Jajanan</label><input class="form-control form-control-sm" name="nama" required></div>
    <div class="col-md-2"><label class="small">Harga (Rp)</label><input type="number" class="form-control form-control-sm" name="harga" min="0" step="500" required></div>
    <div class="col-md-2"><label class="small">Stok</label><input type="number" class="form-control form-control-sm" name="stok" min="0" value="10"></div>
    <div class="col-md-2"><label class="small">Kategori</label><input class="form-control form-control-sm" name="kategori" placeholder="Makanan/Minuman/Snack"></div>
    <div class="col-md-2 d-flex align-items-end"><div class="form-check"><input class="form-check-input" type="checkbox" name="aktif" id="ak2" checked><label class="form-check-label small" for="ak2">Aktif</label></div></div>
    <div class="col-md-6"><label class="small">Deskripsi</label><textarea class="form-control form-control-sm" name="deskripsi" rows="2"></textarea></div>
    <div class="col-md-3"><label class="small">URL Foto (opsional)</label><input class="form-control form-control-sm" name="foto_url" placeholder="https://..."></div>
    <div class="col-md-3"><label class="small">Upload Foto → ImageKit</label><input type="file" class="form-control form-control-sm" name="foto" accept="image/*"></div>
    <div class="col-md-3"><label class="small">Lat</label><input class="form-control form-control-sm" name="lat" placeholder="-6.926263" inputmode="decimal"></div>
    <div class="col-md-3"><label class="small">Lng</label><input class="form-control form-control-sm" name="lng" placeholder="107.717553" inputmode="decimal"></div>
    <div class="col-12"><button class="btn btn-sm btn-primary"><i class="bi bi-cloud-upload"></i> Simpan &amp; Upload</button></div>
  </form>
</div></div>

<!-- ===== Tabel produk dengan pagination ===== -->
<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <div><i class="bi bi-list-ul"></i> Daftar Produk <span class="badge bg-secondary"><?= $total ?></span></div>
    <div class="small text-muted">Halaman <?= $page ?> dari <?= $totalPage ?></div>
  </div>
  <div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th style="width:60px">Foto</th>
          <th>Nama</th>
          <th>Kategori</th>
          <th class="text-end">Harga</th>
          <th class="text-end">Stok</th>
          <th>Lokasi</th>
          <th>Aktif</th>
          <th class="text-end" style="width:140px">Aksi</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach($rows as $r):
        $rJson = htmlspecialchars(json_encode([
          'id'=>(int)$r['id'],'nama'=>$r['nama'],'deskripsi'=>$r['deskripsi'],
          'harga'=>(int)$r['harga'],'stok'=>(int)$r['stok'],'kategori'=>$r['kategori'],
          'foto_url'=>$r['foto_url'],'aktif'=>($r['aktif']==='t'||$r['aktif']===true),
          'lat'=>$r['lat']??'','lng'=>$r['lng']??''
        ], JSON_UNESCAPED_UNICODE), ENT_QUOTES);
      ?>
        <tr>
          <td><?php if(!empty($r['foto_url'])): ?><img src="<?= htmlspecialchars($r['foto_url']) ?>" style="width:48px;height:48px;object-fit:cover;border-radius:6px"><?php else: ?><span class="text-muted small">—</span><?php endif; ?></td>
          <td><div class="fw-semibold"><?= htmlspecialchars($r['nama']) ?></div><div class="text-muted small text-truncate" style="max-width:280px"><?= htmlspecialchars($r['deskripsi'] ?? '') ?></div></td>
          <td><?= htmlspecialchars($r['kategori'] ?? '-') ?></td>
          <td class="text-end">Rp <?= number_format((int)$r['harga'],0,',','.') ?></td>
          <td class="text-end"><?= (int)$r['stok'] ?></td>
          <td><?php if(!empty($r['lat']) && !empty($r['lng'])): ?>
              <a target="_blank" rel="noopener" href="https://www.google.com/maps?q=<?= htmlspecialchars($r['lat']) ?>,<?= htmlspecialchars($r['lng']) ?>"><i class="bi bi-geo-alt-fill text-danger"></i> Maps</a>
            <?php else: ?><span class="text-muted small">—</span><?php endif; ?></td>
          <td><?= ($r['aktif']==='t'||$r['aktif']===true) ? '<span class="badge bg-success">Aktif</span>' : '<span class="badge bg-secondary">Off</span>' ?></td>
          <td class="text-end">
            <button class="btn btn-sm btn-outline-primary btn-edit" data-row="<?= $rJson ?>"><i class="bi bi-pencil"></i></button>
            <form method="post" class="d-inline" onsubmit="return confirm('Hapus produk ini?')">
              <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
              <input type="hidden" name="_action" value="delete">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
            </form>
          </td>
        </tr>
      <?php endforeach; if (!$rows): ?>
        <tr><td colspan="8" class="text-center text-muted small py-3">Belum ada produk.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($totalPage > 1): ?>
  <div class="card-footer">
    <nav><ul class="pagination pagination-sm justify-content-center mb-0">
      <li class="page-item <?= $page<=1?'disabled':'' ?>"><a class="page-link" href="?page=<?= max(1,$page-1) ?>">«</a></li>
      <?php for($p=1;$p<=$totalPage;$p++): ?>
        <li class="page-item <?= $p===$page?'active':'' ?>"><a class="page-link" href="?page=<?= $p ?>"><?= $p ?></a></li>
      <?php endfor; ?>
      <li class="page-item <?= $page>=$totalPage?'disabled':'' ?>"><a class="page-link" href="?page=<?= min($totalPage,$page+1) ?>">»</a></li>
    </ul></nav>
  </div>
  <?php endif; ?>
</div>

<!-- ===== Modal Edit ===== -->
<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered modal-fullscreen-md-down">
    <div class="modal-content">
      <form method="post" enctype="multipart/form-data">
        <div class="modal-header"><h5 class="modal-title">Edit Jajanan</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body row g-2">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
          <input type="hidden" name="_action" value="edit">
          <input type="hidden" name="id" id="ef_id">
          <div class="col-md-6"><label class="small">Nama</label><input class="form-control form-control-sm" name="nama" id="ef_nama" required></div>
          <div class="col-md-3"><label class="small">Harga</label><input type="number" class="form-control form-control-sm" name="harga" id="ef_harga" min="0" step="500" required></div>
          <div class="col-md-3"><label class="small">Stok</label><input type="number" class="form-control form-control-sm" name="stok" id="ef_stok" min="0"></div>
          <div class="col-md-6"><label class="small">Kategori</label><input class="form-control form-control-sm" name="kategori" id="ef_kategori"></div>
          <div class="col-md-3"><label class="small">Lat</label><input class="form-control form-control-sm" name="lat" id="ef_lat" inputmode="decimal"></div>
          <div class="col-md-3"><label class="small">Lng</label><input class="form-control form-control-sm" name="lng" id="ef_lng" inputmode="decimal"></div>
          <div class="col-12"><label class="small">Deskripsi</label><textarea class="form-control form-control-sm" name="deskripsi" id="ef_desk" rows="2"></textarea></div>
          <div class="col-md-7"><label class="small">URL Foto (akan dipakai bila tidak upload file baru)</label><input class="form-control form-control-sm" name="foto_url" id="ef_foto_url"></div>
          <div class="col-md-5"><label class="small">Upload baru → ImageKit</label><input type="file" class="form-control form-control-sm" name="foto" accept="image/*"></div>
          <div class="col-12"><img id="ef_preview" src="" style="max-height:120px;display:none" class="rounded border"></div>
          <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="aktif" id="ef_aktif"><label class="form-check-label small" for="ef_aktif">Aktif</label></div></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
          <button class="btn btn-primary btn-sm"><i class="bi bi-check2"></i> Simpan Perubahan</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
/* Tunggu DOMContentLoaded supaya bootstrap.bundle.min.js (dimuat di footer.php)
   sudah pasti tersedia. Sebelumnya script ini jalan inline -> `bootstrap`
   undefined -> seluruh IIFE crash -> tombol Edit tidak ter-bind. */
document.addEventListener('DOMContentLoaded', function(){
  var modalEl = document.getElementById('editModal');
  if (!modalEl || typeof bootstrap === 'undefined') return;
  var modal = new bootstrap.Modal(modalEl);

  function fillAndShow(b){
    var d;
    try { d = JSON.parse(b.getAttribute('data-row')); }
    catch(e){ console.error('Gagal parse data-row:', e); return; }
    document.getElementById('ef_id').value       = d.id;
    document.getElementById('ef_nama').value     = d.nama || '';
    document.getElementById('ef_harga').value    = d.harga || 0;
    document.getElementById('ef_stok').value     = d.stok || 0;
    document.getElementById('ef_kategori').value = d.kategori || '';
    document.getElementById('ef_desk').value     = d.deskripsi || '';
    document.getElementById('ef_foto_url').value = d.foto_url || '';
    document.getElementById('ef_lat').value      = d.lat || '';
    document.getElementById('ef_lng').value      = d.lng || '';
    document.getElementById('ef_aktif').checked  = !!d.aktif;
    var prev = document.getElementById('ef_preview');
    if (d.foto_url){ prev.src = d.foto_url; prev.style.display=''; }
    else { prev.style.display='none'; }
    modal.show();
  }

  // Delegasi event — tahan banting walaupun footer.php memindahkan modal ke body.
  document.addEventListener('click', function(ev){
    var b = ev.target.closest && ev.target.closest('.btn-edit');
    if (!b) return;
    ev.preventDefault();
    fillAndShow(b);
  });
});
</script>

<?php include __DIR__.'/../includes/footer.php'; ?>
