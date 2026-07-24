/*!
 * KawanKeringat SPA Router (Revisi Juli 2026 R53)
 * ------------------------------------------------
 * Tujuan: navigasi antar halaman utama (Beranda, Aktivitas, Kalori, Profil,
 * Tempat, Upload) tanpa full page reload. Header, bottom nav, search bar,
 * notifikasi, dan floating button tetap persistent — hanya #app-content yang
 * disegarkan.
 *
 * Sifat: PROGRESSIVE ENHANCEMENT.
 *   - Tidak mengubah HTML/ID/class, tidak mengubah endpoint, tidak mengubah
 *     database, tidak mengubah business logic.
 *   - Kalau JS mati / URL masuk daftar SKIP / element opt-out (data-no-spa)
 *     → link jalan seperti biasa (full navigation).
 *   - Kalau server tidak mengembalikan #app-content (mis. redirect ke login)
 *     → fallback ke location.href agar aman.
 *
 * API global: window.KKRouter = { navigate(url), reload(), lock(reason), unlock(),
 *                                 onNavigated(fn) }
 * Event global: 'spa:navigated' di-dispatch pada document setelah swap.
 */
(function () {
  'use strict';
  if (window.KKRouter) return; // idempotent

  var CONTENT_ID = 'app-content';

  /* Halaman yang HARUS full-reload (tidak boleh diintercept SPA).
     Alasan: state kompleks (GPS/tracking/upload progress), auth flow,
     atau layout berbeda tanpa #app-content. */
  var SKIP_PATTERNS = [
    /(^|\/)run\.php\b/i,
    /(^|\/)live_tracking\.php\b/i,
    /(^|\/)login\.php\b/i,
    /(^|\/)logout\.php\b/i,
    /(^|\/)register\.php\b/i,
    /(^|\/)splash\.php\b/i,
    /(^|\/)onboarding\.php\b/i,
    /(^|\/)manifest\.php\b/i,
    /(^|\/)admin\//i,
    /\.(zip|pdf|png|jpe?g|gif|mp4|mp3|gpx|csv|xlsx?)(\?|$)/i
  ];

  var cache = new Map();          // url -> { html, title, ts }
  var CACHE_TTL = 5 * 60 * 1000;  // 5 menit
  var CACHEABLE = [
    /(^|\/)index\.php\b/i,
    /(^|\/)riwayat\.php\b/i,
    /(^|\/)kalori_mingguan\.php\b/i,
    /(^|\/)profile\.php\b/i,
    /(^|\/)tempat(_list)?\.php\b/i
  ];

  var locked = false;
  var lockReason = '';
  var inFlight = null;
  var listeners = [];

  function sameOrigin(url) {
    try {
      var u = new URL(url, location.href);
      return u.origin === location.origin;
    } catch (_) { return false; }
  }

  function shouldSkip(url) {
    try {
      var u = new URL(url, location.href);
      var p = u.pathname + u.search;
      for (var i = 0; i < SKIP_PATTERNS.length; i++) {
        if (SKIP_PATTERNS[i].test(p)) return true;
      }
      return false;
    } catch (_) { return true; }
  }

  function isCacheable(url) {
    try {
      var p = new URL(url, location.href).pathname;
      for (var i = 0; i < CACHEABLE.length; i++) {
        if (CACHEABLE[i].test(p)) return true;
      }
    } catch (_) {}
    return false;
  }

  function topbarStart() {
    var tb = document.getElementById('gjTopBar');
    if (tb) tb.classList.add('active');
  }
  function topbarEnd() {
    var tb = document.getElementById('gjTopBar');
    if (tb) tb.classList.remove('active');
  }

  function skeletonInto(container) {
    if (!container) return;
    // Skeleton ringan — hindari blank putih.
    container.innerHTML =
      '<div class="container py-3" data-spa-skeleton="1">' +
        '<div class="skeleton skel-line lg" style="width:40%"></div>' +
        '<div class="skeleton skel-line" style="width:70%"></div>' +
        '<div class="skeleton skel-block" style="height:140px;margin-top:.75rem"></div>' +
        '<div class="skeleton skel-block" style="height:100px;margin-top:.5rem"></div>' +
        '<div class="skeleton skel-block" style="height:100px;margin-top:.5rem"></div>' +
      '</div>';
  }

  /* Jalankan ulang <script> yang berada di HTML baru.
     Tanpa ini, inline JS di halaman baru tidak akan berjalan setelah swap. */
  function runScripts(root) {
    if (!root) return;
    var scripts = root.querySelectorAll('script');
    scripts.forEach(function (old) {
      // Skip script yang eksplisit di-mark tidak untuk SPA
      if (old.hasAttribute('data-spa-skip')) return;
      var s = document.createElement('script');
      for (var i = 0; i < old.attributes.length; i++) {
        var a = old.attributes[i];
        s.setAttribute(a.name, a.value);
      }
      if (old.src) {
        // Hindari re-download script eksternal yg sama berkali-kali
        if (document.querySelector('script[data-spa-loaded="' + CSS.escape(old.src) + '"]')) {
          return;
        }
        s.setAttribute('data-spa-loaded', old.src);
      } else {
        s.text = old.textContent || '';
      }
      old.parentNode.replaceChild(s, old);
    });
  }

  function updateActiveNav() {
    try {
      var here = (location.pathname || '').split('/').pop() || 'index.php';
      document.querySelectorAll('.gj-nav .gj-item, .gj-nav .gj-fab').forEach(function (a) {
        var href = (a.getAttribute('href') || '').split('/').pop().split('?')[0];
        if (!href) return;
        if (href === here) a.classList.add('active');
        else a.classList.remove('active');
      });
    } catch (_) {}
  }

  function dispatch(url) {
    try {
      document.dispatchEvent(new CustomEvent('spa:navigated', { detail: { url: url } }));
    } catch (_) {}
    listeners.forEach(function (fn) { try { fn(url); } catch (_) {} });
  }

  function swapHtml(html, url, push) {
    var container = document.getElementById(CONTENT_ID);
    if (!container) { window.location.href = url; return; }

    var doc;
    try {
      doc = new DOMParser().parseFromString(html, 'text/html');
    } catch (_) {
      window.location.href = url; return;
    }
    var incoming = doc.getElementById(CONTENT_ID);
    if (!incoming) {
      // Kemungkinan redirect ke login / halaman non-SPA. Fallback aman.
      window.location.href = url; return;
    }

    // Update title
    var t = doc.querySelector('title');
    if (t && t.textContent) document.title = t.textContent;

    container.innerHTML = incoming.innerHTML;
    runScripts(container);

    if (push) {
      try { history.pushState({ spa: 1, url: url }, '', url); } catch (_) {}
    }

    // Reset scroll & auto-skeleton hooks lama
    try { window.scrollTo({ top: 0, left: 0, behavior: 'instant' }); }
    catch (_) { window.scrollTo(0, 0); }
    try { if (window.SK && typeof window.SK.auto === 'function') window.SK.auto(); } catch (_) {}

    updateActiveNav();
    dispatch(url);
  }

  function fetchPage(url) {
    return fetch(url, {
      credentials: 'same-origin',
      headers: {
        'X-Requested-With': 'KKRouter',
        'X-SPA-Nav': '1',
        'Accept': 'text/html'
      }
    }).then(function (res) {
      if (!res.ok) throw new Error('HTTP ' + res.status);
      // Bila server redirect ke halaman non-SPA (login), fetch mengikuti redirect.
      // Kalau URL final beda origin, batalkan.
      if (res.redirected && !sameOrigin(res.url)) {
        throw new Error('cross-origin redirect');
      }
      return res.text().then(function (t) {
        return { html: t, finalUrl: res.url || url };
      });
    });
  }

  function navigate(url, opts) {
    opts = opts || {};
    if (locked) {
      if (!confirm('Ada aktivitas berjalan (' + (lockReason || 'tracking') +
                   '). Yakin pindah halaman?')) {
        return Promise.resolve(false);
      }
    }
    if (!sameOrigin(url) || shouldSkip(url)) {
      window.location.href = url;
      return Promise.resolve(false);
    }

    // Cache hit
    if (!opts.force && isCacheable(url) && cache.has(url)) {
      var c = cache.get(url);
      if (Date.now() - c.ts < CACHE_TTL) {
        swapHtml(c.html, url, opts.push !== false);
        return Promise.resolve(true);
      }
    }

    topbarStart();
    var container = document.getElementById(CONTENT_ID);
    // Skeleton hanya jika bukan cache hit — hindari flicker.
    if (container && !opts.silent) skeletonInto(container);

    if (inFlight && inFlight.abort) { try { inFlight.abort(); } catch (_) {} }

    return fetchPage(url).then(function (r) {
      if (isCacheable(url)) cache.set(url, { html: r.html, ts: Date.now() });
      swapHtml(r.html, r.finalUrl || url, opts.push !== false);
      topbarEnd();
      return true;
    }).catch(function (err) {
      topbarEnd();
      // Fallback aman: full navigation
      console.warn('[KKRouter] fallback full load:', err && err.message);
      window.location.href = url;
      return false;
    });
  }

  function onClick(e) {
    if (e.defaultPrevented) return;
    if (e.button !== 0) return;
    if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;

    var a = e.target && e.target.closest ? e.target.closest('a[href]') : null;
    if (!a) return;
    if (a.hasAttribute('data-no-spa')) return;
    if (a.target && a.target !== '' && a.target !== '_self') return;
    if (a.hasAttribute('download')) return;
    var href = a.getAttribute('href') || '';
    if (!href || href[0] === '#' || href.indexOf('javascript:') === 0 ||
        href.indexOf('mailto:') === 0 || href.indexOf('tel:') === 0) return;
    if (!sameOrigin(a.href)) return;
    if (shouldSkip(a.href)) return;

    // Hanya intercept link ke .php atau path tanpa ekstensi
    var pathname = '';
    try { pathname = new URL(a.href, location.href).pathname; } catch (_) { return; }
    if (!/\.php$/i.test(pathname) && /\.[a-z0-9]{2,5}$/i.test(pathname)) return;

    e.preventDefault();
    navigate(a.href);
  }

  function onPopState(e) {
    // Reload konten sesuai URL baru
    navigate(location.href, { push: false, force: false });
  }

  document.addEventListener('click', onClick, false);
  window.addEventListener('popstate', onPopState, false);

  // Ganti state awal supaya popstate punya baseline
  try { history.replaceState({ spa: 1, url: location.href }, '', location.href); } catch (_) {}

  window.KKRouter = {
    navigate: navigate,
    reload: function () { return navigate(location.href, { push: false, force: true }); },
    lock: function (reason) { locked = true; lockReason = reason || ''; },
    unlock: function () { locked = false; lockReason = ''; },
    onNavigated: function (fn) { if (typeof fn === 'function') listeners.push(fn); },
    _cache: cache
  };

  // Peringatan sebelum keluar bila terkunci (mis. sedang tracking)
  window.addEventListener('beforeunload', function (e) {
    if (locked) { e.preventDefault(); e.returnValue = ''; return ''; }
  });
})();
