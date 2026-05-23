<?php
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
require __DIR__.'/includes/islami_data.php';
require __DIR__.'/includes/islami_helpers.php';
send_security_headers();
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

$bm = $u ? db_all("SELECT ayat, catatan FROM quran_bookmarks WHERE user_id=$1 AND surah=$2", [(int)$u['id'], $s]) : [];
$bmMap = [];
foreach ($bm as $b) $bmMap[(int)$b['ayat']] = $b['catatan'];

include __DIR__.'/includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="m-0"><i class="bi bi-book text-success"></i> QS <?= htmlspecialchars($info[0]) ?> <small class="text-muted">(<?= $info[1] ?> ayat)</small></h4>
  <div>
    <?php if ($s>1): ?><a href="/quran_surah.php?s=<?= $s-1 ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-chevron-left"></i></a><?php endif; ?>
    <a href="/quran.php" class="btn btn-sm btn-outline-success">Daftar</a>
    <?php if ($s<114): ?><a href="/quran_surah.php?s=<?= $s+1 ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-chevron-right"></i></a><?php endif; ?>
  </div>
</div>

<div id="ayatList" class="card shadow-sm"><div class="card-body">
  <div class="text-center text-muted">Memuat ayat dari sumber online (api.alquran.cloud)…</div>
</div></div>

<script>
(async function(){
  var s = <?= $s ?>;
  var bmMap = <?= json_encode($bmMap) ?>;
  var loggedIn = <?= $u ? 'true' : 'false' ?>;
  var csrf = '<?= $u ? csrf_token() : '' ?>';
  var container = document.getElementById('ayatList').querySelector('.card-body');
  try {
    var [r1, r2] = await Promise.all([
      fetch('https://api.alquran.cloud/v1/surah/' + s).then(r=>r.json()),
      fetch('https://api.alquran.cloud/v1/surah/' + s + '/id.indonesian').then(r=>r.json())
    ]);
    var ayatAr = r1.data.ayahs, ayatId = r2.data.ayahs;
    var html = '';
    for (var i = 0; i < ayatAr.length; i++) {
      var no = ayatAr[i].numberInSurah;
      var isBm = bmMap.hasOwnProperty(no);
      html += '<div id="a'+no+'" class="border-bottom py-3">' +
        '<div class="d-flex justify-content-between align-items-center mb-2">' +
        '<span class="badge bg-success">'+no+'</span>' +
        (loggedIn ? '<div class="btn-group btn-group-sm">' +
          '<form method="post" style="display:inline"><input type="hidden" name="csrf" value="'+csrf+'"><input type="hidden" name="_action" value="last_read"><input type="hidden" name="ayat" value="'+no+'"><button class="btn btn-outline-primary btn-sm" title="Tandai last read"><i class="bi bi-bookmark-check"></i></button></form>' +
          '<form method="post" style="display:inline"><input type="hidden" name="csrf" value="'+csrf+'"><input type="hidden" name="_action" value="'+(isBm?'unbookmark':'bookmark')+'"><input type="hidden" name="ayat" value="'+no+'"><button class="btn btn-'+(isBm?'warning':'outline-warning')+' btn-sm" title="Bookmark"><i class="bi bi-star'+(isBm?'-fill':'')+'"></i></button></form>' +
          '</div>' : '') +
        '</div>' +
        '<div class="text-end fs-4" style="font-family:\'Amiri\',serif;line-height:2">'+ayatAr[i].text+'</div>' +
        '<div class="small text-muted mt-2">'+ayatId[i].text+'</div>' +
        (isBm && bmMap[no] ? '<div class="small fst-italic mt-1">📝 '+bmMap[no]+'</div>' : '') +
        '</div>';
    }
    container.innerHTML = html;
    if (location.hash) {
      var el = document.querySelector(location.hash);
      if (el) el.scrollIntoView({behavior:'smooth', block:'start'});
    }
  } catch (e) {
    container.innerHTML = '<div class="alert alert-warning small">Gagal memuat ayat. Pastikan koneksi internet aktif.</div>';
  }
})();
</script>
<?php include __DIR__.'/includes/footer.php'; ?>
