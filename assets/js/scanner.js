// Ticket Scanner and Snack Scanner. After Open camera, frames go to a hidden
// canvas a few times a second and jsQR looks for a code. Non-Cinemax codes
// are turned away at once; Cinemax codes are sent to data-api-url, and the
// server decides. data-mode=ticket admits a good ticket on Confirm; data-
// mode=snack shows the order and Confirm sends it to Preparing. Replies are
// shown with textContent, never as HTML.
(function () {
  var panel = document.getElementById('scanner');
  if (!panel) {
    return;
  }

  var apiUrl = panel.getAttribute('data-api-url') || '';
  var snackMode = panel.getAttribute('data-mode') === 'snack';
  var tokenMeta = document.querySelector('meta[name="csrf-token"]');
  var csrfToken = tokenMeta ? (tokenMeta.getAttribute('content') || '') : '';

  var startBtn = document.getElementById('start-camera');
  var closeBtn = document.getElementById('close-camera');
  var videoBox = document.getElementById('camera-video');
  var message = document.getElementById('camera-message');
  var result = document.getElementById('scan-result');
  var resultTitle = document.getElementById('scan-result-title');
  var resultWho = document.getElementById('scan-result-who');
  // Snack Scanner only: the order lines
  var itemsList = document.getElementById('scan-items');
  var actions = document.getElementById('scan-actions');
  var confirmBtn = document.getElementById('confirm-booking');
  var cancelBtn = document.getElementById('cancel-booking');

  // How often a frame is searched
  var SCAN_EVERY_MS = 250;
  // A code unseen this long has left the view. Until then it is not checked
  // again; shown again after that, it is checked again.
  var LEFT_VIEW_MS = 1200;
  // Frames are shrunk to this width: enough for a ticket and faster to search
  var MAX_FRAME_WIDTH = 640;
  // The server refuses longer codes
  var MAX_CODE_LENGTH = 300;
  // What a Cinemax ticket code looks like. Anything else is turned away
  // without asking the server; the server still checks the rest.
  var TICKET_PATTERN = /^CINEMAX\|CMX-[A-Z0-9]{6}\|[a-f0-9]{32}$/;

  var IDLE_TEXT = 'No QR code in view. Point the camera at the QR code on the ticket.';
  // Say nothing while the camera is off
  var OFF_TEXT = '';

  var stream = null;
  var video = null;
  var canvas = document.createElement('canvas');
  var context = canvas.getContext('2d', { willReadFrequently: true });
  var scanTimer = null;
  var retryTimer = null;
  // True while a code is checked, or a good ticket waits for staff
  var busy = false;
  // True once signed out (going to sign-in)
  var stopped = false;
  // Last code read, and when it was last seen
  var lastCode = '';
  var lastSeenAt = 0;
  // Good ticket on screen, waiting for Confirm or Cancel
  var pendingCode = '';

  function idleMessage() {
    message.textContent = stream ? IDLE_TEXT : OFF_TEXT;
  }

  // ---- Showing results ----

  var RESULT_CLASSES = {
    good: 'scan-result-good',
    used: 'scan-result-used',
    bad: 'scan-result-bad'
  };

  function showResult(kind, title, who, items) {
    result.classList.remove('scan-result-good', 'scan-result-used', 'scan-result-bad');
    result.classList.add(RESULT_CLASSES[kind]);
    resultTitle.textContent = title;
    resultWho.textContent = who || '';
    resultWho.classList.toggle('hidden', !who);
    showItems(items || []);
    result.classList.remove('hidden');
  }

  // One line per snack, like 2 × Popcorn, Large
  function showItems(items) {
    if (!itemsList) {
      return;
    }
    while (itemsList.firstChild) {
      itemsList.removeChild(itemsList.firstChild);
    }
    items.forEach(function (item) {
      if (!item || typeof item.name !== 'string') {
        return;
      }
      var quantity = typeof item.quantity === 'number' ? item.quantity : 1;
      var line = document.createElement('li');
      line.textContent = quantity + ' × ' + item.name;
      itemsList.appendChild(line);
    });
    itemsList.classList.toggle('hidden', itemsList.childNodes.length === 0);
  }

  function hideResult() {
    result.classList.add('hidden');
    actions.classList.add('hidden');
    showItems([]);
  }

  // A text field from the reply, or empty
  function field(data, key) {
    return typeof data[key] === 'string' ? data[key] : '';
  }

  // Name • Movie • Seats C5, C6, skipping blanks
  function joinParts(parts) {
    return parts.filter(function (part) {
      return part !== '';
    }).join(' • ');
  }

  function seatsText(seats) {
    if (seats === '') {
      return '';
    }
    return (seats.indexOf(',') === -1 ? 'Seat ' : 'Seats ') + seats;
  }

  function whoText(data) {
    return joinParts([field(data, 'name'), field(data, 'movie'), seatsText(field(data, 'seats'))]);
  }

  // ---- Server ----

  // Site folder, from the API address (.../api/scan.php)
  function siteBase() {
    return new URL('../', new URL(apiUrl, window.location.href));
  }

  // Get a fresh security token from this page when it expires, so staff never
  // refresh
  function refreshToken() {
    return fetch(window.location.href, { credentials: 'same-origin', cache: 'no-store' })
      .then(function (response) {
        return response.ok ? response.text() : '';
      })
      .then(function (html) {
        var page = new DOMParser().parseFromString(html, 'text/html');
        var meta = page.querySelector('meta[name="csrf-token"]');
        var fresh = meta ? (meta.getAttribute('content') || '') : '';
        // Signed out: the reply was the sign-in page
        if (fresh === '' || !page.getElementById('scanner')) {
          return false;
        }
        csrfToken = fresh;
        return true;
      })
      .catch(function () {
        return false;
      });
  }

  function post(action, code) {
    return fetch(apiUrl, {
      method: 'POST',
      credentials: 'same-origin',
      cache: 'no-store',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-Token': csrfToken
      },
      body: JSON.stringify({ action: action, code: code })
    }).then(function (response) {
      return response.json().catch(function () {
        return null;
      }).then(function (data) {
        if (!response.ok || !data || data.ok !== true || typeof data.status !== 'string') {
          var problem = new Error('The scan request failed.');
          problem.status = response.status;
          throw problem;
        }
        return data;
      });
    });
  }

  // One request, retried once with a fresh token if needed
  function send(action, code) {
    return post(action, code).catch(function (error) {
      if (error && error.status === 403) {
        return refreshToken().then(function (renewed) {
          if (!renewed) {
            var signedOut = new Error('Signed out.');
            signedOut.status = 401;
            throw signedOut;
          }
          return post(action, code);
        });
      }
      throw error;
    });
  }

  // ---- Answers ----

  function showAnswer(data, code) {
    if (snackMode) {
      showSnackAnswer(data, code);
    } else {
      showTicketAnswer(data, code);
    }
  }

  // Good ticket or order: wait for Confirm or Cancel
  function awaitConfirm(code, hint) {
    pendingCode = code;
    confirmBtn.disabled = false;
    cancelBtn.disabled = false;
    actions.classList.remove('hidden');
    message.textContent = hint;
  }

  // Any other answer stays while the code is in view and clears soon after.
  // Scanning carries on at once.
  function doneWith(code) {
    busy = false;
    lastCode = code;
    lastSeenAt = Date.now();
    message.textContent = 'Take the ticket away, or show the next one.';
  }

  // Snack counter answer
  function showSnackAnswer(data, code) {
    var status = data.status;
    var items = Array.isArray(data.items) ? data.items : [];
    var who = joinParts([field(data, 'name'), field(data, 'reference')]);

    if (status === 'waiting') {
      showResult('good', 'Snack order', joinParts([who, field(data, 'snacks_total')]), items);
      awaitConfirm(code, 'Check the order with the customer, then press Confirm order to send it to Preparing.');
      return;
    }

    if (status === 'confirmed') {
      showResult('good', 'Sent to Preparing', who, items);
    } else if (status === 'in_progress') {
      showResult('used', field(data, 'stage') === 'ready' ? 'Ready at the counter' : 'Already being prepared', who, items);
    } else if (status === 'collected') {
      showResult('used', 'Already picked up', who, items);
    } else if (status === 'no_snacks') {
      showResult('bad', 'No snacks on this ticket', who);
    } else if (status === 'refunded') {
      showResult('bad', 'Refunded ticket', joinParts(['This booking was refunded', field(data, 'reference')]));
    } else if (status === 'wrong_day') {
      var day = field(data, 'show_date');
      showResult('bad', 'Not for today', day !== '' ? 'These snacks are for ' + day : 'These snacks are for another day', items);
    } else if (status === 'not_cinemax') {
      showResult('bad', 'Not a Cinemax ticket', 'This QR code is not from a Cinemax e-ticket');
    } else {
      showResult('bad', 'Not a valid ticket', 'No booking matches that code');
    }
    doneWith(code);
  }

  // Door answer
  function showTicketAnswer(data, code) {
    var status = data.status;

    if (status === 'valid') {
      showResult('good', 'Valid ticket', whoText(data));
      awaitConfirm(code, 'Check the name, then press Confirm booking to let them in.');
      return;
    }

    if (status === 'admitted') {
      showResult('good', 'Admitted', whoText(data));
    } else if (status === 'used') {
      var when = field(data, 'scanned_at');
      showResult('used', 'Already scanned', joinParts([when !== '' ? 'Scanned at ' + when : '', field(data, 'reference')]));
    } else if (status === 'refunded') {
      showResult('bad', 'Refunded ticket', joinParts(['This booking was refunded', field(data, 'reference')]));
    } else if (status === 'wrong_day') {
      var day = field(data, 'show_date');
      showResult('bad', 'Not for today', day !== '' ? 'This ticket is for ' + day : 'This ticket is for another day');
    } else if (status === 'not_cinemax') {
      showResult('bad', 'Not a Cinemax ticket', 'This QR code is not from a Cinemax e-ticket');
    } else {
      showResult('bad', 'Not a valid ticket', 'No booking matches that code');
    }
    doneWith(code);
  }

  // No answer about the ticket
  function showProblem(error) {
    var status = error && error.status ? error.status : 0;
    hideResult();
    pendingCode = '';
    // Forget the code so it is tried again when seen
    lastCode = '';

    if (status === 401) {
      // Signed out: go sign in, then come back here
      stopped = true;
      message.textContent = 'You have been signed out. Taking you to sign in…';
      var base = siteBase();
      var here = window.location.pathname.indexOf(base.pathname) === 0
        ? window.location.pathname.slice(base.pathname.length)
        : '';
      window.setTimeout(function () {
        window.location.href = new URL('signin.php' + (here !== '' ? '?return=' + encodeURIComponent(here) : ''), base).href;
      }, 1500);
      return;
    }

    // Anything else: try again in a moment
    var wait = status === 429 ? 15000 : 3000;
    message.textContent = status === 429
      ? 'Too many scans in a short time. Scanning again in a few seconds…'
      : 'Could not reach the server. Trying again in a moment…';
    clearTimeout(retryTimer);
    retryTimer = setTimeout(function () {
      busy = false;
      idleMessage();
    }, wait);
  }

  // ---- Reading codes ----

  function codeFound(text) {
    var now = Date.now();
    if (text === lastCode && now - lastSeenAt < LEFT_VIEW_MS) {
      // Same code still in view: already done
      lastSeenAt = now;
      return;
    }
    lastCode = text;
    lastSeenAt = now;
    busy = true;
    hideResult();

    // Not a Cinemax ticket: no need to ask the server
    if (text.length > MAX_CODE_LENGTH || !TICKET_PATTERN.test(text)) {
      showAnswer({ status: 'not_cinemax' }, text);
      return;
    }

    message.textContent = snackMode ? 'Looking up the order…' : 'Checking the ticket…';
    send('check', text).then(function (data) {
      showAnswer(data, text);
    }, showProblem);
  }

  // No code: clear the last result after a moment, unless a good ticket is
  // waiting
  function nothingInView() {
    if (lastCode === '' || Date.now() - lastSeenAt < LEFT_VIEW_MS) {
      return;
    }
    lastCode = '';
    if (!busy && pendingCode === '') {
      hideResult();
      idleMessage();
    }
  }

  function scanFrame() {
    if (stopped || !video || video.readyState < 2) {
      return;
    }
    var width = video.videoWidth;
    var height = video.videoHeight;
    if (!width || !height) {
      return;
    }

    var scale = Math.min(1, MAX_FRAME_WIDTH / width);
    var w = Math.max(1, Math.round(width * scale));
    var h = Math.max(1, Math.round(height * scale));
    if (canvas.width !== w) {
      canvas.width = w;
    }
    if (canvas.height !== h) {
      canvas.height = h;
    }

    var frame;
    try {
      context.drawImage(video, 0, 0, w, h);
      frame = context.getImageData(0, 0, w, h);
    } catch (e) {
      return;
    }

    // attemptBoth also reads light-on-dark codes (dark mode)
    var found = window.jsQR(frame.data, w, h, { inversionAttempts: 'attemptBoth' });
    var text = found && typeof found.data === 'string' ? found.data : '';

    if (text === '') {
      nothingInView();
      return;
    }
    if (text === lastCode) {
      // Keep noting a code still in view so it is not taken as new
      lastSeenAt = Date.now();
      return;
    }
    // A new code: read it, unless one is being checked or waiting
    if (!busy && pendingCode === '') {
      codeFound(text);
    }
  }

  function startScanning() {
    clearInterval(scanTimer);
    scanTimer = setInterval(scanFrame, SCAN_EVERY_MS);
  }

  // ---- Camera ----

  function startCamera() {
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      // Browsers only allow the camera on https pages and localhost
      message.textContent = window.isSecureContext === false
        ? 'The camera only works when the site is opened over https.'
        : 'This browser cannot access the camera.';
      return;
    }
    if (typeof window.jsQR !== 'function') {
      message.textContent = 'The code reader did not load. Refresh the page to try again.';
      return;
    }

    // No second press while the browser asks for the camera
    startBtn.classList.add('hidden');
    message.textContent = 'Requesting camera access…';

    navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false })
      .then(function (mediaStream) {
        stream = mediaStream;

        // The phone may stop the camera; restart it when the page is looked
        // at again
        stream.getVideoTracks().forEach(function (track) {
          track.addEventListener('ended', function () {
            if (document.visibilityState === 'visible') {
              restartCamera();
            }
          });
        });

        video = document.createElement('video');
        video.autoplay = true;
        video.playsInline = true;
        video.muted = true;
        video.setAttribute('playsinline', '');
        // Block, not inline, so no dark strip shows under the picture
        video.style.display = 'block';
        video.style.width = '100%';
        video.style.height = '100%';
        video.style.objectFit = 'cover';
        video.style.borderRadius = 'inherit';
        video.srcObject = stream;

        videoBox.textContent = '';
        videoBox.appendChild(video);
        var playing = video.play();
        if (playing && typeof playing.catch === 'function') {
          playing.catch(function () {});
        }

        // A good ticket may still wait after a restart: keep its hint
        if (pendingCode === '') {
          idleMessage();
        }
        startBtn.classList.add('hidden');
        closeBtn.classList.remove('hidden');
        startScanning();
      })
      .catch(function (error) {
        var name = error && error.name ? error.name : '';
        // Blocked access is the usual reason, and staff can fix it
        if (name === 'NotAllowedError' || name === 'SecurityError') {
          message.textContent = 'Camera access is blocked. Allow the camera in your browser, then press Open camera.';
        } else if (name === 'NotFoundError' || name === 'OverconstrainedError') {
          message.textContent = 'No camera was found. Connect one, then press Open camera.';
        } else if (name === 'NotReadableError') {
          message.textContent = 'Another app is using the camera. Close it, then press Open camera.';
        } else {
          message.textContent = 'Could not open the camera' + (error && error.message ? ': ' + error.message : '.');
        }
        startBtn.classList.remove('hidden');
      });
  }

  function stopStream() {
    clearInterval(scanTimer);
    scanTimer = null;
    if (stream) {
      stream.getTracks().forEach(function (track) {
        track.stop();
      });
      stream = null;
    }
    video = null;
    videoBox.textContent = '';
  }

  function closeCamera() {
    stopStream();
    clearTimeout(retryTimer);
    pendingCode = '';
    busy = false;
    lastCode = '';
    hideResult();
    message.textContent = OFF_TEXT;
    closeBtn.classList.add('hidden');
    startBtn.classList.remove('hidden');
  }

  // A waiting good ticket survives a camera restart
  function restartCamera() {
    stopStream();
    startCamera();
  }

  function cameraStalled() {
    if (!stream) {
      return false;
    }
    var live = stream.getVideoTracks().some(function (track) {
      return track.readyState === 'live';
    });
    return !live || (video && video.paused);
  }

  document.addEventListener('visibilitychange', function () {
    if (document.visibilityState === 'visible' && !stopped && cameraStalled()) {
      restartCamera();
    }
  });

  // ---- Buttons ----

  confirmBtn.addEventListener('click', function () {
    if (pendingCode === '') {
      return;
    }
    var code = pendingCode;
    confirmBtn.disabled = true;
    cancelBtn.disabled = true;
    message.textContent = snackMode ? 'Sending the order to Preparing…' : 'Letting them in…';

    send(snackMode ? 'confirm' : 'admit', code).then(function (data) {
      pendingCode = '';
      actions.classList.add('hidden');
      showAnswer(data, code);
    }, showProblem);
  });

  cancelBtn.addEventListener('click', function () {
    pendingCode = '';
    busy = false;
    hideResult();
    idleMessage();
  });

  startBtn.addEventListener('click', startCamera);
  closeBtn.addEventListener('click', closeCamera);

  // The camera stays off until Open camera is pressed. The first time, the
  // browser asks for permission.
  message.textContent = OFF_TEXT;
  startBtn.classList.remove('hidden');
})();
