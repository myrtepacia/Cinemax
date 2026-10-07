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
    var q = qrcode(0, 'M');
    q.addData(text);
    q.make();
    img.src = q.createDataURL(6, 2);
  } catch (err) {
    if (window.console) {
      console.error('Could not draw the QR code', err);
    }
  }
})();
