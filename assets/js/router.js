/*!
 * KawanKeringat Router (R54 — HYBRID SHELL, bukan SPA penuh)
 * ==========================================================
 * Pengganti router R53 yang menyebabkan halaman berhenti di skeleton.
 *
 * Filosofi:
 *   - App tetap PHP multi-page + server-side rendering.
 *   - Router HANYA mengintercept link tertentu (bottom nav, drawer,
 *     chip menu, atau link yang eksplisit ditandai data-spa="1").
 *   - Semua link lain berjalan normal (full navigation).
 *   - Skeleton HARD-CAP 500ms — tidak boleh ada loading tanpa batas.
 *   - Bila router gagal (network error, timeout, respons tanpa #app-content,
 *     status non-2xx) → langsung window.location.href = url.
 *
 * Tidak mengubah:
 *   - header.php / footer.php
 *   - ID / class utama / event JS lama
 *   - endpoint / database / business logic
 *
 * Bergantung pada: shell.js, loader.js, navigation.js, drawer.js
 * (dimuat otomatis oleh file ini secara idempotent).
 *
 * API global: window.KKRouter = { navigate(url), enabled, disable(), enable() }
 */
(function () {
  'use strict';
  if (window.KKRouter) return;

  var CONTENT_ID = 'app-content';
  var TIMEOUT_MS = 4000;   // batas total request
  var SKELETON_MS = 500;   // hard cap skeleton

  // ---- Muat helper modules (idempotent) ---------------------------------
  var BASE = (function () {
    var s = document.currentScript;
    if (!s) return '/assets/js/';
    var src = s.getAttribute('src') || '';
    return src.replace(/router\.js.*$/, '');
  })();

  function loadOnce(name) {
    var full = BASE + name;
    if (document.querySelector('script[data-kk-mod="' + name + '"]')) return;
    var s = document.createElement('script');
    s.src = full;
    s.defer = true;
    s.setAttribute('data-kk-mod', name);
    document.head.appendChild(s);
  }
  loadOnce('loader.js');
  loadOnce('shell.js');
  loadOnce('navigation.js');
  loadOnce('drawer.js');

  // ---- Konfigurasi intercept -------------------------------------------
  // HANYA link di dalam selector berikut yang di-SPA-kan.
  var OPT_IN_SELECTORS = [
    '.gj-nav',           // bottom navigation (includes/bottom_nav.php)
    '#gtDrawer',         // menu drawer (includes/header.php)
    '.gt-chips',         // chip menu di header
    '[data-spa-scope]'   // opt-in eksplisit oleh halaman lain (opsional)
  ];

  // Halaman yang HARUS full-reload meskipun link-nya ada di scope di atas.
  var SKIP_PATTERNS = [
    /(^|\/)run\.php\b/i,
    /(^|\/)live_tracking\.php\b/i,
    /(^|\/)activity_detail\.php\b/i,
    /(^|\/)login\.php\b/i,
    /(^|\/)logout\.php\b/i,
    /(^|\/)register\.php\b/i,
    /(^|\/)splash\.php\b/i,
    /(^|\/)onboarding\.php\b/i,
    /(^|\/)manifest\.php\b/i,
    /(^|\/)admin(\/|\b)/i,
    /(^|\/)upload\.php\b/i,           // upload state kompleks
    /\.(zip|pdf|png|jpe?g|gif|webp|mp4|mp3|gpx|csv|xlsx?)(\?|$)/i
  ];

  var enabled = true;
  var inFlight = null;

  function sameOrigin(url) {
    try { return new URL(url, location.href).origin === location.origin; }
    catch (_) { return false; }
  }
  function shouldSkip(url) {
    try {
      var p = new URL(url, location.href).pathname;
      for (var i = 0; i < SKIP_PATTERNS.length; i++)
        if (SKIP_PATTERNS[i].test(p)) return true;
    } catch (_) { return true; }
    return false;
  }
  function isOptIn(anchor) {
    if (!anchor || anchor.nodeType !== 1) return false;
    if (anchor.hasAttribute('data-no-spa')) return false;
    if (anchor.hasAttribute('data-spa')) return true;
    for (var i = 0; i < OPT_IN_SELECTORS.length; i++) {
      if (anchor.closest(OPT_IN_SELECTORS[i])) return true;
    }
    return false;
  }

  // ---- Fallback keras --------------------------------------------------
  function hardNavigate(url) {
    try { if (window.KKLoader) KKLoader.hide(); } catch (_) {}
    try { window.location.href = url; }
    catch (_) { window.location.assign(url); }
  }

  // ---- Navigasi utama --------------------------------------------------
  function navigate(url, opts) {
    opts = opts || {};
    if (!enabled) return hardNavigate(url);
    if (!sameOrigin(url) || shouldSkip(url)) return hardNavigate(url);

    // Belum siap? Fallback saja — jangan tunggu.
    if (!window.KKShell || !document.getElementById(CONTENT_ID)) {
      return hardNavigate(url);
    }

    // Batalkan request sebelumnya kalau ada.
    if (inFlight && inFlight.abort) { try { inFlight.abort(); } catch (_) {} }

    var ctrl = ('AbortController' in window) ? new AbortController() : null;
    inFlight = ctrl;

    // Skeleton dengan HARD CAP.
    try { if (window.KKLoader) KKLoader.show(); } catch (_) {}
    var capTimer = setTimeout(function () {
      try { if (window.KKLoader) KKLoader.hide(); } catch (_) {}
    }, SKELETON_MS);

    var timeoutTimer = setTimeout(function () {
      if (ctrl) { try { ctrl.abort(); } catch (_) {} }
    }, TIMEOUT_MS);

    var fetchOpts = {
      credentials: 'same-origin',
      redirect: 'follow',
      headers: { 'X-Requested-With': 'KKRouter', 'Accept': 'text/html' }
    };
    if (ctrl) fetchOpts.signal = ctrl.signal;

    fetch(url, fetchOpts)
      .then(function (res) {
        if (!res || !res.ok) throw new Error('HTTP ' + (res && res.status));
        // Kalau server redirect ke URL lain (mis. login), pakai full navigation.
        if (res.redirected && res.url && res.url !== url) {
          hardNavigate(res.url);
          return null;
        }
        var ct = (res.headers.get('content-type') || '').toLowerCase();
        if (ct.indexOf('text/html') === -1) throw new Error('non-html');
        return res.text();
      })
      .then(function (html) {
        if (html == null) return;
        var ok = window.KKShell && window.KKShell.swap(html, url);
        if (!ok) { hardNavigate(url); return; }

        // Update history hanya bila swap sukses.
        try {
          if (!opts.replace) history.pushState({ kk: 1 }, '', url);
          else history.replaceState({ kk: 1 }, '', url);
        } catch (_) {}

        try { if (window.KKNav) KKNav.syncActive(url); } catch (_) {}
        try { if (window.KKDrawer) KKDrawer.close(); } catch (_) {}

        try {
          document.dispatchEvent(new CustomEvent('spa:navigated', {
            detail: { url: url }
          }));
        } catch (_) {}
      })
      .catch(function () {
        hardNavigate(url);
      })
      .then(function () {
        clearTimeout(capTimer);
        clearTimeout(timeoutTimer);
        try { if (window.KKLoader) KKLoader.hide(); } catch (_) {}
        if (inFlight === ctrl) inFlight = null;
      });
  }

  // ---- Click interceptor (opt-in only) --------------------------------
  document.addEventListener('click', function (e) {
    if (!enabled) return;
    if (e.defaultPrevented) return;
    if (e.button !== 0) return;
    if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;

    var a = e.target && e.target.closest ? e.target.closest('a[href]') : null;
    if (!a) return;
    if (a.target && a.target !== '' && a.target !== '_self') return;
    if (a.hasAttribute('download')) return;
    var href = a.getAttribute('href');
    if (!href || href.charAt(0) === '#') return;
    if (/^(mailto:|tel:|javascript:)/i.test(href)) return;

    if (!isOptIn(a)) return;                       // <-- kunci hybrid shell
    if (!sameOrigin(href) || shouldSkip(href)) return;

    e.preventDefault();
    navigate(a.href);
  }, false);

  // ---- Back / forward ---------------------------------------------------
  window.addEventListener('popstate', function (e) {
    // Kalau state bukan milik kita, biarkan browser handle normal.
    if (!e.state || !e.state.kk) return;
    navigate(location.href, { replace: true });
  });

  // ---- API publik -------------------------------------------------------
  window.KKRouter = {
    navigate: navigate,
    get enabled() { return enabled; },
    disable: function () { enabled = false; },
    enable:  function () { enabled = true; }
  };
})();
