<?php
declare(strict_types=1);

// PayMongo sends the customer here after paying. Arriving here proves
// nothing (anyone can type this address), so PayMongo itself is asked
// whether the booking's checkout was paid before a ticket is shown.

require __DIR__ . '/includes/bootstrap.php';

$user = require_login();

$reference = normalize_reference(input_string($_GET, 'ref', 20));
$booking = $reference !== null ? find_user_booking((int) $user['id'], $reference) : null;
if ($booking === null) {
    abort(404, 'We could not find that booking.');
}

// Every visit asks PayMongo, so a reload loop cannot turn into a flood of
// calls from our server.
if (too_many_attempts('settle', 'user:' . $user['id'], 30, 300)) {
    abort(429);
}
record_attempt('settle', 'user:' . $user['id']);

$wasRefunded = $booking['status'] === 'refunded';

try {
    $result = settle_booking($booking);
} catch (PayMongoException $e) {
    error_log('[payment-success] ' . $reference . ': ' . $e->getMessage());
    $result = 'unknown';
}

switch ($result) {
    case 'paid':
        $_SESSION['just_paid'] = $reference;
        redirect('ticket.php?ref=' . rawurlencode($reference));
        break;
    case 'refunded':
        flash('notice', $wasRefunded
            ? 'Booking ' . $reference . ' was refunded, so its ticket can no longer be used.'
            : 'Your payment arrived after your seat hold ran out and the seats had been taken, so it was refunded in full.');
        redirect('account.php');
        break;
    case 'review':
        flash('notice', 'We received your payment for booking ' . $reference
            . ', but our staff need to confirm it before your ticket is ready. Please check My Bookings again later.');
        redirect('account.php');
        break;
}

// Still here: not paid yet, or PayMongo could not be asked just now
$stillHeld = $booking['status'] === 'pending' && strtotime((string) $booking['expires_at']) > time();
$filmPath = find_movie_by_slug((string) $booking['slug']) !== null
    ? 'book.php?movie=' . rawurlencode((string) $booking['slug'])
    : 'index.php';

/**
 * The booking's details, laid out like the e-ticket's body.
 */
function payment_success_summary(array $booking): void
{
    ?>
    <div class="ticket">
      <div class="ticket-body">
        <div class="ticket-pair">
          <div>
            <p class="ticket-label">Reference No.</p>
            <p class="ticket-value ticket-reference"><?= e($booking['reference']) ?></p>
          </div>
          <div>
            <p class="ticket-label">Seat(s)</p>
            <p class="ticket-value"><?= e($booking['seat_list']) ?></p>
          </div>
        </div>
        <div class="ticket-pair">
          <div>
            <p class="ticket-label">Movie</p>
            <p class="ticket-value ticket-value-small"><?= e($booking['title']) ?></p>
          </div>
          <div>
            <p class="ticket-label">Amount Due</p>
            <p class="ticket-value"><?= e(peso((int) $booking['total'])) ?></p>
          </div>
        </div>
        <p class="ticket-label">Showing</p>
        <p class="ticket-value ticket-value-small"><?= e(format_showing((string) $booking['show_date'], (string) $booking['show_time'], (int) $booking['cinema'])) ?></p>
      </div>
    </div>
<?php
}

render_head('Payment', ['assets/css/booking.css']);
render_header(['current' => 'account']);
?>
  <main class="ticket-page">

    <?php render_flashes(); ?>

    <?php
    // Seats still held: a big gap above "held until" and a small one below
    $holdLine = $result !== 'unknown' && $booking['status'] !== 'cancelled' && $stillHeld;
    ?>
    <div class="confirm-box<?= $holdLine ? ' confirm-box-tight' : '' ?>">
<?php if ($result === 'unknown'): ?>
      <div class="status-mark">!</div>
      <h1>We could not confirm your payment yet</h1>
      <p>The payment service did not answer just now. If you paid, nothing is lost: your ticket appears under My Bookings as soon as the payment is confirmed.</p>
<?php elseif ($booking['status'] === 'cancelled'): ?>
      <div class="status-mark status-mark-stopped">&times;</div>
      <h1>This booking was cancelled</h1>
      <p>Nothing was charged and its seats are free again. If you did pay before cancelling, it shows under My Bookings: a payment that arrives late is either confirmed or refunded in full.</p>
<?php elseif ($stillHeld): ?>
      <div class="status-mark">!</div>
      <h1>We have not received your payment yet</h1>
      <p class="held-until"><strong>Your seats are held until <?= e(format_time(date('H:i:s', (int) strtotime((string) $booking['expires_at'])))) ?>.</strong></p>
<?php else: ?>
      <div class="status-mark status-mark-stopped">!</div>
      <h1>Your seat hold ran out</h1>
      <p>We did not receive your payment in time, so the seats went back on sale. If you did pay, it shows under My Bookings: a payment that arrives late is either confirmed or refunded in full.</p>
<?php endif; ?>
    </div>

    <?php payment_success_summary($booking); ?>

    <div class="ticket-actions">
<?php if ($stillHeld): ?>
      <!-- Back to PayMongo to finish paying (it checks for a payment first) -->
      <form class="inline-form" method="post" action="<?= e(url('pay-now.php')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="ref" value="<?= e($reference) ?>">
        <button class="button button-red" type="submit">Pay now</button>
      </form>
      <form class="inline-form" method="post" action="<?= e(url('payment-cancel.php')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="ref" value="<?= e($reference) ?>">
        <button class="button button-outline" type="submit">Cancel booking</button>
      </form>
<?php else: ?>
      <a class="button button-red" href="<?= e(url('account.php')) ?>">My Bookings</a>
      <a class="button button-outline" href="<?= e(url($filmPath)) ?>">Back to the movie</a>
<?php endif; ?>
    </div>

<?php if ($stillHeld): ?>
    <p class="status-note">Changed your mind? Cancelling frees the seats straight away.</p>
<?php endif; ?>

  </main>
<?php
render_footer();
