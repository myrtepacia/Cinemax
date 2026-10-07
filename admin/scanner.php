<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';

require_role('admin', 'scanner');

$chooseFirst = !empty($_SESSION['choose_scanner']);
unset($_SESSION['choose_scanner']);

render_head('Ticket Scanner');
render_header(['staff' => true, 'current' => 'scanner']);
?>
<?php if ($chooseFirst): ?>
  <div class="scanner-choice">
    <h1>Choose a scanner</h1>
    <p class="admin-intro">Which one are you working on?</p>

    <a class="choice-card" href="<?= e(url('admin/scanner.php')) ?>">
      <span>
        <span class="choice-name">Ticket Scanner</span>
        <span class="choice-note">Check tickets at the door</span>
      </span>
      <span class="choice-arrow" aria-hidden="true"></span>
    </a>

    <a class="choice-card" href="<?= e(url('admin/snack-scanner.php')) ?>">
      <span>
        <span class="choice-name">Snack Scanner</span>
        <span class="choice-note">Confirm snack orders at the counter</span>
      </span>
      <span class="choice-arrow" aria-hidden="true"></span>
    </a>
  </div>
<?php endif; ?>

  <div class="admin-layout<?= $chooseFirst ? ' scanner-waiting' : '' ?>">

    <?php render_staff_sidebar('scanner'); ?>

    <div class="admin-head">
      <h1>Ticket Scanner</h1>
    </div>

    <main class="admin-page">

      <div class="panel camera-panel" id="scanner" data-api-url="<?= e(url('api/scan.php')) ?>">

        <p class="panel-note">Open the camera to scan the QR code on the ticket</p>

        <div class="camera-video" id="camera-video"></div>

        <p class="scan-result scan-result-good hidden" id="scan-result" role="status" aria-live="polite">
          <span id="scan-result-title"></span>
          <span class="scan-result-who" id="scan-result-who"></span>
        </p>

        <div class="scan-actions hidden" id="scan-actions">
          <button class="button button-red" id="confirm-booking" type="button">Confirm booking</button>
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
