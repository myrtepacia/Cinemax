(function () {
  var toggle = document.getElementById('menu-open');
  if (toggle) {
    document.querySelectorAll('.menu a').forEach(function (link) {
      link.addEventListener('click', function () {
        toggle.checked = false;
      });
    });
  }

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
      var line = (bar ? bar.offsetHeight : 0) + 80;
      var reading = parts[0];
      parts.forEach(function (part) {
        if (part.section.getBoundingClientRect().top <= line) {
          reading = part;
        }
      });
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

    var goTo = function (section, smooth) {
      var top = section.getBoundingClientRect().top + window.scrollY - (bar ? bar.offsetHeight : 0);
      window.scrollTo({ top: Math.max(0, top), behavior: smooth ? 'smooth' : 'auto' });
    };
    var calm = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    parts.forEach(function (part) {
      part.link.addEventListener('click', function (event) {
        event.preventDefault();
        goTo(part.section, !calm);
      });
    });

    var askedPart = function () {
      return parts.filter(function (part) {
        return '#' + part.section.id === window.location.hash;
      })[0];
    };
    var goToAsked = function () {
      var asked = askedPart();
      if (asked) {
        goTo(asked.section, false);
        highlight();
        window.history.replaceState(null, '', window.location.pathname + window.location.search);
      }
      return asked;
    };
    var arrived = goToAsked();
    if (arrived) {
      window.addEventListener('load', function () {
        goTo(arrived.section, false);
        highlight();
      });
    }
    window.addEventListener('hashchange', goToAsked);
  }
})();
