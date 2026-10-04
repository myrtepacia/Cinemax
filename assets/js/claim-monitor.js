// The Snacks Claim monitor keeps itself up to date. Every few seconds it asks
// api/claim-monitor.php which numbers are being prepared and which are
// ready, and redraws a column only when its numbers change. A number that
// has just become ready flashes, so customers notice it.
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

  // Keeps asking even while signed out, so the screen carries on by itself
  // once the admin signs in again (in another tab, say)
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

  // A button to fill the whole screen, where the browser allows it. It
  // hides itself while the page is full screen.
  var fullButton = document.getElementById('claim-fullscreen');
  var page = document.documentElement;
  if (fullButton && page.requestFullscreen) {
    fullButton.hidden = false;
    fullButton.addEventListener('click', function () {
      page.requestFullscreen().catch(function () {
        // Refused (some browsers only allow it from certain places): no harm
      });
    });
    document.addEventListener('fullscreenchange', function () {
      fullButton.hidden = Boolean(document.fullscreenElement);
    });
  }
})();
