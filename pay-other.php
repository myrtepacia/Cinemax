<?php
declare(strict_types=1);

// "Choose another payment" on pay.php: card goes to card.php, GCash to the
// wallet's own page. Checks first that the booking is not already paid.

require __DIR__ . '/includes/bootstrap.php';

require_post();
verify_csrf();
$user = require_role('customer');

$reference = normalize_reference(input_string($_POST, 'ref', 20));
$booking = $reference !== null ? find_user_booking((int) $user['id'], $reference) : null;
if ($booking === null) {
    abort(404, 'We could not find that booking.');
}
$payPath = 'pay.php?ref=' . rawurlencode($reference);

$offered = paymongo_other_methods();
$method = input_string($_POST, 'method', 20);
if (!array_key_exists($method, $offered)) {
    redirect($payPath);
}

// With live keys, PayMongo refuses ways it has not turned on
if (paymongo_is_live() && !in_array($method, paymongo_enabled_methods(), true)) {
    redirect($payPath . '&other=failed');
}

if ($method === 'card') {
    redirect('card.php?ref=' . rawurlencode($reference));
}

// Shares the booking limit, since each press asks PayMongo
if (too_many_attempts('checkout', 'user:' . $user['id'], 20, 3600, 60)) {
    redirect($payPath);
}
record_attempt('checkout', 'user:' . $user['id']);

$returnPath = $payPath . '&wallet=' . rawurlencode($method);

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
    if ($booking === null || $booking['status'] !== 'pending' || booking_seconds_left($booking) <= 0) {
        redirect($payPath);
    }

    // One wallet payment per booking, reused for every try; made again if it
    // belongs to the other keys
    $intentId = (string) ($booking['paymongo_wallet_intent_id'] ?? '');
    $intent = null;
    if ($intentId !== '') {
        try {
            $intent = paymongo_get_intent($intentId);
        } catch (PayMongoNotFoundException $e) {
        }
    }
    if ($intent === null) {
        $made = paymongo_create_intent($booking, array_values(array_intersect(PAYMONGO_WALLETS, array_keys($offered))));
        attach_intent((int) $booking['id'], $made['id'], 'paymongo_wallet_intent_id');
        $intentId = $made['id'];
        $clientKey = $made['client_key'];
    } else {
        if (($intent['attributes']['status'] ?? '') === 'processing') {
            // Paid; PayMongo is still confirming
            redirect($returnPath);
        }
        $clientKey = (string) ($intent['attributes']['client_key'] ?? '');
    }

    redirect_external(paymongo_start_wallet($intentId, $clientKey, $method, [
        'name'   => (string) $user['name'],
        'email'  => (string) $user['email'],
        'mobile' => (string) ($user['mobile'] ?? ''),
    ], $returnPath));
} catch (RuntimeException $e) {
    // PayMongo refused or could not be reached; the QR code still works
    error_log('[pay-other] ' . $reference . ' (' . $method . '): ' . $e->getMessage());
    redirect($payPath . '&other=failed');
}
