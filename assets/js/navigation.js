/*!
 * KawanKeringat Navigation (R54 — Hybrid Shell)
 * ---------------------------------------------
 * Menjaga status aktif bottom navigation (.gj-nav .gj-item) setelah swap
 * konten oleh router. TIDAK mengubah struktur/ID/class navigasi.
 *
 * API: window.KKNav = { syncActive(url) }
 */
(function () {
  'use strict';
  if (window.KKNav) return;

  // Peta path -> daftar item bottom nav yang harus dianggap aktif.
  // Sinkron dengan _gj_active() di includes/bottom_nav.php.
  var MAP = [
    { match: /(^|\/)(index\.php|)$/i,                   href: '/index.php' },
    { match: /(^|\/)(riwayat|statistik_islami)\.php/i,  href: '/riwayat.php' },
    { match: /(^|\/)upload\.php/i,                      href: '/upload.php' },
    { match: /(^|\/)kalori_mingguan\.php/i,             href: '/kalori_mingguan.php' },
    { match: /(^|\/)(profile|user)\.php/i,              href: '/profile.php' }
  ];

  function syncActive(url) {
    try {
      var p = new URL(url || location.href, location.href).pathname;
      var target = null;
      for (var i = 0; i < MAP.length; i++) {
        if (MAP[i].match.test(p)) { target = MAP[i].href; break; }
      }
      var items = document.querySelectorAll('.gj-nav .gj-item, .gj-nav .gj-fab');
      items.forEach(function (a) {
        if (!a || !a.classList) return;
        a.classList.remove('active');
      });
      if (!target) return;
      items.forEach(function (a) {
        var href = a.getAttribute('href') || '';
        if (href.indexOf(target) !== -1) a.classList.add('active');
      });
    } catch (_) {}
  }

  window.KKNav = { syncActive: syncActive };
})();
