<?php
declare(strict_types=1);

// My Bookings: the signed-in customer's tickets, newest showing first.

require __DIR__ . '/includes/bootstrap.php';

// The fewest seconds between two PayMongo checks for one visitor.
const ACCOUNT_SETTLE_EVERY_SECONDS = 60;

$user = require_login();

// Staff accounts cannot book, so they have no tickets here: send them to
// their own home (the Dashboard for admin, the Scanner for the scanner).
if (is_staff($user)) {
    redirect(home_for($user));
}

// Someone who paid but never made it back from PayMongo (closed the tab,
// lost signal) should still find their ticket here, so ask PayMongo first.
// Each check can call PayMongo several times, so it runs at most once a
// minute per visitor and shares payment-success.php's 'settle' limit;
// reloads in between (or past the limit) show the bookings as they are.
$lastSettled = (int) ($_SESSION['account_settled_at'] ?? 0);
if (time() - $lastSettled >= ACCOUNT_SETTLE_EVERY_SECONDS
    && !too_many_attempts('settle', 'user:' . $user['id'], 30, 300)) {
    $_SESSION['account_settled_at'] = time();
    record_attempt('settle', 'user:' . $user['id']);
    settle_recent_bookings((int) $user['id']);
}

$bookings = user_bookings((int) $user['id']);

/**
 * The small label beside a booking's price, as [text, class], or null when
 * it is simply a paid ticket waiting to be used.
 */
function account_status_label(array $booking): ?array
{
    switch ($booking['status']) {
        case 'pending':
            return ['Awaiting payment', 'pill-amber'];
        case 'refunded':
            return ['Refunded', 'pill-red'];
        case 'paid':
            return $booking['scanned_at'] !== null ? ['Used', 'pill-grey'] : null;
        default:
            return null;
    }
}

render_head('My Bookings', ['assets/css/public.css']);
render_header(['current' => 'account']);
?>
  <main class="admin-page">

    <h1>My Bookings</h1>
    <p class="admin-intro" id="intro">Signed in as <?= e($user['email']) ?>.</p>

    <section class="panel">

<?php if ($bookings === []): ?>
      <p class="empty-state">
        <strong>No bookings yet</strong>
        Once you book a seat, your ticket appears here.
      </p>
      <p class="empty-action">
        <a class="button button-red" href="<?= e(url('index.php')) ?>">Browse movies</a>
      </p>
<?php else: ?>
      <div id="bookings">
<?php foreach ($bookings as $booking): ?>
<?php $label = account_status_label($booking); ?>

        <a class="booking-row booking-link" href="<?= e(url('ticket.php?ref=' . rawurlencode((string) $booking['reference']))) ?>">
          <img class="table-poster" src="<?= e(poster_url($booking)) ?>" alt="<?= e($booking['title'] . ' poster') ?>"
               width="40" height="54" loading="lazy" decoding="async">
          <div class="booking-text">
            <p class="order-items"><?= e($booking['title']) ?></p>
            <p class="order-who"><?= e(format_showing((string) $booking['show_date'], (string) $booking['show_time'], (int) $booking['cinema'])) ?> &bull; <strong><?= e($booking['reference']) ?></strong></p>
          </div>
          <div class="booking-side">
            <p class="order-price"><?= e(peso((int) $booking['total'])) ?></p>
<?php if ($label !== null): ?>
            <span class="pill <?= e($label[1]) ?>"><?= e($label[0]) ?></span>
<?php endif; ?>
          </div>
        </a>
<?php endforeach; ?>

      </div>
<?php endif; ?>

    </section>

  </main>

<?php render_footer();
