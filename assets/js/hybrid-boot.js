/*!
 * KawanKeringat Hybrid Boot (R56 — App Shell + Fragment)
 * ======================================================
 * Menyambungkan sistem lama (footer.php: HFLoader, softRefresh, notif
 * poll, dm poll, firebase, service worker) dengan router R56, TANPA
 * menyentuh file PHP dan TANPA me-reinit apa pun per navigasi.
 *
 * Aturan:
 *   1. Firebase, FCM, Service Worker, Notification Polling, Bottom Nav,
 *      Drawer, Bootstrap: HANYA dijalankan sekali oleh footer.php pada
 *      full-load pertama. File ini tidak pernah re-init.
 *   2. Selama router sedang navigasi:
 *        - window.softRefresh di-pause (diganti noop sementara).
 *        - HFLoader di-reset supaya progress bar global tidak menumpuk
 *          dengan KKLoader (yang lokal di #app-content).
 *        - HFPreloader ikut di-reset.
 *      Setelah navigasi selesai (event kkrouter:end ok=true) semuanya
 *      dipulihkan.
 *   3. Request KKRouter (header X-KK-Router: 1) tidak memicu HFLoader.
 *   4. Setelah swap, halaman baru boleh mendaftarkan init-nya via
 *      event `spa:navigated`. Kami hanya mengirim sinyal — tidak
 *      memanggil apa pun sendiri.
 *
 * Tidak menyentuh business logic (tracking, GPS, upload, save, riwayat,
 * screenshot, fullscreen, pause, stop, review).
 */
(function () {
  'use strict';
  if (window.__KKHybridBoot56) return;
  window.__KKHybridBoot56 = true;

  window.__kkNavigating = false;

  // ---- Fetch bypass untuk KKRouter -----------------------------------
  (function patchFetch() {
    if (!window.fetch || window.fetch.__kkHybrid56) return;
    var orig = window.fetch;
    var patched = function (input, init) {
      var isRouter = false;
      try {
        var hdrs = init && init.headers;
        if (hdrs) {
          if (typeof Headers !== 'undefined' && hdrs instanceof Headers) {
            isRouter = hdrs.get('X-KK-Router') === '1';
          } else {
            isRouter = hdrs['X-KK-Router'] === '1' || hdrs['x-kk-router'] === '1';
          }
        }
      } catch (_) {}
      if (!isRouter) return orig.apply(this, arguments);
      try { if (window.HFLoader && window.HFLoader.reset) window.HFLoader.reset(); } catch (_) {}
      var p = orig.apply(this, arguments);
      var done = function () {
        try { if (window.HFLoader && window.HFLoader.reset) window.HFLoader.reset(); } catch (_) {}
      };
      p.then(done, done);
      return p;
    };
    patched.__kkHybrid56 = true;
    for (var k in orig) { try { patched[k] = orig[k]; } catch (_) {} }
    window.fetch = patched;
  })();

  // ---- Pause/resume soft-refresh + polling ---------------------------
  var pausedSoftRefresh = null;

  function pauseGlobals() {
    if (window.__kkNavigating) return;
    window.__kkNavigating = true;

    if (typeof window.softRefresh === 'function' && !window.softRefresh.__kkPaused) {
      pausedSoftRefresh = window.softRefresh;
      var noop = function () { return Promise.resolve(); };
      noop.__kkPaused = true;
      window.softRefresh = noop;
    }
    try { if (window.HFLoader && window.HFLoader.reset) window.HFLoader.reset(); } catch (_) {}
    try { if (window.HFPreloader && window.HFPreloader.reset) window.HFPreloader.reset(); } catch (_) {}

    try { document.dispatchEvent(new CustomEvent('kk:pause-polling')); } catch (_) {}
  }

  function resumeGlobals() {
    window.__kkNavigating = false;
    if (pausedSoftRefresh) {
      window.softRefresh = pausedSoftRefresh;
      pausedSoftRefresh = null;
    }
    try { document.dispatchEvent(new CustomEvent('kk:resume-polling')); } catch (_) {}
  }

  document.addEventListener('kkrouter:start', pauseGlobals);
  document.addEventListener('kkrouter:end',   function () {
    // beri jeda 1 tick supaya inline script fragment sempat pasang listener.
    setTimeout(resumeGlobals, 0);
  });

  // ---- Setelah swap: pancing observer footer.php ---------------------
  document.addEventListener('spa:navigated', function () {
    try {
      // Trigger observer softRefresh/table/emoji (mereka pakai MutationObserver
      // atau event ini). Kita cuma mengirim sinyal, TIDAK memanggil init.
      document.dispatchEvent(new CustomEvent('kk:content-swapped'));
    } catch (_) {}
  });
})();
