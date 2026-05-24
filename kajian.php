<?php
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
require __DIR__.'/includes/islami_helpers.php';
send_security_headers(); require_login();
$pageTitle = 'Kajian Literatur Buku';
$u = current_user();

$upDir = __DIR__.'/uploads/kajian';
if (!is_dir($upDir)) @mkdir($upDir, 0775, true);

function save_pdf_upload(string $field, string $upDir): ?string {
    if (empty($_FILES[$field]['name']) || !is_uploaded_file($_FILES[$field]['tmp_name'])) return null;
    $ext = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
    if ($ext !== 'pdf') return null;
    if ($_FILES[$field]['size'] > 15*1024*1024) return null;
    $name = 'kajian_'.time().'_'.bin2hex(random_bytes(4)).'.pdf';
    $dest = $upDir.'/'.$name;
    if (move_uploaded_file($_FILES[$field]['tmp_name'], $dest)) {
        return '/uploads/kajian/'.$name;
    }
    return null;
}

if ($_SERVER['REQUEST_METHOD']==='POST' && $u) {
    csrf_check();
    $a = $_POST['_action'] ?? '';
    if ($a === 'create') {
        $pdf = save_pdf_upload('pdf_file', $upDir);
        db_exec("INSERT INTO islami_kajian(user_id,judul,penulis,tipe,isi,link_web,pdf_path,link_video)
                 VALUES($1,$2,$3,$4,$5,$6,$7,$8)",
          [(int)$u['id'],
           substr(trim($_POST['judul'] ?? ''), 0, 180),
           substr(trim($_POST['penulis'] ?? ''), 0, 120),
           in_array($_POST['tipe'] ?? 'buku', ['buku','artikel','jurnal','pdf','web'], true) ? $_POST['tipe'] : 'buku',
           substr($_POST['isi'] ?? '', 0, 5000),
           substr(trim($_POST['link_web'] ?? ''), 0, 500),
           $pdf,
           substr(trim($_POST['link_video'] ?? ''), 0, 255),
          ]);
        $_SESSION['flash'] = 'Literatur ditambahkan.';
    } elseif ($a === 'edit') {
        $id = (int)$_POST['id'];
        $o = db_one("SELECT user_id, pdf_path FROM islami_kajian WHERE id=$1", [$id]);
        if ($o && ((int)$o['user_id'] === (int)$u['id'] || $u['role']==='admin')) {
            $pdf = save_pdf_upload('pdf_file', $upDir) ?: $o['pdf_path'];
            db_exec("UPDATE islami_kajian SET judul=$1, penulis=$2, tipe=$3, isi=$4, link_web=$5, pdf_path=$6, link_video=$7, updated_at=now() WHERE id=$8",
              [substr(trim($_POST['judul'] ?? ''), 0, 180),
               substr(trim($_POST['penulis'] ?? ''), 0, 120),
               in_array($_POST['tipe'] ?? 'buku', ['buku','artikel','jurnal','pdf','web'], true) ? $_POST['tipe'] : 'buku',
               substr($_POST['isi'] ?? '', 0, 5000),
               substr(trim($_POST['link_web'] ?? ''), 0, 500),
               $pdf,
               substr(trim($_POST['link_video'] ?? ''), 0, 255),
               $id]);
            $_SESSION['flash'] = 'Literatur diperbarui.';
        }
    } elseif ($a === 'delete') {
        $id = (int)$_POST['id'];
        $o = db_one("SELECT user_id, pdf_path FROM islami_kajian WHERE id=$1", [$id]);
        if ($o && ((int)$o['user_id'] === (int)$u['id'] || $u['role']==='admin')) {
            if (!empty($o['pdf_path']) && file_exists(__DIR__.$o['pdf_path'])) @unlink(__DIR__.$o['pdf_path']);
            db_exec("DELETE FROM islami_kajian WHERE id=$1", [$id]);
            $_SESSION['flash'] = 'Literatur dihapus.';
        }
    }
    header('Location: /kajian.php'); exit;
}

