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

  // dark mode dihapus (selalu light)
  document.documentElement.setAttribute('data-bs-theme','light');
  try { localStorage.removeItem('darkMode'); } catch(e) {}

  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/service-worker.js').catch(()=>{});
  }
});
</script>
</body></html>
