</main>
<!-- ===== Revisi 1 Jun 2026 #3 — Skeleton loading global =====
     Pakai di halaman manapun:
       <div class="skeleton skel-line" style="width:60%"></div>
       <div class="skeleton skel-block" style="height:120px"></div>
       <div class="skeleton skel-circle" style="width:56px;height:56px"></div>
     Atau panggil via JS: SK.rows(targetEl, 3) — render 3 baris skeleton.
-->
<style>
.skeleton{
  position:relative; overflow:hidden;
  background:linear-gradient(90deg,#eef2f7 0%,#f7fafc 50%,#eef2f7 100%);
  background-size:200% 100%; animation:skelShimmer 1.2s ease-in-out infinite;
  border-radius:.45rem; display:block;
}
.skel-line{ height:14px; margin:.35rem 0; width:100%; }
.skel-line.sm{ height:10px; }
.skel-line.lg{ height:18px; }
.skel-block{ height:80px; margin:.4rem 0; }
.skel-circle{ border-radius:50%; }
.skel-card{ padding:.85rem; border:1px solid #e5e7eb; border-radius:.6rem; margin-bottom:.6rem; background:#fff; }
@keyframes skelShimmer{ 0%{background-position:200% 0} 100%{background-position:-200% 0} }
[data-bs-theme=dark] .skeleton{
  background:linear-gradient(90deg,#1f2937 0%,#273345 50%,#1f2937 100%); background-size:200% 100%;
}
[data-bs-theme=dark] .skel-card{ background:#0f172a; border-color:#1f2937; }
</style>
<script>
/* Helper skeleton global. Idempotent — aman dipanggil berkali-kali. */
window.SK = window.SK || {
  rows: function(el, n){
    if (!el) return; n = Math.max(1, n||3);
    var h = ''; for (var i=0;i<n;i++){ h += '<div class="skeleton skel-line" style="width:'+(60+Math.random()*40)+'%"></div>'; }
    el.innerHTML = h;
  },
  cards: function(el, n){
    if (!el) return; n = Math.max(1, n||3);
    var h = ''; for (var i=0;i<n;i++){
      h += '<div class="skel-card">'+
             '<div class="d-flex gap-2 align-items-center mb-2">'+
               '<div class="skeleton skel-circle" style="width:42px;height:42px"></div>'+
               '<div style="flex:1"><div class="skeleton skel-line" style="width:60%"></div>'+
               '<div class="skeleton skel-line sm" style="width:40%"></div></div>'+
             '</div>'+
             '<div class="skeleton skel-block" style="height:60px"></div>'+
           '</div>';
    }
    el.innerHTML = h;
  },
  block: function(el, h){
    if (!el) return;
    el.innerHTML = '<div class="skeleton skel-block" style="height:'+(h||120)+'px"></div>';
  },
  /* Isi otomatis semua elemen [data-skel] dengan skeleton sesuai tipenya.
     Contoh penggunaan di halaman manapun:
       <div data-skel="rows" data-skel-count="5"> ... data asli ... </div>
       <div data-skel="cards" data-skel-count="3"></div>
       <div data-skel="block" data-skel-h="180"></div>
     Skeleton hanya digambar SETELAH halaman terbuka (DOMContentLoaded),
     sehingga efeknya muncul "setelah pindah halaman", bukan sebelumnya.
     Tandai data-skel-keep="1" agar skeleton tidak otomatis dihapus saat
     halaman selesai load (mis. menunggu fetch JS milik halaman). */
  auto: function(root){
    var scope = root || document;
    scope.querySelectorAll('[data-skel]').forEach(function(el){
      if (el.dataset.skelDone === '1') return;
      var kind = el.getAttribute('data-skel') || 'rows';
      var n = parseInt(el.getAttribute('data-skel-count')||'3',10);
      var h = parseInt(el.getAttribute('data-skel-h')||'120',10);
      if (kind === 'cards') window.SK.cards(el, n);
      else if (kind === 'block') window.SK.block(el, h);
      else window.SK.rows(el, n);
      el.dataset.skelDone = '1';
    });
  },
  /* Wrapper fetch yang otomatis menampilkan skeleton di target sampai
     response datang. Mengembalikan promise teks. */
  fetch: function(url, targetEl, kind, opts){
    if (targetEl){
      if (kind === 'cards') window.SK.cards(targetEl, (opts&&opts.count)||3);
      else if (kind === 'block') window.SK.block(targetEl, (opts&&opts.h)||120);
      else window.SK.rows(targetEl, (opts&&opts.count)||3);
    }
    return fetch(url, Object.assign({credentials:'same-origin'}, opts||{}))
      .then(function(r){ return (opts&&opts.json) ? r.json() : r.text(); });
  }
};
/* Jalankan auto-skeleton begitu DOM siap → muncul setelah halaman terbuka. */
document.addEventListener('DOMContentLoaded', function(){ try { window.SK.auto(); } catch(e){} });

</script>

<?php include __DIR__ . '/bottom_nav.php'; ?>
<?php include __DIR__ . '/dm_floating.php'; ?>
<footer class="app-footer text-center text-muted py-3 small">
  <div class="container">&copy; 2026 HapFam SportApp · v4 - By <a href="https://www.yuk-mari.com" target="_blank" rel="noopener" class="text-decoration-none">Yuk-Mari CyberLab</a></div>
</footer>

<?php if (!empty($_SESSION['error_popup'])): $__ep = $_SESSION['error_popup']; unset($_SESSION['error_popup']); ?>
<div class="modal fade" id="sqlErrorModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-danger">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title"><i class="bi bi-exclamation-octagon"></i> Terjadi Kesalahan Database</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="mb-2"><strong>Pesan:</strong></p>
        <pre class="bg-light p-2 border rounded small" style="white-space:pre-wrap;"><?= htmlspecialchars($__ep) ?></pre>
        <small class="text-muted">Bila terus berulang, hubungi admin.</small>
      </div>
      <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button></div>
    </div>
  </div>
</div>
<script>document.addEventListener('DOMContentLoaded',()=>{ new bootstrap.Modal(document.getElementById('sqlErrorModal')).show(); });</script>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
<script src="/assets/js/firebase-config.js"></script>
<script type="module" src="/assets/js/fcm.js"></script>
<script>
/* Pastikan preloader fullscreen lama tidak pernah tampil. */
(function(){
  function killPreloader(){ document.querySelectorAll('#appPreloader').forEach(function(el){ el.remove(); }); }
  killPreloader();
  document.addEventListener('DOMContentLoaded', killPreloader);
  window.addEventListener('load', killPreloader);
  window.addEventListener('pageshow', killPreloader);
})();
</script>
<!-- Top progress preloader (tampil tipis di atas, tidak menutupi konten) -->
<div id="appTopLoader"></div>
<div id="appCornerSpinner"></div>

<!-- Revisi 18 Juni 2026:
     Fullscreen overlay #appNavPreloader DIHAPUS karena menutupi konten saat
     navigasi. Skeleton loading (includes/skeleton.php) sudah menjadi indikator
     loading utama; cukup thin top progress bar (#appTopLoader) di atas layar.
     Stub HFNavPreloader dipertahankan agar pemanggil lama tidak error. -->
<script>
(function(){
  // No-op API supaya kode lain yang masih memanggil HFNavPreloader.show()/finish()
  // tidak crash — sekarang loader diwakili oleh #appTopLoader + skeleton.
  function noop(){}
  window.HFNavPreloader = { show: noop, finish: noop };

  // Form submit: tetap matikan tombol & tampilkan spinner kecil di tombol,
  // tanpa overlay fullscreen. (Sama seperti perilaku 17 Juni 2026 Part J.)
  document.addEventListener('submit', function(ev){
    var f = ev.target;
    if (!(f instanceof HTMLFormElement)) return;
    if (ev.defaultPrevented) return;
    var btns = f.querySelectorAll('button[type=submit], button:not([type]), input[type=submit]');
    btns.forEach(function(b){
      if (b.disabled) return;
      b.disabled = true;
      if (b.tagName === 'BUTTON') {
        if (!b.dataset.origHtml) b.dataset.origHtml = b.innerHTML;
        b.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>' + (b.dataset.loadingText || 'Menyimpan…');
      }
    });
    setTimeout(function(){
      btns.forEach(function(b){
        if (b.dataset.origHtml) { b.innerHTML = b.dataset.origHtml; delete b.dataset.origHtml; }
        b.disabled = false;
      });
    }, 15000);
  }, true);
})();
</script>
<script>
/* ===== Global Top Preloader =====
 * Tipis di atas layar + spinner kecil di pojok. Tampil di semua halaman saat:
 *   - navigasi (klik link, back/forward)
 *   - form submit (CRUD)
 *   - fetch/XHR berlangsung
 * Tidak menutupi konten halaman yang sudah terbuka.
 */
(function(){
  var bar = document.getElementById('appTopLoader');
  var spn = document.getElementById('appCornerSpinner');
  var pending = 0, timer = null, width = 0;
  function start(){
    pending++;
    if (bar){ bar.classList.add('active'); }
    if (spn){ spn.classList.add('active'); }
    if (timer) return;
    width = 8; if (bar) bar.style.width = width+'%';
    timer = setInterval(function(){
      // naik perlahan, tidak pernah mencapai 100% sampai done()
      var inc = width < 60 ? 4 : (width < 85 ? 1.2 : 0.3);
      width = Math.min(92, width + inc);
      if (bar) bar.style.width = width+'%';
    }, 220);
  }
  function done(force){
    if (!force) pending = Math.max(0, pending - 1);
    if (pending > 0) return;
    if (timer){ clearInterval(timer); timer = null; }
    if (bar){
      bar.style.width = '100%';
      setTimeout(function(){
        bar.classList.remove('active');
        setTimeout(function(){ bar.style.width='0'; }, 350);
      }, 180);
    }
    if (spn){ spn.classList.remove('active'); }
  }
  window.HFLoader = { start: start, done: done, reset: function(){ pending=0; done(true); } };

  // Pastikan sembunyi saat halaman ready / kembali dari BFCache
  function hideAll(){ window.HFLoader.reset(); }
  if (document.readyState === 'complete') hideAll();
  document.addEventListener('DOMContentLoaded', hideAll);
  window.addEventListener('load', hideAll);
  window.addEventListener('pageshow', hideAll);
  window.addEventListener('popstate', function(){ start(); setTimeout(done, 600); });

  // Revisi: TIDAK lagi menampilkan loader saat klik link / submit / beforeunload.
  // Efek loading (skeleton) sekarang muncul SETELAH pindah halaman, dijalankan
  // oleh SK.auto() pada DOMContentLoaded di halaman tujuan. Loader top-bar
  // tetap aktif untuk request AJAX/XHR di bawah ini.


  // Bungkus fetch & XHR agar request AJAX juga menampilkan progress
  if (window.fetch){
    var _f = window.fetch;
    window.fetch = function(){
      start();
      var p = _f.apply(this, arguments);
      p.then(function(){ done(); }, function(){ done(); });
      return p;
    };
  }
  if (window.XMLHttpRequest){
    var _open = XMLHttpRequest.prototype.open;
    var _send = XMLHttpRequest.prototype.send;
    XMLHttpRequest.prototype.open = function(){ this.__hfTrack = true; return _open.apply(this, arguments); };
    XMLHttpRequest.prototype.send = function(){
      if (this.__hfTrack){
        start();
        this.addEventListener('loadend', function(){ done(); });
      }
      return _send.apply(this, arguments);
    };
  }
})();
</script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('.modal').forEach(function(m) { document.body.appendChild(m); });
  document.querySelectorAll('textarea[data-wysiwyg]').forEach(function(ta){
    var wrap = document.createElement('div'); wrap.className='wysiwyg-wrap';
    var holder = document.createElement('div'); holder.style.minHeight = '140px';
    ta.parentNode.insertBefore(wrap, ta); wrap.appendChild(holder);
    ta.style.display = 'none'; wrap.appendChild(ta);
    holder.innerHTML = ta.value || '';
    var q = new Quill(holder, { theme:'snow', modules:{ toolbar:[['bold','italic','underline','strike'],[{list:'ordered'},{list:'bullet'}],['link','clean']] } });
    var form = ta.closest('form'); if (form) form.addEventListener('submit', function(){ ta.value = q.root.innerHTML; });
  });

  // light mode only
  document.documentElement.setAttribute('data-bs-theme','light');
  try { localStorage.removeItem('darkMode'); } catch(e) {}

  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/service-worker.js').catch(()=>{});
  }


/* === Soft auto-refresh (tanpa reload page) ===
 * Setiap 25 detik, fetch ulang HTML halaman aktif dan replace bagian ber-attribute [data-live].
 * Cara pakai: bungkus section dengan <div data-live="forum"> ... </div>
 * Halaman lain (riwayat, profile, dll) bisa pakai pola yang sama tanpa perubahan JS.
 */
(function(){
  const LIVE_INTERVAL = 25000;
  let busy=false;
  async function softRefresh(opts){
    opts = opts || {};
    if((busy && !opts.force) || (document.hidden && !opts.force)) return;
    busy=true;
    try{
      let doc;
      if(opts.html){
        doc=new DOMParser().parseFromString(opts.html,'text/html');
      } else {
        const r=await fetch(location.pathname+location.search, {headers:{'X-Soft-Refresh':'1'}, credentials:'same-origin', cache:'no-store'});
        if(!r.ok) return;
        const html=await r.text();
        doc=new DOMParser().parseFromString(html,'text/html');
      }
      let changed=false;
      const liveNodes = document.querySelectorAll('[data-live]');
      liveNodes.forEach(node=>{
        const key=node.getAttribute('data-live');
        const fresh=doc.querySelector('[data-live="'+key+'"]');
        if(fresh && fresh.innerHTML !== node.innerHTML){
          node.innerHTML=fresh.innerHTML; changed=true;
        }
      });
      if(liveNodes.length===0){
        const m1=document.querySelector('main'); const m2=doc.querySelector('main');
        if(m1 && m2 && m1.innerHTML !== m2.innerHTML){ m1.innerHTML=m2.innerHTML; changed=true; }
      }
      if(changed){
        const b=document.getElementById('liveRefreshBadge');
        if(b){ b.style.display='inline-block'; setTimeout(()=>b.style.display='none',1500); }
      }
    }catch(e){}
    finally{ busy=false; }
  }
  if(document.querySelector('[data-live]')) setInterval(softRefresh, LIVE_INTERVAL);
  window.softRefresh = softRefresh;

  document.addEventListener('click', function(ev){
    const b=ev.target.closest('[data-soft-refresh]');
    if(!b) return;
    ev.preventDefault();
    softRefresh({force:true});
  });
})();

}); /* FIX: close DOMContentLoaded handler opened on line 144 (WYSIWYG + soft-refresh) */

</script>

<?php if ($u): ?>
<script>
/* ===== Global push notification poll (WhatsApp-like) =====
 * Aktif di semua halaman selama user login & izin notifikasi diberikan.
 * Sumber: /api_notif_poll.php (termasuk pesan DM baru, event, badge).
 */
(function(){
  if (!('Notification' in window)) return;
  function showOne(n){
    var opt = { body: n.isi || '', icon:'/assets/icon-192.png', badge:'/assets/icon-192.png',
                tag: 'hapfam-'+n.id, data:{ url: n.url || '/' }, vibrate:[120,60,120] };
    if (navigator.serviceWorker && navigator.serviceWorker.ready) {
      navigator.serviceWorker.ready.then(function(reg){ reg.showNotification(n.judul || 'HapFam', opt); }).catch(function(){});
    } else {
      try { new Notification(n.judul || 'HapFam', opt); } catch(e){}
    }
  }
  async function tick(){
    if (Notification.permission !== 'granted') return;
    try{
      var r = await fetch('/api_notif_poll.php', { credentials:'same-origin' });
      if (!r.ok) return;
      var d = await r.json();
      (d.items || []).forEach(showOne);
    }catch(e){}
  }
  // Auto-minta izin pertama kali user klik di mana saja (memenuhi syarat user-gesture browser).
  document.addEventListener('click', function once(){
    if (Notification.permission === 'default') {
      try { Notification.requestPermission(); } catch(e){}
    }
    document.removeEventListener('click', once, true);
  }, true);
  tick(); setInterval(tick, 15000);
  // Polling ekstra untuk unread badge DM
  async function tickDm(){
    try{
      var r = await fetch('/api_dm.php?unread=1', { credentials:'same-origin' });
      if (!r.ok) return;
      var d = await r.json();
      var lnk = document.querySelector('a[href="/dm.php"]');
      if (lnk){
        var b = lnk.querySelector('.dm-unread-badge');
        if (d.unread > 0){
          if (!b){ b=document.createElement('span'); b.className='dm-unread-badge badge rounded-pill bg-danger ms-1'; b.style.fontSize='.65rem'; lnk.appendChild(b); }
          b.textContent = d.unread;
        } else if (b){ b.remove(); }
      }
    }catch(e){}
  }
  tickDm(); setInterval(tickDm, 20000);
})();
</script>
<?php endif; ?>

<!-- Lightbox global untuk foto -->
<div class="modal fade" id="imgLightbox" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content bg-transparent border-0">
      <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3" data-bs-dismiss="modal" style="z-index:10;"></button>
      <img id="imgLightboxImg" src="" alt="" style="max-width:100%;max-height:90vh;border-radius:8px;object-fit:contain;background:#0008;">
    </div>
  </div>
</div>
<style>
.zoomable{cursor:zoom-in;}
#appMiniLoader{position:fixed;inset:0;background:rgba(255,255,255,.45);display:flex;align-items:center;justify-content:center;z-index:9998;backdrop-filter:blur(2px);}
#appMiniLoader .spinner-border{width:2.5rem;height:2.5rem;color:#0ea5e9;}
.pagination-bar{display:flex;align-items:center;justify-content:space-between;gap:.5rem;padding:.5rem .25rem;font-size:.85rem;}
.pagination-bar .pg-btns button{border:1px solid var(--bs-border-color,#e2e8f0);background:#fff;border-radius:6px;padding:2px 10px;margin-left:4px;cursor:pointer;}
.pagination-bar .pg-btns button:disabled{opacity:.45;cursor:not-allowed;}
/* Emoji picker */
.emoji-picker-btn{position:absolute;right:6px;bottom:6px;background:#fff;border:1px solid #e2e8f0;border-radius:6px;padding:2px 6px;cursor:pointer;font-size:1rem;line-height:1;z-index:5;}
.emoji-picker-wrap{position:relative;}
.emoji-picker-pop{position:absolute;z-index:1090;background:#fff;border:1px solid #e2e8f0;border-radius:10px;box-shadow:0 8px 24px rgba(0,0,0,.12);padding:6px;max-width:280px;display:none;}
.emoji-picker-pop.show{display:block;}
.emoji-picker-pop button{background:transparent;border:0;font-size:1.2rem;line-height:1;padding:4px 6px;cursor:pointer;border-radius:6px;}
.emoji-picker-pop button:hover{background:#f1f5f9;}
</style>
<script>
/* ===== Global lightbox ===== */
(function(){
  let m=null;
  document.addEventListener('click', function(ev){
    const t = ev.target.closest('img.zoomable,[data-zoom] img,img[data-zoom]');
    if(!t) return;
    const src = t.getAttribute('data-full') || t.src;
    if(!src) return;
    ev.preventDefault();
    if(!m) m = new bootstrap.Modal(document.getElementById('imgLightbox'));
    document.getElementById('imgLightboxImg').src = src;
    m.show();
  });
})();

/* ===== Auto image compression for inputs[data-compress] =====
 * Resize ke maxW dan kompres ke JPEG ~0.8 jika ukuran > thresholdKB.
 */
window.compressImageFile = async function(file, opts){
  opts = Object.assign({maxW:1600, quality:0.8, thresholdKB:600}, opts||{});
  if(!file || !file.type || !file.type.startsWith('image/')) return file;
  if(file.size <= opts.thresholdKB*1024) return file;
  try{
    const bmp = await createImageBitmap(file);
    const scale = Math.min(1, opts.maxW / bmp.width);
    const w = Math.round(bmp.width*scale), h = Math.round(bmp.height*scale);
    const cv = document.createElement('canvas'); cv.width=w; cv.height=h;
    cv.getContext('2d').drawImage(bmp, 0,0, w,h);
    const blob = await new Promise(res=>cv.toBlob(res, 'image/jpeg', opts.quality));
    if(!blob || blob.size >= file.size) return file;
    return new File([blob], (file.name.replace(/\.[a-z0-9]+$/i,'')||'image')+'.jpg', {type:'image/jpeg', lastModified:Date.now()});
  }catch(e){ return file; }
};
document.addEventListener('change', async function(ev){
  const inp = ev.target;
  if(!(inp instanceof HTMLInputElement)) return;
  if(inp.type!=='file' || !inp.hasAttribute('data-compress')) return;
  if(!inp.files || !inp.files.length) return;
  const origSize = inp.files[0].size;
  const lbl = inp.parentElement && inp.parentElement.querySelector('.compress-info');
  if(lbl) lbl.textContent = 'Mengoptimasi gambar...';
  // Revisi 4 Jun 2026: blokir submit selama kompresi berjalan agar form tidak ter-submit
  // dengan file lama (penyebab "gagal submit" pada Posting/Story).
  const formC = inp.closest('form');
  const btnC  = formC && formC.querySelector('button[type=submit],button:not([type])');
  if (formC) formC.setAttribute('data-compressing','1');
  if (btnC)  btnC.disabled = true;
  const compressed = await window.compressImageFile(inp.files[0]);
  if(compressed !== inp.files[0]){
    const dt = new DataTransfer(); dt.items.add(compressed); inp.files = dt.files;
  }
  if(lbl){
    const kb = (inp.files[0].size/1024).toFixed(0);
    const oKb = (origSize/1024).toFixed(0);
    lbl.textContent = origSize>inp.files[0].size ? ('Optimasi: '+oKb+' KB → '+kb+' KB') : ('Ukuran: '+kb+' KB');
  }
  if (formC) formC.removeAttribute('data-compressing');
  if (btnC)  btnC.disabled = false;
});

/* ===== AJAX submit (forms[data-ajax]) =====
 * Submit tanpa reload page; trigger soft refresh region [data-live] terdekat.
 */
function showMiniLoader(label){
  let el = document.getElementById('appMiniLoader');
  if(!el){
    el = document.createElement('div'); el.id='appMiniLoader';
    el.innerHTML='<div class="text-center"><div class="spinner-border"></div><div class="small mt-2 text-muted">'+(label||'Memproses...')+'</div></div>';
    document.body.appendChild(el);
  }
  return el;
}
function hideMiniLoader(){ const el=document.getElementById('appMiniLoader'); if(el) el.remove(); }
document.addEventListener('submit', async function(ev){
  const f = ev.target;
  if(!(f instanceof HTMLFormElement)) return;
  if(!f.hasAttribute('data-ajax')) return;
  // Hormati onsubmit yang sudah preventDefault (mis. confirm() dibatalkan)
  if(ev.defaultPrevented) return;
  ev.preventDefault();
  // Revisi 4 Jun 2026: jika ada kompresi gambar berjalan, tunda submit sampai selesai.
  if (f.hasAttribute('data-compressing')) {
    alert('Gambar masih dioptimasi, coba lagi 1-2 detik.');
    return;
  }
  const btn = f.querySelector('button[type=submit],button:not([type])');
  if(btn) btn.disabled = true;
  showMiniLoader(f.getAttribute('data-ajax-label')||'Menyimpan...');
  try{
    const fd = new FormData(f);
    const r = await fetch(f.action || location.pathname+location.search, {
      method: (f.method||'POST').toUpperCase(),
      body: fd, credentials:'same-origin',
      headers:{'X-Requested-With':'fetch','X-Soft-Refresh':'1'},
      cache:'no-store'
    });
    if(r.ok || r.redirected){
      // Reuse body dari response (sudah halaman terbaru setelah redirect) → lebih cepat & pasti up-to-date
      let html = null;
      try { html = await r.text(); } catch(_) {}
      try {
        if(typeof window.softRefresh==='function'){
          await window.softRefresh(html ? {html, force:true} : {force:true});
        }
      } catch(_) { /* refresh boleh gagal — data sudah tersimpan */ }
      // Bersihkan input teks pesan/komentar
      f.querySelectorAll('input[name="pesan"],input[name="isi"],textarea[name="caption"],textarea[name="pesan"],textarea[name="isi"]').forEach(i=>{ i.value=''; });
      // Tutup modal bila ada
      const md = f.closest('.modal');
      if(md && window.bootstrap){
        const inst = bootstrap.Modal.getInstance(md) || bootstrap.Modal.getOrCreateInstance(md);
        if(inst) inst.hide();
      }
      // Reset file input
      f.querySelectorAll('input[type=file]').forEach(i=>{ i.value=''; });
      const prev = f.querySelector('img[id$="Preview"]'); if(prev){ prev.style.display='none'; prev.src=''; }
    } else {
      // Revisi 4 Jun 2026: tampilkan error agar user tahu submit gagal (mis. 413/429/CSRF)
      let msg = 'Gagal submit (HTTP '+r.status+').';
      try { const t = await r.text(); if(t && t.length<400) msg += ' '+t; } catch(_) {}
      alert(msg);
    }
  }catch(e){
    alert('Gagal submit: '+(e && e.message ? e.message : 'koneksi terputus'));
  }
  finally{
    if(btn) btn.disabled = false;
    hideMiniLoader();
  }
});

/* Auto-tandai semua <form method=post> di dalam region [data-live] sebagai data-ajax,
 * sehingga aksi CRUD (komentar, hapus, like, chat, dll) langsung me-refresh tanpa reload. */
(function(){
  function tag(){
    document.querySelectorAll('[data-live] form').forEach(function(f){
      if(f.hasAttribute('data-ajax')) return;
      if(f.hasAttribute('data-no-ajax')) return;
      var m = (f.getAttribute('method')||'').toLowerCase();
      if(m !== 'post') return;
      // Skip form upload file ke endpoint lain (mis. upload story dgn aksi khusus) jika sudah punya action eksternal beda host
      f.setAttribute('data-ajax','');
    });
  }
  tag();
  document.addEventListener('DOMContentLoaded', tag);
  // Re-tag setelah softRefresh mengganti innerHTML
  var _origSR = window.softRefresh;
  if(typeof _origSR === 'function'){
    window.softRefresh = async function(opts){
      var r = await _origSR(opts);
      tag();
      return r;
    };
  }
})();



/* ===== Generic tabel pagination + sorting global =====
 * - Pagination: <table data-paginate="N">.
 * - Sorting: otomatis pada SEMUA <table> ber-<thead><th>. Klik header asc/desc.
 *   Nonaktifkan dengan atribut data-no-sort di <table> atau <th>.
 *   Gunakan data-sort-value="..." di <td> untuk override nilai sorting.
 */
(function(){
  function parseCell(td){
    if(!td) return '';
    const raw = ((td.getAttribute && td.getAttribute('data-sort-value')) || td.textContent || '').trim();
    if(raw === '') return '';
    if(/^[-+]?\d[\d.,]*\s*%?$/.test(raw)){
      const n = parseFloat(raw.replace(/\./g,'').replace(',','.').replace('%',''));
      if(!isNaN(n)) return n;
    }
    const m1 = raw.match(/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})/);
    if(m1){ const y=m1[3].length===2?('20'+m1[3]):m1[3]; const t=new Date(+y,+m1[2]-1,+m1[1]).getTime(); if(!isNaN(t)) return t; }
    const m2 = raw.match(/^(\d{4})-(\d{1,2})-(\d{1,2})/);
    if(m2){ const t=new Date(+m2[1],+m2[2]-1,+m2[3]).getTime(); if(!isNaN(t)) return t; }
    return raw.toLowerCase();
  }
  function applySort(tbl, colIdx, dir){
    const tbody = tbl.tBodies[0]; if(!tbody) return;
    const rows = Array.from(tbody.rows);
    rows.sort((a,b)=>{
      const va = parseCell(a.cells[colIdx]); const vb = parseCell(b.cells[colIdx]);
      if(va<vb) return dir==='asc'?-1:1;
      if(va>vb) return dir==='asc'?1:-1;
      return 0;
    });
    rows.forEach(r=>tbody.appendChild(r));
    if(typeof tbl._pgRender==='function') tbl._pgRender(true);
  }
  function attachSort(tbl){
    if(tbl.hasAttribute('data-no-sort') || tbl.dataset.sortInit) return;
    const thead = tbl.tHead; if(!thead || !thead.rows.length) return;
    const tbody = tbl.tBodies[0]; if(!tbody || tbody.rows.length<2) { tbl.dataset.sortInit='1'; return; }
    tbl.dataset.sortInit='1';
    const ths = Array.from(thead.rows[0].cells);
    ths.forEach((th, idx)=>{
      if(th.hasAttribute('data-no-sort')) return;
      th.style.cursor='pointer'; th.style.userSelect='none';
      if(!th.querySelector('.sort-ind')){
        const s=document.createElement('span'); s.className='sort-ind text-muted'; s.style.cssText='margin-left:4px;font-size:.75em;opacity:.6;'; s.textContent='⇅'; th.appendChild(s);
      }
      th.addEventListener('click', (ev)=>{
        if(ev.target.closest('a,button,input,select,label')) return;
        const cur = th.dataset.sortDir || '';
        const next = cur==='asc'?'desc':'asc';
        ths.forEach(o=>{ o.dataset.sortDir=''; const i=o.querySelector('.sort-ind'); if(i){ i.textContent='⇅'; i.style.opacity='.6'; } });
        th.dataset.sortDir = next;
        const ind = th.querySelector('.sort-ind'); if(ind){ ind.textContent = next==='asc'?'▲':'▼'; ind.style.opacity='1'; }
        applySort(tbl, idx, next);
      });
    });
  }
  function paginate(tbl){
    const per = parseInt(tbl.getAttribute('data-paginate')||'10',10) || 10;
    const tbody = tbl.tBodies[0]; if(!tbody) return;
    let page = 0;
    const bar = document.createElement('div'); bar.className='pagination-bar';
    const info = document.createElement('div'); info.className='pg-info text-muted';
    const btns = document.createElement('div'); btns.className='pg-btns';
    const prev = document.createElement('button'); prev.type='button'; prev.innerHTML='‹ Prev';
    const next = document.createElement('button'); next.type='button'; next.innerHTML='Next ›';
    btns.appendChild(prev); btns.appendChild(next);
    bar.appendChild(info); bar.appendChild(btns);
    tbl.parentNode.insertBefore(bar, tbl.nextSibling);
    function render(keepPage){
      const rows = Array.from(tbody.rows);
      const total = rows.length, pages = Math.max(1, Math.ceil(total/per));
      if(!keepPage) page = 0;
      if(page>=pages) page=pages-1; if(page<0) page=0;
      rows.forEach((r,i)=>{ r.style.display = (i>=page*per && i<(page+1)*per) ? '' : 'none'; });
      info.textContent = 'Hal. '+(page+1)+' / '+pages+' · '+total+' baris';
      prev.disabled = page===0; next.disabled = page>=pages-1;
      bar.style.display = total<=per ? 'none' : '';
    }
    prev.addEventListener('click', ()=>{ page--; render(true); });
    next.addEventListener('click', ()=>{ page++; render(true); });
    tbl._pgRender = render;
    render(false);
  }
  function initAll(){
    document.querySelectorAll('table:not([data-sort-init])').forEach(attachSort);
    document.querySelectorAll('table[data-paginate]:not([data-pg-init])').forEach(t=>{ t.setAttribute('data-pg-init','1'); paginate(t); });
  }
  document.addEventListener('DOMContentLoaded', initAll);
  const origSR = window.softRefresh;
  if(typeof origSR==='function'){
    window.softRefresh = async function(){ const r = await origSR.apply(this, arguments); initAll(); return r; };
  }
  new MutationObserver(()=>initAll()).observe(document.body, {childList:true, subtree:true});
})();
</script>

<script>
/* ===== Emoji picker untuk semua textarea & input teks =====
 * Otomatis pasang tombol 😊 di sebelah kanan setiap <textarea> dan
 * input[type=text] / input bertype "search". Klik untuk sisipkan emoji.
 * Juga mendukung Quill WYSIWYG (data-wysiwyg) — emoji disisipkan ke editor aktif.
 */
(function(){
  const EMOJIS = ['😀','😁','😂','🤣','😊','😍','😘','😎','🤩','🤔','🙃','😉','😴','🥳','😇','🤗','🤝','👍','👎','👏','🙏','💪','🔥','✨','⭐','🎉','🎊','💯','❤️','🧡','💛','💚','💙','💜','🖤','🤍','💔','💖','💕','💞','⚽','🏀','🏐','🎾','🏸','🏓','🏊','🏃','🚴','🏋️','🤸','🤾','🥇','🥈','🥉','🏆','🏅','🎯','📅','📆','📍','📌','📝','💬','💡','✅','❌','⚠️','❓','❗','☀️','🌧️','🌈','🌟','🍎','🍌','🥗','🍔','🍕','☕','🥤','🍺'];
  function attachPickerTo(target){
    if(!target || target.dataset.emojiInit) return;
    if(target.closest('.emoji-no')) return;
    // Skip password / file / hidden / number etc
    if(target.tagName==='INPUT'){
      const t=(target.type||'text').toLowerCase();
      if(!['text','search','email','url','tel'].includes(t)) return;
    } else if(target.tagName!=='TEXTAREA'){ return; }
    target.dataset.emojiInit='1';
    let host = target.parentElement;
    // Pastikan wrapper relative
    const wrap = document.createElement('span');
    wrap.className='emoji-picker-wrap d-inline-block';
    wrap.style.position='relative'; wrap.style.width='100%';
    host.insertBefore(wrap, target); wrap.appendChild(target);
    const btn = document.createElement('button');
    btn.type='button'; btn.className='emoji-picker-btn'; btn.title='Sisipkan emoji'; btn.textContent='😊';
    wrap.appendChild(btn);
    const pop = document.createElement('div');
    pop.className='emoji-picker-pop';
    EMOJIS.forEach(e=>{ const b=document.createElement('button'); b.type='button'; b.textContent=e; b.addEventListener('click', ev=>{ ev.preventDefault(); insertAt(target, e); pop.classList.remove('show'); target.focus(); }); pop.appendChild(b); });
    wrap.appendChild(pop);
    btn.addEventListener('click', ev=>{ ev.preventDefault(); ev.stopPropagation(); document.querySelectorAll('.emoji-picker-pop.show').forEach(p=>{ if(p!==pop) p.classList.remove('show'); }); pop.classList.toggle('show');
      const r=btn.getBoundingClientRect(); pop.style.right='0px'; pop.style.bottom=(btn.offsetHeight+6)+'px';
    });
  }
  function insertAt(el, txt){
    if(el.tagName==='TEXTAREA' || el.tagName==='INPUT'){
      const s=el.selectionStart||0, e=el.selectionEnd||0, v=el.value||'';
      el.value = v.slice(0,s)+txt+v.slice(e);
      el.selectionStart=el.selectionEnd=s+txt.length;
      el.dispatchEvent(new Event('input',{bubbles:true}));
    }
  }
  document.addEventListener('click', ev=>{
    if(!ev.target.closest('.emoji-picker-pop') && !ev.target.closest('.emoji-picker-btn')){
      document.querySelectorAll('.emoji-picker-pop.show').forEach(p=>p.classList.remove('show'));
    }
  });
  function initAll(){
    document.querySelectorAll('textarea:not([data-wysiwyg]):not([data-emoji-init])').forEach(attachPickerTo);
    document.querySelectorAll('input[type=text]:not([data-emoji-init]),input[type=search]:not([data-emoji-init]),input:not([type]):not([data-emoji-init])').forEach(attachPickerTo);
    // Quill: tambahkan tombol emoji setelah toolbar
    document.querySelectorAll('.wysiwyg-wrap:not([data-emoji-init])').forEach(w=>{
      w.dataset.emojiInit='1';
      const tb = w.querySelector('.ql-toolbar'); if(!tb) return;
      const btn=document.createElement('button'); btn.type='button'; btn.className='ql-emoji'; btn.style.cssText='border:0;background:transparent;font-size:1rem;cursor:pointer;padding:0 6px;'; btn.textContent='😊'; btn.title='Emoji';
      tb.appendChild(btn);
      const pop=document.createElement('div'); pop.className='emoji-picker-pop'; pop.style.position='absolute';
      EMOJIS.forEach(e=>{ const b=document.createElement('button'); b.type='button'; b.textContent=e; b.addEventListener('click', ev=>{ ev.preventDefault();
        const ed = w.querySelector('.ql-editor'); if(ed){ ed.focus(); document.execCommand('insertText', false, e); }
        pop.classList.remove('show');
      }); pop.appendChild(b); });
      w.style.position='relative'; w.appendChild(pop);
      btn.addEventListener('click', ev=>{ ev.preventDefault(); ev.stopPropagation();
        const r=btn.getBoundingClientRect(); const wr=w.getBoundingClientRect();
        pop.style.top=(r.bottom-wr.top+4)+'px'; pop.style.left=(r.left-wr.left)+'px';
        document.querySelectorAll('.emoji-picker-pop.show').forEach(p=>{ if(p!==pop) p.classList.remove('show'); });
        pop.classList.toggle('show');
      });
    });
  }
  document.addEventListener('DOMContentLoaded', ()=>setTimeout(initAll,150));
  new MutationObserver(()=>initAll()).observe(document.body,{childList:true,subtree:true});
})();
</script>

<!-- Revisi 3 Jun 2026: Keep-alive ping untuk Render free-tier agar tidak cold-start.
     Frontend nge-ping /api_ping.php tiap 4 menit selama tab terbuka. Tambah juga
     prefetch saat halaman pertama dibuka agar sesi berikutnya tidak terasa lambat. -->
<script>
(function(){
  function ping(){ try{ fetch('/api_ping.php',{cache:'no-store',keepalive:true}); }catch(e){} }
  // prefetch ringan saat splash/start app
  if ('requestIdleCallback' in window) requestIdleCallback(ping); else setTimeout(ping, 800);
  // interval 4 menit (Render free-tier sleep ~15 menit idle)
  setInterval(ping, 4 * 60 * 1000);
  // ping juga saat tab kembali aktif
  document.addEventListener('visibilitychange', function(){ if (!document.hidden) ping(); });
})();
</script>

<!-- Revisi 14 Juni 2026: instant navigation. Prefetch link saat user hover/touch
     supaya perpindahan halaman terasa instan seperti aplikasi mobile native. -->
<script>
(function(){
  if (!('fetch' in window)) return;
  var seen = new Set();
  function prefetch(href){
    if (!href || seen.has(href)) return;
    if (!/^https?:\/\//i.test(href) && href[0] !== '/') return;
    try {
      var u = new URL(href, location.href);
      if (u.origin !== location.origin) return;
      if (/\.(zip|pdf|jpg|jpeg|png|gif|mp4|webp)$/i.test(u.pathname)) return;
    } catch(e){ return; }
    seen.add(href);
    var l = document.createElement('link');
    l.rel = 'prefetch'; l.href = href; l.as = 'document';
    document.head.appendChild(l);
  }
  function bind(e){
    var a = e.target.closest && e.target.closest('a[href]');
    if (!a) return;
    if (a.target === '_blank' || a.hasAttribute('download')) return;
    if (a.getAttribute('href').startsWith('#')) return;
    prefetch(a.getAttribute('href'));
  }
  document.addEventListener('mouseover', bind, {passive:true});
  document.addEventListener('touchstart', bind, {passive:true});
  // Tampilkan progress bar tipis saat klik link agar terasa responsif
  var bar = document.createElement('div');
  bar.style.cssText = 'position:fixed;top:0;left:0;height:2px;width:0;background:#0ea5e9;z-index:99999;transition:width .25s ease;pointer-events:none';
  document.addEventListener('DOMContentLoaded', function(){ document.body.appendChild(bar); });
  document.addEventListener('click', function(e){
    var a = e.target.closest && e.target.closest('a[href]');
    if (!a || a.target === '_blank' || a.hasAttribute('download')) return;
    var href = a.getAttribute('href') || '';
    if (href.startsWith('#') || href.startsWith('javascript:')) return;
    bar.style.width = '0';
    requestAnimationFrame(function(){ bar.style.width = '80%'; });
  }, true);
  window.addEventListener('pageshow', function(){ bar.style.width = '0'; });
})();
</script>
</body></html>
