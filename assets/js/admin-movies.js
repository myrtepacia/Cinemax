// Staff Movies page. Remove takes a film off the website at once, so it asks
// first. The server does the removing; this only gives a chance to back out.
(function () {
  document.querySelectorAll('form.remove-form').forEach(function (form) {
    form.addEventListener('submit', function (event) {
      var title = form.getAttribute('data-title') || 'this movie';
      var sure = window.confirm('Take "' + title + '" off the listings? Tickets already sold stay valid.');

      if (!sure) {
        event.preventDefault();
        return;
      }

      // One press is enough: a second would only find the film already gone
      var button = form.querySelector('.remove-button');
      if (button) {
        button.disabled = true;
      }
    });
  });

  // Coming back with the browser's Back button can restore the page as it
  // was, with the button still switched off
  window.addEventListener('pageshow', function () {
    document.querySelectorAll('form.remove-form .remove-button').forEach(function (button) {
      button.disabled = false;
    });
  });

  // Extend: each film's Extend button opens a panel under its row, where a
  // small calendar picks the new last day. Only days from data-min to
  // data-max (when set) can be picked: the server worked those out so the
  // film's shows stay clear of other films in the same cinema, and checks
  // the day again when it is saved.
  var monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December'];
  var dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

  // '2026-10-31' <-> a date. 'YYYY-MM-DD' keys also compare as plain text.
  function readDate(key) {
    var parts = key.split('-');
    return new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
  }

  function dateKey(date) {
    var month = date.getMonth() + 1;
    var day = date.getDate();
    return date.getFullYear() + '-' + (month < 10 ? '0' : '') + month + '-' + (day < 10 ? '0' : '') + day;
  }

  // 'Sat, 31 Oct 2026', the way the page writes dates
  function readableDate(key) {
    var date = readDate(key);
    return dayNames[date.getDay()] + ', ' + date.getDate() + ' ' +
      monthNames[date.getMonth()].slice(0, 3) + ' ' + date.getFullYear();
  }

  // The calendar's frame; the days are filled in by draw()
  function calendarFrame() {
    var calendar = document.createElement('div');
    calendar.className = 'calendar';
    calendar.hidden = true;
    calendar.innerHTML =
      '<div class="calendar-head">' +
        '<button class="calendar-nav" type="button" data-step="-1" aria-label="Previous month">&#8249;</button>' +
        '<p class="calendar-month"></p>' +
        '<button class="calendar-nav" type="button" data-step="1" aria-label="Next month">&#8250;</button>' +
      '</div>' +
      '<div class="calendar-grid calendar-day-names">' +
        dayNames.map(function (name) { return '<span>' + name + '</span>'; }).join('') +
      '</div>' +
      '<div class="calendar-grid calendar-days"></div>';
    return calendar;
  }

  function setUpExtend(row) {
    var toggle = document.querySelector('.extend-toggle[aria-controls="' + row.id + '"]');
    var form = row.querySelector('.extend-form');
    var field = row.querySelector('.extend-date-field');
    if (!toggle || !form) {
      return;
    }

    function openPanel(open) {
      row.hidden = !open;
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      toggle.textContent = open ? 'Close' : 'Extend';
    }

    toggle.addEventListener('click', function () {
      openPanel(row.hidden);
    });

    // Nothing to pick: the panel only says why the run cannot go further
    if (!field) {
      return;
    }

    var dateButton = field.querySelector('.extend-date-button');
    var dateText = field.querySelector('.extend-date-text');
    var holder = form.querySelector('.extend-value');
    var save = form.querySelector('.extend-save');
    var minKey = field.getAttribute('data-min');
    var maxKey = field.getAttribute('data-max') || '';
    var chosen = '';

    var calendar = calendarFrame();
    field.appendChild(calendar);
    var monthLabel = calendar.querySelector('.calendar-month');
    var days = calendar.querySelector('.calendar-days');
    var back = calendar.querySelector('[data-step="-1"]');
    var next = calendar.querySelector('[data-step="1"]');
    var first = readDate(minKey);
    var viewYear = first.getFullYear();
    var viewMonth = first.getMonth();

    function monthNumber(date) {
      return date.getFullYear() * 12 + date.getMonth();
    }

    function draw() {
      monthLabel.textContent = monthNames[viewMonth] + ' ' + viewYear;
      while (days.firstChild) {
        days.removeChild(days.firstChild);
      }
      var startsOn = new Date(viewYear, viewMonth, 1).getDay();
      var daysInMonth = new Date(viewYear, viewMonth + 1, 0).getDate();
      var count;
      for (count = 0; count < startsOn; count++) {
        days.appendChild(document.createElement('span'));
      }
      for (count = 1; count <= daysInMonth; count++) {
        var key = dateKey(new Date(viewYear, viewMonth, count));
        var box = document.createElement('button');
        box.type = 'button';
        box.className = 'calendar-date';
        box.textContent = String(count);
        box.setAttribute('data-date', key);
        if (key < minKey) {
          box.disabled = true;
          box.title = 'Already part of the run, or gone by';
        } else if (maxKey !== '' && key > maxKey) {
          box.disabled = true;
          box.title = 'Another movie uses this cinema at these times';
        }
        if (key === chosen) {
          box.className += ' calendar-date-picked';
          box.setAttribute('aria-pressed', 'true');
        }
        days.appendChild(box);
      }
      var shown = viewYear * 12 + viewMonth;
      back.disabled = shown <= monthNumber(first);
      next.disabled = maxKey !== '' && shown >= monthNumber(readDate(maxKey));
    }

    function openCalendar() {
      var start = readDate(chosen !== '' ? chosen : minKey);
      viewYear = start.getFullYear();
      viewMonth = start.getMonth();
      draw();
      calendar.hidden = false;
      dateButton.setAttribute('aria-expanded', 'true');
    }

    function closeCalendar() {
      calendar.hidden = true;
      dateButton.setAttribute('aria-expanded', 'false');
    }

    dateButton.addEventListener('click', function () {
      if (calendar.hidden) {
        openCalendar();
      } else {
        closeCalendar();
      }
    });

    calendar.addEventListener('click', function (event) {
      var step = event.target.closest('.calendar-nav');
      if (step && !step.disabled) {
        var moved = viewYear * 12 + viewMonth + Number(step.getAttribute('data-step'));
        viewYear = Math.floor(moved / 12);
        viewMonth = moved % 12;
        draw();
        return;
      }
      var box = event.target.closest('.calendar-date');
      if (!box || box.disabled) {
        return;
      }
      chosen = box.getAttribute('data-date');
      holder.value = chosen;
      dateText.textContent = readableDate(chosen);
      save.disabled = false;
      closeCalendar();
    });

    // A click anywhere else, or Escape, puts the calendar away
    document.addEventListener('click', function (event) {
      if (!field.contains(event.target)) {
        closeCalendar();
      }
    });
    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') {
        closeCalendar();
      }
    });

    // Cancel forgets the day picked and folds the panel away
    form.querySelector('.extend-cancel').addEventListener('click', function () {
      chosen = '';
      holder.value = '';
      dateText.textContent = 'Choose the new last day';
      save.disabled = true;
      closeCalendar();
      openPanel(false);
    });

    form.addEventListener('submit', function (event) {
      if (chosen === '') {
        event.preventDefault();
        return;
      }
      // One press is enough
      save.disabled = true;
      save.textContent = 'Saving…';
    });

    window.addEventListener('pageshow', function () {
      save.disabled = chosen === '';
      save.textContent = 'Save';
    });
  }

  document.querySelectorAll('.extend-row').forEach(setUpExtend);
})();
