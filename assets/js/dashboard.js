(function () {
  var main = document.getElementById('dashboard');
  if (!main || !window.fetch || !window.DOMParser) {
    return;
  }

  var liveUrl = main.getAttribute('data-live-url') || '';
  var version = main.getAttribute('data-version') || '';
  var CHECK_EVERY_MS = 4000;

  // True while a Ready / Picked Up form is being sent, so the page is not
  // swapped under it
  var submitting = false;
  var checking = false;
  var stopped = false;

  document.addEventListener('submit', function () {
    submitting = true;
  });

  // ---- Live updates ----

  function swapIn(html) {
    var fresh = new DOMParser().parseFromString(html, 'text/html');
    var freshMain = fresh.getElementById('dashboard');
    if (!freshMain) {
      // Not the dashboard (signed out): stop
      stopped = true;
      return;
    }
    ['dashboard-stats', 'snack-orders'].forEach(function (id) {
      var now = document.getElementById(id);
      var next = fresh.getElementById(id);
      if (now && next) {
        now.replaceWith(document.importNode(next, true));
      }
    });
    version = freshMain.getAttribute('data-version') || version;
    main.setAttribute('data-version', version);
  }

  function check() {
    if (stopped || checking || submitting || document.visibilityState !== 'visible') {
      return;
    }
    checking = true;
    fetch(liveUrl, { credentials: 'same-origin', cache: 'no-store', headers: { 'Accept': 'application/json' } })
      .then(function (response) {
        if (response.status === 401 || response.status === 403) {
          stopped = true;
          return null;
        }
        return response.ok ? response.json() : null;
      })
      .then(function (data) {
        if (!data || data.ok !== true || typeof data.version !== 'string' || data.version === version) {
          return null;
        }
        return fetch(window.location.pathname + '?live=1', { credentials: 'same-origin', cache: 'no-store' })
          .then(function (response) {
            return response.ok ? response.text() : null;
          })
          .then(function (html) {
            if (html !== null && !submitting) {
              swapIn(html);
            }
          });
      })
      .catch(function () {
        // Connection dropped: try again next time
      })
      .then(function () {
        checking = false;
      });
  }

  window.setInterval(check, CHECK_EVERY_MS);

  // Back on the tab: update now
  document.addEventListener('visibilitychange', function () {
    if (document.visibilityState === 'visible') {
      check();
    }
  });
})();
