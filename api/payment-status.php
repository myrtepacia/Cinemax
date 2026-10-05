<?php
declare(strict_types=1);

// Is it paid yet? Asked every few seconds by pay.js: ?ref=CMX-XXXXXX. Each
// check asks PayMongo. Status is waiting (with secondsLeft), paid (next = the
// ticket), ended (next = payment-success.php) or refresh (the PayMongo keys
// changed, so the page reloads). Own bookings only.

require __DIR__ . '/../includes/bootstrap.php';

// A page left open 10 minutes asks about 150 times
const PAY_CHECKS_PER_MINUTE = 40;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    json_response(['ok' => false, 'error' => 'Only GET requests are accepted here.'], 405);
}

$user = current_user();
if ($user === null) {
    json_response(['ok' => false, 'error' => 'Please sign in again.'], 401);
}

$reference = normalize_reference(input_string($_GET, 'ref', 20));
$booking = $reference !== null ? find_user_booking((int) $user['id'], $reference) : null;
if ($booking === null) {
    json_response(['ok' => false, 'error' => 'We could not find that booking.'], 404);
}

if (too_many_attempts('pay-check', 'user:' . $user['id'], PAY_CHECKS_PER_MINUTE, 60)) {
    json_response(['ok' => false, 'error' => 'Checking too often. Please wait a moment.'], 429);
}
record_attempt('pay-check', 'user:' . $user['id']);

if (payment_keys_changed()) {
    json_response(['ok' => true, 'status' => 'refresh']);
}

$wasPaid = $booking['status'] === 'paid';
try {
    $result = settle_booking($booking);
} catch (PayMongoException $e) {
    // PayMongo did not answer: ask again next time
    error_log('[payment-status] ' . $reference . ': ' . $e->getMessage());
    $result = 'unknown';
}

if ($result === 'paid') {
    // Shows Booking Confirmed! on the ticket
    if (!$wasPaid) {
        $_SESSION['just_paid'] = $reference;
    }
    json_response(['ok' => true, 'status' => 'paid', 'next' => url('ticket.php?ref=' . rawurlencode($reference))]);
}

$booking = find_booking_by_id((int) $booking['id']);
$secondsLeft = booking_seconds_left($booking);
if ($booking['status'] === 'pending' && $secondsLeft > 0) {
    json_response(['ok' => true, 'status' => 'waiting', 'secondsLeft' => $secondsLeft]);
}
json_response(['ok' => true, 'status' => 'ended', 'next' => url('payment-success.php?ref=' . rawurlencode($reference))]);
