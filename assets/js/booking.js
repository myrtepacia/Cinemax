(function () {
  'use strict';

  var form = document.getElementById('booking-form');
  if (!form) {
    return;
  }

  var PESO = '₱';
  var TIMES = '×';

  var monthNames = window.CinemaxDates.monthNames;
  var pad = window.CinemaxDates.pad;
  var readDate = window.CinemaxDates.readDate;
  var dateKey = window.CinemaxDates.dateKey;
  var monthNumber = window.CinemaxDates.monthNumber;
  var readableDate = window.CinemaxDates.readableDate;

  var seatsUrl = form.getAttribute('data-seats-url') || '';
  var movieId = form.getAttribute('data-movie-id') || '';
  var firstDay = form.getAttribute('data-from') || '';
  var lastDay = form.getAttribute('data-to') || '';
  var serverToday = form.getAttribute('data-today') || firstDay;
  var utcOffset = parseInt(form.getAttribute('data-utc-offset'), 10) || 0;
  var afterNote = form.getAttribute('data-after-note') || 'This movie has stopped showing by then';
  var ticketPrice = parseInt(form.getAttribute('data-ticket-price'), 10) || 0;
  var maxSeats = parseInt(form.getAttribute('data-max-seats'), 10) || 10;
  var maxPerSnack = parseInt(form.getAttribute('data-max-per-snack'), 10) || 10;

  var dateField = document.getElementById('date-field');
  var dateButton = document.getElementById('date-button');
  var dateText = document.getElementById('date-text');
  var dateHolder = document.getElementById('date');

  var calendar = document.getElementById('calendar');
  var calendarDates = document.getElementById('calendar-dates');
  var calendarMonth = document.getElementById('calendar-month');
  var calendarBack = document.getElementById('calendar-back');
  var calendarNext = document.getElementById('calendar-next');

  var showtimeSelect = document.getElementById('showtime');
  var showtimePlaceholder = showtimeSelect.querySelector('option[value=""]');
  var pickFirst = document.getElementById('pick-first');
  var seatsAndSnacks = document.getElementById('seats-and-snacks');
  var seatMap = document.getElementById('seat-map');

  var summarySeats = document.getElementById('summary-seats');
  var summarySnacks = document.getElementById('summary-snacks');
  var summaryTickets = document.getElementById('summary-tickets');
  var summaryAmount = document.getElementById('summary-amount');
  var errorBox = document.getElementById('booking-error');
  var confirmButton = document.getElementById('confirm-button');
  var confirmLabel = confirmButton ? confirmButton.textContent : '';

  var seatInputs = Array.prototype.slice.call(seatMap.querySelectorAll('.seat input[type="checkbox"]'));
  var snacks = Array.prototype.slice.call(seatsAndSnacks.querySelectorAll('.snack'));

  function peso(amount) {
    return PESO + amount.toLocaleString('en-US');
  }

  function cinemaNow() {
    var shifted = new Date(Date.now() + utcOffset * 1000);
    return {
      date: shifted.getUTCFullYear() + '-' + pad(shifted.getUTCMonth() + 1) + '-' + pad(shifted.getUTCDate()),
      time: pad(shifted.getUTCHours()) + ':' + pad(shifted.getUTCMinutes()) + ':' + pad(shifted.getUTCSeconds())
    };
  }

  function hasStarted(key, time) {
    var now = cinemaNow();
    if (key !== now.date) {
      return key < now.date;
    }
    return time <= now.time;
  }

  function showtimeOptions() {
    return Array.prototype.filter.call(showtimeSelect.options, function (option) {
      return option.value !== '';
    });
  }

  function hasShowingsLeft(key) {
    return showtimeOptions().some(function (option) {
      return !hasStarted(key, option.value);
    });
  }

  function showError(message) {
    errorBox.textContent = message;
    errorBox.hidden = false;
  }

  function clearError() {
    errorBox.textContent = '';
    errorBox.hidden = true;
  }

  var today = readDate(serverToday);
  var chosenDate = '';
  var viewYear = today.getFullYear();
  var viewMonth = today.getMonth();

  function whyNotAvailable(key) {
    if (key < firstDay) {
      return 'This date has already passed';
    }
    if (key > lastDay) {
      return afterNote;
    }
    if (!hasShowingsLeft(key)) {
      return 'Every showing on this day has already started';
    }
    return '';
  }

  function drawCalendar() {
    calendarMonth.textContent = monthNames[viewMonth] + ' ' + viewYear;
    while (calendarDates.firstChild) {
      calendarDates.removeChild(calendarDates.firstChild);
    }

    var startsOn = new Date(viewYear, viewMonth, 1).getDay();
    var daysInMonth = new Date(viewYear, viewMonth + 1, 0).getDate();
    var count;

    for (count = 0; count < startsOn; count++) {
      calendarDates.appendChild(document.createElement('span'));
    }

    for (count = 1; count <= daysInMonth; count++) {
      var key = dateKey(new Date(viewYear, viewMonth, count));
      var box = document.createElement('button');
      var reason = whyNotAvailable(key);

      box.type = 'button';
      box.className = 'calendar-date';
      box.textContent = String(count);
      box.setAttribute('data-date', key);

      if (reason === '') {
        box.title = 'Book ' + count + ' ' + monthNames[viewMonth] + ' ' + viewYear;
      } else {
        box.disabled = true;
        box.title = reason;
      }

      if (key === serverToday) {
        box.className += ' calendar-date-today';
      }

      if (key === chosenDate) {
        box.className += ' calendar-date-picked';
        box.setAttribute('aria-pressed', 'true');
      }

      calendarDates.appendChild(box);
    }

    calendarBack.disabled = (viewYear * 12 + viewMonth) <= monthNumber(today);
  }

  function moveMonth(step) {
    var moved = viewYear * 12 + viewMonth + step;
    if (moved < monthNumber(today)) {
      return;
    }
    viewYear = Math.floor(moved / 12);
    viewMonth = moved % 12;
    drawCalendar();
  }

  function openCalendar() {
    var start = chosenDate === '' ? today : readDate(chosenDate);
    viewYear = start.getFullYear();
    viewMonth = start.getMonth();
    drawCalendar();
    calendar.hidden = false;
    dateButton.setAttribute('aria-expanded', 'true');
    var still = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    calendar.scrollIntoView({ block: 'nearest', behavior: still ? 'auto' : 'smooth' });
  }

  function closeCalendar() {
    calendar.hidden = true;
    dateButton.setAttribute('aria-expanded', 'false');
  }

  function pickDate(key) {
    chosenDate = key;
    dateHolder.value = key;
    dateText.textContent = readableDate(key);
  }

  function refreshShowtimes() {
    var hasDate = chosenDate !== '';
    showtimeSelect.disabled = !hasDate;
    showtimePlaceholder.textContent = hasDate ? 'Choose a time' : 'Choose a date first';
    if (!hasDate) {
      showtimeSelect.value = '';
    }

    showtimeOptions().forEach(function (option) {
      var started = chosenDate !== '' && hasStarted(chosenDate, option.value);
      var label = option.getAttribute('data-label') || option.textContent;
      option.disabled = started;
      option.textContent = started ? label + ' (started)' : label;
    });
    var picked = showtimeSelect.options[showtimeSelect.selectedIndex];
    if (picked && picked.disabled) {
      showtimeSelect.value = '';
    }
  }

  function isReady() {
    return chosenDate !== '' && showtimeSelect.value !== '';
  }

  function showOrHideSeatsAndSnacks() {
    var ready = isReady();
    pickFirst.hidden = ready;
    seatsAndSnacks.classList.toggle('seats-waiting', !ready);
  }

  var lookupNumber = 0;

  function finishLookup() {
    seatMap.classList.remove('seat-map-loading');
    seatMap.removeAttribute('aria-busy');
  }

  function markTakenSeats(taken, background) {
    var takenSet = {};
    taken.forEach(function (code) {
      if (typeof code === 'string') {
        takenSet[code] = true;
      }
    });

    var lost = [];
    seatInputs.forEach(function (input) {
      var isTaken = takenSet[input.value] === true;
      if (isTaken && input.checked) {
        lost.push(input.value);
      }
      if (isTaken) {
        input.checked = false;
      }
      input.disabled = isTaken;
      input.nextElementSibling.classList.toggle('seat-box-sold', isTaken);
      input.parentNode.title = isTaken ? 'Unavailable' : '';
    });

    if (lost.length > 0) {
      showError('Someone else has just taken ' + lost.join(', ') + '. Please pick other seats.');
    } else if (!background) {
      clearError();
    }
    updateSummary();
  }

  function loadTakenSeats(background) {
    if (!isReady()) {
      return;
    }
    var mine = ++lookupNumber;
    if (!background) {
      seatMap.classList.add('seat-map-loading');
      seatMap.setAttribute('aria-busy', 'true');
    }

    var address = seatsUrl +
      '?movie=' + encodeURIComponent(movieId) +
      '&date=' + encodeURIComponent(chosenDate) +
      '&time=' + encodeURIComponent(showtimeSelect.value);

    fetch(address, {
      method: 'GET',
      credentials: 'same-origin',
      cache: 'no-store',
      headers: { 'Accept': 'application/json' }
    })
      .then(function (response) {
        return response.json().then(function (body) {
          return body;
        }, function () {
          return null;
        });
      })
      .then(function (body) {
        if (mine !== lookupNumber) {
          return;
        }
        finishLookup();
        if (body && body.ok === true && Array.isArray(body.taken)) {
          markTakenSeats(body.taken, background);
          return;
        }
        if (background) {
          return;
        }
        showError(body && typeof body.error === 'string'
          ? body.error
          : 'We could not check which seats are free. Please try again.');
      })
      .catch(function () {
        if (mine !== lookupNumber) {
          return;
        }
        finishLookup();
        if (!background) {
          showError('We could not check which seats are free. Check your connection and try again.');
        }
      });
  }

  var RECHECK_SEATS_MS = 10000;
  window.setInterval(function () {
    if (document.visibilityState === 'visible' && !sending && isReady()) {
      loadTakenSeats(true);
    }
  }, RECHECK_SEATS_MS);

  function choiceChanged() {
    refreshShowtimes();
    showOrHideSeatsAndSnacks();
    clearError();
    loadTakenSeats();
  }

  function snackQuantity(snack) {
    var howMany = parseInt(snack.querySelector('.snack-input').value, 10);
    if (isNaN(howMany) || howMany < 0) {
      return 0;
    }
    return Math.min(howMany, maxPerSnack);
  }

  function chosenSeats() {
    return seatInputs.filter(function (input) {
      return input.checked && !input.disabled;
    }).map(function (input) {
      return input.value;
    });
  }

  function updateSummary() {
    var seats = chosenSeats();
    summarySeats.textContent = seats.length ? seats.join(', ') : 'Tap the seats above';

    var chosenSnackNames = [];
    var snackTotal = 0;
    snacks.forEach(function (snack) {
      var howMany = snackQuantity(snack);
      if (howMany > 0) {
        var name = snack.querySelector('.snack-name').textContent.trim();
        var price = parseInt(snack.getAttribute('data-price'), 10) || 0;
        chosenSnackNames.push(name + ' ' + TIMES + ' ' + howMany);
        snackTotal += price * howMany;
      }
    });
    summarySnacks.textContent = chosenSnackNames.length ? chosenSnackNames.join(', ') : 'Use + to add any items above';

    var ticketCount = seats.length;
    var ticketWord = ticketCount === 1 ? 'ticket' : 'tickets';
    summaryTickets.textContent = ticketCount + ' ' + ticketWord + ' ' + TIMES + ' ' + peso(ticketPrice);
    summaryAmount.textContent = peso(ticketCount * ticketPrice + snackTotal);
  }

  function setSnackQuantity(snack, howMany) {
    howMany = Math.max(0, Math.min(maxPerSnack, howMany));
    snack.querySelector('.snack-input').value = String(howMany);
    snack.querySelector('.snack-quantity').textContent = String(howMany);
    snack.querySelector('.snack-less').disabled = howMany === 0;
    snack.querySelector('.snack-more').disabled = howMany === maxPerSnack;
    snack.querySelector('.snack-box').classList.toggle('snack-box-picked', howMany > 0);
  }

  dateButton.addEventListener('click', function () {
    if (calendar.hidden) {
      openCalendar();
    } else {
      closeCalendar();
    }
  });

  calendarDates.addEventListener('click', function (event) {
    var box = event.target.closest('.calendar-date');
    if (!box || box.disabled) {
      return;
    }
    pickDate(box.getAttribute('data-date'));
    closeCalendar();
    dateButton.focus();
    choiceChanged();
  });

  calendarBack.addEventListener('click', function () {
    moveMonth(-1);
  });

  calendarNext.addEventListener('click', function () {
    moveMonth(1);
  });

  document.addEventListener('click', function (event) {
    if (!dateField.contains(event.target)) {
      closeCalendar();
    }
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && !calendar.hidden) {
      closeCalendar();
      dateButton.focus();
    }
  });

  showtimeSelect.addEventListener('change', choiceChanged);

  seatsAndSnacks.addEventListener('click', function (event) {
    var step = event.target.closest('.snack-step');
    if (!step || step.disabled) {
      return;
    }
    var snack = step.closest('.snack');
    setSnackQuantity(snack, snackQuantity(snack) + Number(step.getAttribute('data-step')));
    updateSummary();
  });

  seatMap.addEventListener('change', function (event) {
    var input = event.target;
    if (!input.matches('input[type="checkbox"]')) {
      return;
    }
    if (input.checked && chosenSeats().length > maxSeats) {
      input.checked = false;
      showError('One booking can hold up to ' + maxSeats + ' seats.');
    } else {
      clearError();
    }
    updateSummary();
  });

  var sending = false;
  form.addEventListener('submit', function (event) {
    var problem = '';
    if (chosenDate === '' || dateHolder.value === '') {
      problem = 'Choose a date first.';
    } else if (showtimeSelect.value === '') {
      problem = 'Choose a showtime.';
    } else if (chosenSeats().length === 0) {
      problem = 'Pick at least one seat.';
    }

    if (problem !== '' || sending) {
      event.preventDefault();
      if (problem !== '') {
        showError(problem);
      }
      return;
    }

    sending = true;
    clearError();
    if (confirmButton) {
      confirmButton.disabled = true;
      confirmButton.textContent = 'Taking you to payment…';
    }
  });

  function unlockConfirm() {
    sending = false;
    if (confirmButton) {
      confirmButton.disabled = false;
      confirmButton.textContent = confirmLabel;
    }
  }

  window.addEventListener('pageshow', function (event) {
    if (event.persisted) {
      unlockConfirm();
      refreshShowtimes();
      showOrHideSeatsAndSnacks();
      loadTakenSeats();
    }
  });

  document.addEventListener('visibilitychange', function () {
    if (document.visibilityState === 'visible') {
      loadTakenSeats();
    }
  });

  window.setInterval(function () {
    if (chosenDate === '') {
      return;
    }
    var hadTime = showtimeSelect.value !== '';
    refreshShowtimes();
    if (hadTime && showtimeSelect.value === '') {
      showOrHideSeatsAndSnacks();
    }
  }, 60000);

  var startDate = form.getAttribute('data-initial-date') || dateHolder.value || '';
  if (/^\d{4}-\d{2}-\d{2}$/.test(startDate) && whyNotAvailable(startDate) === '') {
    pickDate(startDate);
  } else {
    dateHolder.value = '';
  }

  var startTime = form.getAttribute('data-initial-time') || '';
  if (startTime !== '' && showtimeSelect.value === '') {
    showtimeOptions().forEach(function (option) {
      if (option.value === startTime) {
        showtimeSelect.value = startTime;
      }
    });
  }

  snacks.forEach(function (snack) {
    setSnackQuantity(snack, snackQuantity(snack));
  });

  unlockConfirm();
  drawCalendar();
  refreshShowtimes();
  showOrHideSeatsAndSnacks();
  updateSummary();
  loadTakenSeats();
})();
