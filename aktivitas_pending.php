<?php
/* ============================================================
 * KawanKeringat — Aktivitas Belum Tersinkron  (R52)
 * ------------------------------------------------------------
 * Halaman ini murni client-side: membaca antrian "pending:*"
 * dari IndexedDB (kk_run_db → activity) yang dibuat oleh
 * assets/js/run/save.js ketika upload aktivitas gagal.
 * Pengguna dapat menekan "Upload Ulang" untuk mencoba lagi
 * menggunakan chunked upload yang sama (init → chunk → finalize).
 * ============================================================ */
require __DIR__.'/includes/header.php';
$u = current_user();
$KK_CSRF = $_SESSION['csrf'] ?? '';
?>
<!doctype html>
<html lang="id"><head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>Aktivitas Belum Tersinkron · KawanKeringat</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
<style>
  body{background:#060c18;color:#e6edf7;font-family:system-ui,-apple-system,'Segoe UI',Roboto,sans-serif;
       padding:calc(env(safe-area-inset-top,0px) + 12px) 14px 48px;}
  .kkp-wrap{max-width:640px;margin:0 auto;}
  .kkp-header{display:flex;align-items:center;gap:10px;margin-bottom:14px;}
  .kkp-header .btn-back{background:rgba(255,255,255,.06);color:#e6edf7;border-radius:12px;padding:8px 12px;
       border:0;}
  .kkp-header h1{font-size:18px;margin:0;font-weight:800;letter-spacing:-.01em;}
  .kkp-card{background:#0f1a2e;border-radius:16px;padding:16px;margin-bottom:12px;
       box-shadow:0 8px 24px rgba(0,0,0,.35);}
  .kkp-card .meta{color:#94a3b8;font-size:12.5px;}
  .kkp-card h6{color:#fff;font-weight:800;margin:0 0 4px;}
  .kkp-stats{display:flex;gap:14px;flex-wrap:wrap;margin:8px 0 10px;font-size:13.5px;color:#cbd5e1;}
  .kkp-stats b{color:#fff;}
  .kkp-actions{display:flex;gap:8px;}
  .kkp-actions .btn{border-radius:12px;font-weight:700;flex:1;}
  .kkp-empty{background:#0f1a2e;border-radius:16px;padding:36px 20px;text-align:center;color:#94a3b8;}
  .kkp-empty i{font-size:36px;display:block;margin-bottom:8px;color:#4FB0FF;}
  .kkp-err{color:#f87171;font-size:12.5px;margin-top:4px;}
  .kkp-bar{height:8px;background:rgba(255,255,255,.08);border-radius:99px;overflow:hidden;margin-top:8px;display:none;}
  .kkp-bar.on{display:block;}
  .kkp-bar>span{display:block;height:100%;width:0;background:linear-gradient(90deg,#1E90FF,#4FB0FF);transition:width .25s;}
  .kkp-bar-msg{font-size:12px;color:#7aa7ff;margin-top:4px;min-height:16px;}
</style>
</head><body>
<div class="kkp-wrap">
  <div class="kkp-header">
    <button class="btn-back" onclick="history.length>1?history.back():location.href='/run.php'">
      <i class="bi bi-arrow-left"></i></button>
    <h1>Aktivitas Belum Tersinkron</h1>
  </div>
  <div id="kkp-list"></div>
</div>

<script>
  window.KK_RUN = { csrf: <?= json_encode($KK_CSRF) ?> };
</script>
<script src="/assets/js/run/save.js?v=r52"></script>
<script>
(function(){
  'use strict';
  var host = document.getElementById('kkp-list');

  function fmtTime(sec){
    sec = Math.max(0, Math.floor(sec||0));
    var h = Math.floor(sec/3600), m = Math.floor((sec%3600)/60), s = sec%60;
    var pad = function(n){ return (n<10?'0':'')+n; };
    return h ? (h+':'+pad(m)+':'+pad(s)) : (pad(m)+':'+pad(s));
  }
  function fmtDate(ms){
    if (!ms) return '-';
    try { return new Date(ms).toLocaleString('id-ID', { dateStyle:'medium', timeStyle:'short' }); }
    catch(e){ return new Date(ms).toString(); }
  }

  function renderEmpty(){
    host.innerHTML = '<div class="kkp-empty">'
      + '<i class="bi bi-cloud-check"></i>'
      + '<div>Semua aktivitas sudah tersinkron.</div>'
      + '<div class="mt-2"><a href="/run.php" class="btn btn-outline-light btn-sm">Kembali ke Tracking</a></div>'
      + '</div>';
  }

  function render(list){
    if (!list.length){ renderEmpty(); return; }
    host.innerHTML = '';
    list.forEach(function(item){
      var d = item.data || {};
      var km = ((+d.totalM||0)/1000).toFixed(2);
      var dur = fmtTime(+d.durSec||0);
      var np = (d.points||[]).length;
      var when = fmtDate(d.startedAt || d.savedAt);
      var err = d.lastError ? '<div class="kkp-err">Error terakhir: '+String(d.lastError)+' · Percobaan: '+(d.attempts||1)+'</div>' : '';

      var card = document.createElement('div');
      card.className = 'kkp-card';
      card.innerHTML =
          '<div class="meta">'+when+'</div>'
        + '<h6>Aktivitas Jogging</h6>'
        + '<div class="kkp-stats">'
        +   '<div><b>'+km+'</b> km</div>'
        +   '<div><b>'+dur+'</b></div>'
        +   '<div><b>'+np+'</b> titik GPS</div>'
        + '</div>'
        + err
        + '<div class="kkp-actions">'
        +   '<button class="btn btn-outline-light" data-del="'+item.id+'"><i class="bi bi-trash"></i> Hapus</button>'
        +   '<button class="btn btn-primary" data-retry="'+item.id+'"><i class="bi bi-cloud-arrow-up"></i> Upload Ulang</button>'
        + '</div>'
        + '<div class="kkp-bar"><span></span></div>'
        + '<div class="kkp-bar-msg"></div>';
      host.appendChild(card);
    });
  }

  function refresh(){
    KKSave.listPending().then(render);
  }

  host.addEventListener('click', async function(ev){
    var t = ev.target.closest('[data-retry],[data-del]');
    if (!t) return;
    var card = t.closest('.kkp-card');
    var bar = card.querySelector('.kkp-bar');
    var fill = bar ? bar.querySelector('span') : null;
    var msg  = card.querySelector('.kkp-bar-msg');

    if (t.hasAttribute('data-del')){
      if (!confirm('Hapus aktivitas ini secara permanen dari perangkat?')) return;
      await KKSave.deletePending(t.getAttribute('data-del'));
      refresh(); return;
    }
    if (t.hasAttribute('data-retry')){
      var id = t.getAttribute('data-retry');
      // pasang progress
      KKSave.onProgress = function(pct, m){
        if (bar) bar.classList.add('on');
        if (fill) fill.style.width = pct+'%';
        if (msg) msg.textContent = (pct|0) + '% · ' + (m||'');
      };
      t.disabled = true; t.innerHTML = '<i class="bi bi-hourglass-split"></i> Mengunggah…';
      try {
        var res = await KKSave.retryPending(id);
        if (res && res.ok){
          if (msg) msg.textContent = 'Berhasil disinkronkan.';
          setTimeout(refresh, 600);
        } else {
          alert('Gagal upload: ' + (res && res.error ? res.error : 'tidak diketahui'));
          refresh();
        }
      } catch(e){
        alert('Gagal upload: ' + (e && e.message));
        refresh();
      }
    }
  });

  refresh();
})();
</script>
</body></html>
