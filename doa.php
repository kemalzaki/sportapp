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
        $j = trim($_POST['judul'] ?? '');
        $ar = trim($_POST['arab'] ?? '');
        $tr = doa_sanitize_html($_POST['terjemah'] ?? '');
        if ($j !== '' && $ar !== '') {
            db_exec("INSERT INTO doa_user(user_id,judul,arab,terjemah) VALUES($1,$2,$3,$4)",
                [(int)$u['id'], substr($j,0,180), $ar, $tr]);
            $_SESSION['flash'] = 'Doa berhasil ditambahkan ✨';
        } else { $_SESSION['flash_err'] = 'Judul dan teks Arab wajib diisi.'; }
    } elseif ($a === 'edit') {
        db_exec("UPDATE doa_user SET judul=$1, arab=$2, terjemah=$3, updated_at=now() WHERE id=$4 AND user_id=$5",
            [substr(trim($_POST['judul']),0,180), trim($_POST['arab']), doa_sanitize_html($_POST['terjemah'] ?? ''), (int)$_POST['id'], (int)$u['id']]);
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
    <div class="col-md-6"><input class="form-control" name="judul" placeholder="Judul, mis. Doa Sebelum Wudhu" required></div>
    <div class="col-md-6"><input class="form-control text-end" dir="rtl" style="font-family:'Amiri',serif" name="arab" placeholder="Teks Arab" required></div>
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
    <?php if($d['terjemah']): ?><div class="small fst-italic mt-2 doa-terjemah"><?= $d['terjemah'] ?></div><?php endif; ?>
    <div class="collapse mt-2" id="edit<?= (int)$d['id'] ?>">
      <form method="post" class="row g-2">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="_action" value="edit"><input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
        <div class="col-12"><input class="form-control form-control-sm" name="judul" value="<?= htmlspecialchars($d['judul']) ?>"></div>
        <div class="col-12"><input class="form-control form-control-sm text-end" dir="rtl" style="font-family:'Amiri',serif" name="arab" value="<?= htmlspecialchars($d['arab']) ?>"></div>
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
<?php elseif ($u && $q): ?>
  <div class="alert alert-info py-2 small">Tidak ada doa pribadi yang cocok dengan "<strong><?= htmlspecialchars($q) ?></strong>".</div>
<?php endif; ?>

<h5 class="mt-3"><i class="bi bi-collection text-warning"></i> Doa Harian Anak-Anak — Doa Bawaan Aplikasi (<?= count($builtin) ?>)</h5>
<div class="alert alert-info py-2 small mb-3">
  <i class="bi bi-volume-up-fill"></i> <strong>Fitur Baru:</strong> Klik tombol
  <span class="badge bg-primary">🧑 Dewasa</span> atau
  <span class="badge bg-warning text-dark">👶 Anak-anak</span>
  untuk mendengarkan pelafalan doa (suara berbeda untuk dewasa &amp; anak-anak).
</div>

<?php if (!$builtin): ?>
  <div class="alert alert-warning py-2 small">Tidak ditemukan doa bawaan yang cocok.</div>
<?php endif; ?>
<div class="row g-3">
<?php foreach ($builtin as $idx=>$d): ?>
  <div class="col-md-6"><div class="card h-100"><div class="card-body">
    <div class="fw-semibold text-warning mb-1"><i class="bi bi-bookmark"></i> <?= htmlspecialchars($d[0]) ?></div>
    <div class="text-end doa-arab-text" dir="rtl" style="font-family:'Amiri',serif;font-size:1.3rem;line-height:2" data-arab="<?= htmlspecialchars($d[1]) ?>"><?= htmlspecialchars($d[1]) ?></div>
    <div class="small fst-italic mt-2 doa-terjemah-text" data-terjemah="<?= htmlspecialchars($d[2]) ?>"><?= htmlspecialchars($d[2]) ?></div>
    <!-- R14 #3: Tombol play TTS dewasa & anak -->
    <div class="d-flex gap-2 mt-2 flex-wrap">
      <button type="button" class="btn btn-sm btn-primary js-tts-play"
              data-judul="<?= htmlspecialchars($d[0]) ?>"
              data-arab="<?= htmlspecialchars($d[1]) ?>"
              data-terjemah="<?= htmlspecialchars($d[2]) ?>"
              data-mode="dewasa">
        <i class="bi bi-play-fill"></i> 🧑 Suara Dewasa
      </button>
      <button type="button" class="btn btn-sm btn-warning text-dark js-tts-play"
              data-judul="<?= htmlspecialchars($d[0]) ?>"
              data-arab="<?= htmlspecialchars($d[1]) ?>"
              data-terjemah="<?= htmlspecialchars($d[2]) ?>"
              data-mode="anak">
        <i class="bi bi-play-fill"></i> 👶 Suara Anak-Anak
      </button>
      <button type="button" class="btn btn-sm btn-outline-secondary js-tts-stop">
        <i class="bi bi-stop-fill"></i> Stop
      </button>
    </div>
  </div></div></div>
<?php endforeach; ?>
</div>

<!-- WYSIWYG style -->
<style>
.wysiwyg-toolbar{display:flex;flex-wrap:wrap;gap:.25rem;margin-bottom:.25rem}
.wysiwyg-toolbar button{border:1px solid var(--bs-border-color,#ced4da);background:var(--bs-body-bg,#fff);border-radius:.25rem;padding:.1rem .5rem;font-size:.85rem;line-height:1.2;cursor:pointer}
.wysiwyg-toolbar button:hover{background:var(--bs-tertiary-bg,#f1f3f5)}
.wysiwyg-area{min-height:90px;border:1px solid var(--bs-border-color,#ced4da);border-radius:.375rem;padding:.5rem .75rem;background:var(--bs-body-bg,#fff);font-size:.95rem}
.wysiwyg-area:focus{outline:0;border-color:#86b7fe;box-shadow:0 0 0 .2rem rgba(13,110,253,.15)}
.doa-terjemah p, .doa-terjemah-text p{margin:0 0 .35rem}
.doa-terjemah ul,.doa-terjemah ol{margin:0 0 .35rem 1.1rem;padding:0}
.js-tts-playing{box-shadow:0 0 0 .25rem rgba(255,193,7,.4)!important}
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

// === R14 #3: Text-To-Speech untuk doa bawaan (Dewasa & Anak-anak) ===
(function(){
  if (!('speechSynthesis' in window)) {
    document.querySelectorAll('.js-tts-play').forEach(function(b){
      b.disabled = true; b.title = 'Browser tidak mendukung Text-To-Speech.';
    });
    return;
  }
  var synth = window.speechSynthesis;
  var voices = [];
  function loadVoices(){ voices = synth.getVoices(); }
  loadVoices();
  if (typeof synth.onvoiceschanged !== 'undefined') synth.onvoiceschanged = loadVoices;

  function pickVoice(lang, prefer){
    if (!voices.length) voices = synth.getVoices();
    // Cari voice sesuai bahasa (id-ID untuk Indonesia, ar-SA untuk Arab)
    var langVoices = voices.filter(function(v){ return v.lang && v.lang.toLowerCase().indexOf(lang.toLowerCase()) === 0; });
    if (!langVoices.length) langVoices = voices;
    if (prefer === 'female') {
      var f = langVoices.find(function(v){ return /female|wanita|perempuan|google.*female|samantha|zira|google.*wavenet-?[abef]/i.test(v.name); });
      if (f) return f;
    }
    return langVoices[0];
  }

  var lastBtn = null;
  function stopAll(){
    try { synth.cancel(); } catch(e){}
    if (lastBtn) lastBtn.classList.remove('js-tts-playing');
    lastBtn = null;
  }

  function speak(text, mode, lang){
    if (!text) return;
    var u = new SpeechSynthesisUtterance(text);
    u.lang = lang;
    // Mode dewasa = nada normal, mode anak = pitch tinggi + sedikit lebih cepat
    if (mode === 'anak') {
      u.pitch = 1.6;       // suara lebih tinggi (mirip anak-anak)
      u.rate  = 1.05;
      u.voice = pickVoice(lang, 'female');
    } else {
      u.pitch = 0.95;      // suara dewasa pria/normal
      u.rate  = 0.92;
      u.voice = pickVoice(lang, null);
    }
    synth.speak(u);
  }

  document.querySelectorAll('.js-tts-play').forEach(function(b){
    b.addEventListener('click', function(){
      stopAll();
      lastBtn = b; b.classList.add('js-tts-playing');
      var mode = b.dataset.mode || 'dewasa';
      var judul = b.dataset.judul || '';
      var arab = b.dataset.arab || '';
      var terjemah = b.dataset.terjemah || '';
      // Urutan baca: Judul (ID) → Arab (AR) → Terjemah (ID).
      // Karena banyak browser tidak punya voice Arab, fallback ke voice default.
      if (judul) speak(judul + '.', mode, 'id-ID');
      if (arab) speak(arab, mode, 'ar-SA');
      if (terjemah) speak('Artinya: ' + terjemah, mode, 'id-ID');
      // Bersihkan highlight setelah selesai
      var checker = setInterval(function(){
        if (!synth.speaking && !synth.pending) {
          if (lastBtn === b) { b.classList.remove('js-tts-playing'); lastBtn = null; }
          clearInterval(checker);
        }
      }, 400);
    });
  });
  document.querySelectorAll('.js-tts-stop').forEach(function(b){
    b.addEventListener('click', stopAll);
  });
  window.addEventListener('beforeunload', stopAll);
})();
</script>
<?php include __DIR__.'/includes/footer.php'; ?>
