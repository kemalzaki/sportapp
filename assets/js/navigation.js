/*!
 * KawanKeringat Navigation (R55 — Hybrid Shell)
 * ---------------------------------------------
 * Sinkron status aktif .gj-nav .gj-item setelah swap. Tidak mengubah
 * struktur/ID/class navigasi.
 *
 * API: window.KKNav = { syncActive(url) }
 */
(function () {
  'use strict';
  if (window.KKNav && window.KKNav.__r55) return;

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

  window.KKNav = { __r55: true, syncActive: syncActive };
})();
