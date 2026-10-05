// The card form on card.php. The card details go from this browser straight
// to PayMongo (api.paymongo.com) with the site's public key, never to
// Cinemax's server: PayMongo first makes a payment method of the card, which
// is then attached to the booking's card payment (its Payment Intent, using
// the client key the server put on the page). PayMongo answers with:
//   succeeded              paid: back to card.php, which opens the ticket
//   processing             the bank is still deciding: card.php waits
//   awaiting_next_action   the bank wants to check it is the cardholder:
//                          off to the bank's page, which returns to card.php
//   an error               declined, or the details are wrong: said here,
//                          and another card can be tried
(function () {
  var box = document.getElementById('card-pay');
  var form = document.getElementById('card-form');
  if (!box || !form) {
    return;
  }

  var API = 'https://api.paymongo.com/v1/';
  var BANK_CHECK_DELAY_MS = 2000;
  var publicKey = box.getAttribute('data-public-key') || '';
  var intentId = box.getAttribute('data-intent') || '';
  var clientKey = box.getAttribute('data-client-key') || '';
  var returnUrl = box.getAttribute('data-return-url') || '';
  var email = box.getAttribute('data-email') || '';
  var phone = box.getAttribute('data-phone') || '';

  var number = document.getElementById('card-number');
  var expiry = document.getElementById('card-expiry');
  var cvc = document.getElementById('card-cvc');
  var holder = document.getElementById('card-name');
  var errorLine = document.getElementById('card-error');
  var submit = document.getElementById('card-submit');
  var statusLine = document.getElementById('pay-status');
  var payLabel = submit.textContent;
  var idleStatus = statusLine.textContent;
  var sending = false;

  function digits(value) {
    return value.replace(/\D/g, '');
  }

  // 1234 5678 9012 3456 while typing
  number.addEventListener('input', function () {
    number.value = digits(number.value).slice(0, 19).replace(/(\d{4})(?=\d)/g, '$1 ');
  });

  // MM / YY while typing, without fighting the backspace key
  expiry.addEventListener('input', function (event) {
    var typed = digits(expiry.value).slice(0, 4);
    var deleting = (event.inputType || '').indexOf('delete') === 0;
    if (typed.length > 2) {
      expiry.value = typed.slice(0, 2) + ' / ' + typed.slice(2);
    } else if (typed.length === 2 && !deleting) {
      expiry.value = typed + ' / ';
    } else {
      expiry.value = typed;
    }
  });

  cvc.addEventListener('input', function () {
    cvc.value = digits(cvc.value).slice(0, 4);
  });

  // The check digit every card number ends with (Luhn), which catches most
  // typing mistakes before PayMongo is asked
  function passesLuhn(cardNumber) {
    var sum = 0;
    var double = false;
    for (var i = cardNumber.length - 1; i >= 0; i--) {
      var digit = parseInt(cardNumber.charAt(i), 10);
      if (double) {
        digit *= 2;
        if (digit > 9) {
          digit -= 9;
        }
      }
      sum += digit;
      double = !double;
    }
    return sum % 10 === 0;
  }

  function showError(message, field) {
    errorLine.textContent = message;
    errorLine.hidden = false;
    if (field) {
      field.focus();
    }
  }

  function clearError() {
    errorLine.hidden = true;
    errorLine.textContent = '';
  }

  // The card as PayMongo wants it, or null (and the problem shown)
  function readCard() {
    var cardNumber = digits(number.value);
    if (cardNumber.length < 13 || !passesLuhn(cardNumber)) {
      showError('Please check the card number.', number);
      return null;
    }
    var expires = digits(expiry.value);
    var month = parseInt(expires.slice(0, 2), 10);
    var year = 2000 + parseInt(expires.slice(2), 10);
    if (expires.length !== 4 || month < 1 || month > 12) {
      showError('Please enter the expiry date as MM / YY.', expiry);
      return null;
    }
    var today = new Date();
    if (year < today.getFullYear() || (year === today.getFullYear() && month < today.getMonth() + 1)) {
      showError('This card has expired.', expiry);
      return null;
    }
    var code = digits(cvc.value);
    if (code.length < 3) {
      showError('Please enter the 3 or 4 digit CVC from the back of the card.', cvc);
      return null;
    }
    var name = holder.value.trim();
    if (name === '') {
      showError('Please enter the name on the card.', holder);
      return null;
    }
    var billing = { name: name.slice(0, 100) };
    if (email !== '') {
      billing.email = email;
    }
    if (phone !== '') {
      billing.phone = phone;
    }
    return {
      type: 'card',
      details: { card_number: cardNumber, exp_month: month, exp_year: year, cvc: code },
      billing: billing
    };
  }

  // A problem PayMongo explained, as an error the customer can read
  function payMongoError(detail, pointer) {
    var error = new Error(detail || 'Your card was not charged. Please try another card.');
    error.fromPayMongo = true;
    error.pointer = pointer || '';
    return error;
  }

  // One call to PayMongo's API with the public key. Resolves with the
  // reply's data; rejects with PayMongo's explanation.
  function askPayMongo(path, attributes) {
    return fetch(API + path, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'Authorization': 'Basic ' + window.btoa(publicKey + ':')
      },
      body: JSON.stringify({ data: { attributes: attributes } })
    }).then(function (response) {
      return response.json().catch(function () {
        return {};
      }).then(function (reply) {
        if (!response.ok) {
          var first = (reply.errors || [])[0] || {};
          throw payMongoError(first.detail, (first.source || {}).pointer || first.code);
        }
        return reply.data || {};
      });
    });
  }

  function done() {
    sending = false;
    submit.disabled = false;
    submit.textContent = payLabel;
    statusLine.textContent = idleStatus;
  }

  form.addEventListener('submit', function (event) {
    event.preventDefault();
    if (sending) {
      return;
    }
    clearError();
    if (box.classList.contains('pay-ended')) {
      showError(box.getAttribute('data-ended-text') || 'Time is up.');
      return;
    }
    var card = readCard();
    if (card === null) {
      return;
    }

    sending = true;
    submit.disabled = true;
    submit.textContent = 'Paying…';
    statusLine.textContent = 'Sending your card to PayMongo…';

    askPayMongo('payment_methods', card)
      .then(function (method) {
        // The bank's check returns to card.php marked bank=1, so a check
        // that failed can be told from a first visit
        return askPayMongo('payment_intents/' + intentId + '/attach', {
          payment_method: method.id,
          client_key: clientKey,
          return_url: returnUrl + '&bank=1'
        });
      })
      .then(function (intent) {
        var attributes = intent.attributes || {};
        if (attributes.status === 'succeeded' || attributes.status === 'processing') {
          // card.php asks PayMongo itself before the ticket is shown
          statusLine.textContent = 'Payment received! Opening your ticket…';
          window.location.href = returnUrl;
          return;
        }
        var redirect = ((attributes.next_action || {}).redirect || {}).url || '';
        if (attributes.status === 'awaiting_next_action' && /^https:\/\//.test(redirect)) {
          statusLine.textContent = 'Opening your bank’s check…';
          // Opened the very moment it is made, PayMongo's check page can
          // say it does not exist yet
          window.setTimeout(function () {
            window.location.href = redirect;
          }, BANK_CHECK_DELAY_MS);
          return;
        }
        var failure = attributes.last_payment_error || {};
        throw payMongoError(failure.failed_message || failure.detail);
      })
      .catch(function (error) {
        done();
        if (!error.fromPayMongo) {
          showError('Could not reach PayMongo. Please check your connection and try again.');
        } else if (/card_number/.test(error.pointer)) {
          showError('Please check the card number.', number);
        } else if (/exp_(month|year)/.test(error.pointer)) {
          showError('Please check the expiry date.', expiry);
        } else if (/cvc/.test(error.pointer)) {
          showError('Please check the CVC.', cvc);
        } else {
          showError(error.message);
        }
      });
  });
})();
