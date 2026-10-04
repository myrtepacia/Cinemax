<?php
declare(strict_types=1);

// "Is it paid yet?", asked every few seconds by assets/js/pay.js while a
// customer's QR Ph code is on screen (pay.php): GET ?ref=CMX-XXXXXX.
//
// Each check asks PayMongo directly. Answers:
//   {"ok": true, "status": "waiting", "secondsLeft": n}   not paid, still held
//   {"ok": true, "status": "paid", "next": <the e-ticket>}
//   {"ok": true, "status": "ended", "next": <payment-success.php>}  the hold
//        is over, or the booking changed some other way
//
// The customer's own bookings only; anyone else's is "not found".

require __DIR__ . '/../includes/bootstrap.php';

// A page left open for the whole 10 minutes asks about 150 times
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

$wasPaid = $booking['status'] === 'paid';
try {
    $result = settle_booking($booking);
} catch (PayMongoException $e) {
    // PayMongo did not answer: the page simply asks again
    error_log('[payment-status] ' . $reference . ': ' . $e->getMessage());
    $result = 'unknown';
}

if ($result === 'paid') {
    // "Booking Confirmed!" on the ticket, only when the money has just come in
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
