<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';

$user = require_role('admin');

if (is_post()) {
    verify_csrf();

    $bookingId = input_int($_POST, 'booking_id', 1);
    $from = input_string($_POST, 'from', 20);

    if ($bookingId !== null && in_array($from, ['preparing', 'ready'], true)) {
        advance_snack_order($bookingId, $from);
    }
    redirect('admin/index.php');
}

$stats = dashboard_stats();
$queue = snack_queue();

$version = dashboard_version($stats, $queue);

$snacksShown = 0;
foreach ($queue as $orders) {
    foreach ($orders as $order) {
        $snacksShown += (int) $order['item_count'];
    }
}

$groups = [
    'preparing' => ['title' => 'Preparing', 'empty' => 'Nothing being prepared', 'pill' => 'pill-red'],
    'ready'     => ['title' => 'Ready', 'empty' => 'Nothing waiting at the counter', 'pill' => 'pill-green'],
    'sold'      => ['title' => 'Sold', 'empty' => 'Nothing handed over yet', 'pill' => 'pill-grey'],
];

render_head('Staff Dashboard', ['assets/css/admin.css']);
render_header(['staff' => true, 'current' => 'dashboard']);
?>
  <div class="admin-layout">
    <?php render_staff_sidebar('dashboard'); ?>

    <div class="admin-head">
      <h1>Staff Dashboard</h1>

      <div class="stat-row" id="dashboard-stats">

        <div class="stat stat-money">
          <p class="stat-name">Total Revenue</p>
          <p class="stat-figure"><?= e(peso($stats['total_revenue'])) ?></p>
          <p class="stat-extra">Tickets plus snacks<?php if ($stats['refunded_total'] > 0): ?>, after <?= e(peso($stats['refunded_total'])) ?> refunded<?php endif; ?></p>
        </div>

        <div class="stat">
          <p class="stat-name">Ticket Sales</p>
          <p class="stat-figure"><?= e(peso($stats['ticket_revenue'])) ?></p>
          <p class="stat-extra"><?= e(count_label($stats['tickets_sold'], 'ticket', 'tickets')) ?> sold</p>
        </div>

        <div class="stat">
          <p class="stat-name">Snack Sales</p>
          <p class="stat-figure"><?= e(peso($stats['snack_revenue'])) ?></p>
          <p class="stat-extra"><?= e(count_label($stats['snacks_sold'], 'snack', 'snacks')) ?> sold</p>
        </div>

      </div>
    </div>

    <main class="admin-page" id="dashboard" data-live-url="<?= e(url('api/dashboard.php')) ?>" data-version="<?= e($version) ?>">

      <section class="panel" id="snack-orders">

        <h2>Snack orders</h2>
        <p class="panel-note">
          Tap a button to move an order to the next stage. <?= e(count_label($snacksShown, 'snack', 'snacks')) ?> in total.
        </p>

<?php foreach ($groups as $stage => $group): ?>
        <div class="order-group">

          <h3 class="order-group-title">
            <?= e($group['title']) ?>

            <span class="pill <?= e($group['pill']) ?>"><?= e(count($queue[$stage])) ?></span>
          </h3>

<?php if ($stage === 'sold'): ?>
          <div class="sold-search" id="sold-search" data-url="<?= e(url('api/snack-search.php')) ?>">
            <div class="form-field search-field">
              <input id="sold-search-input" type="search" maxlength="40"
                     placeholder="Search by reference number" autocomplete="off" spellcheck="false"
                     aria-label="Search picked-up snack orders by reference number">
            </div>
            <p class="sold-search-note" id="sold-search-note" role="status" hidden></p>
            <div id="sold-search-results"></div>
          </div>
<?php endif; ?>

<?php if (count($queue[$stage]) === 0): ?>
          <p class="empty-note"><?= e($group['empty']) ?></p>
<?php endif; ?>

          <div class="order-list">
<?php foreach ($queue[$stage] as $order): ?>

            <div class="order-row">
              <div class="order-left">
                <p class="order-items"><?= e($order['items']) ?></p>
                <p class="order-who"><?php if ($order['snack_number'] !== null): ?><strong>#<?= e(snack_number_label((int) $order['snack_number'])) ?></strong> &bull; <?php endif; ?><?= e($order['reference']) ?> &bull; <?= e($order['customer_name']) ?> &bull; <?= e(count_label((int) $order['item_count'], 'snack', 'snacks')) ?></p>
              </div>
              <p class="order-price"><?= e(peso((int) $order['snacks_total'])) ?></p>
<?php if ($stage === 'sold'): ?>
              <span class="pill pill-grey sold-tag">Sold</span>
<?php else: ?>
              <form method="post" action="<?= e(url('admin/index.php')) ?>" class="inline-form">
                <?= csrf_field() ?>

                <input type="hidden" name="booking_id" value="<?= e($order['id']) ?>">
                <input type="hidden" name="from" value="<?= e($stage) ?>">
<?php if ($stage === 'preparing'): ?>
                <button type="submit" class="button button-red button-small">Ready</button>
<?php else: ?>
                <button type="submit" class="button button-red button-small">Picked Up</button>
<?php endif; ?>
              </form>
<?php endif; ?>
            </div>
<?php endforeach; ?>

          </div>
        </div>

<?php endforeach; ?>
      </section>

    </main>

  </div>
<?php render_footer(['js' => ['assets/js/dashboard.js']]);
