<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';

[, $action, $code] = read_scan_request('snack-scan', ['check', 'confirm']);

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
    json_response(snack_scan_reply($result['status'], $result['booking'], $result['items']));
}

$bookingId = (int) $result['booking']['id'];
if (confirm_snack_order($bookingId)) {
    $booking = find_booking_by_id($bookingId);
    json_response(snack_scan_reply('confirmed', $booking, booking_snacks($bookingId)));
}

$again = check_snack_order($code);
if ($again['status'] === 'waiting') {
    json_response(['ok' => false, 'error' => 'This order could not be confirmed. Scan it again.'], 409);
}
json_response(snack_scan_reply($again['status'], $again['booking'], $again['items']));
