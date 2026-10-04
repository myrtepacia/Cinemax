// On every page.
(function () {
  // Links in the phone menu that jump down the same page (like "Now
  // Showing" on the home page) do not load a new page, so the drop-down
  // menu would stay open over the content. This shuts it.
  var toggle = document.getElementById('menu-open');
  if (toggle) {
    document.querySelectorAll('.menu a').forEach(function (link) {
      link.addEventListener('click', function () {
        toggle.checked = false;
      });
    });
  }

  // The site used to install a service worker (for the phone app). A browser
  // that still has it drops it, and the files it stored. Only this site's
  // own is touched: the site's folder is worked out from this script's
  // address, .../assets/js/menu.js, and other projects on localhost keep theirs.
  var script = document.currentScript;
  if ('serviceWorker' in navigator && script && script.src) {
    var base = new URL('../../', script.src).href;
    navigator.serviceWorker.getRegistrations().then(function (registrations) {
      registrations.forEach(function (registration) {
        if (registration.scope === base) {
          registration.unregister();
        }
      });
    }).catch(function () {});
    if (window.caches) {
      caches.keys().then(function (keys) {
        keys.forEach(function (key) {
          if (key.indexOf('cinemax-') === 0) {
            caches.delete(key);
          }
        });
      }).catch(function () {});
    }
  }
})();
