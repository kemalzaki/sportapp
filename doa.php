<?php
/** Revisi R14 (25 Juni 2026)
 *  #3 — Tambah 2 fitur play suara (Dewasa & Anak-anak) untuk Doa Bawaan Aplikasi.
 *  Menggunakan Web Speech API (SpeechSynthesis) — tidak perlu file audio.
 */
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
require __DIR__.'/includes/islami_data.php';
require __DIR__.'/includes/islami_helpers.php';
send_security_headers(); require_login();
$pageTitle = 'Doa Harian';
$u = current_user();

function doa_sanitize_html(string $html): string {
    $html = trim($html);
    if ($html === '') return '';
    $allowed = '<b><strong><i><em><u><br><p><ul><ol><li><span><div>';
    $html = strip_tags($html, $allowed);
    $html = preg_replace('/<([a-zA-Z0-9]+)(\s[^>]*)?>/', '<$1>', $html);
    if (mb_strlen($html) > 8000) $html = mb_substr($html, 0, 8000);
    return $html;
}

if ($_SERVER['REQUEST_METHOD']==='POST' && $u) {
    csrf_check();
    $a = $_POST['_action'] ?? '';
    if ($a === 'doa_done') {
        islami_touch_streak((int)$u['id'], 'doa_done');
        islami_log_challenge((int)$u['id'], 'doa');
        $_SESSION['flash'] = 'Tercatat 🤲';
    } elseif ($a === 'create') {
        // Revisi 27 Juni 2026 #4 — judul & arab kini WYSIWYG (HTML disanitasi)
        $j = doa_sanitize_html($_POST['judul'] ?? '');
        $ar = doa_sanitize_html($_POST['arab'] ?? '');
        $tr = doa_sanitize_html($_POST['terjemah'] ?? '');
        if (strip_tags($j) !== '' && strip_tags($ar) !== '') {
            db_exec("INSERT INTO doa_user(user_id,judul,arab,terjemah) VALUES($1,$2,$3,$4)",
                [(int)$u['id'], mb_substr($j,0,500), $ar, $tr]);
            $_SESSION['flash'] = 'Doa berhasil ditambahkan ✨';
        } else { $_SESSION['flash_err'] = 'Judul dan teks Arab wajib diisi.'; }
    } elseif ($a === 'edit') {
        db_exec("UPDATE doa_user SET judul=$1, arab=$2, terjemah=$3, updated_at=now() WHERE id=$4 AND user_id=$5",
            [mb_substr(doa_sanitize_html($_POST['judul'] ?? ''),0,500), doa_sanitize_html($_POST['arab'] ?? ''), doa_sanitize_html($_POST['terjemah'] ?? ''), (int)$_POST['id'], (int)$u['id']]);
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

$builtin = $ISLAMI_DOA;
if ($q !== '') {
    $ql = mb_strtolower($q);
    $builtin = array_values(array_filter($ISLAMI_DOA, function($d) use ($ql){
        return mb_stripos($d[0], $ql) !== false
            || mb_stripos($d[1], $ql) !== false
            || mb_stripos($d[2], $ql) !== false;
    }));
}

include __DIR__.'/includes/header.php';
?>
<nav aria-label="breadcrumb" class="mb-2"><ol class="breadcrumb small mb-0">
  <li class="breadcrumb-item"><a href="/index.php">Beranda</a></li>
  <li class="breadcrumb-item"><a href="/islami.php">Islami</a></li>
  <li class="breadcrumb-item active">Doa Harian</li>
</ol></nav>
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

<?php
// Revisi 27 Juni 2026 #5 — Pagination 2 data per halaman utk Doa Saya & Doa Aplikasi
$perPage = 2;
$pMy  = max(1, (int)($_GET['p_my']  ?? 1));
$pApp = max(1, (int)($_GET['p_app'] ?? 1));
$myDoaAll  = $myDoa;
$builtinAll = $builtin;
$myTotal   = count($myDoaAll);
$appTotal  = count($builtinAll);
$myPages   = max(1, (int)ceil($myTotal/$perPage));
$appPages  = max(1, (int)ceil($appTotal/$perPage));
$pMy = min($pMy, $myPages); $pApp = min($pApp, $appPages);
$myDoa   = array_slice($myDoaAll, ($pMy-1)*$perPage, $perPage);
$builtin = array_slice($builtinAll, ($pApp-1)*$perPage, $perPage);
function doa_pager_url(array $over): string {
    $base = $_GET; foreach ($over as $k=>$v) $base[$k] = $v;
    $base = array_filter($base, fn($v)=>$v!==null && $v!=='');
    return '?'.http_build_query($base);
}
function doa_render_pager(string $param, int $cur, int $totalPages): string {
    if ($totalPages <= 1) return '';
    $out = '<nav><ul class="pagination pagination-sm justify-content-center mt-2 mb-3 flex-wrap">';
    $out .= '<li class="page-item '.($cur<=1?'disabled':'').'"><a class="page-link" href="'.htmlspecialchars(doa_pager_url([$param=>max(1,$cur-1)])).'">«</a></li>';
    for ($p=1; $p<=$totalPages; $p++) {
        $out .= '<li class="page-item '.($p===$cur?'active':'').'"><a class="page-link" href="'.htmlspecialchars(doa_pager_url([$param=>$p])).'">'.$p.'</a></li>';
    }
    $out .= '<li class="page-item '.($cur>=$totalPages?'disabled':'').'"><a class="page-link" href="'.htmlspecialchars(doa_pager_url([$param=>min($totalPages,$cur+1)])).'">»</a></li>';
    $out .= '</ul></nav>';
    return $out;
}
?>

<?php if ($u): ?>
<div class="card shadow-sm mb-3"><div class="card-header"><i class="bi bi-plus-circle text-success"></i> Tambah Doa Harian (CRUD pribadi)</div>
<div class="card-body">
  <form method="post" class="row g-2">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <input type="hidden" name="_action" value="create">
    <div class="col-md-6">
      <label class="small fw-semibold text-muted mb-1">Judul (WYSIWYG)</label>
      <div class="wysiwyg" data-target="judul_create"></div>
      <textarea name="judul" id="judul_create" class="d-none" required></textarea>
    </div>
    <div class="col-md-6">
      <label class="small fw-semibold text-muted mb-1">Teks Arab (WYSIWYG)</label>
      <div class="wysiwyg wysiwyg-arab" data-target="arab_create" data-dir="rtl"></div>
      <textarea name="arab" id="arab_create" class="d-none" required></textarea>
    </div>
    <div class="col-12">
      <label class="small fw-semibold text-muted mb-1">Terjemah (opsional)</label>
      <div class="wysiwyg" data-target="terjemah_create"></div>
      <textarea name="terjemah" id="terjemah_create" class="d-none"></textarea>
    </div>
    <div class="col-12"><button class="btn btn-success"><i class="bi bi-plus-lg"></i> Tambah Doa</button></div>
  </form>
</div></div>

<form method="post" class="mb-3"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="_action" value="doa_done">
  <button class="btn btn-success btn-sm"><i class="bi bi-check2-circle"></i> Catat: saya sudah berdoa hari ini</button></form>
<?php endif; ?>

<?php if ($myDoaAll): ?>
<h5 class="mt-3"><i class="bi bi-person-heart text-success"></i> Doa Saya (<?= $myTotal ?>) <small class="text-muted">— halaman <?= $pMy ?>/<?= $myPages ?></small></h5>
<div class="row g-3 mb-3">
<?php foreach ($myDoa as $d): ?>
  <div class="col-md-6"><div class="card h-100 border-success"><div class="card-body">
    <div class="d-flex justify-content-between">
      <div class="fw-semibold text-success mb-1"><i class="bi bi-bookmark-heart"></i> <?= $d['judul'] ?></div>
      <div class="btn-group btn-group-sm">
        <button class="btn btn-outline-primary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#edit<?= (int)$d['id'] ?>"><i class="bi bi-pencil"></i></button>
        <form method="post" onsubmit="return confirm('Hapus doa ini?');" style="display:inline">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
          <input type="hidden" name="_action" value="delete"><input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
          <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
        </form>
      </div>
    </div>
    <div class="text-end doa-arab-html" dir="rtl" style="font-family:'Amiri',serif;font-size:1.3rem;line-height:2"><?= $d['arab'] ?></div>
    <?php if($d['terjemah']): ?><div class="small fst-italic mt-2 doa-terjemah"><?= $d['terjemah'] ?></div><?php endif; ?>
    <div class="collapse mt-2" id="edit<?= (int)$d['id'] ?>">
      <form method="post" class="row g-2">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="_action" value="edit"><input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
        <div class="col-12">
          <label class="small text-muted mb-1">Judul</label>
          <div class="wysiwyg" data-target="judul_edit<?= (int)$d['id'] ?>"><?= $d['judul'] ?></div>
          <textarea name="judul" id="judul_edit<?= (int)$d['id'] ?>" class="d-none"><?= htmlspecialchars($d['judul'] ?? '') ?></textarea>
        </div>
        <div class="col-12">
          <label class="small text-muted mb-1">Teks Arab</label>
          <div class="wysiwyg wysiwyg-arab" data-target="arab_edit<?= (int)$d['id'] ?>" data-dir="rtl"><?= $d['arab'] ?></div>
          <textarea name="arab" id="arab_edit<?= (int)$d['id'] ?>" class="d-none"><?= htmlspecialchars($d['arab'] ?? '') ?></textarea>
        </div>
        <div class="col-12">
          <label class="small text-muted mb-1">Terjemah</label>
          <div class="wysiwyg" data-target="terjemah_edit<?= (int)$d['id'] ?>"><?= $d['terjemah'] ?></div>
          <textarea name="terjemah" id="terjemah_edit<?= (int)$d['id'] ?>" class="d-none"><?= htmlspecialchars($d['terjemah'] ?? '') ?></textarea>
        </div>
        <div class="col-12"><button class="btn btn-primary btn-sm w-100"><i class="bi bi-save"></i> Simpan</button></div>
      </form>
    </div>
  </div></div></div>
<?php endforeach; ?>
</div>
<?= doa_render_pager('p_my', $pMy, $myPages) ?>
<?php elseif ($u && $q): ?>
  <div class="alert alert-info py-2 small">Tidak ada doa pribadi yang cocok dengan "<strong><?= htmlspecialchars($q) ?></strong>".</div>
<?php endif; ?>

<h5 class="mt-3"><i class="bi bi-collection text-warning"></i> Doa Harian Anak-Anak — Doa Bawaan Aplikasi (<?= $appTotal ?>) <small class="text-muted">— halaman <?= $pApp ?>/<?= $appPages ?></small></h5>

<?php if (!$builtin): ?>
  <div class="alert alert-warning py-2 small">Tidak ditemukan doa bawaan yang cocok.</div>
<?php endif; ?>
<div class="row g-3">
<?php foreach ($builtin as $idx=>$d): ?>
  <div class="col-md-6"><div class="card h-100"><div class="card-body">
    <div class="fw-semibold text-warning mb-1"><i class="bi bi-bookmark"></i> <?= htmlspecialchars($d[0]) ?></div>
    <div class="text-end" dir="rtl" style="font-family:'Amiri',serif;font-size:1.3rem;line-height:2"><?= htmlspecialchars($d[1]) ?></div>
    <div class="small fst-italic mt-2"><?= htmlspecialchars($d[2]) ?></div>
  </div></div></div>
<?php endforeach; ?>
</div>
<?= doa_render_pager('p_app', $pApp, $appPages) ?>

<!-- WYSIWYG style -->
<style>
.wysiwyg-toolbar{display:flex;flex-wrap:wrap;gap:.25rem;margin-bottom:.25rem}
.wysiwyg-toolbar button{border:1px solid var(--bs-border-color,#ced4da);background:var(--bs-body-bg,#fff);border-radius:.25rem;padding:.1rem .5rem;font-size:.85rem;line-height:1.2;cursor:pointer}
.wysiwyg-toolbar button:hover{background:var(--bs-tertiary-bg,#f1f3f5)}
.wysiwyg-area{min-height:90px;border:1px solid var(--bs-border-color,#ced4da);border-radius:.375rem;padding:.5rem .75rem;background:var(--bs-body-bg,#fff);font-size:.95rem}
.wysiwyg-area:focus{outline:0;border-color:#86b7fe;box-shadow:0 0 0 .2rem rgba(13,110,253,.15)}
.wysiwyg-arab .wysiwyg-area{font-family:'Amiri',serif;font-size:1.25rem;text-align:right;direction:rtl;line-height:2}
.doa-terjemah p, .doa-terjemah-text p{margin:0 0 .35rem}
.doa-terjemah ul,.doa-terjemah ol{margin:0 0 .35rem 1.1rem;padding:0}
</style>
<script>
// === WYSIWYG editor (sama seperti sebelumnya) ===
(function(){
  function buildEditor(holder){
    var taId = holder.dataset.target;
    var ta = document.getElementById(taId);
    if(!ta) return;
    var initial = holder.innerHTML.trim() || ta.value || '';
    holder.innerHTML = '';
    var bar = document.createElement('div'); bar.className='wysiwyg-toolbar';
    var btns = [
      ['<b>B</b>','bold'],['<i>I</i>','italic'],['<u>U</u>','underline'],
      ['• List','insertUnorderedList'],['1. List','insertOrderedList'],['⨯','removeFormat']
    ];
    var area = document.createElement('div');
    area.className='wysiwyg-area'; area.contentEditable='true'; area.innerHTML=initial;
    if (holder.dataset.dir === 'rtl') { area.setAttribute('dir','rtl'); }
    btns.forEach(function(b){
      var btn=document.createElement('button'); btn.type='button'; btn.innerHTML=b[0];
      btn.addEventListener('click',function(e){e.preventDefault();area.focus();document.execCommand(b[1],false,null);sync();});
      bar.appendChild(btn);
    });
    function sync(){ ta.value = area.innerHTML.trim(); }
    area.addEventListener('input',sync); area.addEventListener('blur',sync);
    holder.appendChild(bar); holder.appendChild(area); sync();
    var form = holder.closest('form'); if(form) form.addEventListener('submit',sync);
  }
  document.querySelectorAll('.wysiwyg').forEach(buildEditor);
})();
</script>
<?php include __DIR__.'/includes/footer.php'; ?>
