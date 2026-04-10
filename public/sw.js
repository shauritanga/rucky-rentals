const CACHE = 'ruky-v1';

self.addEventListener('install', e => {
  e.waitUntil(caches.open(CACHE).then(c => c.addAll(['/'])));
  self.skipWaiting();
});

self.addEventListener('activate', e => {
  e.waitUntil(
    caches.keys().then(keys =>
      Promise.all(keys.filter(k => k !== CACHE).map(k => caches.delete(k)))
    )
  );
  self.clients.claim();
});

self.addEventListener('fetch', e => {
  const { request } = e;

  // Never intercept non-GET or Inertia XHR requests
  if (request.method !== 'GET' || request.headers.get('X-Inertia')) return;

  const url = new URL(request.url);

  // Cache-first for built assets and icons (fingerprinted, safe to cache forever)
  if (url.pathname.startsWith('/build/') || url.pathname.startsWith('/icons/')) {
    e.respondWith(
      caches.match(request).then(hit => hit || fetch(request).then(res => {
        const clone = res.clone();
        caches.open(CACHE).then(c => c.put(request, clone));
        return res;
      }))
    );
    return;
  }

  // Network-first for everything else; fall back to cached shell if offline
  e.respondWith(
    fetch(request)
      .then(res => {
        const clone = res.clone();
        caches.open(CACHE).then(c => c.put(request, clone));
        return res;
      })
      .catch(() => caches.match('/'))
  );
});
