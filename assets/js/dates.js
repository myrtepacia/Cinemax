// Date helpers for the booking and Add Movie calendars (window.CinemaxDates).
// Days are YYYY-MM-DD strings, which also sort as text.
window.CinemaxDates = (function () {
  var monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December'];
  var dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

  function pad(number) {
    return (number < 10 ? '0' : '') + number;
  }

  // YYYY-MM-DD to a Date
  function readDate(key) {
    var parts = key.split('-');
    return new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
  }

  // A Date to YYYY-MM-DD
  function dateKey(date) {
    return date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate());
  }

  // A month as one number, easy to compare
  function monthNumber(date) {
    return date.getFullYear() * 12 + date.getMonth();
  }

  // Sat, Oct 31, 2026, same as the server (format_day)
  function readableDate(key) {
    var date = readDate(key);
    return dayNames[date.getDay()] + ', ' + monthNames[date.getMonth()].slice(0, 3) + ' ' +
      date.getDate() + ', ' + date.getFullYear();
  }

  return {
    monthNames: monthNames,
    dayNames: dayNames,
    pad: pad,
    readDate: readDate,
    dateKey: dateKey,
    monthNumber: monthNumber,
    readableDate: readableDate
  };
})();
