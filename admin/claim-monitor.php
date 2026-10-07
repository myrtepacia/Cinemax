<?php
declare(strict_types=1);

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
      <button class="button button-outline button-small claim-fullscreen" id="claim-fullscreen" type="button" hidden>Full screen</button>
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

    <p class="claim-problem" id="claim-problem" role="status" hidden></p>

  </div>

  <script src="<?= e(asset('assets/js/claim-monitor.js')) ?>"></script>
</body>
</html>
