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
        case 'review':
            // My Bookings shows where it stands
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

if (is_post()) {
    verify_csrf();
    $user = require_login();
    $booking = payment_cancel_booking($user, $_POST);

    // A paid ticket is never cancelled from here (only staff can refund it)
    if ($booking['status'] === 'paid') {
        redirect('ticket.php?ref=' . rawurlencode((string) $booking['reference']));
    }

    // A booking already closed (cancelled, refunded) has nothing to cancel
    if (in_array($booking['status'], ['pending', 'expired'], true)) {
        // Paid in the meantime? Then this leaves for the ticket instead.
        payment_cancel_settle($user, $booking);
        cancel_pending_booking($booking);
    }
    redirect(booking_film_path($booking));
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

    <?php render_booking_summary($booking, $stillHeld ? 'Amount Due' : 'Total'); ?>

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
