// Staff Add Movie page. The run date is picked from a small calendar, which
// writes the date into the form's hidden run_date box. Before the form is
// sent, empty boxes are pointed out here to save a round trip; the server
// checks everything again anyway.
(function () {

  var monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December'];
  var dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

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
  var statusSelect = document.getElementById('new-status');

  var hoursInput = document.getElementById('new-hours');
  var minutesInput = document.getElementById('new-minutes');

  // The page states the server's poster limit, so both sides agree on it
  var posterInput = document.getElementById('new-poster');
  var posterLimit = parseInt(posterInput.getAttribute('data-max-bytes'), 10);
  if (isNaN(posterLimit) || posterLimit <= 0) {
    posterLimit = 5 * 1024 * 1024;
  }
  var posterTooBigText = 'The poster must be ' + Math.round(posterLimit / (1024 * 1024)) + ' MB or smaller.';

  var dateLabel = document.getElementById('run-date-label');
  var dateField = document.getElementById('run-date-field');
  var dateButton = document.getElementById('run-date-button');
  var dateText = document.getElementById('run-date-text');
  var dateHolder = document.getElementById('new-run-date');

  var calendar = document.getElementById('run-calendar');
  var calendarDates = document.getElementById('run-calendar-dates');
  var calendarMonth = document.getElementById('run-calendar-month');
  var calendarBack = document.getElementById('run-calendar-back');
  var calendarNext = document.getElementById('run-calendar-next');

  // Turns '2026-10-31' into a date the browser can compare
  function readDate(text) {
    var parts = text.split('-');
    return new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
  }

  // Turns a date back into '2026-10-31', the shape the server reads
  function dateKey(date) {
    var month = date.getMonth() + 1;
    var day = date.getDate();
    return date.getFullYear() +
      '-' + (month < 10 ? '0' : '') + month +
      '-' + (day < 10 ? '0' : '') + day;
  }

  // Counts a month as one number so two months are easy to compare
  function monthNumber(date) {
    return date.getFullYear() * 12 + date.getMonth();
  }

  // How a picked date reads on the box, like 'Sat, 31 Oct 2026'
  function readableDate(key) {
    var date = readDate(key);
    return dayNames[date.getDay()] + ', ' + date.getDate() + ' ' +
      monthNames[date.getMonth()].slice(0, 3) + ' ' + date.getFullYear();
  }

  // Today, with the clock part dropped so only the day itself is compared
  var today = new Date();
  today.setHours(0, 0, 0, 0);

  // After the server sends the form back with a problem, the date picked
  // before is already in the hidden box
  var chosenDate = /^\d{4}-\d{2}-\d{2}$/.test(dateHolder.value) ? dateHolder.value : '';
  var viewYear = today.getFullYear();
  var viewMonth = today.getMonth();

  // A run cannot end, and a movie cannot open, on a day already gone by
  function isAvailable(date) {
    return date >= today;
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

      if (key === chosenDate) {
        box.className += ' calendar-date-picked';
        box.setAttribute('aria-pressed', 'true');
      }

      calendarDates.appendChild(box);
    }

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
    var start = chosenDate === '' ? today : readDate(chosenDate);
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

  // A showing movie counts down to its last day; an upcoming one counts up
  // to its first, so the same date box is labelled for whichever it is
  function showDate() {
    var upcoming = statusSelect.value === 'upcoming';
    dateLabel.textContent = upcoming ? 'Opening day' : 'Last day showing';
    dateText.textContent = chosenDate === '' ? 'Choose a date' : readableDate(chosenDate);
  }

  // The two boxes read back as one length, like '1h 58m'
  function readLength() {
    var hours = parseInt(hoursInput.value, 10);
    var minutes = parseInt(minutesInput.value, 10);
    if (isNaN(hours) || isNaN(minutes)) {
      return '';
    }
    return hours + 'h ' + minutes + 'm';
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

  calendarDates.addEventListener('click', function (event) {
    var box = event.target.closest('.calendar-date');
    if (!box || box.disabled) {
      return;
    }
    chosenDate = box.getAttribute('data-date');
    dateHolder.value = chosenDate;
    showDate();
    closeCalendar();
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

  // Where the movie goes decides how its date reads
  statusSelect.addEventListener('change', showDate);

  hoursInput.addEventListener('input', function () {
    tidyNumber(hoursInput, 0, 9);
  });

  minutesInput.addEventListener('input', function () {
    tidyNumber(minutesInput, 0, 59);
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

    var noLength = readLength() === '';
    hoursInput.classList.toggle('invalid', noLength);
    minutesInput.classList.toggle('invalid', noLength);
    if (noLength) {
      missing.push('the length');
    }

    if (chosenDate === '') {
      missing.push(statusSelect.value === 'upcoming' ? 'the opening day' : 'the last day showing');
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
