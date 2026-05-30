<?php
require __DIR__.'/../config/db.php';
require __DIR__.'/../includes/auth.php';
require __DIR__.'/../includes/helpers.php';
require_role('admin');
$pageTitle = 'CRUD Jajanan';

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    $a = $_POST['_action'] ?? '';
    $nama = substr(trim($_POST['nama'] ?? ''),0,160);
    $des  = trim($_POST['deskripsi'] ?? '');
    $harga= max(0,(int)($_POST['harga'] ?? 0));
    $stok = max(0,(int)($_POST['stok'] ?? 0));
    $kat  = substr(trim($_POST['kategori'] ?? ''),0,60);
    $foto = substr(trim($_POST['foto_url'] ?? ''),0,500);
    $aktif= !empty($_POST['aktif']);

    // upload lokal opsional
    if (!empty($_FILES['foto']['name']) && is_uploaded_file($_FILES['foto']['tmp_name'])) {
        $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','webp'], true)) {
            $dir = __DIR__.'/../uploads/jajanan';
            if (!is_dir($dir)) @mkdir($dir,0775,true);
            $safe = 'jjn_'.time().'_'.bin2hex(random_bytes(3)).'.'.$ext;
            if (move_uploaded_file($_FILES['foto']['tmp_name'], $dir.'/'.$safe)) $foto = '/uploads/jajanan/'.$safe;
        }
    }

    if ($a==='add' && $nama!=='') {
        db_exec("INSERT INTO jajanan(nama,deskripsi,harga,stok,foto_url,kategori,aktif) VALUES($1,$2,$3,$4,$5,$6,$7)",
          [$nama,$des?:null,$harga,$stok,$foto?:null,$kat?:null,$aktif?'t':'f']);
    } elseif ($a==='edit') {
        $id=(int)$_POST['id'];
        if ($foto==='') {
            $cur = db_one("SELECT foto_url FROM jajanan WHERE id=$1",[$id]); $foto = $cur['foto_url'] ?? null;
        }
        db_exec("UPDATE jajanan SET nama=$1,deskripsi=$2,harga=$3,stok=$4,foto_url=$5,kategori=$6,aktif=$7 WHERE id=$8",
          [$nama,$des?:null,$harga,$stok,$foto?:null,$kat?:null,$aktif?'t':'f',$id]);
    } elseif ($a==='delete') {
        db_exec("DELETE FROM jajanan WHERE id=$1",[(int)$_POST['id']]);
    }
    header('Location: jajanan.php'); exit;
}
$rows = db_all("SELECT * FROM jajanan ORDER BY aktif DESC, nama");
include __DIR__.'/../includes/header.php';
?>
<h2 class="mb-3"><i class="bi bi-shop text-warning"></i> CRUD Jajanan (Gojek-style)</h2>
<p class="text-muted small">Jajanan yang aktif akan tampil di <a href="/jajanan.php">halaman pesan jajan</a> untuk umum/guest.</p>

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
    <div class="col-md-2"><label class="small">URL Foto</label><input class="form-control form-control-sm" name="foto_url" placeholder="https://..."></div>
    <div class="col-md-2"><label class="small">atau Upload</label><input type="file" class="form-control form-control-sm" name="foto" accept="image/*"></div>
    <div class="col-12"><button class="btn btn-sm btn-primary"><i class="bi bi-save"></i> Simpan</button></div>
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
          <div class="col-12"><input class="form-control form-control-sm" name="kategori" value="<?= htmlspecialchars($r['kategori'] ?? '') ?>"></div>
          <div class="col-12"><textarea class="form-control form-control-sm" name="deskripsi" rows="2"><?= htmlspecialchars($r['deskripsi'] ?? '') ?></textarea></div>
          <div class="col-12"><input class="form-control form-control-sm" name="foto_url" value="<?= htmlspecialchars($r['foto_url'] ?? '') ?>" placeholder="URL foto"></div>
          <div class="col-12"><input type="file" class="form-control form-control-sm" name="foto"></div>
          <div class="col-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="aktif" id="a<?= $r['id'] ?>" <?= ($r['aktif']==='t'||$r['aktif']===true)?'checked':'' ?>><label class="form-check-label small" for="a<?= $r['id'] ?>">Aktif</label></div></div>
          <div class="col-6 text-end"><button class="btn btn-sm btn-outline-primary"><i class="bi bi-check2"></i> Simpan</button></div>
        </form>
        <form method="post" class="mt-1" onsubmit="return confirm('Hapus jajanan ini?')">
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
