const preLoad = async function () {
  const cache = await caches.open("offline");
  return await cache.addAll(filesToCache);
};

self.addEventListener("install", function (event) {
  event.waitUntil(preLoad());
});

const filesToCache = [
  '/',
  '/js/app.js',
  '/js/app.js.map',
  '/css/app.css',
  '/offline.html',
  '/frontend/images/logo.svg',
  '/images/light_icon.svg?d2f8cab8569e9708193efe8b7c530108',
  '/fonts/font-size-icon.svg?1a46af7946cb79ada4da95f16b0a04d5'
];

const checkResponse = function (request) {
  return new Promise(function (resolve, reject) {
    fetch(request).then(function (response) {
      if (response.status !== 404) {
        resolve(response);
      } else {
        // eslint-disable-next-line prefer-promise-reject-errors
        reject();
      }
    }, reject);
  });
};

const addToCache = async function (request) {
  const cache = await caches.open("offline");
  const response = await fetch(request);
  return await cache.put(request, response);
};

const returnFromCache = async function (request) {
  const cache = await caches.open("offline");
  const matching = await cache.match(request);
  if (!matching || matching.status === 404) {
    return cache.match("offline.html");
  } else {
    return matching;
  }
};

self.addEventListener("fetch", function (event) {
  event.respondWith(checkResponse(event.request).catch(function () {
    return returnFromCache(event.request);
  }));
  if (!event.request.url.startsWith('http')) {
    event.waitUntil(addToCache(event.request));
  }
});
