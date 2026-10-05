// QR page (pay.php) and card page (card.php): counts down the seat hold and
// checks api/payment-status.php every few seconds. Opens the ticket once
// paid; fades the box out when time is up.
(function () {
  var box = document.querySelector('[data-status-url][data-seconds-left]');
  if (!box) {
    return;
  }

  var statusUrl = box.getAttribute('data-status-url') || '';
  var countdown = document.getElementById('pay-countdown');
  var statusLine = document.getElementById('pay-status');
  var CHECK_EVERY_MS = 4000;

  // From the server time, not this device's clock
  var endsAt = Date.now() + (parseInt(box.getAttribute('data-seconds-left'), 10) || 0) * 1000;
  var ended = false;
  var checking = false;
  var leaving = false;

  function secondsLeft() {
    return Math.max(0, Math.ceil((endsAt - Date.now()) / 1000));
  }

  function showTime() {
    var left = secondsLeft();
    var seconds = left % 60;
    countdown.textContent = Math.floor(left / 60) + ':' + (seconds < 10 ? '0' : '') + seconds;
    // Last minute in red
    box.classList.toggle('pay-hurry', left > 0 && left <= 60);
    if (left === 0 && !ended) {
      ended = true;
      box.classList.add('pay-ended');
      statusLine.textContent = box.getAttribute('data-ended-text') || 'Time is up.';
      // A payment in the last seconds still counts
      check();
    }
  }

  function check() {
    if (checking || leaving || statusUrl === '') {
      return;
    }
    checking = true;
    fetch(statusUrl, { credentials: 'same-origin', cache: 'no-store', headers: { 'Accept': 'application/json' } })
      .then(function (response) {
        if (response.status === 401) {
          statusLine.textContent = 'You have been signed out. Sign in again to see your ticket under My Bookings.';
          return null;
        }
        return response.ok ? response.json() : null;
      })
      .then(function (data) {
        if (!data || data.ok !== true) {
          return;
        }
        if (data.status === 'paid') {
          leaving = true;
          statusLine.textContent = 'Payment received! Opening your ticket…';
          window.location.href = data.next;
        } else if (data.status === 'ended' && data.next) {
          leaving = true;
          window.location.href = data.next;
        } else if (data.status === 'refresh') {
          // PayMongo keys changed (test/live): reload for a new QR code or
          // card form
          leaving = true;
          window.location.reload();
        } else if (typeof data.secondsLeft === 'number') {
          // Keep the countdown in step with the server
          endsAt = Date.now() + data.secondsLeft * 1000;
        }
      })
      .catch(function () {
        // Connection dropped: ask again next time
      })
      .then(function () {
        checking = false;
      });
  }

  showTime();
  window.setInterval(showTime, 1000);
  window.setInterval(check, CHECK_EVERY_MS);

  // Back on the tab (after paying in another app): check now
  document.addEventListener('visibilitychange', function () {
    if (document.visibilityState === 'visible') {
      showTime();
      check();
    }
  });
})();
