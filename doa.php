<?php
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
require __DIR__.'/includes/islami_data.php';
require __DIR__.'/includes/islami_helpers.php';
send_security_headers(); require_login();
$pageTitle = 'Doa Harian';
$u = current_user();

if ($_SERVER['REQUEST_METHOD']==='POST' && $u) {
    csrf_check();
    $a = $_POST['_action'] ?? '';
    if ($a === 'doa_done') {
        islami_touch_streak((int)$u['id'], 'doa_done');
        islami_log_challenge((int)$u['id'], 'doa');
        $_SESSION['flash'] = 'Tercatat 🤲';
    } elseif ($a === 'create') {
        $j = trim($_POST['judul'] ?? '');
        $ar = trim($_POST['arab'] ?? '');
        $tr = trim($_POST['terjemah'] ?? '');
        if ($j !== '' && $ar !== '') {
            db_exec("INSERT INTO doa_user(user_id,judul,arab,terjemah) VALUES($1,$2,$3,$4)",
                [(int)$u['id'], substr($j,0,180), $ar, $tr]);
            $_SESSION['flash'] = 'Doa berhasil ditambahkan ✨';
        } else { $_SESSION['flash_err'] = 'Judul dan teks Arab wajib diisi.'; }
    } elseif ($a === 'edit') {
        db_exec("UPDATE doa_user SET judul=$1, arab=$2, terjemah=$3, updated_at=now() WHERE id=$4 AND user_id=$5",
            [substr(trim($_POST['judul']),0,180), trim($_POST['arab']), trim($_POST['terjemah'] ?? ''), (int)$_POST['id'], (int)$u['id']]);
        $_SESSION['flash'] = 'Doa diperbarui.';
    } elseif ($a === 'delete') {
        db_exec("DELETE FROM doa_user WHERE id=$1 AND user_id=$2", [(int)$_POST['id'], (int)$u['id']]);
        $_SESSION['flash'] = 'Doa dihapus.';
    }
    header('Location: /doa.php' . (isset($_POST['q'])?'?q='.urlencode($_POST['q']):'')); exit;
}

$q = trim($_GET['q'] ?? '');
$myDoa = [];
if ($u) {
    if ($q !== '') {
        $like = '%'.$q.'%';
        $myDoa = db_all("SELECT * FROM doa_user WHERE user_id=$1 AND (judul ILIKE $2 OR arab ILIKE $2 OR terjemah ILIKE $2) ORDER BY id DESC",
            [(int)$u['id'], $like]);
    } else {
        $myDoa = db_all("SELECT * FROM doa_user WHERE user_id=$1 ORDER BY id DESC", [(int)$u['id']]);
    }
}

// Filter daftar bawaan jika ada pencarian
$builtin = $ISLAMI_DOA;
if ($q !== '') {
    $ql = mb_strtolower($q);
    $builtin = array_values(array_filter($ISLAMI_DOA, function($d) use ($ql){
        return mb_stripos($d[0], $ql) !== false
            || mb_stripos($d[1], $ql) !== false
            || mb_stripos($d[2], $ql) !== false;
    }));
}

require_once __DIR__.'/includes/htmx.php'; htmx_layout_start($pageTitle ?? 'Doa');
?>
<?php if (!empty($_SESSION['flash'])): ?><div class="alert alert-success py-2 small"><?= htmlspecialchars($_SESSION['flash']) ?></div><?php unset($_SESSION['flash']); endif; ?>
<?php if (!empty($_SESSION['flash_err'])): ?><div class="alert alert-danger py-2 small"><?= htmlspecialchars($_SESSION['flash_err']) ?></div><?php unset($_SESSION['flash_err']); endif; ?>

<h4 class="mb-3"><i class="bi bi-chat-quote text-warning"></i> Doa Harian Singkat</h4>

<form method="get" class="row g-2 mb-3">
  <div class="col-md-9"><input type="search" name="q" value="<?= htmlspecialchars($q) ?>" class="form-control" placeholder="🔎 Cari kata pada judul / teks Arab / terjemah…"></div>
  <div class="col-md-3 d-flex gap-2">
    <button class="btn btn-warning flex-fill"><i class="bi bi-search"></i> Cari</button>
    <?php if($q): ?><a href="/doa.php" class="btn btn-outline-secondary">Reset</a><?php endif; ?>
  </div>
</form>

