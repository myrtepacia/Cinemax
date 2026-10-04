// Dates for the three calendars: the booking page, Add Movie, and Extend on
// the Movies page. A day travels as a 'YYYY-MM-DD' key, which also compares
// correctly as plain text. Loaded before the page's own script, which reads
// these as window.CinemaxDates.
window.CinemaxDates = (function () {
  var monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December'];
  var dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

  function pad(number) {
    return (number < 10 ? '0' : '') + number;
  }

  // '2026-10-31' becomes a date the browser can work with
  function readDate(key) {
    var parts = key.split('-');
    return new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
  }

  // And back again: a date becomes '2026-10-31', the shape the server reads
  function dateKey(date) {
    return date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate());
  }

  // A month as one number, so two months are easy to compare
  function monthNumber(date) {
    return date.getFullYear() * 12 + date.getMonth();
  }

  // How a picked day reads on a date box: 'Sat, Oct 31, 2026', the same as
  // the server writes it (format_day)
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
