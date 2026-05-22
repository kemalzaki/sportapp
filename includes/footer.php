</main>
<?php include __DIR__ . '/bottom_nav.php'; ?>
<footer class="app-footer text-center text-muted py-3 small">
  <div class="container">&copy; 2026 HapFam SportApp · v4</div>
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
      document.querySelectorAll('[data-live]').forEach(node=>{
        const key=node.getAttribute('data-live');
        const fresh=doc.querySelector('[data-live="'+key+'"]');
        if(fresh && fresh.innerHTML !== node.innerHTML){
          node.innerHTML=fresh.innerHTML; changed=true;
        }
      });
      if(changed){
        const b=document.getElementById('liveRefreshBadge');
        if(b){ b.style.display='inline-block'; setTimeout(()=>b.style.display='none',1500); }
      }
    }catch(e){}
    finally{ busy=false; }
  }
  if(document.querySelector('[data-live]')) setInterval(softRefresh, LIVE_INTERVAL);

  // Tombol "Refresh" generik: <button data-soft-refresh> → trigger soft refresh tanpa reload
  document.addEventListener('click', function(ev){
    const b=ev.target.closest('[data-soft-refresh]');
    if(!b) return;
    ev.preventDefault();
    softRefresh();
  });
})();
</script>
</body></html>
