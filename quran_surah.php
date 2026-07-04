<?php
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
require __DIR__.'/includes/islami_data.php';
require __DIR__.'/includes/islami_helpers.php';
require __DIR__.'/includes/asbab_nuzul.php';
require __DIR__.'/includes/surah_meta.php';
send_security_headers(); require_login();
$u = current_user();
$s = max(1, min(114, (int)($_GET['s'] ?? 1)));
$info = $ISLAMI_SURAH[$s];
$totalAyat = (int)$info[1];
$pageTitle = 'QS '.$info[0];

if ($_SERVER['REQUEST_METHOD']==='POST' && $u) {
    csrf_check();
    $a = $_POST['_action'] ?? '';
    if ($a === 'bookmark') {
        $ayat = max(1, (int)$_POST['ayat']);
        try {
            db_exec("INSERT INTO quran_bookmarks(user_id,surah,ayat,catatan) VALUES($1,$2,$3,$4)
                     ON CONFLICT (user_id,surah,ayat) DO UPDATE SET catatan=EXCLUDED.catatan",
                [(int)$u['id'], $s, $ayat, substr($_POST['catatan'] ?? '', 0, 200)]);
        } catch (Throwable $e) {}
    } elseif ($a === 'unbookmark') {
        db_exec("DELETE FROM quran_bookmarks WHERE user_id=$1 AND surah=$2 AND ayat=$3",
            [(int)$u['id'], $s, (int)$_POST['ayat']]);
    } elseif ($a === 'last_read') {
        $ayat = max(1, (int)$_POST['ayat']);
        db_exec("INSERT INTO quran_last_read(user_id,surah,ayat) VALUES($1,$2,$3)
                 ON CONFLICT (user_id) DO UPDATE SET surah=$2, ayat=$3, updated_at=now()",
            [(int)$u['id'], $s, $ayat]);
        islami_touch_streak((int)$u['id'], 'quran_done');
    }
    header('Location: /quran_surah.php?s='.$s.'#a'.((int)($_POST['ayat'] ?? 1))); exit;
}

$bm = db_all("SELECT ayat, catatan FROM quran_bookmarks WHERE user_id=$1 AND surah=$2", [(int)$u['id'], $s]);
$bmMap = [];
foreach ($bm as $b) $bmMap[(int)$b['ayat']] = $b['catatan'];

$asbabIdx = asbab_indices_for_surah($s);
$asbabMap = [];
foreach ($asbabIdx as $ai) $asbabMap[$ai] = asbab_for($s, $ai);

// Pagination & jump-to ayat (read from query, default page 1, 10 per page)
$perPage = 10;
$totalPages = max(1, (int)ceil($totalAyat / $perPage));
$page = max(1, min($totalPages, (int)($_GET['p'] ?? 1)));
$jumpAyat = isset($_GET['a']) ? max(1, min($totalAyat, (int)$_GET['a'])) : 0;
if ($jumpAyat > 0) {
    $page = (int)ceil($jumpAyat / $perPage);
}

include __DIR__.'/includes/header.php';
?>
<nav aria-label="breadcrumb" class="mb-2"><ol class="breadcrumb small mb-0"><li class="breadcrumb-item"><a href="/index.php">Beranda</a></li><li class="breadcrumb-item"><a href="/islami.php">Islami</a></li><li class="breadcrumb-item active">QS </li></ol></nav>

<div class="mb-2">
  <a href="/quran.php" class="btn btn-sm btn-success"><i class="bi bi-arrow-left"></i> Kembali ke Daftar Surat</a>
</div>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h4 class="m-0"><i class="bi bi-book text-success"></i> QS <?= htmlspecialchars($info[0]) ?>
    <small class="text-muted">(<?= $totalAyat ?> ayat)</small>
    <?= surah_tempat_badge($s) ?>
  </h4>
  <div>
    <?php if ($s>1): ?><a href="/quran_surah.php?s=<?= $s-1 ?>" class="btn btn-sm btn-outline-secondary" title="Surat sebelumnya"><i class="bi bi-chevron-left"></i></a><?php endif; ?>
    <a href="/quran.php" class="btn btn-sm btn-outline-success"><i class="bi bi-list-ul"></i> Daftar Surat</a>
    <?php if ($s<114): ?><a href="/quran_surah.php?s=<?= $s+1 ?>" class="btn btn-sm btn-outline-secondary" title="Surat berikutnya"><i class="bi bi-chevron-right"></i></a><?php endif; ?>
  </div>
