<?php
/**
 * includes/ai_qa_widget.php — Revisi 18 Juni 2026
 * Widget Tanya Jawab AI (mengikuti pola dari islami.php) — dapat dipakai berulang
 * di halaman cedera_olahraga.php (ai_health) dan kesehatan.php (ai_doctor).
 *
 * Argumen (variabel lokal sebelum include):
 *  $aiTitle       : Judul header card (mis. "AI Health — Tanya Jawab")
 *  $aiTask        : task untuk api_ai.php (mis. "ai_health" atau "ai_doctor")
 *  $aiColor       : warna bootstrap (mis. "danger", "primary", "success")
 *  $aiIcon        : bi icon (mis. "bi-heart-pulse")
 *  $aiPlaceholder : contoh pertanyaan
 *  $aiPostUrl     : URL halaman saat ini untuk simpan/hapus (mis. "/cedera_olahraga.php")
 *  $aiSaved       : array hasil DB (id,pertanyaan,jawaban,created_at)
 *  $aiKey         : id unik form (mis. "aiHealth")
 *  $aiDisclaim    : disclaimer footer
 */
$aiTitle       = $aiTitle       ?? 'AI Tanya Jawab';
$aiTask        = $aiTask        ?? 'chat';
$aiColor       = $aiColor       ?? 'primary';
$aiIcon        = $aiIcon        ?? 'bi-robot';
$aiPlaceholder = $aiPlaceholder ?? 'Tulis pertanyaan Anda di sini...';
$aiPostUrl     = $aiPostUrl     ?? '/'.basename($_SERVER['SCRIPT_NAME']);
$aiSaved       = $aiSaved       ?? [];
$aiKey         = $aiKey         ?? 'aiQA';
$aiDisclaim    = $aiDisclaim    ?? 'Konten edukatif, bukan pengganti tenaga medis.';
?>
<div class="card shadow-sm mb-3 border-<?= htmlspecialchars($aiColor) ?>">
  <div class="card-header bg-<?= htmlspecialchars($aiColor) ?>-subtle text-<?= htmlspecialchars($aiColor) ?>-emphasis">
    <i class="bi <?= htmlspecialchars($aiIcon) ?>"></i> <strong><?= htmlspecialchars($aiTitle) ?></strong>
  </div>
  <div class="card-body">
    <form id="<?= $aiKey ?>Form" class="vstack gap-2 mb-2">
      <textarea id="<?= $aiKey ?>Input" class="form-control" rows="3" placeholder="<?= htmlspecialchars($aiPlaceholder) ?>" required></textarea>
      <div class="d-flex gap-2 flex-wrap">
        <button class="btn btn-<?= htmlspecialchars($aiColor) ?> btn-sm" type="submit"><i class="bi bi-send"></i> Tanyakan</button>
        <button class="btn btn-outline-secondary btn-sm" type="button" id="<?= $aiKey ?>Clear"><i class="bi bi-eraser"></i> Bersihkan</button>
        <small class="text-muted ms-auto align-self-center">Hemat kuota AI — pertanyaan sama tidak dikirim ulang.</small>
      </div>
    </form>
    <div id="<?= $aiKey ?>Out" class="border rounded p-3 bg-body-tertiary small text-muted" style="min-height:80px">Tulis pertanyaan lalu klik <b>Tanyakan</b>.</div>
    <div id="<?= $aiKey ?>Actions" class="d-flex gap-2 mt-2" style="display:none !important">
      <button type="button" id="<?= $aiKey ?>Simpan" class="btn btn-outline-<?= htmlspecialchars($aiColor) ?> btn-sm"><i class="bi bi-bookmark-plus"></i> Simpan Q&amp;A ini</button>
      <span id="<?= $aiKey ?>Stat" class="small text-muted align-self-center"></span>
    </div>
    <div class="alert alert-warning small mt-2 mb-0"><i class="bi bi-info-circle"></i> <?= htmlspecialchars($aiDisclaim) ?></div>

    <div class="mt-3">
      <a class="small" data-bs-toggle="collapse" href="#<?= $aiKey ?>SavedBox" role="button" aria-expanded="false">
        <i class="bi bi-bookmark-star"></i> Tanya Jawab Tersimpan (<?= count($aiSaved) ?>)
      </a>
      <div class="collapse mt-2" id="<?= $aiKey ?>SavedBox">
        <?php if (!$aiSaved): ?>
          <div class="small text-muted">Belum ada Q&amp;A tersimpan. Klik <b>Simpan Q&amp;A ini</b> setelah AI menjawab.</div>
        <?php else: foreach ($aiSaved as $qa): ?>
          <div class="border rounded p-2 mb-2 small" data-qa-id="<?= (int)$qa['id'] ?>">
            <div class="d-flex justify-content-between">
              <strong class="text-<?= htmlspecialchars($aiColor) ?>"><i class="bi bi-patch-question"></i> <?= htmlspecialchars(mb_strimwidth($qa['pertanyaan'],0,200,'…')) ?></strong>
              <button type="button" class="btn btn-sm btn-link text-danger p-0 <?= $aiKey ?>-del-btn" data-id="<?= (int)$qa['id'] ?>" title="Hapus"><i class="bi bi-trash"></i></button>
            </div>
            <div class="text-muted small mb-1"><?= htmlspecialchars(date('d M Y H:i', strtotime($qa['created_at']))) ?></div>
            <details><summary class="text-primary">Lihat jawaban</summary>
              <div class="mt-1" style="white-space:pre-wrap"><?= htmlspecialchars($qa['jawaban']) ?></div>
            </details>
          </div>
        <?php endforeach; endif; ?>
      </div>
    </div>
  </div>
