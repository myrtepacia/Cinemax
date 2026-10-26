// Staff pages: the hamburger at the top of the sidebar widens it over the
// page to show the names next to the icons. The page itself never moves.
// A click anywhere else, or Esc, closes it again.
(function () {
  var sidebar = document.getElementById('staff-sidebar');
  var toggle = document.getElementById('sidebar-toggle');
  if (!sidebar || !toggle) {
    return;
  }

  function setOpen(open) {
    sidebar.classList.toggle('is-open', open);
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    toggle.setAttribute('aria-label', open ? 'Hide menu names' : 'Show menu names');
  }

  function isOpen() {
    return sidebar.classList.contains('is-open');
  }

  // Slide only when pressed, not when the page opens
  window.requestAnimationFrame(function () {
    sidebar.classList.add('can-slide');
  });

  toggle.addEventListener('click', function () {
    setOpen(!isOpen());
  });

  document.addEventListener('click', function (event) {
    if (isOpen() && !sidebar.contains(event.target)) {
      setOpen(false);
    }
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && isOpen()) {
      setOpen(false);
      toggle.focus();
    }
  });
})();
