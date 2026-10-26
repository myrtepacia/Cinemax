// Movies page: a status dropdown (Active / Inactive) closes when another
// opens, on a click anywhere else, or on Esc.
(function () {
  var menus = Array.prototype.slice.call(document.querySelectorAll('details.status-menu'));
  if (menus.length === 0) {
    return;
  }

  function closeAll(except) {
    menus.forEach(function (menu) {
      if (menu !== except) {
        menu.open = false;
      }
    });
  }

  menus.forEach(function (menu) {
    menu.addEventListener('toggle', function () {
      if (menu.open) {
        closeAll(menu);
      }
    });
  });

  document.addEventListener('click', function (event) {
    var inside = event.target.closest ? event.target.closest('details.status-menu') : null;
    closeAll(inside);
  });

  document.addEventListener('keydown', function (event) {
    if (event.key !== 'Escape') {
      return;
    }
    var open = menus.filter(function (menu) {
      return menu.open;
    })[0];
    if (open) {
      open.open = false;
      open.querySelector('summary').focus();
    }
  });

  // Stop double presses while it switches
  menus.forEach(function (menu) {
    var form = menu.querySelector('form');
    if (form) {
      form.addEventListener('submit', function () {
        var button = form.querySelector('button[type="submit"]');
        if (button) {
          window.setTimeout(function () {
            button.disabled = true;
          }, 0);
        }
      });
    }
  });
})();
