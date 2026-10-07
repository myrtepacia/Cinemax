(function () {

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

  var today = new Date();
  today.setHours(0, 0, 0, 0);

  var openDate = /^\d{4}-\d{2}-\d{2}$/.test(opensHolder.value) ? opensHolder.value : '';
  var viewYear = today.getFullYear();
  var viewMonth = today.getMonth();

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

  function showDate() {
    dateText.textContent = openDate === '' ? 'Choose the opening day' : readableDate(openDate);
    opensHolder.value = openDate;
  }

  function lengthGiven() {
    return !isNaN(parseInt(hoursInput.value, 10)) && !isNaN(parseInt(minutesInput.value, 10));
  }

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

  cinemaSelect.addEventListener('change', function () {
    cinemaSelect.classList.remove('invalid');
  });

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

    if (missing.length > 0) {
      event.preventDefault();
      showMessage('Still missing ' + missing.join(', ') + '.', 'error');
      return;
    }

    addButton.disabled = true;
    addButton.textContent = 'Adding…';
  });

  window.addEventListener('pageshow', function () {
    addButton.disabled = false;
    addButton.textContent = 'Add movie';
  });

  showDate();
  drawCalendar();
})();
