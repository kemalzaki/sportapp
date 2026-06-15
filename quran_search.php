<?php
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
require __DIR__.'/includes/islami_data.php';
require __DIR__.'/includes/islami_helpers.php';
send_security_headers(); require_login();
$pageTitle = 'Cari Ayat & Terjemah';
$mode = ($_GET['mode'] ?? 'terjemah') === 'ayat' ? 'ayat' : 'terjemah';
require_once __DIR__.'/includes/htmx.php'; htmx_layout_start($pageTitle ?? 'Quran Search');
?>
<h4 class="mb-3"><i class="bi bi-search text-success"></i> Pencarian Al-Qur'an</h4>

<ul class="nav nav-pills mb-3">
  <li class="nav-item"><a class="nav-link <?= $mode==='terjemah'?'active':'' ?>" href="?mode=terjemah"><i class="bi bi-translate"></i> Cari Terjemah</a></li>
  <li class="nav-item"><a class="nav-link <?= $mode==='ayat'?'active':'' ?>"     href="?mode=ayat"><i class="bi bi-fonts"></i> Cari Ayat (Arab)</a></li>
</ul>

<div class="card shadow-sm mb-3"><div class="card-body">
  <form id="searchForm" class="row g-2">
    <input type="hidden" name="mode" id="mode" value="<?= $mode ?>">
    <div class="col-md-9">
      <input id="q" class="form-control form-control-lg" autofocus
        placeholder="<?= $mode==='ayat' ? 'Ketik kata Arab, mis. الرحمن, رب, مالك' : 'Ketik kata dalam Bahasa Indonesia, mis. sabar, takwa, syukur' ?>">
    </div>
    <div class="col-md-3"><button class="btn btn-success btn-lg w-100"><i class="bi bi-search"></i> Cari</button></div>
  </form>
  <div class="form-text mt-2">
    <?php if ($mode==='ayat'): ?>
      Pencarian kata Arab (Uthmani). Menampilkan hingga 50 ayat yang mengandung kata tersebut.
    <?php else: ?>
      Pencarian dalam terjemahan Bahasa Indonesia (Kemenag). Menampilkan hingga 50 hasil.
    <?php endif; ?>
  </div>
</div></div>

<div id="results"></div>

<script>
var SURAH = <?= json_encode(array_map(function($v){return $v[0];}, $ISLAMI_SURAH), JSON_UNESCAPED_UNICODE) ?>;
function esc(t){return (t||'').toString().replace(/[<>&]/g, c=>({ '<':'&lt;','>':'&gt;','&':'&amp;' }[c]));}
function rabbify(t){
  if(!t) return t;
  return t.toString()
    .replace(/\bTuhan\s+Yang\s+Maha\s+Merajai\b/gi, 'Al-Malik (Tuhan Yang Maha Merajai)')
    .replace(/\bTuhan\s+(?:semesta\s+alam|seluruh\s+alam|sekalian\s+alam)\b/gi, 'Rabb semesta alam')
    .replace(/\bTuhan(-?ku|-?mu|-?nya)?\b/g, function(m, suf){ return 'Rabb' + (suf||''); })
    .replace(/tiada\s+Rabb\s+selain/gi, 'tiada Ilah (sesembahan) selain')
    .replace(/tidak\s+ada\s+Rabb\s+selain/gi, 'tidak ada Ilah (sesembahan) selain');
}
function hl(text, q){
  if(!q) return esc(text);
  try {
    var re = new RegExp('('+q.replace(/[.*+?^${}()|[\]\\]/g,'\\$&')+')','ig');
    return esc(text).replace(re, '<mark>$1</mark>');
  } catch(e){ return esc(text); }
}

document.getElementById('searchForm').addEventListener('submit', async function(e){
  e.preventDefault();
  var q = document.getElementById('q').value.trim();
  var mode = document.getElementById('mode').value;
  var box = document.getElementById('results');
  if (!q) { box.innerHTML = ''; return; }
  box.innerHTML = '<div class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm"></div> Mencari…</div>';

  try {
    // quran.com search API: mendukung Arab & terjemah
    // Untuk terjemah ID, gunakan translations=33 (Indonesian — Kemenag)
    var url;
    if (mode === 'ayat') {
      url = 'https://api.quran.com/api/v4/search?q=' + encodeURIComponent(q) + '&size=50&language=ar';
    } else {
      url = 'https://api.quran.com/api/v4/search?q=' + encodeURIComponent(q) + '&size=50&language=id';
    }
    var r = await fetch(url);
    var j = await r.json();
    var hits = (j.search && j.search.results) || [];
    if (!hits.length) { box.innerHTML = '<div class="alert alert-warning">Tidak ada hasil untuk "<strong>'+esc(q)+'</strong>".</div>'; return; }

    var html = '<div class="mb-2 small text-muted">Ditemukan '+hits.length+' ayat (dari total '+(j.search.total_results||hits.length)+').</div>';
    html += '<div class="list-group">';
    for (var i=0;i<hits.length;i++){
      var h = hits[i];
      var key = (h.verse_key||'').split(':');
      var sn = parseInt(key[0],10), an = parseInt(key[1],10);
      var name = SURAH[sn] || ('Surah '+sn);
      var arab = h.text || '';
      var tr = '';
      if (h.translations && h.translations.length) tr = rabbify(h.translations[0].text || '');

      html += '<a href="/quran_surah.php?s='+sn+'#a'+an+'" class="list-group-item list-group-item-action">'+
        '<div class="d-flex justify-content-between"><strong>'+esc(name)+' : '+an+'</strong>'+
        '<span class="badge bg-success">QS '+sn+':'+an+'</span></div>'+
        '<div class="text-end fs-5" dir="rtl" style="font-family:\'Amiri\',serif;line-height:1.9">'+
          (mode==='ayat'? hl(arab, q) : esc(arab)) +'</div>'+
        (tr ? '<div class="small mt-1 text-muted">'+ (mode==='terjemah' ? hl(tr, q) : esc(tr)) +'</div>' : '') +
      '</a>';
    }
    html += '</div>';
    box.innerHTML = html;
  } catch(err){
    box.innerHTML = '<div class="alert alert-danger small">Gagal mencari: '+esc(err.message)+'</div>';
  }
});
</script>
<?php htmx_layout_end(); ?>
