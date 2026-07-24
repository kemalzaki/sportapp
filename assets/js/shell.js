/*!
 * KawanKeringat Shell (R55 — Hybrid Shell)
 * ----------------------------------------
 * Swap HANYA innerHTML #app-content. Header, drawer, bottom nav, floating
 * button, dan seluruh <script> global di footer.php TETAP HIDUP.
 *
 * Prinsip:
 *   - Kalau HTML respons tidak mengandung #app-content → return false
 *     (router akan fallback ke window.location.href).
 *   - AbortController per swap: sebelum ganti innerHTML, semua event lama
 *     yang ter-attach ke elemen di dalam #app-content pergi bersamanya
 *     (browser garbage-collect listener yang terikat pada node yang dilepas).
 *     Untuk listener yang ter-attach ke document/window oleh inline script
 *     halaman sebelumnya, kami menyediakan window.__kkPageAbort:
 *     inline script boleh pakai `window.__kkPageAbort.signal` sebagai
 *     signal AbortController, dan otomatis di-abort saat halaman berganti.
 *   - Dedup <script src> lintas halaman berdasarkan URL TANPA query string,
 *     supaya Leaflet/Chart.js/Mapbox tidak pernah di-reinit kalau halaman
 *     baru tidak memerlukannya.
 *
 * API: window.KKShell = { swap(html, url, hooks), runInlineScripts(root) }
 */
(function () {
  'use strict';
  if (window.KKShell && window.KKShell.__r55) return;

  var CONTENT_ID = 'app-content';

  function normalizeSrc(src) {
    try {
      var u = new URL(src, location.href);
      return u.origin + u.pathname; // buang query & hash
    } catch (_) { return src; }
  }

  function extract(html) {
    try {
      var doc = new DOMParser().parseFromString(html, 'text/html');
      var next = doc.getElementById(CONTENT_ID);
      if (!next) return null;
      var title = (doc.querySelector('title') || {}).textContent || document.title;
      return { node: next, title: title, doc: doc };
    } catch (_) { return null; }
  }

  function runInlineScripts(root) {
    if (!root) return;
    var loaded = window.__kkLoadedScripts || (window.__kkLoadedScripts = {});
    // Seed dari <script src> yang sudah ada di dokumen (footer.php dsb).
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

  function abortPreviousPage() {
    // Abort AbortController sebelumnya, lalu bikin baru untuk halaman berikutnya.
    try {
      if (window.__kkPageAbort && window.__kkPageAbort.abort) {
        window.__kkPageAbort.abort();
      }
    } catch (_) {}
    try {
      window.__kkPageAbort = ('AbortController' in window) ? new AbortController() : null;
    } catch (_) { window.__kkPageAbort = null; }
  }

  function swap(html, url, hooks) {
    hooks = hooks || {};
    var got = extract(html);
    if (!got) return false;
    var current = document.getElementById(CONTENT_ID);
    if (!current) return false;

    abortPreviousPage();

    try {
      // Ganti innerHTML — jangan replace node, agar listener global yang
      // di-attach ke #app-content tetap hidup.
      current.innerHTML = got.node.innerHTML;

      if (got.node.dataset) {
        Object.keys(got.node.dataset).forEach(function (k) {
          try { current.dataset[k] = got.node.dataset[k]; } catch (_) {}
        });
      }
      if (got.title) document.title = got.title;

      var bodySkel = got.doc.body && got.doc.body.getAttribute('data-skeleton');
      if (bodySkel != null) document.body.setAttribute('data-skeleton', bodySkel);
      else document.body.removeAttribute('data-skeleton');

      try { hooks.onReplaceDom && hooks.onReplaceDom(); } catch (_) {}

      runInlineScripts(current);
      try { hooks.onRunInlineScripts && hooks.onRunInlineScripts(); } catch (_) {}

      try { window.scrollTo(0, 0); } catch (_) {}
      return true;
    } catch (e) {
      console.warn('[KKShell] swap failed', e);
      return false;
    }
  }

  window.KKShell = {
    __r55: true,
    swap: swap,
    runInlineScripts: runInlineScripts
  };
})();
