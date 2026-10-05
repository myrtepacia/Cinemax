// On every page.
(function () {
  // Close the phone menu after a link that jumps down the same page
  var toggle = document.getElementById('menu-open');
  if (toggle) {
    document.querySelectorAll('.menu a').forEach(function (link) {
      link.addEventListener('click', function () {
        toggle.checked = false;
      });
    });
  }

  // Home page: highlight the header link for the part being read (Now Showing
  // or Upcoming Shows)
  var parts = [];
  document.querySelectorAll('.menu a[href^="#"]').forEach(function (link) {
    var section = document.getElementById(link.getAttribute('href').slice(1));
    if (section) {
      parts.push({ link: link, section: section });
    }
  });
  if (parts.length > 1) {
    var bar = document.querySelector('.top-bar');

    var highlight = function () {
      // The last part whose top passed a line just under the header
      var line = (bar ? bar.offsetHeight : 0) + 80;
      var reading = parts[0];
      parts.forEach(function (part) {
        if (part.section.getBoundingClientRect().top <= line) {
          reading = part;
        }
      });
      // At the very bottom, the last part counts
      var page = document.documentElement;
      if (window.innerHeight + window.scrollY >= page.scrollHeight - 2) {
        reading = parts[parts.length - 1];
      }
      parts.forEach(function (part) {
        part.link.classList.toggle('current', part === reading);
      });
    };

    var waiting = false;
    window.addEventListener('scroll', function () {
      if (!waiting) {
        waiting = true;
        window.requestAnimationFrame(function () {
          waiting = false;
          highlight();
        });
      }
    }, { passive: true });
    window.addEventListener('resize', highlight);
    highlight();
  }

  // Remove the old service worker and its stored files, for this site only
  // (found from this script's address)
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
