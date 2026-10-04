<?php
declare(strict_types=1);

// The door scanner's questions, asked by assets/js/scanner.js:
//
//   POST {"action": "check", "code": "<what the QR code says>"}
//     Is this a good ticket? Changes nothing.
//   POST {"action": "admit", "code": "..."}
//     Let it in. Only a ticket that checks out as valid right now is
//     admitted, and admit_ticket() only lets a ticket in once, so two
//     scanners reading the same ticket at once cannot both admit it.
//
// Staff only, CSRF-checked, and rate-limited per address. The reply never
// carries the QR secret or any internal id, only what the door needs to see.

require __DIR__ . '/../includes/bootstrap.php';

[$user, $action, $code] = read_scan_request('scan', ['check', 'admit']);

/**
 * The reply for one ticket: its status and, for a genuine ticket, the few
 * details staff check against the customer. Never the QR token or ids.
 */
function scan_reply(string $status, ?array $booking): array
{
    $reply = ['ok' => true, 'status' => $status];
    if ($booking === null) {
        return $reply;
    }
    $reply['reference'] = (string) $booking['reference'];
    $reply['name'] = (string) $booking['customer_name'];
    $reply['movie'] = (string) $booking['title'];
    $reply['seats'] = (string) $booking['seat_list'];
    $reply['showing'] = format_showing((string) $booking['show_date'], (string) $booking['show_time'], (int) $booking['cinema']);
    $reply['show_date'] = format_date_short((string) $booking['show_date']);
    if ($booking['scanned_at'] !== null) {
        $reply['scanned_at'] = format_time((new DateTimeImmutable((string) $booking['scanned_at']))->format('H:i:s'));
    }
    return $reply;
}

$result = check_ticket($code);

if ($action === 'check' || $result['status'] !== 'valid') {
    // A check, or an admit for a ticket that is not good (any more):
    // say what it is and change nothing
    json_response(scan_reply($result['status'], $result['booking']));
}

$bookingId = (int) $result['booking']['id'];
if (admit_ticket($bookingId, (int) $user['id'])) {
    json_response(scan_reply('admitted', find_booking_by_id($bookingId)));
}

// Someone else let it in a moment ago (or it changed under us): say what
// it is now
$again = check_ticket($code);
if ($again['status'] === 'valid') {
    json_response(['ok' => false, 'error' => 'This ticket could not be let in. Scan it again.'], 409);
}
json_response(scan_reply($again['status'], $again['booking']));
