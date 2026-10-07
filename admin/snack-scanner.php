<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';

$user = require_role('admin', 'scanner');

render_head('Snack Scanner');
render_header(['staff' => true, 'current' => 'snack-scanner']);
?>
  <div class="admin-layout">

    <?php render_staff_sidebar('snack-scanner'); ?>

    <div class="admin-head">
      <h1>Snack Scanner</h1>
      <p class="admin-intro">Scan the QR code on a customer&rsquo;s ticket to see their snack order, then confirm it to send it to Preparing.</p>
    </div>

    <main class="admin-page">

      <div class="panel camera-panel" id="scanner" data-mode="snack" data-api-url="<?= e(url('api/snack-scan.php')) ?>">

        <p class="panel-note">Open the camera to scan the QR code on the ticket</p>

        <div class="camera-video" id="camera-video"></div>

        <div class="scan-result scan-result-good hidden" id="scan-result" role="status" aria-live="polite">
          <span id="scan-result-title"></span>
          <span class="scan-result-who" id="scan-result-who"></span>
          <ul class="scan-items hidden" id="scan-items"></ul>
        </div>

        <div class="scan-actions hidden" id="scan-actions">
          <button class="button button-red" id="confirm-booking" type="button">Confirm order</button>
          <button class="button button-outline" id="cancel-booking" type="button">Cancel</button>
        </div>

        <p class="camera-message" id="camera-message" aria-live="polite"></p>
        <noscript><p class="camera-message">The scanner needs JavaScript. Turn it on in this browser, then reload the page.</p></noscript>

        <button class="button button-red" id="start-camera" type="button">Open camera</button>
        <button class="button button-outline hidden" id="close-camera" type="button">Close camera</button>

      </div>

    </main>

  </div>

<?php render_footer(['js' => ['assets/vendor/jsQR.js', 'assets/js/scanner.js']]);
