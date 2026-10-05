// Movies page: Remove asks before taking a film off the site.
(function () {
  document.querySelectorAll('form.remove-form').forEach(function (form) {
    form.addEventListener('submit', function (event) {
      var title = form.getAttribute('data-title') || 'this movie';
      var sure = window.confirm('Take "' + title + '" off the listings? Tickets already sold stay valid.');

      if (!sure) {
        event.preventDefault();
        return;
      }

      // Stop double presses
      var button = form.querySelector('button[type="submit"]');
      if (button) {
        button.disabled = true;
      }
    });
  });

  // The Back button can restore the page with the button still off
  window.addEventListener('pageshow', function () {
    document.querySelectorAll('form.remove-form button[type="submit"]').forEach(function (button) {
      button.disabled = false;
    });
  });
})();
