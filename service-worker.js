/**
 * SportApp Service Worker — v5 (HTMX-aware)
 * Strategi:
 *   - App shell (CSS/JS/icon/font CDN): CacheFirst
 *   - HTMX fragment (header HX-Request: true): StaleWhileRevalidate
 *   - Navigasi penuh (.php / "/"): NetworkFirst dgn fallback cache + offline page
 *   - API JSON (api_*.php): NetworkFirst, jangan cache POST
 *   - POST / non-GET: passthrough (network only)
 *
 * Ganti CACHE_VERSION setiap kali deploy supaya client lama auto-update.
 */
const CACHE_VERSION = 'v5-htmx-2026-06-15';
const SHELL_CACHE   = `sportapp-shell-${CACHE_VERSION}`;
const FRAG_CACHE    = `sportapp-frag-${CACHE_VERSION}`;
const PAGE_CACHE    = `sportapp-page-${CACHE_VERSION}`;

const SHELL_ASSETS = [
  '/assets/css/app.css',
  '/assets/css/app-v3.css',
  '/assets/css/desktop-fix.css',
  '/assets/css/gojek-top.css',
  '/assets/css/gojek-nav.css',
  '/assets/icon-192.png',
  '/assets/icon-512.png',
  '/assets/js/sfx.js',
  '/assets/js/htmx-boot.js',
  '/offline.html'
];

self.addEventListener('install', e => {
  self.skipWaiting();
  e.waitUntil(caches.open(SHELL_CACHE).then(c =>
    Promise.all(SHELL_ASSETS.map(u => c.add(u).catch(()=>{})))
  ));
});

self.addEventListener('activate', e => {
  e.waitUntil((async () => {
    const keys = await caches.keys();
    await Promise.all(keys
      .filter(k => k.startsWith('sportapp-') &&
                   ![SHELL_CACHE, FRAG_CACHE, PAGE_CACHE].includes(k))
      .map(k => caches.delete(k)));
    await self.clients.claim();
  })());
});

// --- helpers ---
function isHtmx(req)   { return req.headers.get('HX-Request') === 'true'; }
function isShell(url)  {
  return /\.(css|js|png|jpg|jpeg|webp|svg|woff2?|ttf)$/i.test(url.pathname) ||
         url.hostname.endsWith('jsdelivr.net') ||
         url.hostname.endsWith('gstatic.com')  ||
         url.hostname.endsWith('googleapis.com');
}
function isApi(url)    { return /^\/api_/.test(url.pathname); }
function isNav(req,url){
  return req.mode === 'navigate' ||
         url.pathname.endsWith('.php') ||
         url.pathname === '/';
}

async function cacheFirst(req, cacheName) {
  const cache = await caches.open(cacheName);
  const hit = await cache.match(req);
  if (hit) return hit;
  try {
    const res = await fetch(req);
    if (res.ok) cache.put(req, res.clone());
    return res;
  } catch { return hit || Response.error(); }
}

async function networkFirst(req, cacheName, fallback) {
  const cache = await caches.open(cacheName);
  try {
    const res = await fetch(req);
    if (res.ok && req.method === 'GET') cache.put(req, res.clone());
    return res;
  } catch {
    const hit = await cache.match(req);
    if (hit) return hit;
    if (fallback) return caches.match(fallback);
    return Response.error();
  }
}

async function staleWhileRevalidate(req, cacheName) {
  const cache = await caches.open(cacheName);
  const hit = await cache.match(req);
  const net = fetch(req).then(res => {
    if (res.ok) cache.put(req, res.clone());
    return res;
  }).catch(() => hit);
  return hit || net;
}

// --- router ---
self.addEventListener('fetch', e => {
  const req = e.request;
  if (req.method !== 'GET') return; // POST/PUT/DELETE = network langsung

  const url = new URL(req.url);

  // Jangan cache halaman sensitif
  if (/^\/(login|logout|register|admin)/.test(url.pathname)) return;

  if (isHtmx(req))      return e.respondWith(staleWhileRevalidate(req, FRAG_CACHE));
  if (isApi(url))       return e.respondWith(networkFirst(req, FRAG_CACHE));
  if (isShell(url))     return e.respondWith(cacheFirst(req, SHELL_CACHE));
  if (isNav(req, url))  return e.respondWith(networkFirst(req, PAGE_CACHE, '/offline.html'));
});

// --- push & notif (dipertahankan dari v4) ---
self.addEventListener('notificationclick', e => {
  e.notification.close();
  const url = (e.notification.data && e.notification.data.url) || '/';
  e.waitUntil(clients.matchAll({type:'window'}).then(list => {
    for (const c of list) { if ('focus' in c) { c.navigate(url); return c.focus(); } }
    if (clients.openWindow) return clients.openWindow(url);
  }));
});
self.addEventListener('push', e => {
  let data = {};
  try { data = e.data ? e.data.json() : {}; } catch(_) { data = { title:'HapFam', body: e.data ? e.data.text() : '' }; }
  e.waitUntil(self.registration.showNotification(data.title || 'HapFam', {
    body: data.body || '', icon:'/assets/icon-192.png', badge:'/assets/icon-192.png',
    data: { url: data.url || '/' }
  }));
});

// Pesan dari client (mis. skipWaiting on demand)
self.addEventListener('message', e => {
  if (e.data === 'SKIP_WAITING') self.skipWaiting();
});
