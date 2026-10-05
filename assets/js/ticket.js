// E-ticket page: draws the QR code from the image's data-qr using
// assets/vendor/qrcode.js, in the browser, so the secret never leaves the
// page.
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
    // Type 0 = smallest size that fits; M handles a scratched or dim screen.
    var q = qrcode(0, 'M');
    q.addData(text);
    q.make();
    img.src = q.createDataURL(6, 2);
  } catch (err) {
    // Keep the reference under the box so staff can type it in.
    if (window.console) {
      console.error('Could not draw the QR code', err);
    }
  }
})();
