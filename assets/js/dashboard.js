// Staff Dashboard. Every few seconds asks api/dashboard.php for a
// fingerprint; when it changes, fetches the page again and swaps in the new
// figures and orders without a reload. Also the search above Sold, which asks
// api/snack-search.php as the admin types.
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

  // ---- Search above Sold ----

  var search = document.getElementById('sold-search');
  var searchInput = document.getElementById('sold-search-input');
  var searchNote = document.getElementById('sold-search-note');
  var searchResults = document.getElementById('sold-search-results');
  var searchUrl = search ? search.getAttribute('data-url') || '' : '';
  var searchTimer = null;
  var searchNumber = 0;

  // Sold list and its empty line, hidden while searching
  function soldParts() {
    var group = search ? search.closest('.order-group') : null;
    return group ? group.querySelectorAll('.order-list, .empty-note') : [];
  }

  function showSoldList(show) {
    Array.prototype.forEach.call(soldParts(), function (part) {
      part.hidden = !show;
    });
  }

  function line(className, text) {
    var p = document.createElement('p');
    p.className = className;
    p.textContent = text;
    return p;
  }

  // One found order, laid out like a Sold row, with its times
  function orderRow(order) {
    var row = document.createElement('div');
    row.className = 'order-row';

    var left = document.createElement('div');
    left.className = 'order-left';
    left.appendChild(line('order-items', order.items));

    var who = line('order-who', '');
    if (order.number) {
      var number = document.createElement('strong');
      number.textContent = order.number;
      who.appendChild(number);
      who.appendChild(document.createTextNode(' • '));
    }
    who.appendChild(document.createTextNode([order.reference, order.name, order.count].join(' • ')));
    left.appendChild(who);

    left.appendChild(line('order-time', 'Scanned at the snack counter: ' + (order.scanned || 'not recorded')));
    left.appendChild(line('order-time', 'Picked up: ' + (order.sold || 'not recorded')));
    row.appendChild(left);

    row.appendChild(line('order-price', order.total));
    var tag = document.createElement('span');
    tag.className = 'pill pill-grey sold-tag';
    tag.textContent = 'Sold';
    row.appendChild(tag);
    return row;
  }

  function showSearch(orders, note) {
    while (searchResults.firstChild) {
      searchResults.removeChild(searchResults.firstChild);
    }
    orders.forEach(function (order) {
      searchResults.appendChild(orderRow(order));
    });
    searchNote.textContent = note;
    searchNote.hidden = note === '';
  }

  function runSearch() {
    var typed = searchInput.value.trim();
    var mine = ++searchNumber;
    if (typed === '') {
      showSearch([], '');
      showSoldList(true);
      return;
    }
    showSoldList(false);
    fetch(searchUrl + '?q=' + encodeURIComponent(typed), { credentials: 'same-origin', cache: 'no-store', headers: { 'Accept': 'application/json' } })
      .then(function (response) {
        return response.ok ? response.json() : null;
      })
      .then(function (data) {
        // Only the latest search counts
        if (mine !== searchNumber) {
          return;
        }
        if (!data || data.ok !== true || !Array.isArray(data.orders)) {
          showSearch([], 'The search did not work just now. Keep typing to try again.');
          return;
        }
        var note = '';
        if (data.orders.length === 0) {
          note = typeof data.note === 'string' && data.note !== ''
            ? data.note
            : 'No picked-up snack order matches ' + typed.toUpperCase() + '.';
        }
        showSearch(data.orders, note);
      })
      .catch(function () {
        if (mine === searchNumber) {
          showSearch([], 'The search did not work just now. Keep typing to try again.');
        }
      });
  }

  if (search) {
    // Wait until typing stops
    searchInput.addEventListener('input', function () {
      clearTimeout(searchTimer);
      searchTimer = setTimeout(runSearch, 250);
    });
  }

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
    // Keep the search box as it was and search again, in case a match was
    // just picked up
    var freshSearch = document.getElementById('sold-search');
    if (search && freshSearch && freshSearch !== search) {
      freshSearch.replaceWith(search);
      if (searchInput.value.trim() !== '') {
        runSearch();
      }
    }
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
