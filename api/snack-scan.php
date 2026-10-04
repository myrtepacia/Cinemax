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

[, $action, $code] = read_scan_request('snack-scan', ['check', 'confirm']);

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
