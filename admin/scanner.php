<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';

require_role('admin', 'scanner');

render_head('Scanner');
render_header(['staff' => true, 'current' => 'scanner']);
?>
  <div class="admin-layout">

    <?php render_staff_sidebar('scanner'); ?>

    <div class="admin-head">
      <h1>Scanner</h1>
    </div>

    <main class="admin-page">

      <div class="panel camera-panel" id="scanner" data-api-url="<?= e(url('api/scan.php')) ?>">

        <p class="panel-note">Open the camera to scan the QR code on the ticket</p>

        <!-- The answer sits above the camera: Valid Ticket, then the name -->
        <p class="scan-result scan-result-plain scan-result-good hidden" id="scan-result" role="status" aria-live="polite">
          <span id="scan-result-title"></span>
          <span class="scan-result-who" id="scan-result-who"></span>
        </p>

        <div class="camera-video" id="camera-video"></div>

        <!-- Confirm snack only shows when the ticket has snacks waiting -->
        <div class="scan-actions hidden" id="scan-actions">
          <button class="button button-red" id="confirm-booking" type="button">Confirm ticket</button>
          <button class="button button-red hidden" id="confirm-snack" type="button">Confirm snack</button>
        </div>

        <p class="camera-message" id="camera-message" aria-live="polite"></p>
        <noscript><p class="camera-message">The scanner needs JavaScript. Turn it on in this browser, then reload the page.</p></noscript>

        <button class="button button-red" id="start-camera" type="button">Open camera</button>
        <button class="button button-outline hidden" id="close-camera" type="button">Close camera</button>

      </div>

    </main>

  </div>

<?php render_footer(['js' => ['assets/vendor/jsQR.js', 'assets/js/scanner.js']]);