</div>
<script>
(function(){
  var key = <?= json_encode($aiKey) ?>;
  var task = <?= json_encode($aiTask) ?>;
  var postUrl = <?= json_encode($aiPostUrl) ?>;
  var csrf = <?= json_encode(csrf_token()) ?>;
  var form = document.getElementById(key+'Form');
  if (!form) return;
  var inp  = document.getElementById(key+'Input');
  var out  = document.getElementById(key+'Out');
  var actions = document.getElementById(key+'Actions');
  var btnSimpan = document.getElementById(key+'Simpan');
  var stat = document.getElementById(key+'Stat');
  var isLoading = false;
  var lastQ = '', lastA = '', lastSavedKey = '';
  document.getElementById(key+'Clear').addEventListener('click', function(){
    inp.value='';
    out.className='border rounded p-3 bg-body-tertiary small text-muted';
    out.textContent='Tulis pertanyaan lalu klik Tanyakan.';
    if (actions) actions.style.display='none';
    lastQ=''; lastA=''; lastSavedKey='';
  });
  form.addEventListener('submit', async function(e){
    e.preventDefault();
    if (isLoading) return;
    var q = (inp.value||'').trim(); if (!q) return;
    if (q === lastQ && lastA) { stat.textContent = 'Pertanyaan sama — gunakan jawaban sebelumnya (hemat kuota AI).'; return; }
    isLoading = true;
    var btn = form.querySelector('button[type=submit]'); var oh = btn.innerHTML;
    btn.disabled=true; btn.innerHTML='<span class="spinner-border spinner-border-sm"></span> AI menjawab...';
    out.className='border rounded p-3 bg-body-tertiary small text-muted';
    out.textContent='Sedang menjawab... (hanya 1x kirim, mohon tunggu)';
    if (actions) actions.style.display='none';
    try {
      var fd = new FormData();
      fd.append('csrf', csrf);
      fd.append('task', task);
      fd.append('prompt', q);
      var r = await fetch('/api_ai.php',{method:'POST', body:fd, credentials:'same-origin'});
      var j = await r.json();
      if (!j.ok) {
        out.className='border rounded p-3 bg-warning-subtle small';
        out.textContent='Gagal: '+(j.err||'?');
      } else {
        out.className='border rounded p-3 bg-body-tertiary';
        var html = (j.text||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
                    .replace(/\*\*(.+?)\*\*/g,'<strong>$1</strong>')
                    .replace(/\n\n/g,'</p><p>').replace(/\n/g,'<br>');
        out.innerHTML = '<p>'+html+'</p>';
        lastQ = q; lastA = j.text || '';
        if (actions) actions.style.display='flex';
        stat.textContent = '';
      }
    } catch(err){ out.className='border rounded p-3 bg-warning-subtle small'; out.textContent='Error: '+err.message; }
    btn.disabled=false; btn.innerHTML=oh;
    isLoading = false;
  });
  if (btnSimpan) btnSimpan.addEventListener('click', async function(){
    if (!lastQ || !lastA) { stat.textContent='Tidak ada jawaban untuk disimpan.'; return; }
    var sk = lastQ+'||'+lastA.length;
    if (sk === lastSavedKey) { stat.textContent='Sudah tersimpan.'; return; }
    btnSimpan.disabled = true;
    var fd = new FormData();
    fd.append('csrf', csrf);
    fd.append('_action', 'qa_save');
    fd.append('pertanyaan', lastQ);
    fd.append('jawaban', lastA);
    try {
      var r = await fetch(postUrl,{method:'POST', body:fd, credentials:'same-origin'});
      var j = await r.json();
      if (j.ok){ lastSavedKey = sk; stat.innerHTML = '<i class="bi bi-check-circle text-success"></i> Tersimpan (#'+j.id+'). <a href="#'+key+'SavedBox">Lihat daftar</a>'; }
      else stat.textContent = 'Gagal menyimpan.';
    } catch(e){ stat.textContent = 'Error: '+e.message; }
    btnSimpan.disabled = false;
  });
  document.querySelectorAll('.'+key+'-del-btn').forEach(function(b){
    b.addEventListener('click', async function(){
      if (!confirm('Hapus Q&A ini?')) return;
      var id = b.dataset.id;
      var fd = new FormData();
      fd.append('csrf', csrf);
      fd.append('_action','qa_delete');
      fd.append('id', id);
      var r = await fetch(postUrl,{method:'POST', body:fd, credentials:'same-origin'});
      var j = await r.json();
      if (j.ok){ var el = document.querySelector('[data-qa-id="'+id+'"]'); if(el) el.remove(); }
    });
  });
})();
</script>
