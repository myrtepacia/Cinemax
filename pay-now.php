<?php
declare(strict_types=1);

// "Pay now": back to paying for a booking still held. Checks first it is not
// already paid, then reopens its PayMongo page or makes a new one.

require __DIR__ . '/includes/bootstrap.php';

require_post();
verify_csrf();
$user = require_role('customer');

$reference = normalize_reference(input_string($_POST, 'ref', 20));
$booking = $reference !== null ? find_user_booking((int) $user['id'], $reference) : null;
if ($booking === null) {
    abort(404, 'We could not find that booking.');
}

// Shares the payment pages limit
if (too_many_attempts('settle', 'user:' . $user['id'], 30, 300)) {
    abort(429);
}
record_attempt('settle', 'user:' . $user['id']);

$filmPath = booking_film_path($booking);

try {
    // Already paid: no second payment
    $result = settle_booking($booking);
    if ($result === 'paid') {
        $_SESSION['just_paid'] = $reference;
        redirect('ticket.php?ref=' . rawurlencode($reference));
    }
    if ($result === 'refunded' || $result === 'review') {
        redirect('payment-success.php?ref=' . rawurlencode($reference));
    }

    // Only a booking still held can be paid
    $booking = find_booking_by_id((int) $booking['id']);
    $stillHeld = $booking !== null && $booking['status'] === 'pending'
        && strtotime((string) $booking['expires_at']) > time();
    if (!$stillHeld) {
        redirect($filmPath);
    }

    // QR Ph: back to the code and countdown
    if (paymongo_uses_qrph() || !empty($booking['paymongo_intent_id'])) {
        redirect('pay.php?ref=' . rawurlencode($reference));
    }

    // Its PayMongo page, if still open
    $checkoutId = (string) ($booking['paymongo_checkout_id'] ?? '');
    if ($checkoutId !== '') {
        $checkout = paymongo_get_checkout($checkoutId);
        $url = (string) ($checkout['attributes']['checkout_url'] ?? '');
        if (($checkout['attributes']['status'] ?? '') === 'active' && $url !== '') {
            redirect_external($url);
        }
    }

    // Otherwise a new one
    $fresh = paymongo_create_checkout($booking, booking_line_items($booking), [
        'name'   => (string) $user['name'],
        'email'  => (string) $user['email'],
        'mobile' => (string) ($user['mobile'] ?? ''),
    ]);
    attach_checkout((int) $booking['id'], $fresh['id']);
    redirect_external($fresh['checkout_url']);
} catch (PayMongoException $e) {
    error_log('[pay-now] ' . $reference . ': ' . $e->getMessage());
    redirect('payment-success.php?ref=' . rawurlencode($reference));
}
