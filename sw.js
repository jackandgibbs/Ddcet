/* DDCET Prep service worker.
 * Strategy:
 *   - Static assets (fonts, icons, css, optimized logo): cache-first.
 *   - Page navigations: network-first, falling back to a cached offline page.
 *     App pages are auth-gated and send Cache-Control: no-store, so HTML is
 *     never cached — only the offline fallback is.
 * Bump CACHE_VERSION to invalidate old caches on deploy.
 */
const CACHE_VERSION = 'ddcet-v1';
const BASE = '/Dddcet/';
const PRECACHE = [
  BASE + 'offline.html',
  BASE + 'assets/logo.png',
  BASE + 'assets/fonts/fonts.css',
  BASE + 'assets/fonts/dmsans.woff2',
  BASE + 'assets/fonts/dmmono-400.woff2',
  BASE + 'assets/fonts/dmmono-500.woff2',
  BASE + 'assets/icon-192.png',
  BASE + 'assets/icon-512.png',
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_VERSION)
      .then((cache) => cache.addAll(PRECACHE))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((k) => k !== CACHE_VERSION).map((k) => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET') return;

  const url = new URL(req.url);
  // Only handle our own origin.
  if (url.origin !== self.location.origin) return;

  // Page navigations → network-first, offline fallback. Never cache HTML.
  if (req.mode === 'navigate') {
    event.respondWith(
      fetch(req).catch(() => caches.match(BASE + 'offline.html'))
    );
    return;
  }

  // Static assets → cache-first with background refresh.
  if (/\.(woff2|css|js|png|jpg|jpeg|svg|webp|ico)$/i.test(url.pathname)) {
    event.respondWith(
      caches.open(CACHE_VERSION).then((cache) =>
        cache.match(req).then((cached) => {
          const network = fetch(req).then((res) => {
            if (res && res.status === 200) cache.put(req, res.clone());
            return res;
          }).catch(() => cached);
          return cached || network;
        })
      )
    );
  }
});
