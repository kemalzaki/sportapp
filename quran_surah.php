<?php
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
require __DIR__.'/includes/islami_data.php';
require __DIR__.'/includes/islami_helpers.php';
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
  <i class="bi bi-info-circle"></i> Klik kata Arab untuk melihat terjemah per-kata.
  Tombol <i class="bi bi-lightbulb"></i> menampilkan <strong>tafsir cerdas</strong> dan tombol
  <i class="bi bi-journal-text"></i> menampilkan <strong>Asbabun Nuzul</strong> (jika tersedia).
</div>

<div id="ayatList" class="card shadow-sm"><div class="card-body">
  <div class="text-center text-muted py-4">
    <div class="spinner-border spinner-border-sm"></div>
    Memuat Al-Qur'an + tafsir + per-kata…
  </div>
</div></div>

<style>
.word-arab{display:inline-block;margin:0 .25rem .5rem;padding:.25rem .4rem;border-radius:6px;cursor:pointer;transition:.15s;}
.word-arab:hover{background:rgba(34,197,94,.12);}
.word-arab .ar{font-family:'Amiri','Scheherazade New',serif;font-size:1.9rem;line-height:1.4;display:block;}
.word-arab .tr{font-size:.7rem;color:var(--bs-secondary-color,#64748b);display:block;text-align:center;}
.ayat-block{padding:1rem 0;border-bottom:1px solid var(--bs-border-color,#e5e7eb);}
.tafsir-box,.asbab-box{background:var(--bs-tertiary-bg,#f8fafc);border-left:4px solid #16a34a;padding:.75rem 1rem;border-radius:6px;margin-top:.5rem;display:none;}
.asbab-box{border-left-color:#f59e0b;}
.ayat-block.show-tafsir .tafsir-box,.ayat-block.show-asbab .asbab-box{display:block;}
</style>

<script>
(async function(){
  var s = <?= $s ?>;
  var bmMap = <?= json_encode($bmMap) ?>;
  var csrf = '<?= csrf_token() ?>';
  var container = document.getElementById('ayatList').querySelector('.card-body');

  function esc(t){return (t||'').toString().replace(/[<>&]/g, c=>({ '<':'&lt;','>':'&gt;','&':'&amp;' }[c]));}

  try {
    // === equran.id v2: ayat + audio + tafsir per ayat (bahasa Indonesia) ===
    var [rSurah, rTafsir, rWords] = await Promise.allSettled([
      fetch('https://equran.id/api/v2/surat/' + s).then(r=>r.json()),
      fetch('https://equran.id/api/v2/tafsir/' + s).then(r=>r.json()),
      // Per-kata dari quran.com v4 (bahasa Indonesia)
      fetch('https://api.quran.com/api/v4/verses/by_chapter/' + s +
            '?words=true&word_fields=text_uthmani,transliteration&word_translation_language=id&per_page=300')
        .then(r=>r.json())
    ]);

    var ayatList = (rSurah.value && rSurah.value.data && rSurah.value.data.ayat) || [];
    var tafsirList = (rTafsir.value && rTafsir.value.data && rTafsir.value.data.tafsir) || [];
    var verses = (rWords.value && rWords.value.verses) || [];
    var tafsirMap = {}; tafsirList.forEach(function(t){ tafsirMap[t.ayat] = t.teks; });
    var wordMap = {};
    verses.forEach(function(v){
      // v.verse_key like "2:5"
      var num = parseInt((v.verse_key||'').split(':')[1] || '0', 10);
      wordMap[num] = (v.words || []).filter(function(w){ return w.char_type_name === 'word'; });
    });

    if (!ayatList.length) throw new Error('Data ayat kosong');

    var html = '';
    for (var i=0; i<ayatList.length; i++){
      var ay = ayatList[i];
      var no = ay.nomorAyat;
      var isBm = Object.prototype.hasOwnProperty.call(bmMap, no);
      var words = wordMap[no] || [];
      var perKata = '';
      if (words.length) {
        perKata = '<div class="text-end" dir="rtl">';
        for (var w=0; w<words.length; w++){
          var ww = words[w];
          var ar = ww.text_uthmani || ww.text || '';
          var tr = (ww.translation && ww.translation.text) || ww.transliteration && ww.transliteration.text || '';
          perKata += '<span class="word-arab" title="'+esc(tr)+'"><span class="ar">'+esc(ar)+'</span><span class="tr">'+esc(tr)+'</span></span>';
        }
        perKata += '</div>';
      } else {
        perKata = '<div class="text-end fs-3" style="font-family:\'Amiri\',serif;line-height:2">'+esc(ay.teksArab)+'</div>';
      }

      var tafsir = tafsirMap[no] || 'Tafsir untuk ayat ini belum tersedia.';
      // Asbabun Nuzul – best effort dari teks tafsir (kalau kata kuncinya ada)
      var asbab = '';
      var mAs = tafsir.match(/Asb[ab]+un[\s-]?Nuzul[^\.]*\.[^]*?(?=\n\n|$)/i);
      if (mAs) asbab = mAs[0];
      else asbab = 'Riwayat Asbabun Nuzul khusus untuk ayat ini tidak ditemukan di sumber tafsir Kemenag online. '+
                   'Banyak ayat memang tidak memiliki riwayat Asbabun Nuzul yang shahih.';

      html += '<div class="ayat-block" id="a'+no+'">' +
        '<div class="d-flex justify-content-between align-items-center mb-2">' +
          '<span class="badge bg-success">Ayat '+no+'</span>' +
          '<div class="btn-group btn-group-sm">' +
            '<button type="button" class="btn btn-outline-success btn-sm js-toggle-tafsir" data-no="'+no+'" title="Tafsir cerdas"><i class="bi bi-lightbulb"></i> Tafsir</button>' +
            '<button type="button" class="btn btn-outline-warning btn-sm js-toggle-asbab"  data-no="'+no+'" title="Asbabun Nuzul"><i class="bi bi-journal-text"></i> Asbab</button>' +
            '<form method="post" style="display:inline"><input type="hidden" name="csrf" value="'+csrf+'"><input type="hidden" name="_action" value="last_read"><input type="hidden" name="ayat" value="'+no+'"><button class="btn btn-outline-primary btn-sm" title="Tandai last read"><i class="bi bi-bookmark-check"></i></button></form>' +
            '<form method="post" style="display:inline"><input type="hidden" name="csrf" value="'+csrf+'"><input type="hidden" name="_action" value="'+(isBm?'unbookmark':'bookmark')+'"><input type="hidden" name="ayat" value="'+no+'"><button class="btn btn-'+(isBm?'warning':'outline-warning')+' btn-sm" title="Bookmark"><i class="bi bi-star'+(isBm?'-fill':'')+'"></i></button></form>' +
          '</div>' +
        '</div>' +
        perKata +
        '<div class="small text-muted mt-2"><strong>Terjemah:</strong> '+esc(ay.teksIndonesia)+'</div>' +
        (ay.audio && ay.audio['05'] ? '<audio class="mt-2 w-100" controls preload="none" src="'+esc(ay.audio['05'])+'"></audio>' : '') +
        '<div class="tafsir-box"><strong><i class="bi bi-lightbulb text-success"></i> Tafsir (Kemenag):</strong><p class="mb-0 mt-1" style="white-space:pre-wrap">'+esc(tafsir)+'</p></div>' +
        '<div class="asbab-box"><strong><i class="bi bi-journal-text text-warning"></i> Asbabun Nuzul:</strong><p class="mb-0 mt-1" style="white-space:pre-wrap">'+esc(asbab)+'</p></div>' +
        (isBm && bmMap[no] ? '<div class="small fst-italic mt-1">📝 '+esc(bmMap[no])+'</div>' : '') +
        '</div>';
    }
    container.innerHTML = html;
    container.querySelectorAll('.js-toggle-tafsir').forEach(function(b){
      b.addEventListener('click', function(){ b.closest('.ayat-block').classList.toggle('show-tafsir'); });
    });
    container.querySelectorAll('.js-toggle-asbab').forEach(function(b){
      b.addEventListener('click', function(){ b.closest('.ayat-block').classList.toggle('show-asbab'); });
    });
    if (location.hash) {
      var el = document.querySelector(location.hash);
      if (el) el.scrollIntoView({behavior:'smooth', block:'start'});
    }
  } catch (e) {
    container.innerHTML = '<div class="alert alert-warning small">Gagal memuat ayat dari sumber online. Pastikan koneksi internet aktif. ('+esc(e.message)+')</div>';
  }
})();
</script>
<?php include __DIR__.'/includes/footer.php'; ?>
