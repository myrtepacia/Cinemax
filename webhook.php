<?php
declare(strict_types=1);

// PayMongo's servers call this when a booking is paid, so a customer who
// closes the tab before coming back to the site still gets their ticket.
// Register it in PayMongo for two events: checkout_session.payment.paid
// (PayMongo's checkout page) and payment.paid (a QR Ph code from pay.php, a
// card from card.php, or GCash from pay-other.php).
//
// Only calls signed with the webhook secret are accepted, each event is
// handled once however often it is delivered, and even a genuine event is
// not taken at its word: settle_booking() asks PayMongo about the payment
// directly before marking anything paid.

// A server-to-server call: no browser, so no session or cookies
define('CINEMAX_NO_SESSION', true);

require __DIR__ . '/includes/bootstrap.php';

// PayMongo's events are a few kilobytes; anything near this is not one
const WEBHOOK_MAX_BYTES = 1048576;

ignore_user_abort(true);

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    json_response(['ok' => false], 405);
}

if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > WEBHOOK_MAX_BYTES) {
    json_response(['ok' => false], 413);
}
$raw = file_get_contents('php://input', false, null, 0, WEBHOOK_MAX_BYTES + 1);
if (!is_string($raw) || $raw === '' || strlen($raw) > WEBHOOK_MAX_BYTES) {
    json_response(['ok' => false], 413);
}

$signature = $_SERVER['HTTP_PAYMONGO_SIGNATURE'] ?? '';
if (!is_string($signature) || !paymongo_verify_webhook($raw, $signature)) {
    json_response(['ok' => false], 401);
}

$event = json_decode($raw, true);
$eventId = is_array($event) ? ($event['data']['id'] ?? null) : null;
$eventType = is_array($event) ? ($event['data']['attributes']['type'] ?? null) : null;
if (!is_string($eventId) || !preg_match('/^[A-Za-z0-9_]{1,64}$/', $eventId)
    || !is_string($eventType) || !preg_match('/^[a-z0-9_.]{1,80}$/', $eventType)) {
    json_response(['ok' => false], 400);
}

// Written down first, so a second delivery of the same event stops here
try {
    db_exec('INSERT INTO webhook_events (event_id, event_type) VALUES (?, ?)', [$eventId, $eventType]);
} catch (PDOException $e) {
    if (is_duplicate_key($e)) {
        json_response(['ok' => true, 'duplicate' => true]);
    }
    // Nothing was recorded, so PayMongo's retry will be handled normally
    error_log(sprintf('[webhook] could not record %s: %s', $eventId, $e->getMessage()));
    json_response(['ok' => false], 500);
}

/**
 * Forgets an event, so PayMongo's next try at delivering it is handled.
 */
function webhook_forget(string $eventId): void
{
    try {
        db_exec('DELETE FROM webhook_events WHERE event_id = ?', [$eventId]);
    } catch (Throwable $e) {
        error_log('[webhook] could not forget ' . $eventId . ': ' . $e->getMessage());
    }
}

try {
    // A checkout page was paid, or a payment came in (a QR Ph code scanned, a
    // card or GCash: the payment names its Payment Intent)
    $resource = $event['data']['attributes']['data'] ?? [];
    $booking = null;
    if ($eventType === 'checkout_session.payment.paid') {
        $checkoutId = $resource['id'] ?? null;
        if (is_string($checkoutId) && preg_match('/^cs_[A-Za-z0-9]{1,60}$/', $checkoutId)) {
            $booking = find_booking_by_checkout($checkoutId);
        }
    } elseif ($eventType === 'payment.paid') {
        $intentId = $resource['attributes']['payment_intent_id'] ?? null;
        if (is_string($intentId) && preg_match('/^pi_[A-Za-z0-9]{1,60}$/', $intentId)) {
            $booking = find_booking_by_intent($intentId);
        }
    } else {
        json_response(['ok' => true, 'ignored' => true]);
    }

    if ($booking === null) {
        // Not one of ours (another site on the same PayMongo account)
        json_response(['ok' => true, 'ignored' => true]);
    }

    $result = settle_booking($booking);
    if ($result === 'unpaid') {
        // PayMongo says paid in the event but not yet on the checkout or
        // Payment Intent itself.
        // Answer with an error so PayMongo sends it again a little later.
        webhook_forget($eventId);
        json_response(['ok' => false], 503);
    }

    json_response(['ok' => true]);
} catch (Throwable $e) {
    error_log(sprintf('[webhook] %s (%s): %s: %s', $eventId, $eventType, get_class($e), $e->getMessage()));
    webhook_forget($eventId);
    json_response(['ok' => false], 500);
}
