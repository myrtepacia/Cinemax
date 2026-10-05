// Adds a show/hide eye button to every password box. Without scripts the box
// stays plain.
(function () {
  var SVG = 'http://www.w3.org/2000/svg';

  function shape(name, attributes) {
    var element = document.createElementNS(SVG, name);
    Object.keys(attributes).forEach(function (key) {
      element.setAttribute(key, attributes[key]);
    });
    return element;
  }

  // An eye, crossed out while the password is showing
  function eye(crossed) {
    var icon = shape('svg', {
      viewBox: '0 0 24 24', width: '20', height: '20', fill: 'none', stroke: 'currentColor',
      'stroke-width': '2', 'stroke-linecap': 'round', 'stroke-linejoin': 'round',
      'aria-hidden': 'true', focusable: 'false'
    });
    icon.appendChild(shape('path', { d: 'M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z' }));
    icon.appendChild(shape('circle', { cx: '12', cy: '12', r: '3' }));
    if (crossed) {
      icon.appendChild(shape('line', { x1: '3', y1: '3', x2: '21', y2: '21' }));
    }
    return icon;
  }

  Array.prototype.forEach.call(document.querySelectorAll('input[type="password"]'), function (input) {
    var box = document.createElement('span');
    box.className = 'password-box';
    input.parentNode.insertBefore(box, input);
    box.appendChild(input);

    var button = document.createElement('button');
    button.type = 'button';
    button.className = 'password-toggle';
    box.appendChild(button);

    function showPassword(visible) {
      input.type = visible ? 'text' : 'password';
      button.setAttribute('aria-label', visible ? 'Hide password' : 'Show password');
      button.setAttribute('aria-pressed', visible ? 'true' : 'false');
      button.textContent = '';
      button.appendChild(eye(visible));
    }

    showPassword(false);
    button.addEventListener('click', function () {
      showPassword(input.type === 'password');
      input.focus();
    });

    // Hide it again before sending, so the browser does not save it as plain
    // text
    if (input.form) {
      input.form.addEventListener('submit', function () {
        showPassword(false);
      });
    }
  });
})();
