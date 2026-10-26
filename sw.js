// CSFlix used to install a service worker here for an offline page. The
// host's security check (InfinityFree's aes.js page) answered its background
// requests with the check page instead, which trapped visitors in a loop
// between the check and "You're offline". This version only removes itself:
// it clears what the old one saved, unregisters, and reloads open pages
// straight from the server. pwa.js no longer installs it.
self.addEventListener('install', function () {
  self.skipWaiting();
});

self.addEventListener('activate', function (event) {
  var home = self.registration.scope;
  event.waitUntil(
    caches.keys()
      .then(function (keys) {
        return Promise.all(keys.map(function (key) {
          return caches.delete(key);
        }));
      })
      .then(function () {
        return self.registration.unregister();
      })
      .then(function () {
        return self.clients.matchAll({ type: 'window' });
      })
      .then(function (windows) {
        windows.forEach(function (page) {
          // A page stuck on the offline screen goes home instead
          page.navigate(page.url.indexOf('offline.html') === -1 ? page.url : home);
        });
      })
  );
});
