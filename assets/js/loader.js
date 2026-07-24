/*!
 * KawanKeringat Loader (R56 — App Shell)
 * --------------------------------------
 * Progress bar tipis 3px yang HANYA berada di atas #app-content, bukan
 * overlay seluruh aplikasi. Header, bottom nav, dan drawer tidak
 * pernah tertutup loader ini.
 *
 * API: window.KKLoader = { show(), hide(), flash(ms) }
 */
(function () {
  'use strict';
  if (window.KKLoader && window.KKLoader.__r56) return;

  var MAX_MS = 800;
  var el = null;
  var timer = null;
  var hideDelay = null;

  function host() {
    return document.getElementById('app-content') || document.body;
  }

  function ensure() {
    if (el && document.body.contains(el)) return el;
    el = document.getElementById('kkLoaderOverlay');
    if (el && el.parentNode) el.parentNode.removeChild(el);
    el = document.createElement('div');
    el.id = 'kkLoaderOverlay';
    el.setAttribute('aria-hidden', 'true');
    el.style.cssText = [
      'position:absolute','left:0','right:0','top:0',
      'height:3px','pointer-events:none',
      'z-index:50','opacity:0',
      'transition:opacity .12s linear',
      'display:none',
      'background:linear-gradient(90deg,transparent,#0ea5e9,transparent)',
      'background-size:200% 100%'
    ].join(';');
    var st = document.getElementById('kkLoaderStyle');
    if (!st) {
      st = document.createElement('style');
      st.id = 'kkLoaderStyle';
      st.textContent =
        '@keyframes kkbar{0%{background-position:100% 0}100%{background-position:-100% 0}}' +
        '#kkLoaderOverlay.kk-on{animation:kkbar 1s linear infinite}';
      document.head.appendChild(st);
    }
    var h = host();
    // Pastikan host punya position relative supaya bar menempel di atasnya.
    var cs = getComputedStyle(h);
    if (cs.position === 'static') h.style.position = 'relative';
    h.appendChild(el);
    return el;
  }

  function clearAll() {
    if (timer) { clearTimeout(timer); timer = null; }
    if (hideDelay) { clearTimeout(hideDelay); hideDelay = null; }
  }

  function show() {
    var n = ensure();
    clearAll();
    n.style.display = 'block';
    void n.offsetHeight;
    n.classList.add('kk-on');
    n.style.opacity = '1';
    timer = setTimeout(hide, MAX_MS);
  }

  function hide() {
    clearAll();
    if (!el) return;
    el.style.opacity = '0';
    el.classList.remove('kk-on');
    hideDelay = setTimeout(function () {
      if (el) el.style.display = 'none';
      hideDelay = null;
    }, 140);
  }

  function flash(ms) {
    show();
    clearAll();
    timer = setTimeout(hide, Math.min(MAX_MS, ms || 200));
  }

  window.KKLoader = { __r56: true, show: show, hide: hide, flash: flash };
})();
