/*!
 * KawanKeringat Drawer helper (R54 — Hybrid Shell)
 * ------------------------------------------------
 * Menutup offcanvas drawer (#gtDrawer) setelah navigasi SPA berhasil,
 * agar UX konsisten dengan full-navigation biasa. Tidak mengubah markup
 * drawer, tidak mengganti event bootstrap.
 *
 * API: window.KKDrawer = { close() }
 */
(function () {
  'use strict';
  if (window.KKDrawer) return;

  function close() {
    try {
      var el = document.getElementById('gtDrawer');
      if (!el) return;
      if (window.bootstrap && bootstrap.Offcanvas) {
        var inst = bootstrap.Offcanvas.getInstance(el) || new bootstrap.Offcanvas(el);
        inst.hide();
      } else {
        el.classList.remove('show');
        document.body.classList.remove('offcanvas-open');
        var bd = document.querySelector('.offcanvas-backdrop');
        if (bd && bd.parentNode) bd.parentNode.removeChild(bd);
      }
    } catch (_) {}
  }

  window.KKDrawer = { close: close };
})();
