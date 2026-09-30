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
})();
