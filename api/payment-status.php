<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';

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

if (payment_keys_changed()) {
    json_response(['ok' => true, 'status' => 'refresh']);
}

try {
    $result = settle_booking($booking);
} catch (PayMongoException $e) {
    error_log('[payment-status] ' . $reference . ': ' . $e->getMessage());
    $result = 'unknown';
}

if ($result === 'paid') {
    json_response(['ok' => true, 'status' => 'paid', 'next' => url('ticket.php?ref=' . rawurlencode($reference))]);
}

$booking = find_booking_by_id((int) $booking['id']);
$secondsLeft = booking_seconds_left($booking);
if ($booking['status'] === 'pending' && $secondsLeft > 0) {
    json_response(['ok' => true, 'status' => 'waiting', 'secondsLeft' => $secondsLeft]);
}
json_response(['ok' => true, 'status' => 'ended', 'next' => url('payment-success.php?ref=' . rawurlencode($reference))]);
