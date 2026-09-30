<?php
declare(strict_types=1);

// "Pay now" on the payment pages: takes a customer back to PayMongo to
// finish paying for a booking whose seats are still held.
//
// PayMongo is asked first whether the booking was paid after all, so nobody
// is sent to pay twice. If it was not, the booking's own PayMongo page is
// reopened; if PayMongo has already closed that page, a fresh one is made
// for the same booking (same seats, same total, same hold time).

require __DIR__ . '/includes/bootstrap.php';

require_post();
verify_csrf();
$user = require_role('customer');

$reference = normalize_reference(input_string($_POST, 'ref', 20));
$booking = $reference !== null ? find_user_booking((int) $user['id'], $reference) : null;
if ($booking === null) {
    abort(404, 'We could not find that booking.');
}

// Each press asks PayMongo, so it shares the payment pages' limit
if (too_many_attempts('settle', 'user:' . $user['id'], 30, 300)) {
    abort(429);
}
record_attempt('settle', 'user:' . $user['id']);

$filmPath = find_movie_by_slug((string) $booking['slug']) !== null
    ? 'book.php?movie=' . rawurlencode((string) $booking['slug'])
    : 'index.php';

try {
    // Paid after all (or refunded meanwhile): no second payment
    $result = settle_booking($booking);
    if ($result === 'paid') {
        $_SESSION['just_paid'] = $reference;
        redirect('ticket.php?ref=' . rawurlencode($reference));
    }
    if ($result === 'refunded' || $result === 'review') {
        redirect('payment-success.php?ref=' . rawurlencode($reference));
    }

    // Only a booking still holding its seats can be paid for
    $booking = find_booking_by_id((int) $booking['id']);
    $stillHeld = $booking !== null && $booking['status'] === 'pending'
        && strtotime((string) $booking['expires_at']) > time();
    if (!$stillHeld) {
        flash('error', 'Your seat hold ran out before payment, so those seats went back on sale. Please pick your seats again.');
        redirect($filmPath);
    }

    // The booking's own PayMongo page, if it is still open
    $checkoutId = (string) ($booking['paymongo_checkout_id'] ?? '');
    if ($checkoutId !== '') {
        $checkout = paymongo_get_checkout($checkoutId);
        $url = (string) ($checkout['attributes']['checkout_url'] ?? '');
        if (($checkout['attributes']['status'] ?? '') === 'active' && $url !== '') {
            redirect_external($url);
        }
    }

    // Otherwise a fresh PayMongo page for the same booking
    $fresh = paymongo_create_checkout($booking, booking_line_items($booking), [
        'name'   => (string) $user['name'],
        'email'  => (string) $user['email'],
        'mobile' => (string) ($user['mobile'] ?? ''),
    ]);
    attach_checkout((int) $booking['id'], $fresh['id']);
    redirect_external($fresh['checkout_url']);
} catch (PayMongoException $e) {
    error_log('[pay-now] ' . $reference . ': ' . $e->getMessage());
    flash('error', 'We could not reach the payment service just now. Nothing was charged. Please try again in a moment.');
    redirect('payment-success.php?ref=' . rawurlencode($reference));
}
