<?php
declare(strict_types=1);

// The snack counter's questions, asked by assets/js/scanner.js on the Snack
// Scanner page:
//
//   POST {"action": "check", "code": "<what the QR code says>"}
//     What did this customer order? Changes nothing.
//   POST {"action": "confirm", "code": "..."}
//     Send the order to the kitchen: it moves to Preparing on the
//     dashboard. Only an order still waiting at the counter is confirmed,
//     and only once, so two counters scanning the same ticket at once
//     cannot both confirm it.
//
// Staff only, CSRF-checked, and rate-limited per address. The reply never
// carries the QR secret or any internal id.

require __DIR__ . '/../includes/bootstrap.php';

const SNACK_SCAN_MAX_BODY_BYTES = 4096;
const SNACK_SCAN_MAX_CODE_LENGTH = 300;
const SNACK_SCAN_MAX_PER_MINUTE = 120;

if (!is_post()) {
    header('Allow: POST');
    json_response(['ok' => false, 'error' => 'Only POST requests are accepted here.'], 405);
}

$user = current_user();
if (!is_staff($user)) {
    json_response(['ok' => false, 'error' => 'Please sign in again.'], 401);
}

verify_csrf(true);

// Counted before the body is read, so junk requests count too
record_attempt('snack-scan');
if (too_many_attempts('snack-scan', '', SNACK_SCAN_MAX_PER_MINUTE, 60)) {
    json_response(['ok' => false, 'error' => 'Too many scans in a short time. Wait a minute, then try again.'], 429);
}

$contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
if (strpos($contentType, 'application/json') !== 0) {
    json_response(['ok' => false, 'error' => 'That request was not understood.'], 415);
}
$raw = file_get_contents('php://input', false, null, 0, SNACK_SCAN_MAX_BODY_BYTES + 1);
if (!is_string($raw) || strlen($raw) > SNACK_SCAN_MAX_BODY_BYTES) {
    json_response(['ok' => false, 'error' => 'That request was too large.'], 413);
}

$body = json_decode($raw, true, 4);
$action = is_array($body) ? ($body['action'] ?? null) : null;
$code = is_array($body) ? ($body['code'] ?? null) : null;

if (!is_string($action) || !in_array($action, ['check', 'confirm'], true)
    || !is_string($code) || $code === '' || strlen($code) > SNACK_SCAN_MAX_CODE_LENGTH) {
    json_response(['ok' => false, 'error' => 'That request was not understood.'], 400);
}

/**
 * The reply for one order: its status and, for a genuine ticket, who it is
 * for and what they ordered. Never the QR token or ids.
 */
function snack_scan_reply(string $status, ?array $booking, array $items): array
{
    $reply = ['ok' => true, 'status' => $status];
    if ($booking === null) {
        return $reply;
    }
    $reply['reference'] = (string) $booking['reference'];
    $reply['name'] = (string) $booking['customer_name'];
    $reply['movie'] = (string) $booking['title'];
    $reply['showing'] = format_showing((string) $booking['show_date'], (string) $booking['show_time'], (int) $booking['cinema']);
    $reply['show_date'] = format_date_short((string) $booking['show_date']);
    $reply['stage'] = (string) ($booking['snack_status'] ?? '');
    $reply['items'] = array_map(static function (array $line): array {
        return ['name' => (string) $line['name'], 'quantity' => (int) $line['quantity']];
    }, $items);
    $reply['snacks_total'] = peso((int) $booking['snacks_total']);
    return $reply;
}

$result = check_snack_order($code);

if ($action === 'check' || $result['status'] !== 'waiting') {
    // A look, or a confirm for an order that is not waiting (any more):
    // say what it is and change nothing
    json_response(snack_scan_reply($result['status'], $result['booking'], $result['items']));
}

$bookingId = (int) $result['booking']['id'];
if (confirm_snack_order($bookingId)) {
    $booking = find_booking_by_id($bookingId);
    json_response(snack_scan_reply('confirmed', $booking, booking_snacks($bookingId)));
}

// Another counter confirmed it a moment ago (or it changed under us): say
// what it is now
$again = check_snack_order($code);
if ($again['status'] === 'waiting') {
    json_response(['ok' => false, 'error' => 'This order could not be confirmed. Scan it again.'], 409);
}
json_response(snack_scan_reply($again['status'], $again['booking'], $again['items']));
