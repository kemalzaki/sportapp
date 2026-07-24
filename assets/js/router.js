/*!
 * KawanKeringat Router (R55 — HYBRID SHELL, refactor stabil)
 * ==========================================================
 * Penerus R54. Fokus perbaikan:
 *   - Singleton keras: kalau sudah ada router aktif, JANGAN buat baru.
 *   - Single loader: skeleton lama dipaksa mati sebelum yang baru show.
 *   - Timeout fetch 1500ms (bukan 4000ms). Lewat itu langsung fallback
 *     ke window.location.href.
 *   - SEMUA jalur gagal (fetch error, HTML kosong, selector tidak
 *     ditemukan, non-2xx, redirect lintas-url, exception JS di inline
 *     script, timeout, abort) → window.location.replace(url). Skeleton
 *     tidak pernah nyangkut.
 *   - Pause soft-refresh / notif poll / dm poll / HFLoader saat navigasi
 *     berjalan (via hybrid-boot.js). Setelah navigasi selesai, resume.
 *   - AbortController per-navigasi + cleanup event listener tiap swap.
 *   - Profiling di console: Router Start, Fetch, Replace DOM,
 *     Run Inline Script, Navigation Ready, Total waktu. Warning kalau
 *     total > 800ms.
 *   - Dedup script eksternal berdasarkan URL tanpa query (Leaflet,
 *     Chart.js, Mapbox, dsb. tidak pernah di-reinit kalau halaman
 *     tidak memerlukannya lagi).
 *
 * Tidak mengubah:
 *   - header.php / footer.php
 *   - ID/class utama, event JS lama, endpoint, session, DB, business logic
 *
 * Bergantung pada: shell.js, loader.js, navigation.js, drawer.js, hybrid-boot.js
 * (dimuat otomatis oleh file ini secara idempotent).
 *
 * API global: window.KKRouter = {
 *   navigate(url, opts), enabled, disable(), enable(),
 *   isNavigating(), onEvent(name, fn)
 * }
 */
