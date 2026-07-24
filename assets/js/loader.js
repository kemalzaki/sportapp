/*!
 * KawanKeringat Loader (R55 — Hybrid Shell)
 * -----------------------------------------
 * Skeleton bar tipis 3px di atas layar dengan HARD CAP 500ms.
 * Kalau show() dipanggil ulang saat loader lama masih tampil, timer lama
 * dibatalkan dan diganti — hanya boleh ada SATU skeleton aktif.
 *
 * API: window.KKLoader = { show(), hide(), flash(ms) }
 */
(function () {
  'use strict';
  if (window.KKLoader && window.KKLoader.__r55) return;

  var MAX_MS = 500;
  var el = null;
  var timer = null;
  var hideDelay = null;

  function ensure() {
    if (el && document.body.contains(el)) return el;
    el = document.getElementById('kkLoaderOverlay');
    if (el) return el;
    el = document.createElement('div');
    el.id = 'kkLoaderOverlay';
    el.setAttribute('aria-hidden', 'true');
    el.style.cssText = [
      'position:fixed', 'left:0', 'right:0', 'top:0',
      'height:3px',
      'pointer-events:none',
      'z-index:2147483000',
      'opacity:0',
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
    (document.body || document.documentElement).appendChild(el);
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
    // paksa reflow
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

  window.KKLoader = { __r55: true, show: show, hide: hide, flash: flash };
})();