</div>

<!-- Dropdown pemilih ayat -->
<div class="card shadow-sm mb-3"><div class="card-body py-2">
  <form method="get" action="/quran_surah.php" class="row g-2 align-items-center">
    <input type="hidden" name="s" value="<?= $s ?>">
    <div class="col-auto small text-muted"><i class="bi bi-list-ol"></i> Lompat ke ayat:</div>
    <div class="col-auto">
      <select name="a" class="form-select form-select-sm" onchange="this.form.submit()">
        <?php for ($i=1; $i<=$totalAyat; $i++): ?>
          <option value="<?= $i ?>" <?= $jumpAyat===$i?'selected':'' ?>>Ayat <?= $i ?></option>
        <?php endfor; ?>
      </select>
    </div>
    <div class="col-auto small text-muted ms-2">Halaman:</div>
    <div class="col-auto">
      <select name="p" class="form-select form-select-sm" onchange="this.form.submit()">
        <?php for ($pp=1; $pp<=$totalPages; $pp++):
              $from = ($pp-1)*$perPage + 1; $to = min($totalAyat, $pp*$perPage); ?>
          <option value="<?= $pp ?>" <?= $page===$pp?'selected':'' ?>>Hal <?= $pp ?> (ayat <?= $from ?>–<?= $to ?>)</option>
        <?php endfor; ?>
      </select>
    </div>
    <div class="col-auto"><button class="btn btn-sm btn-success" type="submit"><i class="bi bi-arrow-right-circle"></i> Buka</button></div>
  </form>
</div></div>

<div class="alert alert-info py-2 small mb-3">
  <i class="bi bi-info-circle"></i> Klik kata Arab untuk lihat <strong>tafsir per-kata (Bahasa Indonesia)</strong>.
  Tombol <i class="bi bi-lightbulb"></i> menampilkan <strong>Tafsir Ibnu Katsir (Bahasa Indonesia)</strong>.
  Tombol <i class="bi bi-journal-bookmark"></i> menampilkan <strong>Makna Ayat (AI)</strong>.
  Tombol <i class="bi bi-stars"></i> menampilkan <strong>Tafsir Kontemporer (AI)</strong>.
  Ayat dengan ikon <i class="bi bi-journal-text text-warning"></i> memiliki riwayat <strong>Asbabun Nuzul</strong>.
</div>

<?php if ($asbabIdx): ?>
<div class="card border-warning shadow-sm mb-3">
  <div class="card-header bg-warning-subtle text-warning-emphasis">
    <i class="bi bi-journal-text"></i> Ayat di surah ini yang memiliki Asbabun Nuzul (<?= count($asbabIdx) ?>):
  </div>
  <div class="card-body py-2">
    <?php foreach ($asbabIdx as $ai):
        $aiPage = (int)ceil($ai/$perPage); ?>
      <a href="/quran_surah.php?s=<?= $s ?>&a=<?= $ai ?>#a<?= $ai ?>" class="badge bg-warning text-dark text-decoration-none me-1">Ayat <?= $ai ?></a>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<?php
