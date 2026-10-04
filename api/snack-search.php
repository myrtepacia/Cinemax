<?php
declare(strict_types=1);

// The Dashboard's search above Sold, asked by assets/js/dashboard.js as the
// admin types: GET ?q=<part or all of a reference number>. Answers with the
// picked-up snack orders whose reference holds it, with when each was
// scanned at the snack counter and when it was picked up.
//
// Admin only. It only reads.

require __DIR__ . '/../includes/bootstrap.php';

require_admin_get();

$found = search_sold_snacks(input_string($_GET, 'q', 40));

$orders = array_map(static function (array $order): array {
    return [
        'reference' => (string) $order['reference'],
        'number'    => $order['snack_number'] !== null ? '#' . snack_number_label((int) $order['snack_number']) : '',
        'name'      => (string) $order['customer_name'],
        'items'     => (string) $order['items'],
        'count'     => count_label((int) $order['item_count'], 'snack', 'snacks'),
        'total'     => peso((int) $order['snacks_total']),
        'scanned'   => $order['snack_scanned_at'] !== null ? format_datetime((string) $order['snack_scanned_at']) : '',
        'sold'      => $order['snack_sold_at'] !== null ? format_datetime((string) $order['snack_sold_at']) : '',
    ];
}, $found['orders']);

// A whole reference that was found but is not picked up: where it is instead
$note = '';
if ($found['other'] !== null) {
    $reference = $found['other']['reference'];
    $note = [
        'ordered'   => $reference . ' has not been scanned at the snack counter yet.',
        'preparing' => $reference . ' is still being prepared.',
        'ready'     => $reference . ' is ready at the counter but has not been picked up yet.',
        'none'      => $reference . ' has no snacks.',
        'refunded'  => $reference . ' was refunded.',
        'unpaid'    => $reference . ' was never paid.',
    ][$found['other']['stage']] ?? '';
}

json_response(['ok' => true, 'orders' => $orders, 'note' => $note]);
