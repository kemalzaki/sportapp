/*!
 * KawanKeringat Navigation (R56 — App Shell)
 * ------------------------------------------
 * Bottom Navigation dibuat SATU KALI oleh header.php + bottom_nav.php.
 * File ini hanya menyinkronkan status .active setelah swap fragment,
 * tanpa destroy/recreate elemen apa pun.
 *
 * API: window.KKNav = { syncActive(url) }
 */
(function () {
  'use strict';
  if (window.KKNav && window.KKNav.__r56) return;

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
        if (a && a.classList) a.classList.remove('active');
      });
      if (!target) return;
      items.forEach(function (a) {
        var href = a.getAttribute('href') || '';
        if (href.indexOf(target) !== -1) a.classList.add('active');
      });
    } catch (_) {}
  }

  window.KKNav = { __r56: true, syncActive: syncActive };
})();
