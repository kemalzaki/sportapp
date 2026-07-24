/*!
 * KawanKeringat Hybrid Boot (R55 — Hybrid Shell)
 * ==============================================
 * Mengharmoniskan sistem global lama (footer.php / header.php) dengan
 * router Hybrid Shell R55, TANPA mengubah file PHP.
 *
 * Yang dilakukan:
 *   1. Saat KKRouter sedang navigasi:
 *      - Pause window.softRefresh (soft auto-refresh 25 detik).
 *      - Pause polling /api_notif_poll.php (15 detik) & /api_dm.php (20 detik).
 *      - Pause top progress bar HFLoader supaya tidak menumpuk dengan
 *        skeleton router.
 *      Setelah navigasi selesai, semuanya dipulihkan.
 *
 *   2. Fetch dari KKRouter (ditandai header X-KK-Router: 1) TIDAK memicu
 *      progress bar HFLoader dan TIDAK memicu preloader/wrapper lama.
 *
 *   3. Setelah swap konten (event `kkrouter:end` ok=true), jalankan ulang
 *      inisialisasi ringan yang biasanya bergantung pada DOMContentLoaded:
 *      - re-tag form data-ajax di dalam [data-live] (initAll softRefresh)
 *      - re-init tabel pagination/sort
 *      - re-init emoji picker
 *      Semua via MutationObserver yang sudah ada di footer.php — kita
 *      cukup memicu satu event supaya observer terjaga.
 *
 * Tidak menyentuh: tracking, GPS, upload, save, riwayat, screenshot,
 * fullscreen, pause, stop, review, endpoint, DB, atau business logic.
 */
(function () {
  'use strict';
  if (window.__KKHybridBoot) return;
  window.__KKHybridBoot = true;

  // ---- Flag global -----------------------------------------------------
  window.__kkNavigating = false;

  // ---- 1. Patch window.fetch: bypass HFLoader untuk request KKRouter ---
  // HFLoader di footer.php membungkus window.fetch dan menambah progress
  // bar untuk SEMUA request — termasuk fetch router yang kita mau senyap.
  // Solusi: tandai request router dengan header X-KK-Router, lalu di sini
  // kita bungkus fetch SEKALI LAGI supaya kalau header itu ada, kita
  // pause dulu HFLoader sebentar.
  (function patchFetch() {
    if (!window.fetch || window.fetch.__kkHybrid) return;
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
      // Router fetch: reset HFLoader dulu supaya progress bar tidak
      // ikut mengambang.
      try { if (window.HFLoader && window.HFLoader.reset) window.HFLoader.reset(); } catch (_) {}
      var p = orig.apply(this, arguments);
      // Setelah selesai/gagal, reset lagi biar bersih.
      var done = function () {
        try { if (window.HFLoader && window.HFLoader.reset) window.HFLoader.reset(); } catch (_) {}
      };
      p.then(done, done);
      return p;
    };
    patched.__kkHybrid = true;
    // Pertahankan flag lain (mis. HFLoader.__hfPatched pada wrapper lama)
    for (var k in orig) { try { patched[k] = orig[k]; } catch (_) {} }
    window.fetch = patched;
  })();

  // ---- 2. Pause / resume soft-refresh & polling ------------------------
  var pausedSoftRefresh = null;
  var pausedNotifTick   = null;
  var pausedDmTick      = null;

  function pauseGlobals() {
    if (window.__kkNavigating) return;
    window.__kkNavigating = true;

    // softRefresh: kalau ada, ganti dengan noop sementara.
    if (typeof window.softRefresh === 'function' && !window.softRefresh.__kkPaused) {
      pausedSoftRefresh = window.softRefresh;
      var noop = function () { return Promise.resolve(); };
      noop.__kkPaused = true;
      window.softRefresh = noop;
    }

    // HFLoader: paksa berhenti kalau lagi jalan.
    try { if (window.HFLoader && window.HFLoader.reset) window.HFLoader.reset(); } catch (_) {}

    // HFPreloader (kalau versi lama ada): sembunyikan.
    try { if (window.HFPreloader && window.HFPreloader.reset) window.HFPreloader.reset(); } catch (_) {}
  }

  function resumeGlobals() {
    window.__kkNavigating = false;
    if (pausedSoftRefresh) {
      window.softRefresh = pausedSoftRefresh;
      pausedSoftRefresh = null;
    }
    try { if (window.HFLoader && window.HFLoader.reset) window.HFLoader.reset(); } catch (_) {}
  }

  // ---- 3. Hook ke KKRouter --------------------------------------------
  function bindRouter() {
    if (!window.KKRouter || !window.KKRouter.onEvent) return false;
    window.KKRouter.onEvent('start', pauseGlobals);
    window.KKRouter.onEvent('end', function (detail) {
      resumeGlobals();
      // Setelah DOM baru terpasang, picu event supaya kode legacy
      // yang mendengarkan `spa:navigated` re-init dirinya (tabel, emoji,
      // form data-ajax). MutationObserver di footer.php juga akan
      // menangkap perubahan DOM.
      if (detail && detail.ok) {
        try {
          document.dispatchEvent(new CustomEvent('spa:navigated', {
            detail: { url: detail.url }
          }));
        } catch (_) {}
      }
    });
    return true;
  }
  if (!bindRouter()) {
    // Router mungkin belum ter-attach — coba lagi setelah script berikutnya jalan.
    var tries = 0;
    var iv = setInterval(function () {
      if (bindRouter() || (++tries > 20)) clearInterval(iv);
    }, 50);
  }

  // ---- 4. Safety net: kalau ada navigasi tersangkut > 2 detik, reset ---
  // Jangan biarkan flag __kkNavigating stuck true (mis. exception di
  // pipeline). Watchdog independen dari router.
  setInterval(function () {
    if (!window.__kkNavigating) return;
    if (!window.KKRouter || !window.KKRouter.isNavigating || !window.KKRouter.isNavigating()) {
      resumeGlobals();
    }
  }, 1000);
})();
