(function () {
  document.querySelectorAll('img.skeleton').forEach(function (img) {
    var done = function () {
      img.classList.remove('skeleton');
    };
    if (img.complete && img.naturalWidth > 0) {
      done();
      return;
    }
    img.addEventListener('load', done);
    img.addEventListener('error', done);
  });
})();
