// Snacks Claim monitor: every few seconds asks api/claim-monitor.php for the
// preparing and ready numbers, redraws a column only when it changes, and
// flashes numbers that just became ready.
(function () {
  var screen = document.getElementById('claim-screen');
  if (!screen || !window.fetch) {
    return;
  }

  var liveUrl = screen.getAttribute('data-live-url') || '';
  var lists = {
    preparing: document.getElementById('claim-preparing'),
    ready: document.getElementById('claim-ready')
  };
  var problem = document.getElementById('claim-problem');
  var CHECK_EVERY_MS = 3000;
  var checking = false;

  function shownNumbers(list) {
    return Array.prototype.map.call(list.children, function (item) {
      return item.textContent;
    });
  }

  function draw(stage, numbers) {
    var list = lists[stage];
    var before = shownNumbers(list);
    if (before.join('|') === numbers.join('|')) {
      return;
    }
    while (list.firstChild) {
      list.removeChild(list.firstChild);
    }
    numbers.forEach(function (number) {
      var item = document.createElement('li');
      item.textContent = number;
      if (stage === 'ready' && before.indexOf(number) === -1) {
        item.className = 'claim-new';
      }
      list.appendChild(item);
    });
  }

  function say(text) {
    problem.textContent = text;
    problem.hidden = text === '';
  }

  // Keep asking while signed out, so it carries on once the admin signs in
  // again
  function check() {
    if (checking) {
      return;
    }
    checking = true;
    fetch(liveUrl, { credentials: 'same-origin', cache: 'no-store', headers: { 'Accept': 'application/json' } })
      .then(function (response) {
        if (response.status === 401 || response.status === 403) {
          say('Signed out: sign in with the admin account to keep this screen up to date.');
          return null;
        }
        if (!response.ok) {
          say('Reconnecting…');
          return null;
        }
        return response.json();
      })
      .then(function (data) {
        if (!data) {
          return;
        }
        if (data.ok !== true || !Array.isArray(data.preparing) || !Array.isArray(data.ready)) {
          say('Reconnecting…');
          return;
        }
        say('');
        draw('preparing', data.preparing.map(String));
        draw('ready', data.ready.map(String));
      })
      .catch(function () {
        say('Reconnecting…');
      })
      .then(function () {
        checking = false;
      });
  }

  window.setInterval(check, CHECK_EVERY_MS);

  // Full-screen button, where the browser allows it; hidden while full screen
  var fullButton = document.getElementById('claim-fullscreen');
  var page = document.documentElement;
  if (fullButton && page.requestFullscreen) {
    fullButton.hidden = false;
    fullButton.addEventListener('click', function () {
      page.requestFullscreen().catch(function () {
        // Refused by the browser: ignore
      });
    });
    document.addEventListener('fullscreenchange', function () {
      fullButton.hidden = Boolean(document.fullscreenElement);
    });
  }
})();
