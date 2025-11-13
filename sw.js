/*
  NASAB Family Tree Service Worker
  - Precache core assets
  - Cache-first for static assets (css/js/images)
  - Network-first for navigations with offline fallback
  - Bypass caching for /api/*
*/

const VERSION = '2025-11-13';
const PRECACHE = `precache-${VERSION}`;

const PRECACHE_URLS = [
  '/',
  '/index.html',
  '/pages/EN/index.html',
  '/pages/MY/index.html',
  '/manifest.webmanifest',
  '/assets/css/main.css',
  '/assets/js/main.js',
  '/assets/js/lang-switch.js',
  '/assets/js/i18n.js',
  '/assets/img/logo.png',
  '/pages/EN/offline.html',
  '/pages/MY/offline.html'
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(PRECACHE)
      .then((cache) => cache.addAll(PRECACHE_URLS))
      .catch(() => {})
  );
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => Promise.all(
      keys.filter((k) => k.startsWith('precache-') && k !== PRECACHE)
          .map((k) => caches.delete(k))
    ))
  );
  self.clients.claim();
});

function isApiRequest(req) {
  const url = new URL(req.url);
  return url.pathname.startsWith('/api/');
}

self.addEventListener('fetch', (event) => {
  const req = event.request;

  // Bypass non-GET or API
  if (req.method !== 'GET' || isApiRequest(req)) return;

  const url = new URL(req.url);

  // Navigation requests: network-first with offline fallback
  if (req.mode === 'navigate') {
    event.respondWith(
      fetch(req).catch(async () => {
        // Choose EN or MY offline page based on path
        const isMY = /\/pages\/MY\//.test(url.pathname);
        const fallback = isMY ? '/pages/MY/offline.html' : '/pages/EN/offline.html';
        const cached = await caches.match(fallback);
        return cached || Response.error();
      })
    );
    return;
  }

  // Static assets: cache-first
  if (['style', 'script', 'image', 'font'].includes(req.destination)) {
    event.respondWith(
      caches.match(req).then((cached) => {
        const network = fetch(req).then((resp) => {
          // Only cache successful basic responses
          if (resp && resp.status === 200 && resp.type === 'basic') {
            const copy = resp.clone();
            caches.open(PRECACHE).then((cache) => cache.put(req, copy)).catch(()=>{});
          }
          return resp;
        }).catch(() => cached);
        return cached || network;
      })
    );
    return;
  }

  // Default: try cache then network
  event.respondWith(
    caches.match(req).then((cached) => cached || fetch(req))
  );
});
