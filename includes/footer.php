</main>
<?php include __DIR__ . '/bottom_nav.php'; ?>
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

  // Hide preloader once DOM ready
  const pre = document.getElementById('appPreloader');
  function hidePreloader(){ if(!pre) return; pre.classList.add('hidden'); setTimeout(()=>{ if(pre && pre.parentNode) pre.remove(); }, 400); }
  // Skip preloader entirely if flagged (after form submit like login/upload simpan)
  try {
    if (sessionStorage.getItem('skipPreloader') === '1') {
      sessionStorage.removeItem('skipPreloader');
      if (pre) { pre.style.display='none'; pre.remove(); }
    } else {
      setTimeout(hidePreloader, 200);
    }
  } catch(e){ setTimeout(hidePreloader, 200); }

  // Fix preloader stuck on back/forward (BFCache)
  window.addEventListener('pageshow', function(ev){
    document.querySelectorAll('#appPreloader').forEach(function(el){ el.classList.add('hidden'); el.style.display='none'; setTimeout(()=>el.remove(),200); });
  });
  window.addEventListener('popstate', function(){
    document.querySelectorAll('#appPreloader').forEach(function(el){ el.classList.add('hidden'); el.style.display='none'; setTimeout(()=>el.remove(),200); });
  });

  // Show preloader briefly on internal link navigation; auto-hide after 4s as safety
  document.body.addEventListener('click', function(ev){
    const a = ev.target.closest('a');
    if(!a) return;
    const href = a.getAttribute('href') || '';
    if(!href || href.startsWith('#') || a.target==='_blank' || a.hasAttribute('download')) return;
    if(a.hasAttribute('data-no-preloader')) return;
    try{
      const u = new URL(href, location.href);
      if(u.origin !== location.origin) return;
      const p = document.createElement('div'); p.id='appPreloader'; p.innerHTML='<div class="spinner"></div><div class="lbl">Memuat…</div>';
      document.body.appendChild(p);
      setTimeout(()=>{ p.classList.add('hidden'); setTimeout(()=>p.remove(),300); }, 4000);
    }catch(e){}
  });

  // Skip preloader saat submit form yang ditandai data-skip-preloader (login, upload simpan, dll.)
  document.querySelectorAll('form[data-skip-preloader]').forEach(function(f){
    f.addEventListener('submit', function(){ try{ sessionStorage.setItem('skipPreloader','1'); }catch(e){} });
  });
});

/* === Soft auto-refresh (tanpa reload page) ===
 * Setiap 25 detik, fetch ulang HTML halaman aktif dan replace bagian ber-attribute [data-live].
 * Cara pakai: bungkus section dengan <div data-live="forum"> ... </div>
 * Halaman lain (riwayat, profile, dll) bisa pakai pola yang sama tanpa perubahan JS.
 */
