const CACHE_NAME = "image-cache-v1";
const IMAGE_CACHE_URLS = [];

self.addEventListener("install", (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => cache.addAll(IMAGE_CACHE_URLS))
  );
});

self.addEventListener("fetch", (event) => {
  const request = event.request;

  // Only intercept image requests
  if (request.destination === "image") {
    event.respondWith(
      caches.match(request).then((cachedResponse) => {
        if (cachedResponse) {
          return cachedResponse;
        }

        return fetch(request).then((networkResponse) => {
          return caches.open(CACHE_NAME).then((cache) => {
            cache.put(request, networkResponse.clone());
            return networkResponse;
          });
        });
      })
    );
  }
});
