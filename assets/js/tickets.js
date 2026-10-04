// Find a ticket, on the Ticket Scanner page (admins only see the refund
// part). A refund cannot be taken back, so the Refund button
// only opens a second row that asks once more. The money only moves when
// that row's form is posted; this script just shows and hides the rows.
//
// Without scripts the confirm form is simply left open, which still works.
(function () {
  var refundRow = document.getElementById('refund-row');
  var refundButton = document.getElementById('refund-button');
  var confirmForm = document.getElementById('refund-confirm');
  var keepButton = document.getElementById('refund-cancel');
  var yesButton = document.getElementById('refund-yes');

  // Only an unused, paid ticket has these
  if (!refundRow || !refundButton || !confirmForm || !keepButton || !yesButton) {
    return;
  }

  refundButton.classList.remove('hidden');
  keepButton.classList.remove('hidden');
  confirmForm.classList.add('hidden');

  refundButton.addEventListener('click', function () {
    refundRow.classList.add('hidden');
    confirmForm.classList.remove('hidden');
    keepButton.focus();
  });

  keepButton.addEventListener('click', function () {
    confirmForm.classList.add('hidden');
    refundRow.classList.remove('hidden');
    refundButton.focus();
  });

  // One press only: PayMongo can take a few seconds to answer, and a second
  // press would only be turned away by the server anyway
  confirmForm.addEventListener('submit', function () {
    yesButton.disabled = true;
    keepButton.disabled = true;
    yesButton.textContent = 'Refunding…';
  });
})();
