<?php
declare(strict_types=1);

// PayMongo sends the customer here when they back out of paying.
//   GET  ?ref=CMX-XXXXXX  "Waiting for payment": how long the seats stay
//        held ("Pay by ...") with Pay now and Back to Movies, laid out
//        like payment-success.php. It changes nothing (apart from recording a
//        payment PayMongo did take after all).
//   POST ref=...         cancels the booking and frees its seats (the
//        Cancel booking button on payment-success.php).

require __DIR__ . '/includes/bootstrap.php';

/**
 * Asks PayMongo about the booking once more (someone can pay in one tab and
 * cancel in another) and leaves this page if it turns out to be settled.
 * Returns quietly when it is still unpaid, or PayMongo cannot be asked.
 */
function payment_cancel_settle(array $user, array $booking): void
{
    $reference = (string) $booking['reference'];
    if (too_many_attempts('settle', 'user:' . $user['id'], 30, 300)) {
        return;
    }
    record_attempt('settle', 'user:' . $user['id']);

    $wasPaid = $booking['status'] === 'paid';
    $wasRefunded = $booking['status'] === 'refunded';
    try {
        $result = settle_booking($booking);
    } catch (PayMongoException $e) {
        error_log('[payment-cancel] ' . $reference . ': ' . $e->getMessage());
        return;
    }

    switch ($result) {
        case 'paid':
            // "Booking Confirmed!" only when the payment has just come in,
            // not when an old paid booking's cancel link is opened again
            if (!$wasPaid) {
                $_SESSION['just_paid'] = $reference;
            }
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
}

/**
 * The customer's own booking named by $source['ref'], or a 404 page.
 */
function payment_cancel_booking(array $user, array $source): array
{
    $reference = normalize_reference(input_string($source, 'ref', 20));
    $booking = $reference !== null ? find_user_booking((int) $user['id'], $reference) : null;
    if ($booking === null) {
        abort(404, 'We could not find that booking.');
    }
    return $booking;
}

/**
 * Where "back to the movie" goes: the film's page while it is still listed.
 */
function payment_cancel_film_path(array $booking): string
{
    return find_movie_by_slug((string) $booking['slug']) !== null
        ? 'book.php?movie=' . rawurlencode((string) $booking['slug'])
        : 'index.php';
}

if (is_post()) {
    verify_csrf();
    $user = require_login();
    $booking = payment_cancel_booking($user, $_POST);

    // A paid ticket is never cancelled from here (only staff can refund it)
    if ($booking['status'] === 'paid') {
        flash('notice', 'Booking ' . $booking['reference'] . ' is already paid, so it was not cancelled.');
        redirect('ticket.php?ref=' . rawurlencode((string) $booking['reference']));
    }

    if (in_array($booking['status'], ['pending', 'expired'], true)) {
        // Paid in the meantime? Then this leaves for the ticket instead.
        payment_cancel_settle($user, $booking);
        cancel_pending_booking($booking);
        flash('success', 'Booking ' . $booking['reference'] . ' was cancelled and its seats are free again.');
    } else {
        flash('notice', 'Booking ' . $booking['reference'] . ' was already closed, so there was nothing to cancel.');
    }
    redirect(payment_cancel_film_path($booking));
}

$user = require_login();
$booking = payment_cancel_booking($user, $_GET);
payment_cancel_settle($user, $booking);

$reference = (string) $booking['reference'];
$expires = strtotime((string) $booking['expires_at']);
$stillHeld = $booking['status'] === 'pending' && $expires !== false && $expires > time();

render_head($stillHeld ? 'Waiting for payment' : 'Payment cancelled', ['assets/css/booking.css']);
render_header(['current' => 'account']);
?>
  <main class="ticket-page">

    <?php render_flashes(); ?>

<?php if ($stillHeld): ?>
    <!-- Laid out like the payment page while seats are held -->
    <div class="confirm-box confirm-box-tight">
      <div class="status-mark">!</div>
      <h1>Waiting for payment</h1>
      <p class="held-until"><strong>Pay by <?= e(format_time(date('H:i:s', $expires))) ?>, or this booking will be cancelled.</strong></p>
    </div>
<?php else: ?>
    <div class="confirm-box">
      <div class="status-mark status-mark-stopped">&times;</div>
      <h1>Payment cancelled</h1>
      <p>Nothing was charged. This booking no longer holds any seats.</p>
    </div>
<?php endif; ?>

    <div class="ticket">
      <div class="ticket-body">
        <div class="ticket-pair">
          <div>
            <p class="ticket-label">Reference No.</p>
            <p class="ticket-value ticket-reference"><?= e($reference) ?></p>
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
            <p class="ticket-label"><?= $stillHeld ? 'Amount Due' : 'Total' ?></p>
            <p class="ticket-value"><?= e(peso((int) $booking['total'])) ?></p>
          </div>
        </div>
        <p class="ticket-label">Showing</p>
        <p class="ticket-value ticket-value-small"><?= e(format_showing((string) $booking['show_date'], (string) $booking['show_time'], (int) $booking['cinema'])) ?></p>
      </div>
    </div>

<?php if ($stillHeld): ?>
    <div class="ticket-actions">
      <!-- Back to PayMongo to finish paying (it checks for a payment first) -->
      <form class="inline-form" method="post" action="<?= e(url('pay-now.php')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="ref" value="<?= e($reference) ?>">
        <button class="button button-red" type="submit">Pay now</button>
      </form>
      <a class="button button-outline" href="<?= e(url('index.php')) ?>">Back to Movies</a>
    </div>
<?php endif; ?>

  </main>
<?php
render_footer();
