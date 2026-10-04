// The QR Ph payment page (pay.php). Counts down to the end of the seat hold,
// and every few seconds asks api/payment-status.php whether PayMongo has the
// money: the e-ticket opens by itself once it has. When the time is up the
// code is faded out (PayMongo stops accepting it at the same moment) and the
// payment page takes over.
(function () {
  var box = document.getElementById('qr-pay');
  if (!box) {
    return;
  }

  var statusUrl = box.getAttribute('data-status-url') || '';
  var countdown = document.getElementById('qr-countdown');
  var statusLine = document.getElementById('qr-status');
  var CHECK_EVERY_MS = 4000;

  // Counted from what the server said, not from this device's clock
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
    // The last minute in red
    box.classList.toggle('qr-pay-hurry', left > 0 && left <= 60);
    if (left === 0 && !ended) {
      ended = true;
      box.classList.add('qr-pay-ended');
      statusLine.textContent = 'Time is up: this code no longer works.';
      // A payment made in the last seconds still counts
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
        } else if (typeof data.secondsLeft === 'number') {
          // Keep the countdown in step with the server
          endsAt = Date.now() + data.secondsLeft * 1000;
        }
      })
      .catch(function () {
        // A dropped connection: just ask again next time
      })
      .then(function () {
        checking = false;
      });
  }

  showTime();
  window.setInterval(showTime, 1000);
  window.setInterval(check, CHECK_EVERY_MS);

  // Coming back to the tab (after paying in the bank's app on the same
  // phone, say): ask straight away
  document.addEventListener('visibilitychange', function () {
    if (document.visibilityState === 'visible') {
      showTime();
      check();
    }
  });
})();
