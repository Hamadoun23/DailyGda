/* Service worker GDA — assets statiques uniquement (pas de pages HTML / redirections) */
const CACHE = 'gda-pwa-v2';

const ASSETS = [
  '/css/gda.css',
  '/js/gda-i18n.js',
  '/js/gda-pwa.js',
  '/img/Constfondblanc.jpg',
  '/img/GDACONST.png',
  '/img/logoconst.png',
];

function isCacheableAsset(url) {
  const p = url.pathname;
  return (
    p.endsWith('.css') ||
    p.endsWith('.js') ||
    p.startsWith('/img/') ||
    p === '/manifest.webmanifest'
  );
}

function canStore(response) {
  if (!response || !response.ok) return false;
  if (response.status >= 300 && response.status < 400) return false;
  if (response.redirected) return false;
  const t = response.type;
  return t === 'basic' || t === 'cors';
}

self.addEventListener('install', event => {
  event.waitUntil(
    caches
      .open(CACHE)
      .then(cache => cache.addAll(ASSETS).catch(() => {}))
      .then(() => self.skipWaiting()),
  );
});

self.addEventListener('activate', event => {
  event.waitUntil(
    caches
      .keys()
      .then(keys => Promise.all(keys.filter(k => k !== CACHE).map(k => caches.delete(k))))
      .then(() => self.clients.claim()),
  );
});

self.addEventListener('message', event => {
  if (event.data && event.data.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }
});

self.addEventListener('fetch', event => {
  const { request } = event;

  if (request.method !== 'GET') return;

  /* Safari : ne jamais intercepter la navigation (login, redirections post-auth) */
  if (request.mode === 'navigate') return;
  if (request.destination === 'document') return;

  const url = new URL(request.url);
  if (url.origin !== self.location.origin) return;
  if (url.pathname.startsWith('/api/')) return;

  if (!isCacheableAsset(url)) return;

  event.respondWith(
    fetch(request)
      .then(response => {
        if (canStore(response)) {
          const clone = response.clone();
          caches.open(CACHE).then(cache => cache.put(request, clone));
        }
        return response;
      })
      .catch(() => caches.match(request)),
  );
});
