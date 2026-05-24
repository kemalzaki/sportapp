<?php
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
require __DIR__.'/includes/islami_data.php';
require __DIR__.'/includes/islami_helpers.php';
require __DIR__.'/includes/asbab_nuzul.php';
send_security_headers(); require_login();
$u = current_user();
$s = max(1, min(114, (int)($_GET['s'] ?? 1)));
$info = $ISLAMI_SURAH[$s];
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

include __DIR__.'/includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="m-0"><i class="bi bi-book text-success"></i> QS <?= htmlspecialchars($info[0]) ?>
    <small class="text-muted">(<?= $info[1] ?> ayat)</small>
  </h4>
  <div>
    <?php if ($s>1): ?><a href="/quran_surah.php?s=<?= $s-1 ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-chevron-left"></i></a><?php endif; ?>
    <a href="/quran.php" class="btn btn-sm btn-outline-success">Daftar</a>
    <?php if ($s<114): ?><a href="/quran_surah.php?s=<?= $s+1 ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-chevron-right"></i></a><?php endif; ?>
  </div>
</div>

<div class="alert alert-info py-2 small mb-3">
  <i class="bi bi-info-circle"></i> Klik kata Arab untuk lihat <strong>tafsir per-kata (Bahasa Indonesia)</strong>.
  Tombol <i class="bi bi-lightbulb"></i> menampilkan tafsir <strong>Ibnu Katsir</strong> &amp; <strong>Fi Zhilalil Qur'an</strong>.
  Tombol <i class="bi bi-journal-bookmark"></i> menampilkan <strong>Makna Ayat</strong>.
  Ayat dengan ikon <i class="bi bi-journal-text text-warning"></i> memiliki riwayat <strong>Asbabun Nuzul</strong>.
</div>

<?php if ($asbabIdx): ?>
<div class="card border-warning shadow-sm mb-3">
  <div class="card-header bg-warning-subtle text-warning-emphasis">
    <i class="bi bi-journal-text"></i> Ayat di surah ini yang memiliki Asbabun Nuzul (<?= count($asbabIdx) ?>):
  </div>
  <div class="card-body py-2">
    <?php foreach ($asbabIdx as $ai): ?>
      <a href="#a<?= $ai ?>" class="badge bg-warning text-dark text-decoration-none me-1">Ayat <?= $ai ?></a>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<div id="ayatList" class="card shadow-sm"><div class="card-body">
  <div class="text-center text-muted py-4">
    <div class="spinner-border spinner-border-sm"></div>
    Memuat Al-Qur'an, tafsir Ibnu Katsir &amp; Fi Zhilalil Qur'an…
  </div>
</div></div>

