// Staff pages (Movies, Snacks): a form.confirm-form asks its data-confirm
// question before it is sent.
(function () {
  document.querySelectorAll('form.confirm-form').forEach(function (form) {
    form.addEventListener('submit', function (event) {
      if (!window.confirm(form.getAttribute('data-confirm') || 'Are you sure?')) {
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
    document.querySelectorAll('form.confirm-form button[type="submit"]').forEach(function (button) {
      button.disabled = false;
    });
  });
})();
