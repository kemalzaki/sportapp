/*!
 * KawanKeringat Shell (R54 — Hybrid Shell)
 * ----------------------------------------
 * Bertugas HANYA mengganti isi #app-content dari respons HTML halaman lain,
 * tanpa mengubah header, bottom nav, drawer, atau elemen persistent lain.
 *
 * Prinsip:
 *   - Tidak refactor global.
 *   - Tidak intercept link. Itu tugas router.js.
 *   - Kalau parsing gagal / #app-content tidak ada di respons → return false
 *     (router akan fallback ke window.location.href).
 *
 * API: window.KKShell = { swap(html, url), runInlineScripts(root) }
 */
(function () {
  'use strict';
  if (window.KKShell) return;

  var CONTENT_ID = 'app-content';

  function extract(html) {
    try {
      var doc = new DOMParser().parseFromString(html, 'text/html');
      var next = doc.getElementById(CONTENT_ID);
      if (!next) return null;
      var title = (doc.querySelector('title') || {}).textContent || document.title;
      return { node: next, title: title, doc: doc };
    } catch (_) { return null; }
  }

  // Eksekusi ulang <script> inline yang di-inject via innerHTML — karena
  // browser tidak mengeksekusinya secara otomatis. Script eksternal
  // dilewati bila src-nya sudah dimuat sebelumnya (deduplikasi ringan).
  function runInlineScripts(root) {
    if (!root) return;
    var loaded = window.__kkLoadedScripts || (window.__kkLoadedScripts = {});
    var scripts = root.querySelectorAll('script');
    scripts.forEach(function (old) {
      var s = document.createElement('script');
      // salin atribut
      for (var i = 0; i < old.attributes.length; i++) {
        var a = old.attributes[i];
        try { s.setAttribute(a.name, a.value); } catch (_) {}
      }
      if (old.src) {
        if (loaded[old.src]) return;   // sudah pernah dimuat
        loaded[old.src] = true;
      } else {
        s.textContent = old.textContent || '';
      }
      old.parentNode.replaceChild(s, old);
    });
  }

  function swap(html, url) {
    var got = extract(html);
    if (!got) return false;
    var current = document.getElementById(CONTENT_ID);
    if (!current) return false;

    // Ganti innerHTML saja — jangan replace node-nya, agar listener global
    // yang di-attach ke #app-content (kalau ada) tetap hidup.
    current.innerHTML = got.node.innerHTML;

    // Sinkronkan atribut data-* penting (mis. data-page) tanpa mengubah id/class.
    if (got.node.dataset) {
      Object.keys(got.node.dataset).forEach(function (k) {
        try { current.dataset[k] = got.node.dataset[k]; } catch (_) {}
      });
    }

    if (got.title) document.title = got.title;

    // Sinkronkan data-skeleton di <body> agar konsisten dengan halaman baru.
    var bodySkel = got.doc.body && got.doc.body.getAttribute('data-skeleton');
    if (bodySkel != null) document.body.setAttribute('data-skeleton', bodySkel);

    runInlineScripts(current);

    // Scroll ke atas seperti navigasi biasa.
    try { window.scrollTo(0, 0); } catch (_) {}
    return true;
  }

  window.KKShell = { swap: swap, runInlineScripts: runInlineScripts };
})();
