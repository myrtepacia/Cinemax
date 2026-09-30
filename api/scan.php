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

const SCAN_MAX_BODY_BYTES = 4096;
const SCAN_MAX_CODE_LENGTH = 300;
const SCAN_MAX_PER_MINUTE = 120;

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
record_attempt('scan');
if (too_many_attempts('scan', '', SCAN_MAX_PER_MINUTE, 60)) {
    json_response(['ok' => false, 'error' => 'Too many scans in a short time. Wait a minute, then try again.'], 429);
}

// The body: small JSON only. One byte past the limit is read so an
// oversized body can be told apart from one exactly at the limit.
$contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
if (strpos($contentType, 'application/json') !== 0) {
    json_response(['ok' => false, 'error' => 'That request was not understood.'], 415);
}
$raw = file_get_contents('php://input', false, null, 0, SCAN_MAX_BODY_BYTES + 1);
if (!is_string($raw) || strlen($raw) > SCAN_MAX_BODY_BYTES) {
    json_response(['ok' => false, 'error' => 'That request was too large.'], 413);
}

$body = json_decode($raw, true, 4);
$action = is_array($body) ? ($body['action'] ?? null) : null;
$code = is_array($body) ? ($body['code'] ?? null) : null;

if (!is_string($action) || !in_array($action, ['check', 'admit'], true)
    || !is_string($code) || $code === '' || strlen($code) > SCAN_MAX_CODE_LENGTH) {
    json_response(['ok' => false, 'error' => 'That request was not understood.'], 400);
}

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
