</main>
<?php include __DIR__ . '/bottom_nav.php'; ?>
<footer class="app-footer text-center text-muted py-3 small">
  <div class="container">&copy; 2026 HapFam SportApp · v4 · QR Check-in · Badges · Calendar · Dark Mode · PWA</div>
</footer>
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

  // ===== Dark Mode toggle =====
  var html = document.documentElement;
  var stored = localStorage.getItem('darkMode');
  if (stored === '1') html.setAttribute('data-bs-theme','dark');
  if (stored === '0') html.setAttribute('data-bs-theme','light');
  var btn = document.getElementById('darkToggle');
  if (btn) {
    function syncIcon(){
      var d = html.getAttribute('data-bs-theme')==='dark';
      btn.innerHTML = d ? '<i class="bi bi-sun"></i>' : '<i class="bi bi-moon-stars"></i>';
    }
    syncIcon();
    btn.addEventListener('click', function(){
      var d = html.getAttribute('data-bs-theme')==='dark';
      var next = d ? 'light':'dark';
      html.setAttribute('data-bs-theme', next);
      localStorage.setItem('darkMode', next==='dark'?'1':'0');
      syncIcon();
      // persist server-side jika login
      fetch('/api_dark_mode.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'mode='+(next==='dark'?'1':'0')}).catch(()=>{});
    });
  }

  // PWA service worker
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/service-worker.js').catch(()=>{});
  }
});
</script>
</body></html>
