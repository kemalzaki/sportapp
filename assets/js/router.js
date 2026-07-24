/*!
 * KawanKeringat Router (R56 — APP SHELL + FRAGMENT, refaktor total)
 * =================================================================
 * Perubahan besar dari R55:
 *   - JANGAN fetch seluruh halaman PHP. Router memanggil endpoint
 *     fragment: <url>?fragment=1 (dengan header X-KK-Fragment: 1).
 *     Respons PHP hanya berisi isi <main> — tanpa <html>, <head>,
 *     header.php, footer.php, bootstrap, firebase, service worker,
 *     notif polling, HFLoader, softRefresh, drawer, atau bottom nav.
 *   - JANGAN parse seluruh HTML. Response body ditempel langsung ke
 *     innerHTML #app-content. Judul & skeleton diambil dari response
 *     header (X-KK-Title, X-KK-Skeleton).
 *   - Header, footer, bottom nav, drawer, firebase, service worker,
 *     dan seluruh script global HANYA berjalan sekali (di full-load
 *     pertama). Router tidak pernah menyentuhnya.
 *   - Soft-refresh, notif poll, dm poll di-pause selama navigasi;
 *     otomatis resume setelah swap (diatur oleh hybrid-boot.js R56).
 *   - Halaman berat (Beranda, Riwayat, Kalori, Profil, Tempat,
 *     Forum) di-cache in-memory supaya back/forward instan.
 *   - Halaman berikut TETAP full reload: run.php, live_tracking.php,
 *     activity_detail.php, upload.php, login.php, logout.php,
 *     register.php, admin/*, splash.php, onboarding.php.
 *   - AbortController per navigasi + fallback keras ke
 *     window.location.replace(url) untuk SEMUA jalur gagal.
 *   - Target navigasi: <300ms. Warning kalau lewat.
 *
 * Tidak mengubah: ID HTML, class utama, event JS lama, endpoint,
 * struktur database, business logic (tracking, GPS, upload, save,
 * riwayat, screenshot, fullscreen, pause, stop, review).
 *
 * API global: window.KKRouter = {
 *   navigate(url, opts), enable(), disable(), isNavigating(),
 *   onEvent(name, fn), invalidate(url?), enabled
 * }
 */