// Bangun bar pagination (atas & bawah)
function render_pager($s, $page, $totalPages){
  if ($totalPages <= 1) return '';
  $out = '<nav class="my-2"><ul class="pagination pagination-sm justify-content-center mb-0 flex-wrap">';
  $prev = max(1, $page-1); $next = min($totalPages, $page+1);
  $out .= '<li class="page-item '.($page<=1?'disabled':'').'"><a class="page-link" href="?s='.$s.'&p='.$prev.'">«</a></li>';
  // window 5 halaman
  $start = max(1, $page-2); $end = min($totalPages, $start+4); $start = max(1, $end-4);
  if ($start > 1) $out .= '<li class="page-item"><a class="page-link" href="?s='.$s.'&p=1">1</a></li><li class="page-item disabled"><span class="page-link">…</span></li>';
  for ($i=$start; $i<=$end; $i++){
    $out .= '<li class="page-item '.($i===$page?'active':'').'"><a class="page-link" href="?s='.$s.'&p='.$i.'">'.$i.'</a></li>';
  }
  if ($end < $totalPages) $out .= '<li class="page-item disabled"><span class="page-link">…</span></li><li class="page-item"><a class="page-link" href="?s='.$s.'&p='.$totalPages.'">'.$totalPages.'</a></li>';
  $out .= '<li class="page-item '.($page>=$totalPages?'disabled':'').'"><a class="page-link" href="?s='.$s.'&p='.$next.'">»</a></li>';
  $out .= '</ul></nav>';
  return $out;
}
$ayatFrom = ($page-1)*$perPage + 1;
$ayatTo   = min($totalAyat, $page*$perPage);
?>
<?= render_pager($s, $page, $totalPages) ?>
<div class="text-center small text-muted mb-2">Menampilkan ayat <strong><?= $ayatFrom ?>–<?= $ayatTo ?></strong> dari <?= $totalAyat ?></div>

<div id="ayatList" class="card shadow-sm"><div class="card-body">
  <div class="text-center text-muted py-4">
    <div class="spinner-border spinner-border-sm"></div>
    Memuat Al-Qur'an &amp; Tafsir Ibnu Katsir (Bahasa Indonesia)…
  </div>
</div></div>

<?= render_pager($s, $page, $totalPages) ?>

