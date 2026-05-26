// SportApp v3 — minimal service worker (PWA shell)
const CACHE = 'sportapp-v3';
const ASSETS = ['/', '/index.php', '/assets/css/app.css', '/assets/css/app-v3.css'];

self.addEventListener('install', e => {
  e.waitUntil(caches.open(CACHE).then(c => c.addAll(ASSETS).catch(()=>{})));
  self.skipWaiting();
});
self.addEventListener('activate', e => {
  e.waitUntil(caches.keys().then(keys =>
    Promise.all(keys.filter(k => k !== CACHE).map(k => caches.delete(k)))
  ));
  self.clients.claim();
});
self.addEventListener('fetch', e => {
  const req = e.request;
  if (req.method !== 'GET') return;
  e.respondWith(
    fetch(req).then(r => {
      const copy = r.clone();
      caches.open(CACHE).then(c => c.put(req, copy)).catch(()=>{});
      return r;
    }).catch(() => caches.match(req).then(r => r || caches.match('/index.php')))
  );
});

// Klik notifikasi -> buka halaman target
self.addEventListener('notificationclick', e => {
  e.notification.close();
  const url = (e.notification.data && e.notification.data.url) || '/';
  e.waitUntil(clients.matchAll({ type:'window' }).then(list => {
    for (const c of list) { if ('focus' in c) { c.navigate(url); return c.focus(); } }
    if (clients.openWindow) return clients.openWindow(url);
  }));
});

// Push payload (mendukung server-trigger nanti, opsional)
self.addEventListener('push', e => {
  let data = {};
  try { data = e.data ? e.data.json() : {}; } catch(_) { data = { title:'HapFam', body: e.data ? e.data.text() : '' }; }
  const title = data.title || 'HapFam';
  const opt = { body: data.body || '', icon: '/assets/icon-192.png', badge: '/assets/icon-192.png', data: { url: data.url || '/' } };
  e.waitUntil(self.registration.showNotification(title, opt));
});