(function () {
  'use strict';
  if (window.KKRouter && window.KKRouter.__r56) return;
  if (window.KKRouter && typeof window.KKRouter.disable === 'function') {
    try { window.KKRouter.disable(); } catch (_) {}
  }

  var CONTENT_ID  = 'app-content';
  var FETCH_MS    = 4000;
  var WARN_MS     = 300;
  var CACHE_MAX   = 12;
  var CACHE_TTL   = 5 * 60 * 1000;

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

  var OPT_IN_SELECTORS = [
    '.gj-nav', '#gtDrawer', '.gt-chips', '[data-spa-scope]'
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

  var CACHEABLE = [
    /(^|\/)(index\.php)?$/i,
    /(^|\/)riwayat\.php/i,
    /(^|\/)kalori_mingguan\.php/i,
    /(^|\/)profile\.php/i,
    /(^|\/)tempat\.php/i,
    /(^|\/)(forum|challenge)\.php/i
  ];

  var enabled = true;
  var inFlight = null;
  var busListeners = {};
  var cache = new Map();

  function perfNow() {
    return (performance && performance.now) ? performance.now() : Date.now();
  }
  function emit(name, detail) {
    var list = busListeners[name] || [];
    for (var i = 0; i < list.length; i++) {
      try { list[i](detail); } catch (_) {}
    }
    try { document.dispatchEvent(new CustomEvent('kkrouter:' + name, { detail: detail || {} })); } catch (_) {}
  }
  function onEvent(name, fn) { (busListeners[name] = busListeners[name] || []).push(fn); }

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
  function isCacheable(url) {
    try {
      var p = new URL(url, location.href).pathname;
      for (var i = 0; i < CACHEABLE.length; i++)
        if (CACHEABLE[i].test(p)) return true;
    } catch (_) {}
    return false;
  }
  function isOptIn(a) {
    if (!a || a.nodeType !== 1) return false;
    if (a.hasAttribute('data-no-spa')) return false;
    if (a.hasAttribute('data-spa')) return true;
    for (var i = 0; i < OPT_IN_SELECTORS.length; i++) {
      if (a.closest(OPT_IN_SELECTORS[i])) return true;
    }
    return false;
  }

  function cacheKey(url) {
    try {
      var u = new URL(url, location.href);
      u.searchParams.delete('fragment');
      return u.pathname + (u.search || '');
    } catch (_) { return url; }
  }
  function cacheGet(url) {
    var v = cache.get(cacheKey(url));
    if (!v) return null;
    if ((Date.now() - v.at) > CACHE_TTL) { cache.delete(cacheKey(url)); return null; }
    return v;
  }
  function cachePut(url, entry) {
    if (!isCacheable(url)) return;
    entry.at = Date.now();
    cache.set(cacheKey(url), entry);
    while (cache.size > CACHE_MAX) cache.delete(cache.keys().next().value);
  }
  function invalidate(url) {
    if (!url) { cache.clear(); return; }
    cache.delete(cacheKey(url));
  }

  function hardNavigate(url, replace) {
    try { if (window.KKLoader) window.KKLoader.hide(); } catch (_) {}
    emit('end', { url: url, ok: false, fallback: true });
    try {
      if (replace) window.location.replace(url); else window.location.href = url;
    } catch (_) { try { window.location.assign(url); } catch (__) {} }
  }

  function toFragmentUrl(url) {
    try {
      var u = new URL(url, location.href);
      u.searchParams.set('fragment', '1');
      return u.toString();
    } catch (_) { return url; }
  }

  function applyFragment(url, entry, opts, stamps) {
    var ok = false;
    try {
      ok = window.KKShell && window.KKShell.applyFragment(entry.body, {
        title: entry.title,
        skeleton: entry.skeleton,
        url: url,
        onSwap: function () {
          stamps.swap = perfNow();
          console.log('%c[KKRouter] Swap', 'color:#0ea5e9',
            (stamps.swap - stamps.start).toFixed(1) + 'ms');
        }
      });
    } catch (e) {
      console.warn('[KKRouter] applyFragment error', e);
      ok = false;
    }
    if (!ok) { hardNavigate(url, true); return false; }

    try {
      if (!opts.replace) history.pushState({ kk: 1 }, '', url);
      else history.replaceState({ kk: 1 }, '', url);
    } catch (_) {}

    try { if (window.KKNav) window.KKNav.syncActive(url); } catch (_) {}
    try { if (window.KKDrawer) window.KKDrawer.close(); } catch (_) {}

    var total = perfNow() - stamps.start;
    console.log('%c[KKRouter] Navigation Ready', 'color:#16a34a',
      total.toFixed(1) + 'ms', url);
    if (total > WARN_MS) {
      console.warn('[KKRouter] SLOW navigation (' + total.toFixed(0) +
        'ms > ' + WARN_MS + 'ms):', url, stamps);
    }
    emit('end', { url: url, ok: true, total: total });
    try { document.dispatchEvent(new CustomEvent('spa:navigated', { detail: { url: url } })); } catch (_) {}
    return true;
  }

  function fetchFragment(url, ctrl) {
    var target = toFragmentUrl(url);
    var opts = {
      credentials: 'same-origin',
      redirect: 'follow',
      cache: 'no-store',
      headers: {
        'X-Requested-With': 'KKRouter',
        'X-KK-Router': '1',
        'X-KK-Fragment': '1',
        'Accept': 'text/html'
      }
    };
    if (ctrl) opts.signal = ctrl.signal;
    return fetch(target, opts).then(function (res) {
      if (!res || !res.ok) throw new Error('HTTP ' + (res && res.status));
      if (res.redirected && res.url) {
        // Redirect (mis. ke /login.php) → full navigation ke URL bersih.
        var clean = res.url
          .replace(/([?&])fragment=1(&|$)/, function (_m, a, b) { return b ? a : ''; })
          .replace(/[?&]$/, '');
        hardNavigate(clean);
        return null;
      }
      var isFrag = (res.headers.get('X-KK-Fragment') === '1');
      var title = res.headers.get('X-KK-Title');
      var skeleton = res.headers.get('X-KK-Skeleton') || '';
      try { if (title) title = decodeURIComponent(title); } catch (_) {}
      return res.text().then(function (body) {
        if (!isFrag) {
          // Server tidak mengembalikan fragment (halaman lama / cache).
          // Aman: full navigate supaya tidak double-render header/footer.
          hardNavigate(url, true);
          return null;
        }
        if (body == null) throw new Error('empty-body');
        return { body: body, title: title || document.title, skeleton: skeleton };
      });
    });
  }

  function navigate(url, opts) {
    opts = opts || {};
    if (!enabled) return hardNavigate(url);
    if (!sameOrigin(url) || shouldSkip(url)) return hardNavigate(url);
    if (!window.KKShell || !document.getElementById(CONTENT_ID)) return hardNavigate(url);

    if (inFlight && inFlight.ctrl && inFlight.ctrl.abort) {
      try { inFlight.ctrl.abort(); } catch (_) {}
    }

    var ctrl = ('AbortController' in window) ? new AbortController() : null;
    var t0 = perfNow();
    var stamps = { start: t0 };
    inFlight = { ctrl: ctrl, url: url, t0: t0, stamps: stamps };

    console.log('%c[KKRouter] Router Start', 'color:#0ea5e9', url);
    emit('start', { url: url });

    try { if (window.KKLoader) { window.KKLoader.hide(); window.KKLoader.show(); } } catch (_) {}

    var hardTimer = setTimeout(function () {
      if (ctrl) { try { ctrl.abort(); } catch (_) {} }
    }, FETCH_MS);

    function cleanup() {
      clearTimeout(hardTimer);
      try { if (window.KKLoader) window.KKLoader.hide(); } catch (_) {}
      if (inFlight && inFlight.ctrl === ctrl) inFlight = null;
    }

    var hit = cacheGet(url);
    if (hit) {
      stamps.fetch = perfNow();
      console.log('%c[KKRouter] Cache HIT', 'color:#a855f7',
        (stamps.fetch - stamps.start).toFixed(1) + 'ms', url);
      applyFragment(url, hit, opts, stamps);
      cleanup();
      // Revalidate in background.
      fetchFragment(url, null)
        .then(function (entry) { if (entry) cachePut(url, entry); })
        .catch(function () {});
      return;
    }

    fetchFragment(url, ctrl).then(function (entry) {
      stamps.fetch = perfNow();
      console.log('%c[KKRouter] Fetch', 'color:#0ea5e9',
        (stamps.fetch - stamps.start).toFixed(1) + 'ms', url);
      if (!entry) { cleanup(); return; }
      cachePut(url, entry);
      applyFragment(url, entry, opts, stamps);
      cleanup();
    }).catch(function (err) {
      console.warn('[KKRouter] fallback →', (err && err.message) || err, url);
      cleanup();
      if (err && err.name === 'AbortError') return;
      hardNavigate(url, true);
    });
  }

  document.addEventListener('click', function (e) {
    if (!enabled) return;
    if (e.defaultPrevented) return;
    if (e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
    var a = e.target && e.target.closest ? e.target.closest('a[href]') : null;
    if (!a) return;
    if (a.target && a.target !== '' && a.target !== '_self') return;
    if (a.hasAttribute('download')) return;
    if (!isOptIn(a)) return;
    var href = a.getAttribute('href') || '';
    if (!href || href.charAt(0) === '#' || href.indexOf('javascript:') === 0) return;
    if (!sameOrigin(href)) return;
    if (shouldSkip(href)) return;
    e.preventDefault();
    navigate(new URL(href, location.href).toString());
  }, true);

  window.addEventListener('popstate', function () {
    if (!enabled) return;
    if (shouldSkip(location.href)) { hardNavigate(location.href, true); return; }
    navigate(location.href, { replace: true });
  });

  document.addEventListener('kkrouter:invalidate', function (e) {
    var u = e && e.detail && e.detail.url;
    invalidate(u);
  });

  window.KKRouter = {
    __r56: true,
    navigate: navigate,
    invalidate: invalidate,
    enable: function () { enabled = true; },
    disable: function () { enabled = false; },
    isNavigating: function () { return !!inFlight; },
    onEvent: onEvent,
    get enabled() { return enabled; }
  };

  try { history.replaceState({ kk: 1 }, '', location.href); } catch (_) {}
})();
