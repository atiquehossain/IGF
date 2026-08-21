const staticCacheName = "ecw-v" + new Date().getTime();
const filesToCache = [
  '/',
  '/js/app.js',
  '/js/app.js.map',
  '/css/app.css',
  '/offline.html'
];

// Cache on install
self.addEventListener("install", event => {
  this.skipWaiting();
  event.waitUntil(
    caches.open(staticCacheName)
      .then(cache => {
        return cache.addAll(filesToCache);
      })
  );
});

// Clear cache on activate
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames
          .filter(cacheName => (cacheName.startsWith("pwa-")))
          .filter(cacheName => (cacheName !== staticCacheName))
          .map(cacheName => caches.delete(cacheName))
      );
    })
  );
});

const returnFromCache = async function (request) {
  const cache = await caches.open("offline");
  const matching = await cache.match(request);
  console.log(matching);
  if (!matching || matching.status === 404) {
    return cache.match("offline.html");
  } else {
    return matching;
  }
};
// Serve from Cache
self.addEventListener("fetch", event => {
  event.respondWith(
    caches.match(event.request)
      .then(response => {
        return returnFromCache(response) || fetch(event.request);
      })
      .catch(() => {
        return caches.match('offline');
      })
  );
});
