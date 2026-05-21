</main>
<footer class="app-footer">
  <div class="container">
    &copy; 2026 HapFam SportApp &middot; Transformasi Sistem Absensi &amp; Monitoring Performa Olahraga &mdash; Versi 2
  </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('.modal').forEach(function(m) { document.body.appendChild(m); });
  // Inisialisasi Quill WYSIWYG untuk semua textarea[data-wysiwyg]
  document.querySelectorAll('textarea[data-wysiwyg]').forEach(function(ta){
    var holder = document.createElement('div');
    holder.style.minHeight = '120px';
    ta.style.display = 'none';
    ta.parentNode.insertBefore(holder, ta);
    holder.innerHTML = ta.value || '';
    var q = new Quill(holder, {
      theme: 'snow',
      modules: { toolbar: [['bold','italic','underline','strike'],[{list:'ordered'},{list:'bullet'}],['link','clean']] }
    });
    var form = ta.closest('form');
    if (form) form.addEventListener('submit', function(){ ta.value = q.root.innerHTML; });
  });
});
</script>
</body></html>
