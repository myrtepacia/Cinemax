// CSFlix no longer installs a service worker: the host's security check
// broke it (see sw.js). A browser that still has the old one drops it here.
(function () {
  'use strict';

  if (!('serviceWorker' in navigator) || !navigator.serviceWorker.getRegistrations) {
    return;
  }
  navigator.serviceWorker.getRegistrations().then(function (registrations) {
    registrations.forEach(function (registration) {
      registration.unregister();
    });
  }).catch(function () {
    // Private window or blocked storage: nothing to remove
  });
})();
