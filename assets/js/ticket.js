// The e-ticket page. Draws the ticket's QR code from the text the server put
// in the image's data-qr attribute, using the qrcode-generator library
// (assets/vendor/qrcode.js, loaded just before this file). The code is drawn
// in the browser, so the ticket's secret never goes to an outside service.
(function () {
  var img = document.getElementById('qr');
  if (!img || typeof qrcode !== 'function') {
    return;
  }
  var text = img.getAttribute('data-qr');
  if (!text) {
    return;
  }
  try {
    // Type 0 picks the smallest size that fits; 'M' survives a scuffed
    // or dim phone screen at the door.
    var q = qrcode(0, 'M');
    q.addData(text);
    q.make();
    img.src = q.createDataURL(6, 2);
  } catch (err) {
    // Leave the reference under the empty box; staff can type it in instead.
    if (window.console) {
      console.error('Could not draw the QR code', err);
    }
  }
})();