<style>
.word-arab{display:inline-block;margin:0 .25rem .5rem;padding:.25rem .4rem;border-radius:6px;cursor:pointer;transition:.15s;text-align:center;}
.word-arab:hover{background:rgba(34,197,94,.12);}
.word-arab .ar{font-family:'Amiri','Scheherazade New',serif;font-size:1.9rem;line-height:1.4;display:block;}
.word-arab .tr{font-size:.72rem;color:var(--bs-secondary-color,#64748b);display:block;text-align:center;max-width:140px;word-wrap:break-word;white-space:normal;}
.ayat-block{padding:1rem 0;border-bottom:1px solid var(--bs-border-color,#e5e7eb);}
.tafsir-box,.asbab-box,.makna-box,.kontemporer-box{background:var(--bs-tertiary-bg,#f8fafc);border-left:4px solid #16a34a;padding:.85rem 1rem;border-radius:6px;margin-top:.5rem;display:none;}
.asbab-box{border-left-color:#f59e0b;}
.makna-box{border-left-color:#0ea5e9;}
.kontemporer-box{border-left-color:#8b5cf6;background:linear-gradient(180deg,rgba(139,92,246,.07),transparent);}
.ayat-block.show-tafsir .tafsir-box,
.ayat-block.show-asbab .asbab-box,
.ayat-block.show-makna .makna-box,
.ayat-block.show-kontemporer .kontemporer-box{display:block;}
.tafsir-tab{font-weight:600;color:#16a34a;margin-top:.25rem;font-size:.95rem;}
.tafsir-body{line-height:1.75;font-size:.95rem;text-align:justify;color:var(--bs-body-color);}
.tafsir-body p{margin:0 0 .65rem 0;}
.kontemporer-body h3{font-size:1rem;font-weight:700;color:#6d28d9;margin:.75rem 0 .25rem;}
.kontemporer-body{line-height:1.7;font-size:.93rem;text-align:justify;}
</style>

<script>
(async function(){
  var s = <?= $s ?>;
  var pageFrom = <?= $ayatFrom ?>;
  var pageTo   = <?= $ayatTo ?>;
  var bmMap = <?= json_encode($bmMap) ?>;
  var asbabMap = <?= json_encode($asbabMap, JSON_UNESCAPED_UNICODE) ?>;
  var csrf = '<?= csrf_token() ?>';
  var container = document.getElementById('ayatList').querySelector('.card-body');

  function esc(t){return (t||'').toString().replace(/[<>&]/g, c=>({ '<':'&lt;','>':'&gt;','&':'&amp;' }[c]));}
  function stripTags(t){return (t||'').toString().replace(/<[^>]+>/g,' ').replace(/\s+/g,' ').trim();}
  function rabbify(t){
    if(!t) return t;
    return t.toString()
      .replace(/\bTuhan\s+Yang\s+Maha\s+Merajai\b/gi, 'Al-Malik (Tuhan Yang Maha Merajai)')
      .replace(/\bTuhan\s+(?:semesta\s+alam|seluruh\s+alam|sekalian\s+alam)\b/gi, 'Rabb semesta alam')
      .replace(/\bTuhan(-?ku|-?mu|-?nya)?\b/g, function(m, suf){ return 'Rabb' + (suf||''); })
      .replace(/tiada\s+Rabb\s+selain/gi, 'tiada Ilah (sesembahan) selain')
      .replace(/tidak\s+ada\s+Rabb\s+selain/gi, 'tidak ada Ilah (sesembahan) selain');
  }
  // Format tafsir agar rapi: pisahkan paragraf pada baris kosong / titik panjang
  function formatTafsir(text){
    text = (text||'').toString().trim();
    if (!text) return '';
    // normalisasi spasi
    text = text.replace(/\r\n/g,'\n').replace(/[ \t]+\n/g,'\n');
    // pecah paragraf
    var paras = text.split(/\n{2,}/);
    if (paras.length < 2) {
      // fallback: pecah per kalimat jika tidak ada break paragraf
      paras = text.split(/(?<=[\.\!\?])\s+(?=[A-Z“"'(])/);
      // gabung tiap 3 kalimat jadi 1 paragraf
      var grouped = [], buf = [];
      paras.forEach(function(p,i){ buf.push(p); if (buf.length>=3){ grouped.push(buf.join(' ')); buf=[]; } });
      if (buf.length) grouped.push(buf.join(' '));
      paras = grouped;
    }
    return paras.map(function(p){ return '<p>'+esc(rabbify(p.trim()))+'</p>'; }).join('');
  }

  async function fetchJSON(url){
    try {
      var r = await fetch(url);
      if(!r.ok) return null;
      var txt = await r.text();
      if(!txt) return null;
      try { return JSON.parse(txt); } catch(e){ return null; }
    } catch(e){ return null; }
  }

  try {
    // === Sumber data ===
    // 1) equran.id v2: ayat Arab + terjemah Indonesia (TIDAK dipakai untuk tafsir)
    // 2) quran.com v4: per-kata + terjemah per-kata Bahasa Indonesia (?language=id)
    // 3) spa5k/tafsir_api (CDN jsDelivr): Tafsir Ibnu Katsir Bahasa Indonesia
    var [rSurah, rWords] = await Promise.all([
      fetchJSON('https://equran.id/api/v2/surat/' + s),
      fetchJSON('https://api.quran.com/api/v4/verses/by_chapter/' + s +
            '?words=true&word_fields=text_uthmani,transliteration&language=id&per_page=300')
    ]);

    var ayatList = (rSurah && rSurah.data && rSurah.data.ayat) || [];
    var verses   = (rWords && rWords.verses) || [];
    var wordMap = {};
    verses.forEach(function(v){
      var num = parseInt((v.verse_key||'').split(':')[1] || '0', 10);
      wordMap[num] = (v.words || []).filter(function(w){ return w.char_type_name === 'word'; });
    });

    if (!ayatList.length) throw new Error('Data ayat kosong');

    // Filter pagination: hanya tampilkan ayat di range halaman aktif
    var ayatPage = ayatList.filter(function(a){ return a.nomorAyat >= pageFrom && a.nomorAyat <= pageTo; });

    var html = '';
    for (var i=0; i<ayatPage.length; i++){
      var ay = ayatPage[i];
      var no = ay.nomorAyat;
      var isBm = Object.prototype.hasOwnProperty.call(bmMap, no);
      var hasAsbab = Object.prototype.hasOwnProperty.call(asbabMap, no);
      var words = wordMap[no] || [];

      var perKata = '';
      if (words.length) {
        perKata = '<div class="text-end" dir="rtl">';
        for (var w=0; w<words.length; w++){
          var ww = words[w];
          var ar = ww.text_uthmani || ww.text || '';
          // Prioritas: translation Bahasa Indonesia, lalu transliteration
          var trObj = ww.translation && ww.translation.text ? ww.translation.text : '';
          var trLit = ww.transliteration && ww.transliteration.text ? ww.transliteration.text : '';
          var tr = trObj || trLit;
          tr = rabbify(tr);
          perKata += '<span class="word-arab" title="'+esc(tr)+'"><span class="ar">'+esc(ar)+'</span><span class="tr">'+esc(tr)+'</span></span>';
        }
        perKata += '</div>';
      } else {
        perKata = '<div class="text-end fs-3" style="font-family:\'Amiri\',serif;line-height:2">'+esc(ay.teksArab)+'</div>';
      }

      html += '<div class="ayat-block" id="a'+no+'" data-no="'+no+'">' +
        '<div class="d-flex justify-content-between align-items-center mb-2">' +
          '<span class="badge bg-success">Ayat '+no+'</span>' +
          '<div class="btn-group btn-group-sm">' +
            '<button type="button" class="btn btn-outline-info btn-sm js-toggle-makna" data-no="'+no+'" title="Makna Ayat (AI)"><i class="bi bi-journal-bookmark"></i> Makna</button>' +
            '<button type="button" class="btn btn-outline-success btn-sm js-toggle-tafsir" data-no="'+no+'" title="Tafsir Ibnu Katsir (Bahasa Indonesia)"><i class="bi bi-lightbulb"></i> Tafsir</button>' +
            '<button type="button" class="btn btn-sm js-toggle-kontemporer" data-no="'+no+'" title="Tafsir Kontemporer (AI)" style="background:#8b5cf6;border-color:#8b5cf6;color:#fff"><i class="bi bi-stars"></i> Tafsir Kontemporer</button>' +
            (hasAsbab ? '<button type="button" class="btn btn-warning btn-sm js-toggle-asbab" data-no="'+no+'" title="Asbabun Nuzul"><i class="bi bi-journal-text"></i> Asbab</button>' : '') +
            '<form method="post" style="display:inline"><input type="hidden" name="csrf" value="'+csrf+'"><input type="hidden" name="_action" value="last_read"><input type="hidden" name="ayat" value="'+no+'"><button class="btn btn-outline-primary btn-sm" title="Tandai last read"><i class="bi bi-bookmark-check"></i></button></form>' +
            '<form method="post" style="display:inline"><input type="hidden" name="csrf" value="'+csrf+'"><input type="hidden" name="_action" value="'+(isBm?'unbookmark':'bookmark')+'"><input type="hidden" name="ayat" value="'+no+'"><button class="btn btn-'+(isBm?'warning':'outline-warning')+' btn-sm" title="Bookmark"><i class="bi bi-star'+(isBm?'-fill':'')+'"></i></button></form>' +
          '</div>' +
        '</div>' +
        perKata +
        '<div class="small mt-2"><strong>Terjemah:</strong> '+esc(rabbify(ay.teksIndonesia))+'</div>' +
        '<div class="makna-box"><strong><i class="bi bi-journal-bookmark text-info"></i> Makna Ayat <span class="badge bg-info-subtle text-info-emphasis ms-1">AI</span>:</strong><div class="mt-1 js-makna">Klik tombol Makna untuk memuat.</div></div>' +
        '<div class="tafsir-box"><div class="js-tafsir">Klik tombol Tafsir untuk memuat.</div></div>' +
        '<div class="kontemporer-box"><strong style="color:#6d28d9"><i class="bi bi-stars"></i> Tafsir Kontemporer <span class="badge ms-1" style="background:#8b5cf6;color:#fff">AI</span>:</strong><div class="mt-1 js-kontemporer kontemporer-body">Klik tombol Tafsir Kontemporer untuk memuat.</div></div>' +
        (hasAsbab ? '<div class="asbab-box"><strong><i class="bi bi-journal-text text-warning"></i> Asbabun Nuzul:</strong><p class="mb-0 mt-1" style="white-space:pre-wrap">'+esc(asbabMap[no])+'</p></div>' : '') +
        (isBm && bmMap[no] ? '<div class="small fst-italic mt-1">📝 '+esc(bmMap[no])+'</div>' : '') +
        '</div>';
    }
    container.innerHTML = html;

    // Revisi R8 Juli 2026 — Tafsir Ibnu Katsir (Bahasa Indonesia) via API publik:
    //   1) spa5k/tafsir_api (CDN jsDelivr) — endpoint utama & alternatif.
    //   2) Fallback: Quran.com API v4 (proxy id 169 = Ibnu Katsir Indonesia).
    // Semua panggilan client-side dan hanya READ. Aman untuk running lokal.
    var tafsirCache = {};
    async function loadTafsir(no){
      if (tafsirCache[no]) return tafsirCache[no];
      var text = '';
      var source = '';

      // Sumber #1: spa5k/tafsir_api via jsDelivr CDN
      try {
        var base = 'https://cdn.jsdelivr.net/gh/spa5k/tafsir_api@main/tafsir';
        var ik = await fetchJSON(base + '/id-tafisr-ibn-kathir/' + s + '/' + no + '.json');
        if (!ik || !ik.text) ik = await fetchJSON(base + '/id-tafsir-ibn-kathir/' + s + '/' + no + '.json');
        if (ik && ik.text) { text = ik.text; source = 'spa5k/tafsir_api'; }
      } catch (e) {}

      // Sumber #2 (fallback): api.quran.com v4 — tafsir id 169 (Ibnu Katsir - Indonesia)
      if (!text) {
        try {
          var qc = await fetchJSON('https://api.quran.com/api/v4/tafsirs/169/by_ayah/' + s + ':' + no);
          if (qc && qc.tafsir && qc.tafsir.text) {
            // Strip HTML sederhana agar rapi
            var tmp = document.createElement('div');
            tmp.innerHTML = qc.tafsir.text;
            text = (tmp.textContent || tmp.innerText || '').trim();
            source = 'quran.com';
          }
        } catch (e) {}
      }

      tafsirCache[no] = { ibnu_id: text, source: source };
      return tafsirCache[no];
    }

    function renderTafsir(d){
      var html = '<div class="tafsir-tab"><i class="bi bi-book"></i> Tafsir Ibnu Katsir (Bahasa Indonesia)</div>';
      if (d.ibnu_id) {
        html += '<div class="tafsir-body mt-2">'+formatTafsir(d.ibnu_id)+'</div>';
        if (d.source) {
          html += '<div class="small text-muted mt-1" style="font-size:.75rem;opacity:.7">Sumber: '+d.source+'</div>';
        }
      } else {
        html += '<div class="small text-muted mt-1">Tafsir Ibnu Katsir belum tersedia untuk ayat ini.</div>';
      }
      return html;
    }

    // Revisi Juli 2026 — Makna & Tafsir Kontemporer di-generate oleh AI (api_ai.php).
    var suratNama = <?= json_encode($info[0], JSON_UNESCAPED_UNICODE) ?>;
    var aiCache = { makna:{}, kontemporer:{} };
    async function aiGenerate(task, no, ay){
      var fd = new FormData();
      fd.append('csrf', csrf);
      fd.append('task', task);
      fd.append('surah', suratNama);
      fd.append('ayat', String(no));
      if (ay && ay.teksArab) fd.append('arab', ay.teksArab);
      if (ay && ay.teksIndonesia) fd.append('terjemah', ay.teksIndonesia);
      try {
        var r = await fetch('/api_ai.php', {method:'POST', body: fd, credentials:'same-origin'});
        var txt = await r.text();
        try { return JSON.parse(txt); } catch(e){ return {ok:false, err:'response tidak valid'}; }
      } catch(e){ return {ok:false, err:e.message}; }
    }
    // Render sederhana untuk markdown ringan (### heading + paragraf)
    function mdLite(text){
      var t = (text||'').toString().trim();
      if (!t) return '';
      var lines = t.split(/\r?\n/); var out = ''; var para = [];
      function flush(){ if (para.length){ out += '<p>'+esc(para.join(' '))+'</p>'; para=[]; } }
      for (var i=0;i<lines.length;i++){
        var ln = lines[i].trim();
        if (!ln){ flush(); continue; }
        var mh = ln.match(/^#{1,6}\s+(.+)$/);
        if (mh){ flush(); out += '<h3>'+esc(mh[1])+'</h3>'; continue; }
        para.push(ln);
      }
      flush();
      return out;
    }

    container.querySelectorAll('.js-toggle-tafsir').forEach(function(b){
      b.addEventListener('click', async function(){
        var block = b.closest('.ayat-block');
        block.classList.toggle('show-tafsir');
        if (block.classList.contains('show-tafsir')) {
          var no = parseInt(block.dataset.no, 10);
          var tafs = block.querySelector('.js-tafsir');
          tafs.innerHTML = '<span class="text-muted"><div class="spinner-border spinner-border-sm"></div> Memuat tafsir Ibnu Katsir…</span>';
          var d = await loadTafsir(no);
          tafs.innerHTML = renderTafsir(d);
        }
      });
    });
    container.querySelectorAll('.js-toggle-makna').forEach(function(b){
      b.addEventListener('click', async function(){
        var block = b.closest('.ayat-block');
        block.classList.toggle('show-makna');
        if (block.classList.contains('show-makna')) {
          var no = parseInt(block.dataset.no, 10);
          var ay = ayatList.find(function(x){ return x.nomorAyat === no; });
          var box = block.querySelector('.js-makna');
          if (aiCache.makna[no]) { box.innerHTML = aiCache.makna[no]; return; }
          box.innerHTML = '<span class="text-muted"><div class="spinner-border spinner-border-sm"></div> AI sedang menyusun makna ayat…</span>';
          var r = await aiGenerate('makna_ayat', no, ay);
          if (r && r.ok) {
            var html = '<div style="white-space:pre-wrap;line-height:1.7">'+esc(rabbify(r.text||'')).replace(/\n/g,'<br>')+'</div>';
            aiCache.makna[no] = html;
            box.innerHTML = html;
          } else {
            box.innerHTML = '<span class="text-danger small">Gagal memuat makna: '+esc((r&&r.err)||'error')+'</span>';
          }
        }
      });
    });
    container.querySelectorAll('.js-toggle-kontemporer').forEach(function(b){
      b.addEventListener('click', async function(){
        var block = b.closest('.ayat-block');
        block.classList.toggle('show-kontemporer');
        if (block.classList.contains('show-kontemporer')) {
          var no = parseInt(block.dataset.no, 10);
          var ay = ayatList.find(function(x){ return x.nomorAyat === no; });
          var box = block.querySelector('.js-kontemporer');
          if (aiCache.kontemporer[no]) { box.innerHTML = aiCache.kontemporer[no]; return; }
          box.innerHTML = '<span class="text-muted"><div class="spinner-border spinner-border-sm"></div> AI sedang menyusun tafsir kontemporer…</span>';
          var r = await aiGenerate('tafsir_kontemporer', no, ay);
          if (r && r.ok) {
            var html = mdLite(rabbify(r.text||''));
            aiCache.kontemporer[no] = html;
            box.innerHTML = html;
          } else {
            box.innerHTML = '<span class="text-danger small">Gagal memuat tafsir kontemporer: '+esc((r&&r.err)||'error')+'</span>';
          }
        }
      });
    });
    container.querySelectorAll('.js-toggle-asbab').forEach(function(b){
      b.addEventListener('click', function(){ b.closest('.ayat-block').classList.toggle('show-asbab'); });
    });
    if (location.hash) {
      var el = document.querySelector(location.hash);
      if (el) el.scrollIntoView({behavior:'smooth', block:'start'});
    }
  } catch (e) {
    container.innerHTML = '<div class="alert alert-warning small">Gagal memuat ayat. Pastikan koneksi internet aktif. ('+esc(e.message)+')</div>';
  }
})();
</script>
<?php include __DIR__.'/includes/footer.php'; ?>
