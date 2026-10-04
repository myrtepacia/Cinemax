// Staff Add Movie page. The movie's run is picked from a small calendar: the
// first day clicked is the opening day, the second the last day, and they
// are written into the form's hidden opens_on and ends_on boxes. Before the
// form is sent, empty boxes are pointed out here to save a round trip; the
// server checks everything again anyway.
(function () {

  // The shared date helpers (dates.js)
  var monthNames = window.CinemaxDates.monthNames;
  var readDate = window.CinemaxDates.readDate;
  var dateKey = window.CinemaxDates.dateKey;
  var monthNumber = window.CinemaxDates.monthNumber;
  var readableDate = window.CinemaxDates.readableDate;

  var addForm = document.getElementById('add-form');
  if (!addForm) {
    return;
  }

  var addMessage = document.getElementById('add-message');
  var serverErrors = document.getElementById('add-errors');
  var addButton = document.getElementById('add-button');

  var titleInput = document.getElementById('new-title');
  var genreInput = document.getElementById('new-genre');
  var priceInput = document.getElementById('new-price');
  var cinemaSelect = document.getElementById('new-cinema');

  var hoursInput = document.getElementById('new-hours');
  var minutesInput = document.getElementById('new-minutes');

  // The page states the server's poster limit, so both sides agree on it
  var posterInput = document.getElementById('new-poster');
  var posterLimit = parseInt(posterInput.getAttribute('data-max-bytes'), 10);
  if (isNaN(posterLimit) || posterLimit <= 0) {
    posterLimit = 5 * 1024 * 1024;
  }
  var posterTooBigText = 'The poster must be ' + Math.round(posterLimit / (1024 * 1024)) + ' MB or smaller.';

  var dateField = document.getElementById('run-date-field');
  var dateButton = document.getElementById('run-date-button');
  var dateText = document.getElementById('run-date-text');
  var opensHolder = document.getElementById('new-opens-on');
  var endsHolder = document.getElementById('new-ends-on');

  var calendar = document.getElementById('run-calendar');
  var calendarDates = document.getElementById('run-calendar-dates');
  var calendarMonth = document.getElementById('run-calendar-month');
  var calendarBack = document.getElementById('run-calendar-back');
  var calendarNext = document.getElementById('run-calendar-next');
  var calendarHint = document.getElementById('run-calendar-hint');

  // Today, with the clock part dropped so only the day itself is compared
  var today = new Date();
  today.setHours(0, 0, 0, 0);

  // After the server sends the form back with a problem, the days picked
  // before are already in the hidden boxes. 'YYYY-MM-DD' keys compare
  // correctly as plain text.
  function readHolder(holder) {
    return /^\d{4}-\d{2}-\d{2}$/.test(holder.value) ? holder.value : '';
  }
  var openDate = readHolder(opensHolder);
  var lastDate = openDate === '' ? '' : readHolder(endsHolder);
  var viewYear = today.getFullYear();
  var viewMonth = today.getMonth();

  // A run cannot start or end on a day already gone by
  function isAvailable(date) {
    return date >= today;
  }

  // The next click sets the opening day, unless one is waiting for its last day
  function pickingLastDay() {
    return openDate !== '' && lastDate === '';
  }

  function clearDates() {
    while (calendarDates.firstChild) {
      calendarDates.removeChild(calendarDates.firstChild);
    }
  }

  function drawCalendar() {
    calendarMonth.textContent = monthNames[viewMonth] + ' ' + viewYear;
    clearDates();

    // Empty boxes so the 1st lands under the right weekday
    var startsOn = new Date(viewYear, viewMonth, 1).getDay();
    var daysInMonth = new Date(viewYear, viewMonth + 1, 0).getDate();
    var count;

    for (count = 0; count < startsOn; count++) {
      calendarDates.appendChild(document.createElement('span'));
    }

    for (count = 1; count <= daysInMonth; count++) {
      var date = new Date(viewYear, viewMonth, count);
      var key = dateKey(date);
      var box = document.createElement('button');

      box.type = 'button';
      box.className = 'calendar-date';
      box.textContent = count;
      box.setAttribute('data-date', key);

      if (!isAvailable(date)) {
        box.disabled = true;
        box.title = 'This date has already passed';
      }

      if (key === dateKey(today)) {
        box.className += ' calendar-date-today';
      }

      if (key === openDate || key === lastDate) {
        box.className += ' calendar-date-picked';
        box.setAttribute('aria-pressed', 'true');
      } else if (lastDate !== '' && key > openDate && key < lastDate) {
        box.className += ' calendar-date-in-range';
      }

      calendarDates.appendChild(box);
    }

    calendarHint.textContent = pickingLastDay() ? 'Now pick the last day showing' : 'Pick the opening day';

    // Months wholly past are of no use. Going forward has no end, so a
    // run can be set as far ahead as the cinema has booked the movie.
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
    var start = openDate === '' ? today : readDate(openDate);
    if (start < today) {
      start = today;
    }
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

  // The date box reads like 'Fri, Oct 9, 2026 – Sat, Oct 31, 2026', the same
  // words the server writes into it
  function showDate() {
    if (openDate === '') {
      dateText.textContent = 'Choose the opening and last day';
    } else {
      dateText.textContent = readableDate(openDate) + ' – ' +
        (lastDate === '' ? 'pick the last day' : readableDate(lastDate));
    }
    opensHolder.value = openDate;
    endsHolder.value = lastDate;
  }

  // Whether both length boxes hold a number
  function lengthGiven() {
    return !isNaN(parseInt(hoursInput.value, 10)) && !isNaN(parseInt(minutesInput.value, 10));
  }

  // Keeps the minutes a real number of minutes, and the hours sensible
  function tidyNumber(input, lowest, highest) {
    var value = parseInt(input.value, 10);
    if (isNaN(value)) {
      return;
    }
    if (value < lowest) {
      input.value = lowest;
    }
    if (value > highest) {
      input.value = highest;
    }
  }

  function showMessage(text, kind) {
    // A newer message replaces the server's list from the last try
    if (serverErrors) {
      serverErrors.hidden = true;
    }
    addMessage.textContent = text;
    addMessage.className = 'form-message form-message-' + kind;
  }

  function clearMessage() {
    addMessage.textContent = '';
    addMessage.className = 'form-message hidden';
  }

  // A poster over the limit would be sent in full only to be refused, and
  // one over the server's whole-form limit loses every other box as well,
  // so it is stopped here before anything is sent
  function posterTooBig() {
    var file = posterInput.files && posterInput.files[0];
    return Boolean(file) && file.size > posterLimit;
  }

  dateButton.addEventListener('click', function () {
    if (calendar.hidden) {
      openCalendar();
    } else {
      closeCalendar();
    }
  });

  // First click: the opening day. Second click: the last day, which may be
  // the same day. A day before the opening day starts over from there, and a
  // click once both are set starts a new run.
  calendarDates.addEventListener('click', function (event) {
    var box = event.target.closest('.calendar-date');
    if (!box || box.disabled) {
      return;
    }
    // Redrawing replaces the day clicked, which would then look like a click
    // outside the calendar and shut it before the last day is picked
    event.stopPropagation();
    var key = box.getAttribute('data-date');
    if (pickingLastDay() && key >= openDate) {
      lastDate = key;
    } else {
      openDate = key;
      lastDate = '';
    }
    dateButton.classList.remove('invalid');
    showDate();
    if (lastDate !== '') {
      closeCalendar();
    } else {
      drawCalendar();
    }
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
    if (event.key === 'Escape') {
      closeCalendar();
    }
  });

  hoursInput.addEventListener('input', function () {
    tidyNumber(hoursInput, 0, 9);
  });

  minutesInput.addEventListener('input', function () {
    tidyNumber(minutesInput, 0, 59);
  });

  // Picking a cinema takes away its red outline
  cinemaSelect.addEventListener('change', function () {
    cinemaSelect.classList.remove('invalid');
  });

  // Says straight away when the chosen poster is too big, and takes the
  // warning down again once a smaller one is chosen
  posterInput.addEventListener('change', function () {
    var tooBig = posterTooBig();
    posterInput.classList.toggle('invalid', tooBig);
    if (tooBig) {
      showMessage(posterTooBigText, 'error');
    } else if (addMessage.textContent === posterTooBigText) {
      clearMessage();
    }
  });

  addForm.addEventListener('submit', function (event) {
    var missing = [];

    if (posterTooBig()) {
      event.preventDefault();
      posterInput.classList.add('invalid');
      showMessage(posterTooBigText, 'error');
      return;
    }

    [[titleInput, 'the title'], [genreInput, 'the genre'], [priceInput, 'the price']]
      .forEach(function (field) {
        var empty = field[0].value.trim() === '';
        field[0].classList.toggle('invalid', empty);
        if (empty) {
          missing.push(field[1]);
        }
      });

    var noLength = !lengthGiven();
    hoursInput.classList.toggle('invalid', noLength);
    minutesInput.classList.toggle('invalid', noLength);
    if (noLength) {
      missing.push('the length');
    }

    if (openDate === '') {
      missing.push('the opening day');
    }
    if (lastDate === '') {
      missing.push('the last day showing');
    }
    dateButton.classList.toggle('invalid', openDate === '' || lastDate === '');

    var noCinema = cinemaSelect.value === '';
    cinemaSelect.classList.toggle('invalid', noCinema);
    if (noCinema) {
      missing.push('the cinema');
    }

    if (missing.length > 0) {
      event.preventDefault();
      showMessage('Still missing ' + missing.join(', ') + '.', 'error');
      return;
    }

    // One press is enough; a second would add the movie twice
    addButton.disabled = true;
    addButton.textContent = 'Adding…';
  });

  // Coming back with the browser's Back button can restore the page as it
  // was, with the button still switched off
  window.addEventListener('pageshow', function () {
    addButton.disabled = false;
    addButton.textContent = 'Add movie';
  });

  showDate();
  drawCalendar();
})();