(function () {
  'use strict';
  if (window.KKRouter && window.KKRouter.__r55) return; // singleton keras
  // Kalau ada router versi lama, matikan dulu sebelum ambil alih.
  if (window.KKRouter && typeof window.KKRouter.disable === 'function') {
    try { window.KKRouter.disable(); } catch (_) {}
  }

  var CONTENT_ID  = 'app-content';
  var FETCH_MS    = 1500;   // batas hard-fallback fetch
  var SKELETON_MS = 500;    // hard cap skeleton
  var WARN_MS     = 800;    // warning navigasi lambat

  // ---- Muat helper modules (idempotent) ---------------------------------
  var BASE = (function () {
    var s = document.currentScript;
    if (!s) return '/assets/js/';
    var src = s.getAttribute('src') || '';
    return src.replace(/router\.js.*$/, '');
  })();

  function loadOnce(name) {
    if (document.querySelector('script[data-kk-mod="' + name + '"]')) return;
    var s = document.createElement('script');
    s.src = BASE + name;
    s.defer = true;
    s.setAttribute('data-kk-mod', name);
    document.head.appendChild(s);
  }
  loadOnce('loader.js');
  loadOnce('shell.js');
  loadOnce('navigation.js');
  loadOnce('drawer.js');
  loadOnce('hybrid-boot.js');

  // ---- Konfigurasi intercept -------------------------------------------
  var OPT_IN_SELECTORS = [
    '.gj-nav',           // bottom navigation
    '#gtDrawer',         // menu drawer
    '.gt-chips',         // chip menu di header
    '[data-spa-scope]'   // opt-in eksplisit
  ];

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
    /(^|\/)upload\.php\b/i,
    /\.(zip|pdf|png|jpe?g|gif|webp|mp4|mp3|gpx|csv|xlsx?)(\?|$)/i
  ];

  var enabled = true;
  var inFlight = null;    // { ctrl, url, t0 }
  var busListeners = {};

  function emit(name, detail) {
    var list = busListeners[name] || [];
    for (var i = 0; i < list.length; i++) {
      try { list[i](detail); } catch (_) {}
    }
    try {
      document.dispatchEvent(new CustomEvent('kkrouter:' + name, { detail: detail || {} }));
    } catch (_) {}
  }
  function onEvent(name, fn) {
    (busListeners[name] = busListeners[name] || []).push(fn);
  }

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
  function hardNavigate(url, replace) {
    try { if (window.KKLoader) window.KKLoader.hide(); } catch (_) {}
    emit('end', { url: url, ok: false, fallback: true });
    try {
      if (replace) window.location.replace(url);
      else window.location.href = url;
    } catch (_) {
      try { window.location.assign(url); } catch (__) {}
    }
  }

  // ---- Navigasi utama --------------------------------------------------
  function navigate(url, opts) {
    opts = opts || {};
    if (!enabled) return hardNavigate(url);
    if (!sameOrigin(url) || shouldSkip(url)) return hardNavigate(url);

    // Shell belum siap → fallback saja, jangan tunggu.
    if (!window.KKShell || !document.getElementById(CONTENT_ID)) {
      return hardNavigate(url);
    }

    // Batalkan navigasi sebelumnya (single-flight).
    if (inFlight && inFlight.ctrl && inFlight.ctrl.abort) {
      try { inFlight.ctrl.abort(); } catch (_) {}
    }

    var ctrl = ('AbortController' in window) ? new AbortController() : null;
    var t0 = (performance && performance.now) ? performance.now() : Date.now();
    var stamps = { start: t0 };
    inFlight = { ctrl: ctrl, url: url, t0: t0, stamps: stamps };

    console.log('%c[KKRouter] Router Start', 'color:#0ea5e9', url);
    emit('start', { url: url });

    // Loader: pastikan yang lama mati dulu, baru show yang baru.
    try {
      if (window.KKLoader) {
        window.KKLoader.hide();
        window.KKLoader.show();
      }
    } catch (_) {}
    var capTimer = setTimeout(function () {
      try { if (window.KKLoader) window.KKLoader.hide(); } catch (_) {}
    }, SKELETON_MS);

    var hardTimer = setTimeout(function () {
      if (ctrl) { try { ctrl.abort(); } catch (_) {} }
    }, FETCH_MS);

    function cleanup() {
      clearTimeout(capTimer);
      clearTimeout(hardTimer);
      try { if (window.KKLoader) window.KKLoader.hide(); } catch (_) {}
      if (inFlight && inFlight.ctrl === ctrl) inFlight = null;
    }

    var fetchOpts = {
      credentials: 'same-origin',
      redirect: 'follow',
      cache: 'no-store',
      headers: {
        'X-Requested-With': 'KKRouter',
        'X-KK-Router': '1',
        'Accept': 'text/html'
      }
    };
    if (ctrl) fetchOpts.signal = ctrl.signal;

    fetch(url, fetchOpts)
      .then(function (res) {
        stamps.fetch = ((performance && performance.now) ? performance.now() : Date.now());
        console.log('%c[KKRouter] Fetch', 'color:#0ea5e9',
          (stamps.fetch - stamps.start).toFixed(1) + 'ms',
          'HTTP', res && res.status);
        if (!res || !res.ok) throw new Error('HTTP ' + (res && res.status));
        // Redirect lintas-url (mis. login) → full navigation.
        if (res.redirected && res.url && res.url !== url) {
          cleanup();
          hardNavigate(res.url);
          return null;
        }
        var ct = (res.headers.get('content-type') || '').toLowerCase();
        if (ct.indexOf('text/html') === -1) throw new Error('non-html');
        return res.text();
      })
      .then(function (html) {
        if (html == null) return;
        if (!html || html.length < 32) throw new Error('empty-html');

        var ok = false;
        try {
          ok = window.KKShell && window.KKShell.swap(html, url, {
            onReplaceDom: function () {
              stamps.replaceDom = ((performance && performance.now) ? performance.now() : Date.now());
              console.log('%c[KKRouter] Replace DOM', 'color:#0ea5e9',
                (stamps.replaceDom - stamps.start).toFixed(1) + 'ms');
            },
            onRunInlineScripts: function () {
              stamps.inline = ((performance && performance.now) ? performance.now() : Date.now());
              console.log('%c[KKRouter] Run Inline Script', 'color:#0ea5e9',
                (stamps.inline - stamps.start).toFixed(1) + 'ms');
            }
          });
        } catch (e) {
          console.warn('[KKRouter] swap error', e);
          ok = false;
        }
        if (!ok) {
          cleanup();
          hardNavigate(url, true);
          return;
        }

        // Update history hanya bila swap sukses.
        try {
          if (!opts.replace) history.pushState({ kk: 1 }, '', url);
          else history.replaceState({ kk: 1 }, '', url);
        } catch (_) {}

        try { if (window.KKNav) window.KKNav.syncActive(url); } catch (_) {}
        try { if (window.KKDrawer) window.KKDrawer.close(); } catch (_) {}

        var t1 = (performance && performance.now) ? performance.now() : Date.now();
        var total = t1 - stamps.start;
        console.log('%c[KKRouter] Navigation Ready', 'color:#16a34a',
          total.toFixed(1) + 'ms', url);
        if (total > WARN_MS) {
          console.warn('[KKRouter] SLOW navigation (' + total.toFixed(0) + 'ms > ' +
            WARN_MS + 'ms):', url, stamps);
        }

        cleanup();
        emit('end', { url: url, ok: true, total: total });

        try {
          document.dispatchEvent(new CustomEvent('spa:navigated', { detail: { url: url } }));
        } catch (_) {}
      })
      .catch(function (err) {
        console.warn('[KKRouter] fallback →', (err && err.message) || err, url);
        cleanup();
        // Semua kegagalan → replace (bukan push) supaya history bersih.
        hardNavigate(url, true);
      });
  }

  // ---- Click interceptor (opt-in only) --------------------------------
  // Gunakan handler yang ditandai supaya bisa dilepas ulang saat re-init.
  function onClick(e) {
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
    if (/^(mailto:|tel:|sms:|whatsapp:|javascript:)/i.test(href)) return;

    if (!isOptIn(a)) return;
    if (!sameOrigin(href) || shouldSkip(href)) return;

    e.preventDefault();
    navigate(a.href);
  }
  document.addEventListener('click', onClick, false);

  // ---- Back / forward ---------------------------------------------------
  function onPop(e) {
    if (!e.state || !e.state.kk) return;
    navigate(location.href, { replace: true });
  }
  window.addEventListener('popstate', onPop);

  // ---- API publik -------------------------------------------------------
  window.KKRouter = {
    __r55: true,
    navigate: navigate,
    get enabled() { return enabled; },
    disable: function () { enabled = false; },
    enable:  function () { enabled = true; },
    isNavigating: function () { return !!inFlight; },
    onEvent: onEvent
  };
})();