<?php if ($u): ?>
<div class="card shadow-sm mb-3"><div class="card-header"><i class="bi bi-plus-circle text-success"></i> Tambah Doa Harian (CRUD pribadi)</div>
<div class="card-body">
  <form method="post" class="row g-2">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <input type="hidden" name="_action" value="create">
    <div class="col-md-4"><input class="form-control" name="judul" placeholder="Judul, mis. Doa Sebelum Wudhu" required></div>
    <div class="col-md-4"><input class="form-control text-end" dir="rtl" style="font-family:'Amiri',serif" name="arab" placeholder="Teks Arab" required></div>
    <div class="col-md-4 d-flex gap-2">
      <input class="form-control" name="terjemah" placeholder="Terjemah (opsional)">
      <button class="btn btn-success"><i class="bi bi-plus-lg"></i></button>
    </div>
  </form>
</div></div>

<form method="post" class="mb-3"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="_action" value="doa_done">
  <button class="btn btn-success btn-sm"><i class="bi bi-check2-circle"></i> Catat: saya sudah berdoa hari ini</button></form>
<?php endif; ?>

<?php if ($myDoa): ?>
<h5 class="mt-3"><i class="bi bi-person-heart text-success"></i> Doa Saya (<?= count($myDoa) ?>)</h5>
<div class="row g-3 mb-3">
<?php foreach ($myDoa as $d): ?>
  <div class="col-md-6"><div class="card h-100 border-success"><div class="card-body">
    <div class="d-flex justify-content-between">
      <div class="fw-semibold text-success mb-1"><i class="bi bi-bookmark-heart"></i> <?= htmlspecialchars($d['judul']) ?></div>
      <div class="btn-group btn-group-sm">
        <button class="btn btn-outline-primary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#edit<?= (int)$d['id'] ?>"><i class="bi bi-pencil"></i></button>
        <form method="post" onsubmit="return confirm('Hapus doa ini?');" style="display:inline">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
          <input type="hidden" name="_action" value="delete"><input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
          <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
        </form>
      </div>
    </div>
    <div class="text-end" dir="rtl" style="font-family:'Amiri',serif;font-size:1.3rem;line-height:2"><?= htmlspecialchars($d['arab']) ?></div>
    <?php if($d['terjemah']): ?><div class="small fst-italic mt-2"><?= htmlspecialchars($d['terjemah']) ?></div><?php endif; ?>
    <div class="collapse mt-2" id="edit<?= (int)$d['id'] ?>">
      <form method="post" class="row g-2">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="_action" value="edit"><input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
        <div class="col-12"><input class="form-control form-control-sm" name="judul" value="<?= htmlspecialchars($d['judul']) ?>"></div>
        <div class="col-12"><input class="form-control form-control-sm text-end" dir="rtl" style="font-family:'Amiri',serif" name="arab" value="<?= htmlspecialchars($d['arab']) ?>"></div>
        <div class="col-12"><input class="form-control form-control-sm" name="terjemah" value="<?= htmlspecialchars($d['terjemah'] ?? '') ?>"></div>
        <div class="col-12"><button class="btn btn-primary btn-sm w-100"><i class="bi bi-save"></i> Simpan</button></div>
      </form>
    </div>
  </div></div></div>
<?php endforeach; ?>
</div>
<?php elseif ($u && $q): ?>
  <div class="alert alert-info py-2 small">Tidak ada doa pribadi yang cocok dengan "<strong><?= htmlspecialchars($q) ?></strong>".</div>
<?php endif; ?>

<h5 class="mt-3"><i class="bi bi-collection text-warning"></i> Doa Bawaan Aplikasi (<?= count($builtin) ?>)</h5>
<?php if (!$builtin): ?>
  <div class="alert alert-warning py-2 small">Tidak ditemukan doa bawaan yang cocok.</div>
<?php endif; ?>
<div class="row g-3">
<?php foreach ($builtin as $d): ?>
  <div class="col-md-6"><div class="card h-100"><div class="card-body">
    <div class="fw-semibold text-warning mb-1"><i class="bi bi-bookmark"></i> <?= htmlspecialchars($d[0]) ?></div>
    <div class="text-end" dir="rtl" style="font-family:'Amiri',serif;font-size:1.3rem;line-height:2"><?= htmlspecialchars($d[1]) ?></div>
    <div class="small fst-italic mt-2"><?= htmlspecialchars($d[2]) ?></div>
  </div></div></div>
<?php endforeach; ?>
</div>
<?php htmx_layout_end(); ?>