<style>
.word-arab{display:inline-block;margin:0 .25rem .5rem;padding:.25rem .4rem;border-radius:6px;cursor:pointer;transition:.15s;}
.word-arab:hover{background:rgba(34,197,94,.12);}
.word-arab .ar{font-family:'Amiri','Scheherazade New',serif;font-size:1.9rem;line-height:1.4;display:block;}
.word-arab .tr{font-size:.7rem;color:var(--bs-secondary-color,#64748b);display:block;text-align:center;}
.ayat-block{padding:1rem 0;border-bottom:1px solid var(--bs-border-color,#e5e7eb);}
.tafsir-box,.asbab-box,.makna-box{background:var(--bs-tertiary-bg,#f8fafc);border-left:4px solid #16a34a;padding:.75rem 1rem;border-radius:6px;margin-top:.5rem;display:none;}
.asbab-box{border-left-color:#f59e0b;}
.makna-box{border-left-color:#0ea5e9;}
.ayat-block.show-tafsir .tafsir-box,
.ayat-block.show-asbab .asbab-box,
.ayat-block.show-makna .makna-box{display:block;}
.tafsir-tab{font-weight:600;color:#16a34a;margin-top:.5rem;}
</style>

<script>
(async function(){
  var s = <?= $s ?>;
  var bmMap = <?= json_encode($bmMap) ?>;
  var asbabMap = <?= json_encode($asbabMap, JSON_UNESCAPED_UNICODE) ?>;
  var csrf = '<?= csrf_token() ?>';
  var container = document.getElementById('ayatList').querySelector('.card-body');

  function esc(t){return (t||'').toString().replace(/[<>&]/g, c=>({ '<':'&lt;','>':'&gt;','&':'&amp;' }[c]));}
  function stripTags(t){return (t||'').toString().replace(/<[^>]+>/g,' ').replace(/\s+/g,' ').trim();}

  async function fetchJSON(url){
    try { var r = await fetch(url); if(!r.ok) return null; return await r.json(); } catch(e){ return null; }
  }

  try {
    // === Sumber data ===
    // 1) equran.id v2: ayat Arab + terjemah Indonesia (tanpa audio)
    // 2) quran.com v4: per-kata + terjemah per-kata Bahasa Indonesia
    // 3) spa5k/tafsir_api (CDN jsDelivr): Tafsir Ibnu Katsir & Fi Zhilalil Qur'an
    var [rSurah, rWords] = await Promise.all([
      fetchJSON('https://equran.id/api/v2/surat/' + s),
      fetchJSON('https://api.quran.com/api/v4/verses/by_chapter/' + s +
            '?words=true&word_fields=text_uthmani,transliteration&word_translation_language=id&per_page=300')
    ]);

    var ayatList = (rSurah && rSurah.data && rSurah.data.ayat) || [];
    var verses   = (rWords && rWords.verses) || [];
    var wordMap = {};
    verses.forEach(function(v){
      var num = parseInt((v.verse_key||'').split(':')[1] || '0', 10);
      wordMap[num] = (v.words || []).filter(function(w){ return w.char_type_name === 'word'; });
    });

    if (!ayatList.length) throw new Error('Data ayat kosong');

    // Render dulu kerangka, tafsir di-lazy per klik
    var html = '';
    for (var i=0; i<ayatList.length; i++){
      var ay = ayatList[i];
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
          var tr = (ww.translation && ww.translation.text) || (ww.transliteration && ww.transliteration.text) || '';
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
            '<button type="button" class="btn btn-outline-info btn-sm js-toggle-makna" data-no="'+no+'" title="Makna ayat"><i class="bi bi-journal-bookmark"></i> Makna</button>' +
            '<button type="button" class="btn btn-outline-success btn-sm js-toggle-tafsir" data-no="'+no+'" title="Tafsir Ibnu Katsir & Fi Zhilal"><i class="bi bi-lightbulb"></i> Tafsir</button>' +
            (hasAsbab ? '<button type="button" class="btn btn-warning btn-sm js-toggle-asbab" data-no="'+no+'" title="Asbabun Nuzul"><i class="bi bi-journal-text"></i> Asbab</button>' : '') +
            '<form method="post" style="display:inline"><input type="hidden" name="csrf" value="'+csrf+'"><input type="hidden" name="_action" value="last_read"><input type="hidden" name="ayat" value="'+no+'"><button class="btn btn-outline-primary btn-sm" title="Tandai last read"><i class="bi bi-bookmark-check"></i></button></form>' +
            '<form method="post" style="display:inline"><input type="hidden" name="csrf" value="'+csrf+'"><input type="hidden" name="_action" value="'+(isBm?'unbookmark':'bookmark')+'"><input type="hidden" name="ayat" value="'+no+'"><button class="btn btn-'+(isBm?'warning':'outline-warning')+' btn-sm" title="Bookmark"><i class="bi bi-star'+(isBm?'-fill':'')+'"></i></button></form>' +
          '</div>' +
        '</div>' +
        perKata +
        '<div class="small mt-2"><strong>Terjemah:</strong> '+esc(ay.teksIndonesia)+'</div>' +
        '<div class="makna-box"><strong><i class="bi bi-journal-bookmark text-info"></i> Makna Ayat:</strong><div class="mt-1 js-makna">Memuat…</div></div>' +
        '<div class="tafsir-box"><div class="js-tafsir">Klik tombol Tafsir untuk memuat.</div></div>' +
        (hasAsbab ? '<div class="asbab-box"><strong><i class="bi bi-journal-text text-warning"></i> Asbabun Nuzul:</strong><p class="mb-0 mt-1" style="white-space:pre-wrap">'+esc(asbabMap[no])+'</p></div>' : '') +
        (isBm && bmMap[no] ? '<div class="small fst-italic mt-1">📝 '+esc(bmMap[no])+'</div>' : '') +
        '</div>';
    }
    container.innerHTML = html;

    var tafsirCache = {};
    async function loadTafsir(no){
      if (tafsirCache[no]) return tafsirCache[no];
      // spa5k tafsir_api: id-tafisr-ibn-kathir (terjemahan ID) & ar-tafsir-fi-zilal-quran
      // (Endpoint Indonesian Ibnu Katsir: id-tafisr-ibn-kathir; Fi Zilal: ar-tafsir-fi-zilal-quran)
      var base = 'https://cdn.jsdelivr.net/gh/spa5k/tafsir_api@main/tafsir';
      var [ik, fz, ikEn] = await Promise.all([
        fetchJSON(base + '/id-tafisr-ibn-kathir/' + s + '/' + no + '.json'),
        fetchJSON(base + '/ar-tafsir-fi-zilal-quran/' + s + '/' + no + '.json'),
        fetchJSON(base + '/en-tafisr-ibn-kathir/' + s + '/' + no + '.json'),
      ]);
      // Kemenag (id) sebagai fallback makna
      var kemen = await fetchJSON('https://equran.id/api/v2/tafsir/' + s);
      var kemenText = '';
      if (kemen && kemen.data && kemen.data.tafsir) {
        var t = kemen.data.tafsir.find(function(x){ return x.ayat === no; });
        if (t) kemenText = t.teks;
      }
      tafsirCache[no] = {
        ibnu_id: (ik && ik.text) || '',
        ibnu_en: (ikEn && ikEn.text) || '',
        fizilal: (fz && fz.text) || '',
        kemenag: kemenText
      };
      return tafsirCache[no];
    }

    function renderTafsir(d){
      var html = '';
      if (d.ibnu_id) {
        html += '<div class="tafsir-tab"><i class="bi bi-book"></i> Tafsir Ibnu Katsir (Bahasa Indonesia)</div>'+
                '<div class="small mt-1" style="white-space:pre-wrap">'+esc(stripTags(d.ibnu_id))+'</div>';
      } else if (d.ibnu_en) {
        html += '<div class="tafsir-tab"><i class="bi bi-book"></i> Tafsir Ibnu Katsir (English — terjemahan ID belum tersedia untuk ayat ini)</div>'+
                '<div class="small mt-1" style="white-space:pre-wrap">'+esc(stripTags(d.ibnu_en))+'</div>';
      } else {
        html += '<div class="tafsir-tab"><i class="bi bi-book"></i> Tafsir Ibnu Katsir</div>'+
                '<div class="small text-muted mt-1">Tidak tersedia untuk ayat ini.</div>';
      }
      html += '<hr class="my-2">';
      if (d.fizilal) {
        html += '<div class="tafsir-tab" style="color:#0ea5e9"><i class="bi bi-book-half"></i> Fi Zhilalil Qur\'an (Sayyid Quthb, teks Arab asli)</div>'+
                '<div class="small mt-1 text-end" dir="rtl" style="font-family:\'Amiri\',serif;line-height:1.9">'+esc(d.fizilal)+'</div>';
      } else {
        html += '<div class="tafsir-tab" style="color:#0ea5e9"><i class="bi bi-book-half"></i> Fi Zhilalil Qur\'an</div>'+
                '<div class="small text-muted mt-1">Tidak tersedia untuk ayat ini.</div>';
      }
      return html;
    }

    function renderMakna(d, teksIndonesia){
      // Makna ringkas: prioritas Ibnu Katsir ID -> Kemenag -> Terjemah
      var src = d.ibnu_id || d.kemenag || teksIndonesia || '';
      src = stripTags(src);
      if (!src) return '<span class="text-muted">Makna belum tersedia.</span>';
      // Ambil 2-3 kalimat pertama sebagai ringkasan makna
      var sentences = src.split(/(?<=[\.\!\?])\s+/).slice(0, 3).join(' ');
      if (sentences.length > 600) sentences = sentences.slice(0, 600) + '…';
      return '<span style="white-space:pre-wrap">'+esc(sentences)+'</span>';
    }

    container.querySelectorAll('.js-toggle-tafsir').forEach(function(b){
      b.addEventListener('click', async function(){
        var block = b.closest('.ayat-block');
        block.classList.toggle('show-tafsir');
        if (block.classList.contains('show-tafsir')) {
          var no = parseInt(block.dataset.no, 10);
          var tafs = block.querySelector('.js-tafsir');
          tafs.innerHTML = '<span class="text-muted">Memuat tafsir…</span>';
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
          box.innerHTML = 'Memuat…';
          var d = await loadTafsir(no);
          box.innerHTML = renderMakna(d, ay ? ay.teksIndonesia : '');
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