(function(){
  const LIVE_INTERVAL = 25000;
  let busy=false;
  async function softRefresh(){
    if(busy || document.hidden) return;
    busy=true;
    try{
      const r=await fetch(location.pathname+location.search, {headers:{'X-Soft-Refresh':'1'}, credentials:'same-origin'});
      if(!r.ok) return;
      const html=await r.text();
      const doc=new DOMParser().parseFromString(html,'text/html');
      let changed=false;
      const liveNodes = document.querySelectorAll('[data-live]');
      liveNodes.forEach(node=>{
        const key=node.getAttribute('data-live');
        const fresh=doc.querySelector('[data-live="'+key+'"]');
        if(fresh && fresh.innerHTML !== node.innerHTML){
          node.innerHTML=fresh.innerHTML; changed=true;
        }
      });
      // Fallback: jika halaman tidak punya [data-live], refresh isi <main>
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

  // Tombol "Refresh" generik: <button data-soft-refresh> → trigger soft refresh tanpa reload
  document.addEventListener('click', function(ev){
    const b=ev.target.closest('[data-soft-refresh]');
    if(!b) return;
    ev.preventDefault();
    softRefresh();
  });
})();
</script>

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
  const compressed = await window.compressImageFile(inp.files[0]);
  if(compressed !== inp.files[0]){
    const dt = new DataTransfer(); dt.items.add(compressed); inp.files = dt.files;
  }
  if(lbl){
    const kb = (inp.files[0].size/1024).toFixed(0);
    const oKb = (origSize/1024).toFixed(0);
    lbl.textContent = origSize>inp.files[0].size ? ('Optimasi: '+oKb+' KB → '+kb+' KB') : ('Ukuran: '+kb+' KB');
  }
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
  ev.preventDefault();
  const btn = f.querySelector('button[type=submit],button:not([type])');
  if(btn) btn.disabled = true;
  showMiniLoader(f.getAttribute('data-ajax-label')||'Menyimpan...');
  try{
    const fd = new FormData(f);
    const r = await fetch(f.action || location.pathname+location.search, {
      method: (f.method||'POST').toUpperCase(),
      body: fd, credentials:'same-origin', headers:{'X-Requested-With':'fetch'}
    });
    if(r.ok || r.redirected){
      if(typeof window.softRefresh==='function') await window.softRefresh();
      // Bersihkan input teks pesan/komentar
      f.querySelectorAll('input[name="pesan"],input[name="isi"],textarea[name="caption"]').forEach(i=>{ i.value=''; });
      // Tutup modal bila ada
      const md = f.closest('.modal');
      if(md){ const inst = bootstrap.Modal.getInstance(md); if(inst) inst.hide(); }
      // Reset file input
      f.querySelectorAll('input[type=file]').forEach(i=>{ i.value=''; });
      const prev = f.querySelector('img[id$="Preview"]'); if(prev){ prev.style.display='none'; prev.src=''; }
    }
  }catch(e){ /* silent */ }
  finally{
    if(btn) btn.disabled = false;
    hideMiniLoader();
  }
});

/* ===== Generic tabel pagination (table[data-paginate="N"]) ===== */
(function(){
  function paginate(tbl){
    const per = parseInt(tbl.getAttribute('data-paginate')||'10',10) || 10;
    const tbody = tbl.tBodies[0]; if(!tbody) return;
    const rows = Array.from(tbody.rows);
    if(rows.length <= per) return;
    let page = 0;
    const bar = document.createElement('div'); bar.className='pagination-bar';
    const info = document.createElement('div'); info.className='pg-info text-muted';
    const btns = document.createElement('div'); btns.className='pg-btns';
    const prev = document.createElement('button'); prev.type='button'; prev.innerHTML='‹ Prev';
    const next = document.createElement('button'); next.type='button'; next.innerHTML='Next ›';
    btns.appendChild(prev); btns.appendChild(next);
    bar.appendChild(info); bar.appendChild(btns);
    tbl.parentNode.insertBefore(bar, tbl.nextSibling);
    function render(){
      const total = rows.length, pages = Math.ceil(total/per);
      if(page>=pages) page=pages-1; if(page<0) page=0;
      rows.forEach((r,i)=>{ r.style.display = (i>=page*per && i<(page+1)*per) ? '' : 'none'; });
      info.textContent = 'Hal. '+(page+1)+' / '+pages+' · '+total+' baris';
      prev.disabled = page===0; next.disabled = page>=pages-1;
    }
    prev.addEventListener('click', ()=>{ page--; render(); });
    next.addEventListener('click', ()=>{ page++; render(); });
    render();
  }
  function initAll(){ document.querySelectorAll('table[data-paginate]:not([data-pg-init])').forEach(t=>{ t.setAttribute('data-pg-init','1'); paginate(t); }); }
  document.addEventListener('DOMContentLoaded', initAll);
  // Re-init setelah soft refresh mengganti innerHTML region
  const origSR = window.softRefresh;
  if(typeof origSR==='function'){
    window.softRefresh = async function(){ const r = await origSR.apply(this, arguments); initAll(); return r; };
  }
  // Observer untuk tabel yang dimuat dinamis
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
</body></html>
