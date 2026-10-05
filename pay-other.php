<?php
declare(strict_types=1);

// "Choose another payment" on pay.php: instead of scanning the QR Ph code,
// the customer pays another way listed in the config. A card goes to
// Cinemax's own card page (card.php). GCash (or another e-wallet) goes
// straight to the wallet's own page to authorize the payment, with no
// PayMongo checkout page in between; the wallet then sends the customer back
// to pay.php (wallet=gcash), which says how it went. The QR Ph code keeps
// working until the hold ends.
//
// PayMongo is asked first whether the booking was paid after all, so nobody
// is sent to pay twice.

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

// With live keys, PayMongo refuses a way to pay it has not turned on
if (paymongo_is_live() && !in_array($method, paymongo_enabled_methods(), true)) {
    redirect($payPath . '&other=failed');
}

// A card is paid on Cinemax's own card page
if ($method === 'card') {
    redirect('card.php?ref=' . rawurlencode($reference));
}

// Each press asks PayMongo for a new wallet page, like booking does, so it
// shares that limit
if (too_many_attempts('checkout', 'user:' . $user['id'], 20, 3600, 60)) {
    redirect($payPath);
}
record_attempt('checkout', 'user:' . $user['id']);

$returnPath = $payPath . '&wallet=' . rawurlencode($method);

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

    // Only a booking still holding its seats can be paid for (pay.php says
    // where it stands otherwise)
    $booking = find_booking_by_id((int) $booking['id']);
    if ($booking === null || $booking['status'] !== 'pending' || booking_seconds_left($booking) <= 0) {
        redirect($payPath);
    }

    // The booking's e-wallet payment: made the first time, for every wallet
    // offered, then used again for each try. One made with the other keys
    // (switched between test and live since) is made again.
    $intentId = (string) ($booking['paymongo_wallet_intent_id'] ?? '');
    $intent = null;
    if ($intentId !== '') {
        try {
            $intent = paymongo_get_intent($intentId);
        } catch (PayMongoNotFoundException $e) {
            // Made with the other keys
        }
    }
    if ($intent === null) {
        $made = paymongo_create_intent($booking, array_values(array_intersect(PAYMONGO_WALLETS, array_keys($offered))));
        attach_intent((int) $booking['id'], $made['id'], 'paymongo_wallet_intent_id');
        $intentId = $made['id'];
        $clientKey = $made['client_key'];
    } else {
        if (($intent['attributes']['status'] ?? '') === 'processing') {
            // Paid in the wallet, and PayMongo is still confirming it
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
    // PayMongoException mostly (PayMongo refused, or could not be reached),
    // or a page address that is not PayMongo's own. The QR Ph code still works.
    error_log('[pay-other] ' . $reference . ' (' . $method . '): ' . $e->getMessage());
    redirect($payPath . '&other=failed');
}
