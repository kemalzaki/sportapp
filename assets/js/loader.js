/*!
 * KawanKeringat Loader (R54 — Hybrid Shell)
 * -----------------------------------------
 * Skeleton overlay ringan dengan HARD CAP 500ms.
 * Jika request halaman belum selesai dalam 500ms, skeleton disembunyikan
 * agar user tidak pernah terjebak di loading kosong.
 *
 * API: window.KKLoader = { show(), hide(), flash(ms) }
 *
 * Catatan: tidak mengganti markup halaman, tidak mengubah #app-content,
 * hanya menampilkan overlay tipis yang berdiri sendiri.
 */
(function () {
  'use strict';
  if (window.KKLoader) return;

  var MAX_MS = 500;
  var el = null;
  var timer = null;
  var showAt = 0;

  function ensure() {
    if (el) return el;
    el = document.getElementById('kkLoaderOverlay');
    if (el) return el;
    el = document.createElement('div');
    el.id = 'kkLoaderOverlay';
    el.setAttribute('aria-hidden', 'true');
    el.style.cssText = [
      'position:fixed', 'left:0', 'right:0',
      'top:56px', 'bottom:64px',
      'background:transparent',
      'pointer-events:none',
      'z-index:1040',
      'opacity:0',
      'transition:opacity .15s ease-out',
      'display:none'
    ].join(';');
    el.innerHTML =
      '<div style="height:3px;background:linear-gradient(90deg,transparent,#0ea5e9,transparent);' +
      'background-size:200% 100%;animation:kkbar 1s linear infinite"></div>';
    var st = document.createElement('style');
    st.textContent = '@keyframes kkbar{0%{background-position:100% 0}100%{background-position:-100% 0}}';
    document.head.appendChild(st);
    document.body.appendChild(el);
    return el;
  }

  function show() {
    var n = ensure();
    n.style.display = 'block';
    // force reflow so transition kicks in
    void n.offsetHeight;
    n.style.opacity = '1';
    showAt = Date.now();
    if (timer) clearTimeout(timer);
    timer = setTimeout(hide, MAX_MS);
  }

  function hide() {
    if (timer) { clearTimeout(timer); timer = null; }
    if (!el) return;
    el.style.opacity = '0';
    setTimeout(function () { if (el) el.style.display = 'none'; }, 160);
  }

  function flash(ms) {
    show();
    setTimeout(hide, Math.min(MAX_MS, ms || 200));
  }

  window.KKLoader = { show: show, hide: hide, flash: flash };
})();
