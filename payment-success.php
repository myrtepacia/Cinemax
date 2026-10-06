<?php
declare(strict_types=1);

// PayMongo sends customers here after paying. Arriving proves nothing, so
// PayMongo is asked before a ticket is shown.

require __DIR__ . '/includes/bootstrap.php';

$user = require_login();

$reference = normalize_reference(input_string($_GET, 'ref', 20));
$booking = $reference !== null ? find_user_booking((int) $user['id'], $reference) : null;
if ($booking === null) {
    abort(404, 'We could not find that booking.');
}

// Each visit asks PayMongo, so limit reloads
if (too_many_attempts('settle', 'user:' . $user['id'], 30, 300)) {
    abort(429);
}
record_attempt('settle', 'user:' . $user['id']);

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
    case 'review':
        redirect('account.php');
        break;
}

// Not paid yet, or PayMongo could not be asked
$stillHeld = $booking['status'] === 'pending' && strtotime((string) $booking['expires_at']) > time();
$filmPath = booking_film_path($booking);

render_head('Payment', ['assets/css/booking.css']);
render_header(['current' => 'account']);
?>
  <main class="ticket-page">

    <?php
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

    <?php render_booking_summary($booking, 'Amount Due'); ?>

    <div class="ticket-actions">
<?php if ($stillHeld): ?>
      <!-- Back to paying (checks for a payment first) -->
      <form class="inline-form" method="post" action="<?= e(url('pay-now.php')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="ref" value="<?= e($reference) ?>">
        <button class="button button-red" type="submit">Pay now</button>
      </form>
<?php else: ?>
      <a class="button button-red" href="<?= e(url('account.php')) ?>">My Bookings</a>
      <a class="button button-outline" href="<?= e(url($filmPath)) ?>">Back to the movie</a>
<?php endif; ?>
    </div>

  </main>
<?php
render_footer();
