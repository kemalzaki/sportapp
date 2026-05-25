/* HapFam SportApp — Service Worker v4
 * Strategi:
 *  - Network-first untuk HTML navigations (selalu coba ambil versi terbaru,
 *    fallback ke cache, terakhir ke offline shell).
 *  - Cache-first untuk static assets (css/js/img/font) dengan stale-while-revalidate.
 *  - Bypass total untuk POST dan request ke endpoint API (api_*.php).
 *  - Aman untuk Capacitor karena hanya aktif di origin yang sama.
 */
const VERSION = 'sportapp-v4-2026-05-25';
const STATIC_CACHE  = VERSION + '-static';
const RUNTIME_CACHE = VERSION + '-runtime';
const HTML_CACHE    = VERSION + '-html';

const PRECACHE = [
  '/',
  '/index.php',
  '/login.php',
  '/assets/css/app.css',
  '/assets/css/app-v3.css',
  '/assets/css/preloader.css',
  '/assets/css/mobile-shell.css',
  '/assets/js/preloader.js',
  '/assets/js/mobile-shell.js',
  '/assets/icon-192.png',
  '/assets/icon-512.png'
];

self.addEventListener('install', (e) => {
  e.waitUntil(
    caches.open(STATIC_CACHE)
      .then(c => c.addAll(PRECACHE).catch(() => {}))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys().then(keys => Promise.all(
      keys.filter(k => !k.startsWith(VERSION)).map(k => caches.delete(k))
    )).then(() => self.clients.claim())
  );
});

function isApi(url){
  return /\/api_[a-z_]+\.php(\?|$)/i.test(url.pathname);
}
function isStatic(url){
  return /\.(css|js|png|jpg|jpeg|webp|svg|woff2?|ttf|gif|ico)$/i.test(url.pathname);
}

self.addEventListener('fetch', (e) => {
  const req = e.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);
  if (url.origin !== location.origin) return; // serahkan ke browser
  if (isApi(url)) return;                     // jangan cache API

  // HTML navigation → network-first
  const accept = req.headers.get('accept') || '';
  if (req.mode === 'navigate' || accept.includes('text/html')){
    e.respondWith(
      fetch(req).then(res => {
        const copy = res.clone();
        caches.open(HTML_CACHE).then(c => c.put(req, copy)).catch(()=>{});
        return res;
      }).catch(() => caches.match(req).then(r =>
        r || caches.match('/index.php') || new Response(
          '<!doctype html><meta charset=utf-8><meta name=viewport content="width=device-width,initial-scale=1">'+
          '<title>Offline</title><style>body{font-family:system-ui;padding:24px;text-align:center;color:#0f172a}'+
          'h1{margin-top:80px}.b{margin-top:16px;display:inline-block;padding:10px 18px;background:#0ea5e9;color:#fff;border-radius:10px;text-decoration:none}</style>'+
          '<h1>📡 Tidak ada koneksi</h1><p>Periksa internet Anda lalu coba lagi.</p>'+
          '<a class=b href="/index.php" onclick="location.reload();return false">Coba lagi</a>',
          { headers: { 'Content-Type': 'text/html; charset=utf-8' } }
        )
      ))
    );
    return;
  }

  // Static → stale-while-revalidate
  if (isStatic(url)){
    e.respondWith(
      caches.match(req).then(cached => {
        const fetcher = fetch(req).then(res => {
          const copy = res.clone();
          caches.open(RUNTIME_CACHE).then(c => c.put(req, copy)).catch(()=>{});
          return res;
        }).catch(() => cached);
        return cached || fetcher;
      })
    );
    return;
  }
});

self.addEventListener('notificationclick', e => {
  e.notification.close();
  const url = (e.notification.data && e.notification.data.url) || '/';
  e.waitUntil(clients.matchAll({ type:'window' }).then(list => {
    for (const c of list) { if ('focus' in c) { c.navigate(url); return c.focus(); } }
    if (clients.openWindow) return clients.openWindow(url);
  }));
});

self.addEventListener('push', e => {
  let data = {};
  try { data = e.data ? e.data.json() : {}; } catch(_) { data = { title:'HapFam', body: e.data ? e.data.text() : '' }; }
  e.waitUntil(self.registration.showNotification(data.title || 'HapFam', {
    body: data.body || '',
    icon: '/assets/icon-192.png',
    badge: '/assets/icon-192.png',
    data: { url: data.url || '/' }
  }));
});
