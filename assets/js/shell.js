/*!
 * KawanKeringat Shell (R56 — App Shell + Fragment)
 * ================================================
 * Bertugas SATU: menempel body fragment (yang berisi HANYA isi <main>)
 * ke innerHTML #app-content, tanpa mem-parse <html>/<head>/header/footer.
 *
 * Prinsip:
 *   - Tidak pernah membaca DOMParser lagi (fragment sudah dijamin bersih
 *     oleh header.php + footer.php dalam mode fragment).
 *   - Sebelum swap, batalkan AbortController halaman sebelumnya
 *     (window.__kkPageAbort) supaya listener global dari halaman lama
 *     otomatis mati. Inline script halaman boleh pakai:
 *        var sig = (window.__kkPageAbort && window.__kkPageAbort.signal);
 *     agar fetch/interval-nya ikut dibatalkan.
 *   - Dedup <script src> lintas fragment berdasarkan URL tanpa query,
 *     supaya Leaflet, Chart.js, Mapbox, dsb. tidak pernah di-reinit
 *     kalau sudah pernah dimuat.
 *   - Update <title> dan atribut data-skeleton dari response header,
 *     bukan dari HTML.
 *
 * API: window.KKShell = {
 *   applyFragment(body, opts), runInlineScripts(root), abortPage()
 * }
 * opts = { title?: string, skeleton?: string, url?: string, onSwap?: fn }
 */
(function () {
  'use strict';
  if (window.KKShell && window.KKShell.__r56) return;

  var CONTENT_ID = 'app-content';

  function normalizeSrc(src) {
    try {
      var u = new URL(src, location.href);
      return u.origin + u.pathname;
    } catch (_) { return src; }
  }

  function runInlineScripts(root) {
    if (!root) return;
    var loaded = window.__kkLoadedScripts || (window.__kkLoadedScripts = {});
    if (!loaded.__seeded) {
      var all = document.querySelectorAll('script[src]');
      for (var i = 0; i < all.length; i++) {
        loaded[normalizeSrc(all[i].getAttribute('src'))] = true;
      }
      loaded.__seeded = true;
    }
    var scripts = root.querySelectorAll('script');
    scripts.forEach(function (old) {
      try {
        var s = document.createElement('script');
        for (var i = 0; i < old.attributes.length; i++) {
          var a = old.attributes[i];
          try { s.setAttribute(a.name, a.value); } catch (_) {}
        }
        if (old.src) {
          var key = normalizeSrc(old.src);
          if (loaded[key]) { old.parentNode.removeChild(old); return; }
          loaded[key] = true;
        } else {
          s.textContent = old.textContent || '';
        }
        old.parentNode.replaceChild(s, old);
      } catch (e) {
        console.warn('[KKShell] inline script failed', e);
      }
    });
  }

  function abortPage() {
    try {
      if (window.__kkPageAbort && window.__kkPageAbort.abort) {
        window.__kkPageAbort.abort();
      }
    } catch (_) {}
    try {
      window.__kkPageAbort = ('AbortController' in window) ? new AbortController() : null;
    } catch (_) { window.__kkPageAbort = null; }
  }

  function applyFragment(body, opts) {
    opts = opts || {};
    var current = document.getElementById(CONTENT_ID);
    if (!current) return false;
    if (typeof body !== 'string') return false;

    abortPage();

    try {
      current.innerHTML = body;

      if (opts.title) { try { document.title = opts.title; } catch (_) {} }
      if (opts.skeleton) document.body.setAttribute('data-skeleton', opts.skeleton);
      else document.body.removeAttribute('data-skeleton');

      try { opts.onSwap && opts.onSwap(); } catch (_) {}

      runInlineScripts(current);

      try { window.scrollTo(0, 0); } catch (_) {}
      return true;
    } catch (e) {
      console.warn('[KKShell] applyFragment failed', e);
      return false;
    }
  }

  window.KKShell = {
    __r56: true,
    applyFragment: applyFragment,
    runInlineScripts: runInlineScripts,
    abortPage: abortPage
  };
})();
