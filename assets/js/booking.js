// The booking page (book.php): the calendar, the showtimes, the seat map, the
// snack counters and the running total.
//
// Everything the page needs from the server comes in data-* attributes on
// the form. The script only helps people choose: checkout.php checks every
// choice again and takes the prices from the database, never from here.
(function () {
  'use strict';

  var form = document.getElementById('booking-form');
  if (!form) {
    return;
  }

  var PESO = '₱';
  var TIMES = '×';

  var monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December'];
  var dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

  // What the server said about this film and this booking
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

  function pad(number) {
    return (number < 10 ? '0' : '') + number;
  }

  // Turns '2026-09-18' into a date the browser can work with
  function readDate(text) {
    var parts = text.split('-');
    return new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
  }

  // Turns a date back into '2026-09-18'. Keys like this compare correctly
  // as plain text, which is how every date below is compared.
  function dateKey(date) {
    return date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate());
  }

  // Counts a month as one number so two months are easy to compare
  function monthNumber(date) {
    return date.getFullYear() * 12 + date.getMonth();
  }

  // How a picked date reads on the date box, like 'Fri, 18 Sep 2026'
  function readableDate(key) {
    var date = readDate(key);
    return dayNames[date.getDay()] + ', ' + date.getDate() + ' ' +
      monthNames[date.getMonth()].slice(0, 3) + ' ' + date.getFullYear();
  }

  function peso(amount) {
    return PESO + amount.toLocaleString('en-US');
  }

  // The cinema's own date and clock, worked out from this browser's clock and
  // the cinema's time zone, so a visitor abroad still sees the right
  // showtimes greyed out. The server checks again anyway.
  function cinemaNow() {
    var shifted = new Date(Date.now() + utcOffset * 1000);
    return {
      date: shifted.getUTCFullYear() + '-' + pad(shifted.getUTCMonth() + 1) + '-' + pad(shifted.getUTCDate()),
      time: pad(shifted.getUTCHours()) + ':' + pad(shifted.getUTCMinutes()) + ':' + pad(shifted.getUTCSeconds())
    };
  }

  // True once a showing ('2026-09-18' at '17:00:00') has begun
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

  // A day still has something to see if at least one showing has not begun
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

  // The calendar

  var today = readDate(serverToday);
  var chosenDate = '';
  var viewYear = today.getFullYear();
  var viewMonth = today.getMonth();

  // Why a day cannot be booked, or '' when it can. Days before the first
  // bookable day and after the last are dead, and so is today once its last
  // showing has begun.
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

    // Empty boxes so the 1st lands under the right weekday
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

    // Months that are wholly past are of no use to anyone. Going forward
    // has no end: December rolls on into January of the next year.
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
    // Always open on the month of the date being held, or on this month
    var start = chosenDate === '' ? today : readDate(chosenDate);
    viewYear = start.getFullYear();
    viewMonth = start.getMonth();
    drawCalendar();
    calendar.hidden = false;
    dateButton.setAttribute('aria-expanded', 'true');
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

  // The showtimes: on today's date the ones already begun are greyed out.
  // If the one picked has just begun, the pick is dropped.
  function refreshShowtimes() {
    // No showtime can be picked before a day is: the box stays locked, and
    // says so, until the calendar has a date in it.
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
    seatsAndSnacks.hidden = !ready;
  }

  // Taken seats for the chosen showing, from api/seats.php. Only the newest
  // request counts, so a slow reply for an old choice cannot overwrite the
  // seats of the showing now on screen.

  var lookupNumber = 0;

  function finishLookup() {
    seatMap.classList.remove('seat-map-loading');
    seatMap.removeAttribute('aria-busy');
  }

  // background: a quiet recheck (see below), which leaves any message on
  // screen alone unless one of the customer's own seats was just taken
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
          // The showtime may have just started: the next full check says so
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

  // While the seats are on screen they are checked again every few seconds,
  // so the map stays true without reloading: a seat someone else starts
  // paying for turns grey, and one whose 10-minute hold ran out without
  // payment turns free again. Nothing is checked while the tab is hidden or
  // the page is on its way to checkout.
  var RECHECK_SEATS_MS = 10000;
  window.setInterval(function () {
    if (document.visibilityState === 'visible' && !sending && isReady() && !seatsAndSnacks.hidden) {
      loadTakenSeats(true);
    }
  }, RECHECK_SEATS_MS);

  function choiceChanged() {
    refreshShowtimes();
    showOrHideSeatsAndSnacks();
    clearError();
    loadTakenSeats();
  }

  // The running total

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

    // Snacks, counted by how many of each were added
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

    // Ticket count and grand total
    var ticketCount = seats.length;
    var ticketWord = ticketCount === 1 ? 'ticket' : 'tickets';
    summaryTickets.textContent = ticketCount + ' ' + ticketWord + ' ' + TIMES + ' ' + peso(ticketPrice);
    summaryAmount.textContent = peso(ticketCount * ticketPrice + snackTotal);
  }

  // Shows a snack's count on its box and writes it to the hidden form field
  function setSnackQuantity(snack, howMany) {
    howMany = Math.max(0, Math.min(maxPerSnack, howMany));
    snack.querySelector('.snack-input').value = String(howMany);
    snack.querySelector('.snack-quantity').textContent = String(howMany);
    snack.querySelector('.snack-less').disabled = howMany === 0;
    snack.querySelector('.snack-more').disabled = howMany === maxPerSnack;
    snack.querySelector('.snack-box').classList.toggle('snack-box-picked', howMany > 0);
  }

  // The date box opens and shuts the calendar
  dateButton.addEventListener('click', function () {
    if (calendar.hidden) {
      openCalendar();
    } else {
      closeCalendar();
    }
  });

  // Picking a day fills the box and shuts the calendar again
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

  // A click anywhere else, or the Escape key, puts the calendar away
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

  // The + and - buttons on the snacks
  seatsAndSnacks.addEventListener('click', function (event) {
    var step = event.target.closest('.snack-step');
    if (!step || step.disabled) {
      return;
    }
    var snack = step.closest('.snack');
    setSnackQuantity(snack, snackQuantity(snack) + Number(step.getAttribute('data-step')));
    updateSummary();
  });

  // The seats: one booking holds up to maxSeats of them
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

  // Nothing goes to checkout without a date, a showtime and a seat. Once it
  // does go, the button is locked so a double click cannot send it twice.
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

  // Coming back with the Back button from PayMongo shows this page from the
  // browser's memory: unlock the button and look the seats up again, since
  // they may have changed in the meantime.
  window.addEventListener('pageshow', function (event) {
    if (event.persisted) {
      unlockConfirm();
      refreshShowtimes();
      showOrHideSeatsAndSnacks();
      loadTakenSeats();
    }
  });

  // Seats can go while the page sits in a background tab
  document.addEventListener('visibilitychange', function () {
    if (document.visibilityState === 'visible') {
      loadTakenSeats();
    }
  });

  // A showing that begins while the page is open is greyed out, and the
  // seats are hidden again if it was the one picked (the showtime box then
  // reads "Choose a showtime", with the started one marked as started).
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

  // Starting state. A date and showtime handed back by checkout.php are
  // picked again; so are counts a browser kept from an earlier visit.
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
