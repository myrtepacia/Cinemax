<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';

// check: what is this ticket; admit: let them in; snack: send their snacks
// to Preparing
[$user, $action, $code] = read_scan_request(['check', 'admit', 'snack']);

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
    // Snacks bought with the ticket and not yet sent to Preparing
    $reply['snacks_waiting'] = $booking['status'] === 'paid' && ($booking['snack_status'] ?? null) === 'ordered';
    if ($booking['scanned_at'] !== null) {
        $reply['scanned_at'] = format_time((new DateTimeImmutable((string) $booking['scanned_at']))->format('H:i:s'));
    }
    return $reply;
}

if ($action === 'snack') {
    // snack_result: confirmed, or why not (in_progress, collected, no_snacks...)
    $order = check_snack_order($code);
    $snackResult = $order['status'];
    if ($snackResult === 'waiting') {
        $snackResult = confirm_snack_order((int) $order['booking']['id']) ? 'confirmed' : check_snack_order($code)['status'];
    }
    $now = check_ticket($code);
    json_response(scan_reply($now['status'], $now['booking']) + ['snack_result' => $snackResult]);
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
