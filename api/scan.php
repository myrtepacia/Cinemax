<?php
declare(strict_types=1);

// Door scanner (scanner.js). POST {"action": "check", "code": ...} looks at a
// ticket; {"action": "admit", ...} lets a valid ticket in, once only. Staff
// only, CSRF-checked, rate-limited. Never returns the QR secret or ids.

require __DIR__ . '/../includes/bootstrap.php';

[$user, $action, $code] = read_scan_request('scan', ['check', 'admit']);

/**
 * A ticket's status and the details staff check. Never the QR token or ids.
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
    // Just a check, or the ticket is not valid: say what it is
    json_response(scan_reply($result['status'], $result['booking']));
}

$bookingId = (int) $result['booking']['id'];
if (admit_ticket($bookingId, (int) $user['id'])) {
    json_response(scan_reply('admitted', find_booking_by_id($bookingId)));
}

// Someone else just let it in: say what it is now
$again = check_ticket($code);
if ($again['status'] === 'valid') {
    json_response(['ok' => false, 'error' => 'This ticket could not be let in. Scan it again.'], 409);
}
json_response(scan_reply($again['status'], $again['booking']));