$q = trim($_GET['q'] ?? '');
if ($q !== '') {
    $rows = db_all("SELECT k.*, u.nama FROM islami_kajian k LEFT JOIN users u ON u.id=k.user_id
                    WHERE k.judul ILIKE $1 OR k.penulis ILIKE $1 OR k.isi ILIKE $1
                    ORDER BY k.created_at DESC", ['%'.$q.'%']);
} else {
    $rows = db_all("SELECT k.*, u.nama FROM islami_kajian k LEFT JOIN users u ON u.id=k.user_id ORDER BY k.created_at DESC");
}

$editId = (int)($_GET['edit'] ?? 0);
$editRow = $editId ? db_one("SELECT * FROM islami_kajian WHERE id=$1", [$editId]) : null;
if ($editRow && (int)$editRow['user_id'] !== (int)$u['id'] && $u['role']!=='admin') $editRow = null;

include __DIR__.'/includes/header.php';
?>
<?php if (!empty($_SESSION['flash'])): ?><div class="alert alert-success py-2 small"><?= htmlspecialchars($_SESSION['flash']) ?></div><?php unset($_SESSION['flash']); endif; ?>
<h4 class="mb-3"><i class="bi bi-journal-bookmark text-info"></i> Kajian Literatur Buku</h4>
<p class="text-muted small">Bagikan ringkasan / catatan buku & literatur islami. Bisa lampirkan link web atau file PDF.</p>

<form class="row g-2 mb-3" method="get">
  <div class="col-md-8"><input class="form-control" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Cari judul, penulis, atau isi..."></div>
  <div class="col-md-2"><button class="btn btn-outline-info w-100"><i class="bi bi-search"></i> Cari</button></div>
  <?php if ($q): ?><div class="col-md-2"><a href="/kajian.php" class="btn btn-outline-secondary w-100">Reset</a></div><?php endif; ?>
</form>

<?php if ($u): ?>
<form method="post" enctype="multipart/form-data" class="card card-body mb-3">
  <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
  <input type="hidden" name="_action" value="<?= $editRow ? 'edit' : 'create' ?>">
  <?php if ($editRow): ?><input type="hidden" name="id" value="<?= (int)$editRow['id'] ?>"><?php endif; ?>
  <h6 class="mb-2"><?= $editRow ? '✏️ Edit Literatur' : '📚 Tambah Literatur' ?></h6>
  <div class="row g-2">
    <div class="col-md-6"><input class="form-control" name="judul" maxlength="180" required placeholder="Judul buku/artikel" value="<?= htmlspecialchars($editRow['judul'] ?? '') ?>"></div>
    <div class="col-md-4"><input class="form-control" name="penulis" maxlength="120" placeholder="Penulis" value="<?= htmlspecialchars($editRow['penulis'] ?? '') ?>"></div>
    <div class="col-md-2"><select class="form-select" name="tipe">
      <?php foreach (['buku','artikel','jurnal','pdf','web'] as $t): ?>
        <option value="<?= $t ?>" <?= (($editRow['tipe'] ?? 'buku')===$t)?'selected':'' ?>><?= ucfirst($t) ?></option>
      <?php endforeach; ?>
    </select></div>
    <div class="col-md-6"><input class="form-control" name="link_web" maxlength="500" placeholder="Link web (https://...)" value="<?= htmlspecialchars($editRow['link_web'] ?? '') ?>"></div>
    <div class="col-md-6"><input class="form-control" name="link_video" maxlength="255" placeholder="Link video (opsional)" value="<?= htmlspecialchars($editRow['link_video'] ?? '') ?>"></div>
    <div class="col-md-12"><label class="small">File PDF (maks 15MB) <?= !empty($editRow['pdf_path'])?'<span class="text-success">[sudah ada PDF, biarkan kosong agar tidak diganti]</span>':'' ?></label>
      <input class="form-control" type="file" name="pdf_file" accept="application/pdf"></div>
    <div class="col-12"><textarea class="form-control" name="isi" rows="4" placeholder="Ringkasan / catatan kajian..."><?= htmlspecialchars($editRow['isi'] ?? '') ?></textarea></div>
  </div>
  <div class="mt-2">
    <button class="btn btn-info"><?= $editRow ? 'Simpan Perubahan' : 'Tambah Literatur' ?></button>
    <?php if ($editRow): ?><a href="/kajian.php" class="btn btn-link">Batal</a><?php endif; ?>
  </div>
</form>
<?php endif; ?>

<?php foreach ($rows as $r):
  $tipeBadge = ['buku'=>'success','artikel'=>'primary','jurnal'=>'info','pdf'=>'danger','web'=>'secondary'][$r['tipe'] ?? 'buku'] ?? 'secondary'; ?>
<div class="card mb-2"><div class="card-body">
  <div class="d-flex justify-content-between align-items-start">
    <div>
      <h6 class="m-0">
        <span class="badge bg-<?= $tipeBadge ?> text-uppercase me-1"><?= htmlspecialchars($r['tipe'] ?? 'buku') ?></span>
        <?= htmlspecialchars($r['judul']) ?>
      </h6>
      <div class="small text-muted">
        <?php if(!empty($r['penulis'])): ?>oleh <strong><?= htmlspecialchars($r['penulis']) ?></strong> · <?php endif; ?>
        Dibagikan <?= htmlspecialchars($r['nama'] ?? 'Anon') ?> · <?= htmlspecialchars($r['created_at']) ?>
      </div>
    </div>
    <?php if ($u && ((int)$r['user_id']===(int)$u['id'] || $u['role']==='admin')): ?>
    <div class="d-flex gap-1">
      <a class="btn btn-sm btn-outline-secondary" href="?edit=<?= (int)$r['id'] ?>#"><i class="bi bi-pencil"></i></a>
      <form method="post" onsubmit="return confirm('Hapus?')"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="_action" value="delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
    </div>
    <?php endif; ?>
  </div>
  <?php if(!empty($r['isi'])): ?><div class="mt-2"><?= nl2br(htmlspecialchars($r['isi'])) ?></div><?php endif; ?>
  <div class="mt-2 d-flex flex-wrap gap-2">
    <?php if(!empty($r['link_web'])): ?><a href="<?= htmlspecialchars($r['link_web']) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary"><i class="bi bi-globe"></i> Buka Web</a><?php endif; ?>
    <?php if(!empty($r['pdf_path'])): ?><a href="<?= htmlspecialchars($r['pdf_path']) ?>" target="_blank" class="btn btn-sm btn-outline-danger"><i class="bi bi-file-earmark-pdf"></i> Baca PDF</a><?php endif; ?>
    <?php if(!empty($r['link_video'])): ?><a href="<?= htmlspecialchars($r['link_video']) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-info"><i class="bi bi-play-circle"></i> Video</a><?php endif; ?>
    <a class="btn btn-sm btn-outline-success" href="https://wa.me/?text=<?= rawurlencode($r['judul'].' - '.($r['link_web'] ?? '')) ?>" target="_blank"><i class="bi bi-share"></i> Bagikan</a>
  </div>
</div></div>
<?php endforeach; if (!$rows): ?><div class="text-muted">Belum ada literatur.</div><?php endif; ?>
<?php include __DIR__.'/includes/footer.php'; ?>
