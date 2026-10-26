
(function () {

  // Shared date helpers (dates.js)
  var monthNames = window.CSFlixDates.monthNames;
  var readDate = window.CSFlixDates.readDate;
  var dateKey = window.CSFlixDates.dateKey;
  var monthNumber = window.CSFlixDates.monthNumber;
  var readableDate = window.CSFlixDates.readableDate;

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

  // Poster limit, read from the page so it matches the server
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

  var calendar = document.getElementById('run-calendar');
  var calendarDates = document.getElementById('run-calendar-dates');
  var calendarMonth = document.getElementById('run-calendar-month');
  var calendarBack = document.getElementById('run-calendar-back');
  var calendarNext = document.getElementById('run-calendar-next');
  var calendarHint = document.getElementById('run-calendar-hint');

  // Show times: a drop-down of tick boxes
  var timeField = document.getElementById('show-time-field');
  var timeButton = document.getElementById('show-time-button');
  var timeText = document.getElementById('show-time-text');
  var timeMenu = document.getElementById('show-time-menu');
  var timeHint = document.getElementById('show-time-hint');
  var timeBoxes = timeMenu.querySelectorAll('input[type="checkbox"]');
  var LAST_END = 24 * 60;

  // Today, day only
  var today = new Date();
  today.setHours(0, 0, 0, 0);

  // A day picked before the server sent the form back is already in the
  // hidden box
  var openDate = /^\d{4}-\d{2}-\d{2}$/.test(opensHolder.value) ? opensHolder.value : '';
  var viewYear = today.getFullYear();
  var viewMonth = today.getMonth();

  // Cannot open on a past day
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

    // Blank boxes so the 1st lands on the right weekday
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

      if (key === openDate) {
        box.className += ' calendar-date-picked';
        box.setAttribute('aria-pressed', 'true');
      }

      calendarDates.appendChild(box);
    }

    calendarHint.textContent = 'Pick the opening day';

    // No going back to past months; forward has no limit
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

  // Reads like Fri, Oct 9, 2026, same as the server
  function showDate() {
    dateText.textContent = openDate === '' ? 'Choose the opening day' : readableDate(openDate);
    opensHolder.value = openDate;
  }

  // 10:30 becomes 10:30 AM, 14:00 becomes 2:00 PM
  function timeLabel(value) {
    var parts = value.split(':');
    var hour = parseInt(parts[0], 10);
    var suffix = hour >= 12 ? 'PM' : 'AM';
    return (hour % 12 === 0 ? 12 : hour % 12) + ':' + parts[1] + ' ' + suffix;
  }

  function chosenTimes() {
    return Array.prototype.filter.call(timeBoxes, function (box) {
      return box.checked;
    });
  }

  // The button says Select a time, or lists the ticked times
  function showTimes() {
    var chosen = chosenTimes();
    timeText.textContent = chosen.length === 0
      ? 'Select a time'
      : chosen.map(function (box) {
        return timeLabel(box.value);
      }).join(', ');
  }

  // A show must end by midnight, so late times are greyed out for a long movie
  function limitTimes() {
    var length = lengthGiven() ? parseInt(hoursInput.value, 10) * 60 + parseInt(minutesInput.value, 10) : 0;
    var blocked = false;

    Array.prototype.forEach.call(timeBoxes, function (box) {
      var parts = box.value.split(':');
      var start = parseInt(parts[0], 10) * 60 + parseInt(parts[1], 10);
      var tooLate = length > 0 && start + length > LAST_END;
      if (tooLate && box.checked) {
        box.checked = false;
        blocked = true;
      }
      box.disabled = tooLate;
      box.parentNode.classList.toggle('time-option-off', tooLate);
    });

    timeHint.textContent = length > 0
      ? 'Tick every time the movie will air each day. Times that end after midnight are greyed out.'
      : 'Tick every time the movie will air each day';
    if (blocked) {
      showTimes();
    }
  }

  function openTimes() {
    timeMenu.hidden = false;
    timeButton.setAttribute('aria-expanded', 'true');
  }

  function closeTimes() {
    timeMenu.hidden = true;
    timeButton.setAttribute('aria-expanded', 'false');
  }

  // Both length boxes hold a number
  function lengthGiven() {
    return !isNaN(parseInt(hoursInput.value, 10)) && !isNaN(parseInt(minutesInput.value, 10));
  }

  // Keep hours and minutes sensible
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
    // A new message replaces the old list
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

  // Stop a poster that is too big before sending; a huge one would also empty
  // every other box
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

  // Clicking a day picks it
  calendarDates.addEventListener('click', function (event) {
    var box = event.target.closest('.calendar-date');
    if (!box || box.disabled) {
      return;
    }
    openDate = box.getAttribute('data-date');
    dateButton.classList.remove('invalid');
    showDate();
    closeCalendar();
  });

  calendarBack.addEventListener('click', function () {
    moveMonth(-1);
  });

  calendarNext.addEventListener('click', function () {
    moveMonth(1);
  });

  // Click outside or press Escape to close the calendar
  document.addEventListener('click', function (event) {
    if (!dateField.contains(event.target)) {
      closeCalendar();
    }
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
      closeCalendar();
      closeTimes();
    }
  });

  hoursInput.addEventListener('input', function () {
    tidyNumber(hoursInput, 0, 9);
    limitTimes();
  });

  minutesInput.addEventListener('input', function () {
    tidyNumber(minutesInput, 0, 59);
    limitTimes();
  });

  timeButton.addEventListener('click', function () {
    if (timeMenu.hidden) {
      openTimes();
    } else {
      closeTimes();
    }
  });

  timeMenu.addEventListener('change', function () {
    timeButton.classList.remove('invalid');
    showTimes();
  });

  document.addEventListener('click', function (event) {
    if (!timeField.contains(event.target)) {
      closeTimes();
    }
  });

  // Picking a cinema clears its red outline
  cinemaSelect.addEventListener('change', function () {
    cinemaSelect.classList.remove('invalid');
  });

  // Warn at once if the poster is too big; clear it when a smaller one is
  // picked
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
    dateButton.classList.toggle('invalid', openDate === '');

    var noCinema = cinemaSelect.value === '';
    cinemaSelect.classList.toggle('invalid', noCinema);
    if (noCinema) {
      missing.push('the cinema');
    }

    var noTimes = chosenTimes().length === 0;
    timeButton.classList.toggle('invalid', noTimes);
    if (noTimes) {
      missing.push('a show time');
    }

    if (missing.length > 0) {
      event.preventDefault();
      showMessage('Still missing ' + missing.join(', ') + '.', 'error');
      return;
    }

    // Stop double presses, which would add the movie twice
    addButton.disabled = true;
    addButton.textContent = 'Adding…';
  });

  // The Back button can restore the page with the button still off
  window.addEventListener('pageshow', function () {
    addButton.disabled = false;
    addButton.textContent = 'Add movie';
  });

  showDate();
  drawCalendar();
  limitTimes();
  showTimes();
})();
