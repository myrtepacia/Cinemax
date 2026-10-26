// Sign up: prove the email with a 6-digit code. Verify swaps the email box
// for six code boxes; the right code shows "verified". api/signup-otp.php
// sends and checks the code, and signup.php refuses an unverified email.
(function () {
  'use strict';

  var step = document.getElementById('email-step');
  var form = document.getElementById('signup-form');
  if (!step || !form) {
    return;
  }

  var otpUrl = step.getAttribute('data-otp-url') || '';
  var tokenMeta = document.querySelector('meta[name="csrf-token"]');
  var csrfToken = tokenMeta ? (tokenMeta.getAttribute('content') || '') : '';

  var label = document.getElementById('email-label');
  var emailRow = document.getElementById('email-row');
  var emailInput = document.getElementById('email');
  var sendButton = document.getElementById('otp-send');
  var codeArea = document.getElementById('otp-area');
  var codeEmail = document.getElementById('otp-email');
  var boxes = Array.prototype.slice.call(document.querySelectorAll('#otp-boxes .otp-box'));
  var changeButton = document.getElementById('otp-change');
  var resendButton = document.getElementById('otp-resend');
  var verifiedLine = document.getElementById('otp-verified');
  var verifiedEmail = document.getElementById('otp-verified-email');
  var verifiedChange = document.getElementById('otp-verified-change');
  var errorBox = document.getElementById('otp-error');
  var serverError = document.getElementById('email-error');

  var sendLabel = sendButton.textContent;
  var state = 'email';
  var busy = false;
  var countdown = null;

  function showError(message) {
    errorBox.textContent = message;
    errorBox.hidden = message === '';
    if (serverError) {
      serverError.hidden = true;
    }
  }

  function currentEmail() {
    return emailInput.value.trim().toLowerCase();
  }

  // 'email': the email box; 'code': the six boxes; 'verified': a tick line
  function show(next) {
    state = next;
    emailRow.hidden = next !== 'email';
    codeArea.hidden = next !== 'code';
    verifiedLine.hidden = next !== 'verified';
    emailInput.readOnly = next !== 'email';
    label.setAttribute('for', next === 'code' ? '' : 'email');
    label.textContent = next === 'code' ? 'Verification code' : 'Email address';
    if (next !== 'code') {
      stopCountdown();
    }
  }

  function stopCountdown() {
    if (countdown) {
      window.clearInterval(countdown);
      countdown = null;
    }
    resendButton.textContent = 'Resend code';
  }

  function startCountdown(seconds) {
    stopCountdown();
    var left = Math.max(0, seconds || 0);
    resendButton.disabled = left > 0;
    if (left === 0) {
      return;
    }
    resendButton.textContent = 'Resend code (' + left + ')';
    countdown = window.setInterval(function () {
      left -= 1;
      if (left <= 0) {
        stopCountdown();
        resendButton.disabled = false;
        return;
      }
      resendButton.textContent = 'Resend code (' + left + ')';
    }, 1000);
  }

  function clearBoxes() {
    boxes.forEach(function (box) {
      box.value = '';
    });
  }

  function code() {
    return boxes.map(function (box) {
      return box.value;
    }).join('');
  }

  function ask(body) {
    return fetch(otpUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-Token': csrfToken
      },
      body: JSON.stringify(body)
    }).then(function (response) {
      return response.json().catch(function () {
        return { ok: false, error: 'Something went wrong. Please try again.' };
      });
    }, function () {
      return { ok: false, error: 'Could not reach the server. Check your connection and try again.' };
    });
  }

  function sendCode() {
    if (busy) {
      return;
    }
    if (!emailInput.checkValidity() || currentEmail() === '') {
      showError('Enter a valid email address, like juandelacruz@gmail.com.');
      emailInput.focus();
      return;
    }
    busy = true;
    sendButton.disabled = true;
    resendButton.disabled = true;
    sendButton.textContent = 'Sending…';
    showError('');

    ask({ action: 'send', email: currentEmail() }).then(function (reply) {
      busy = false;
      sendButton.disabled = false;
      sendButton.textContent = sendLabel;
      if (reply.ok) {
        codeEmail.textContent = reply.email;
        show('code');
        clearBoxes();
        startCountdown(reply.resendIn);
        boxes[0].focus();
        return;
      }
      showError(reply.error || 'We could not send the code. Please try again.');
      if (state === 'code') {
        startCountdown(reply.resendIn || 0);
      }
    });
  }

  function checkCode() {
    if (busy || code().length !== boxes.length) {
      return;
    }
    busy = true;
    boxes.forEach(function (box) {
      box.disabled = true;
    });
    showError('');

    ask({ action: 'verify', email: currentEmail(), code: code() }).then(function (reply) {
      busy = false;
      boxes.forEach(function (box) {
        box.disabled = false;
      });
      if (reply.ok) {
        verifiedEmail.textContent = reply.email;
        show('verified');
        return;
      }
      showError(reply.error || 'That code is not right.');
      clearBoxes();
      boxes[0].focus();
      if (reply.expired) {
        stopCountdown();
        resendButton.disabled = false;
      }
    });
  }

  // Fill the boxes from position `from` with the digits in `text`
  function fill(from, text) {
    var digits = text.replace(/\D/g, '').split('');
    var index = from;
    digits.forEach(function (digit) {
      if (index < boxes.length) {
        boxes[index].value = digit;
        index += 1;
      }
    });
    boxes[Math.min(index, boxes.length - 1)].focus();
    checkCode();
  }

  boxes.forEach(function (box, index) {
    box.addEventListener('input', function () {
      var typed = box.value.replace(/\D/g, '');
      box.value = '';
      if (typed !== '') {
        // A phone's code suggestion can arrive all at once in one box
        fill(index, typed);
      }
    });

    box.addEventListener('keydown', function (event) {
      if (event.key === 'Backspace' && box.value === '' && index > 0) {
        event.preventDefault();
        boxes[index - 1].value = '';
        boxes[index - 1].focus();
      } else if (event.key === 'ArrowLeft' && index > 0) {
        event.preventDefault();
        boxes[index - 1].focus();
      } else if (event.key === 'ArrowRight' && index < boxes.length - 1) {
        event.preventDefault();
        boxes[index + 1].focus();
      } else if (event.key === 'Enter') {
        event.preventDefault();
        checkCode();
      }
    });

    box.addEventListener('paste', function (event) {
      var text = (event.clipboardData || window.clipboardData).getData('text') || '';
      if (/\d/.test(text)) {
        event.preventDefault();
        fill(index, text);
      }
    });

    box.addEventListener('focus', function () {
      box.select();
    });
  });

  sendButton.addEventListener('click', sendCode);
  resendButton.addEventListener('click', sendCode);

  // Enter in the email box sends the code instead of the whole form
  emailInput.addEventListener('keydown', function (event) {
    if (event.key === 'Enter') {
      event.preventDefault();
      sendCode();
    }
  });

  function backToEmail() {
    show('email');
    showError('');
    emailInput.focus();
    emailInput.select();
  }
  changeButton.addEventListener('click', backToEmail);
  verifiedChange.addEventListener('click', backToEmail);

  // No account until the email is verified (signup.php checks again)
  form.addEventListener('submit', function (event) {
    if (state !== 'verified') {
      event.preventDefault();
      showError(state === 'code'
        ? 'Enter the 6-digit code we emailed you first.'
        : 'Verify your email first: press Verify and enter the 6-digit code.');
      (state === 'code' ? boxes[0] : sendButton).focus();
    }
  });

  // Back on the page with this email already verified (the form had another
  // problem): keep it verified
  var alreadyVerified = step.getAttribute('data-verified-email') || '';
  if (alreadyVerified !== '' && alreadyVerified === currentEmail()) {
    verifiedEmail.textContent = alreadyVerified;
    show('verified');
  } else {
    show('email');
  }
})();
