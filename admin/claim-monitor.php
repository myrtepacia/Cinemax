<?php
declare(strict_types=1);

// Snacks Claim monitor: a screen for the snack counter that customers can
// see, like a fast-food claim board. Two columns of reference numbers:
//   Preparing  confirmed at the Snack Scanner
//   Ready      Ready pressed on the Dashboard
// Picked Up on the Dashboard takes a number off. The lists update by
// themselves (claim-monitor.js asks api/claim-monitor.php every few seconds).
//
// Admin only, and on purpose not in the staff menu: it is opened by typing
// its address, admin/claim-monitor.php, on the screen's browser.

require __DIR__ . '/../includes/bootstrap.php';

$user = require_role('admin');

$lists = claim_monitor_orders();
$columns = [
    'preparing' => 'Preparing',
    'ready'     => 'Ready',
];

render_head('Snacks Claim', ['assets/css/claim-monitor.css']);
?>
  <div class="claim-screen" id="claim-screen" data-live-url="<?= e(url('api/claim-monitor.php')) ?>">

    <header class="claim-head">
      <p class="claim-brand"><img src="<?= e(asset('assets/img/logo.png')) ?>" alt="" width="49" height="44">CINEMAX</p>
      <h1>Snacks Claim</h1>
      <button class="claim-fullscreen" id="claim-fullscreen" type="button" hidden>Full screen</button>
    </header>

    <main class="claim-board">
<?php foreach ($columns as $stage => $title): ?>
      <section class="claim-column claim-<?= e($stage) ?>">
        <h2><?= e($title) ?></h2>
        <ol class="claim-list" id="claim-<?= e($stage) ?>" aria-live="polite">
<?php foreach ($lists[$stage] as $reference): ?>
          <li><?= e($reference) ?></li>
<?php endforeach; ?>
        </ol>
      </section>
<?php endforeach; ?>
    </main>

    <!-- Shown by claim-monitor.js when the lists cannot be updated -->
    <p class="claim-problem" id="claim-problem" role="status" hidden></p>

  </div>

  <script src="<?= e(asset('assets/js/claim-monitor.js')) ?>"></script>
</body>
</html>
