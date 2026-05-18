const CACHE_NAME = 'pscissues-v1';

// Pre-cache core UI assets
const STATIC_ASSETS = [
  '/assets/css/style.css',
  '/assets/js/install-app.js',
  '/manifest.json'
];

// File extensions treated as cacheable static assets
const STATIC_EXT = /\.(css|js|woff2?|ttf|otf|svg|png|jpg|jpeg|gif|webp|ico)(\?.*)?$/i;

self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => cache.addAll(STATIC_ASSETS).catch(() => {}))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(names =>
      Promise.all(names.filter(n => n !== CACHE_NAME).map(n => caches.delete(n)))
    ).then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', event => {
  const req = event.request;
  if (req.method !== 'GET') return;

  const url = new URL(req.url);

  // 1. Navigation requests (HTML/PHP pages)
  // Strategy: Network-First
  if (req.mode === 'navigate') {
    event.respondWith(
      fetch(req)
        .then(response => {
          // If successful, cache the latest version
          const clone = response.clone();
          caches.open(CACHE_NAME).then(cache => cache.put(req, clone));
          return response;
        })
        .catch(() => {
          // If network fails, return from cache
          return caches.match(req).then(cached => {
            if (cached) return cached;
            return Response.error();
          });
        })
    );
    return;
  }

  // 2. Static assets (CSS, JS, Images)
  // Strategy: Cache-First
  if (STATIC_EXT.test(url.pathname)) {
    event.respondWith(
      caches.match(req).then(cached => {
        if (cached) return cached;
        return fetch(req).then(response => {
          if (!response || response.status !== 200 || response.type === 'error') return response;
          const clone = response.clone();
          caches.open(CACHE_NAME).then(cache => cache.put(req, clone));
          return response;
        });
      })
    );
  }
});
