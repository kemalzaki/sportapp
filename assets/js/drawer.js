/*!
 * KawanKeringat Drawer helper (R55 — Hybrid Shell)
 * ------------------------------------------------
 * Menutup offcanvas drawer (#gtDrawer) setelah navigasi SPA berhasil.
 * Tidak mengubah markup drawer atau event bootstrap.
 *
 * API: window.KKDrawer = { close() }
 */
(function () {
  'use strict';
  if (window.KKDrawer && window.KKDrawer.__r55) return;

  function close() {
    try {
      var el = document.getElementById('gtDrawer');
      if (!el) return;
      if (window.bootstrap && window.bootstrap.Offcanvas) {
        var inst = window.bootstrap.Offcanvas.getInstance(el)
                || window.bootstrap.Offcanvas.getOrCreateInstance(el);
        if (inst) inst.hide();
      } else {
        el.classList.remove('show');
        document.body.classList.remove('offcanvas-open');
        document.body.style.overflow = '';
        var bd = document.querySelector('.offcanvas-backdrop');
        if (bd && bd.parentNode) bd.parentNode.removeChild(bd);
      }
    } catch (_) {}
  }

  window.KKDrawer = { __r55: true, close: close };
})();
