<?php
declare(strict_types=1);

// Talking to PayMongo (https://developers.paymongo.com).
//
// The flow:
//   1. checkout.php makes a Checkout Session for the booking and sends the
//      customer to PayMongo's own page to pay (card, GCash, Maya, GrabPay).
//      When QR Ph is one of the ways to pay (config: payment_methods), it
//      makes a QR Ph payment instead (a Payment Intent with a QR Ph code)
//      and shows the code on pay.php, valid exactly as long as the seats are
//      held. PayMongo's own page would always give it 30 minutes. The other
//      ways listed are offered under the code ("Choose another payment",
//      pay-other.php), once PayMongo has turned them on for a live account.
//      A card is paid on Cinemax's own card.php: the card details go from
//      the customer's browser straight to PayMongo with the public key
//      (card.js), never through this server. GCash (and the other e-wallets)
//      goes straight to the wallet's own page to authorize the payment, with
//      no PayMongo checkout page in between.
//   2. PayMongo sends them back to payment-success.php (or pay.php asks every
//      few seconds), which asks PayMongo directly whether it was paid. The
//      browser's word is never taken for it.
//   3. webhook.php hears the same news from PayMongo's servers, so a customer
//      who closes the tab before coming back still gets their ticket.
//
// The secret key only ever leaves this server inside the HTTPS request to
// api.paymongo.com. PayMongo counts money in centavos: ₱220 is 22000.

if (!defined('CINEMAX_BOOTSTRAPPED')) {
    http_response_code(404);
    exit;
}

class PayMongoException extends RuntimeException
{
}

/**
 * PayMongo has no such thing (HTTP 404). For a payment the booking has on
 * record, that means it was made with the other keys (test or live): test
 * and live keys cannot see each other's payments.
 */
class PayMongoNotFoundException extends PayMongoException
{
}

/**
 * One call to the PayMongo API. Returns the decoded reply, or throws a
 * PayMongoException carrying PayMongo's own explanation.
 */
function paymongo_request(string $method, string $path, ?array $body = null, int $timeoutSeconds = 30): array
{
    $secret = (string) config('paymongo.secret_key');
    if ($secret === '') {
        throw new PayMongoException('PayMongo is not set up: add the secret key to config/config.php.');
    }

    $curl = curl_init('https://api.paymongo.com/v1/' . ltrim($path, '/'));
    $headers = [
        'Accept: application/json',
        'Authorization: Basic ' . base64_encode($secret . ':'),
    ];
    $options = [
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => min(10, $timeoutSeconds),
        CURLOPT_TIMEOUT        => $timeoutSeconds,
        // Certificates are always checked: no talking to a fake PayMongo
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_PROTOCOLS      => CURLPROTO_HTTPS,
        CURLOPT_FOLLOWLOCATION => false,
    ];
    if ($body !== null) {
        $headers[] = 'Content-Type: application/json';
        $options[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
    $options[CURLOPT_HTTPHEADER] = $headers;
    curl_setopt_array($curl, $options);

    $raw = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $error = curl_error($curl);
    curl_close($curl);

    if ($raw === false) {
        throw new PayMongoException('Could not reach PayMongo: ' . $error);
    }
    $reply = json_decode((string) $raw, true);
    if (!is_array($reply)) {
        throw new PayMongoException('PayMongo sent back something unreadable (HTTP ' . $status . ').');
    }
    if ($status >= 400) {
        $details = [];
        foreach (($reply['errors'] ?? []) as $err) {
            $details[] = trim(($err['code'] ?? '') . ': ' . ($err['detail'] ?? ''));
        }
        $message = 'PayMongo refused the request (HTTP ' . $status . '): ' . implode('; ', $details);
        if ($status === 404) {
            throw new PayMongoNotFoundException($message);
        }
        throw new PayMongoException($message);
    }
    return $reply;
}

/**
 * The customer's details as PayMongo's billing (empty ones left out).
 */
function paymongo_billing(array $customer): array
{
    return array_filter([
        'name'  => mb_substr((string) $customer['name'], 0, 100),
        'email' => (string) $customer['email'],
        'phone' => (string) ($customer['mobile'] ?? ''),
    ], static function ($value) {
        return $value !== '';
    });
}

/**
 * Starts PayMongo's checkout for a booking. $lineItems are
 * ['name' => ..., 'amount' => pesos each, 'quantity' => n]. Returns
 * ['id' => 'cs_...', 'checkout_url' => 'https://checkout.paymongo.com/...'].
 */
function paymongo_create_checkout(array $booking, array $lineItems, array $customer): array
{
    $items = [];
    foreach ($lineItems as $item) {
        $items[] = [
            'currency' => 'PHP',
            'amount'   => (int) $item['amount'] * 100,
            'name'     => mb_substr((string) $item['name'], 0, 255),
            'quantity' => (int) $item['quantity'],
        ];
    }

    $reference = (string) $booking['reference'];
    $attributes = [
        'billing'              => paymongo_billing($customer),
        'description'          => 'Cinemax booking ' . $reference . '. Your seats are held for '
            . max(5, min(60, (int) config('booking_hold_minutes', 10))) . ' minutes: pay before then to keep them.',
        'line_items'           => $items,
        'payment_method_types' => array_values((array) config('paymongo.payment_methods', ['card', 'gcash'])),
        'reference_number'     => $reference,
        'send_email_receipt'   => false,
        'show_description'     => true,
        'show_line_items'      => true,
        'success_url'          => absolute_url('payment-success.php?ref=' . rawurlencode($reference)),
        'cancel_url'           => absolute_url('payment-cancel.php?ref=' . rawurlencode($reference)),
        'metadata'             => [
            'booking_reference' => $reference,
            'booking_id'        => (string) $booking['id'],
        ],
    ];

    $reply = paymongo_request('POST', 'checkout_sessions', ['data' => ['attributes' => $attributes]]);
    $id = (string) ($reply['data']['id'] ?? '');
    $url = (string) ($reply['data']['attributes']['checkout_url'] ?? '');
    if ($id === '' || $url === '') {
        throw new PayMongoException('PayMongo did not return a checkout page.');
    }
    return ['id' => $id, 'checkout_url' => $url];
}

/**
 * True when customers pay by QR Ph (config: 'qrph' in payment_methods). The
 * code is then shown on Cinemax's own page, pay.php.
 */
function paymongo_uses_qrph(): bool
{
    return in_array('qrph', (array) config('paymongo.payment_methods', []), true);
}

/**
 * True with live keys (real money), false with test keys.
 */
function paymongo_is_live(): bool
{
    return strpos((string) config('paymongo.secret_key'), 'sk_live_') === 0;
}

/**
 * A short fingerprint of the keys in use, kept beside anything that only
 * works with them (a QR Ph code on screen, the ways to pay PayMongo has
 * turned on), so switching keys (test and live) makes it again. It never
 * leaves the server.
 */
function paymongo_keys_id(): string
{
    return substr(hash('sha256', (string) config('paymongo.secret_key')), 0, 16);
}

/**
 * The ways to pay PayMongo has turned on for this live account, e.g.
 * ['qrph']. (Test keys get the live account's answer too, yet test checkout
 * pages take every way to pay, so it only matters with live keys.) They
 * change only when PayMongo activates one, so the answer is kept for an hour
 * (in storage/cache). If PayMongo cannot be asked, the answer is none for
 * five minutes: QR Ph still works, and nothing else is.
 */
function paymongo_enabled_methods(): array
{
    $file = APP_ROOT . '/storage/cache/payment-methods.json';
    // Saved for these keys only: switching test and live keys asks again
    $keys = paymongo_keys_id();

    $saved = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
    if (is_array($saved) && ($saved['keys'] ?? '') === $keys && (int) ($saved['until'] ?? 0) > time()) {
        return array_values(array_filter((array) ($saved['methods'] ?? []), 'is_string'));
    }

    try {
        $methods = array_values(array_filter(paymongo_request('GET', 'merchants/capabilities/payment_methods', null, 5), 'is_string'));
        $until = time() + 3600;
    } catch (PayMongoException $e) {
        error_log('[paymongo] could not ask which ways to pay are on: ' . $e->getMessage());
        $methods = [];
        $until = time() + 300;
    }

    if (!is_dir(dirname($file))) {
        mkdir(dirname($file), 0775, true);
    }
    file_put_contents($file, json_encode(['keys' => $keys, 'until' => $until, 'methods' => $methods]), LOCK_EX);
    return $methods;
}

// The e-wallets: paid on the wallet's own page (pay-other.php)
const PAYMONGO_WALLETS = ['gcash', 'paymaya', 'grab_pay'];

/**
 * The other ways to pay offered under the QR Ph code on pay.php, as
 * method => name: the ones listed in the config, card first and the
 * e-wallets below it. They are shown whether or not PayMongo has turned them
 * on; pay-other.php checks that when one is chosen.
 */
function paymongo_other_methods(): array
{
    $names = ['card' => 'Card', 'gcash' => 'GCash', 'paymaya' => 'Maya', 'grab_pay' => 'GrabPay'];
    $listed = (array) config('paymongo.payment_methods', []);
    $other = [];
    foreach ($names as $method => $name) {
        if (in_array($method, $listed, true)) {
            $other[$method] = $name;
        }
    }
    return $other;
}

/**
 * Starts a payment for a booking: a Payment Intent for its total that takes
 * only $methods (['qrph'], ['card'], the e-wallets). Something to pay with is
 * then attached to it; one that fails can be followed by another on the same
 * intent. Returns ['id' => 'pi_...', 'client_key' => ...].
 */
function paymongo_create_intent(array $booking, array $methods): array
{
    $reference = (string) $booking['reference'];
    $reply = paymongo_request('POST', 'payment_intents', ['data' => ['attributes' => [
        'amount'                 => (int) $booking['total'] * 100,
        'currency'               => 'PHP',
        'payment_method_allowed' => array_values($methods),
        'description'            => 'Cinemax booking ' . $reference,
        'metadata'               => [
            'booking_reference' => $reference,
            'booking_id'        => (string) $booking['id'],
        ],
    ]]]);
    $intentId = (string) ($reply['data']['id'] ?? '');
    $clientKey = (string) ($reply['data']['attributes']['client_key'] ?? '');
    if (!preg_match('/^pi_[A-Za-z0-9]+$/', $intentId) || $clientKey === '') {
        throw new PayMongoException('PayMongo did not start the payment.');
    }
    return ['id' => $intentId, 'client_key' => $clientKey];
}

/**
 * Starts a QR Ph payment for a booking: a Payment Intent for its total, with
 * a QR Ph code attached that stops working after $expirySeconds. Returns
 * ['id' => 'pi_...', 'qr' => the code as a data: picture].
 */
function paymongo_create_qrph(array $booking, array $customer, int $expirySeconds): array
{
    $intent = paymongo_create_intent($booking, ['qrph']);
    return ['id' => $intent['id'], 'qr' => paymongo_attach_qrph($intent['id'], $intent['client_key'], $customer, $expirySeconds)];
}

/**
 * Starts paying by e-wallet ($wallet: 'gcash', 'paymaya' or 'grab_pay') on a
 * Payment Intent that takes it. Returns the address of the wallet's own page,
 * where the customer authorizes the payment; the wallet then sends them to
 * $returnPath. Done again, it makes a fresh page on the same intent.
 */
function paymongo_start_wallet(string $intentId, string $clientKey, string $wallet, array $customer, string $returnPath): string
{
    $method = paymongo_request('POST', 'payment_methods', ['data' => ['attributes' => [
        'type'    => $wallet,
        'billing' => paymongo_billing($customer),
    ]]]);
    $methodId = (string) ($method['data']['id'] ?? '');
    if ($methodId === '') {
        throw new PayMongoException('PayMongo did not accept the ' . $wallet . ' payment.');
    }

    $attached = paymongo_request('POST', 'payment_intents/' . $intentId . '/attach', ['data' => ['attributes' => [
        'payment_method' => $methodId,
        'client_key'     => $clientKey,
        'return_url'     => absolute_url($returnPath),
    ]]]);
    $url = (string) ($attached['data']['attributes']['next_action']['redirect']['url'] ?? '');
    if (strpos($url, 'https://') !== 0) {
        throw new PayMongoException('PayMongo did not return the ' . $wallet . ' page.');
    }
    return $url;
}

/**
 * Makes a QR Ph code that stops working after $expirySeconds (PayMongo
 * allows 60 to 9000) and attaches it to a Payment Intent. Returns the code
 * as a data: picture.
 */
function paymongo_attach_qrph(string $intentId, string $clientKey, array $customer, int $expirySeconds): string
{
    $method = paymongo_request('POST', 'payment_methods', ['data' => ['attributes' => [
        'type'           => 'qrph',
        'expiry_seconds' => max(60, min(9000, $expirySeconds)),
        'billing'        => paymongo_billing($customer),
    ]]]);
    $methodId = (string) ($method['data']['id'] ?? '');
    if ($methodId === '') {
        throw new PayMongoException('PayMongo did not make the QR Ph code.');
    }

    $attached = paymongo_request('POST', 'payment_intents/' . $intentId . '/attach', ['data' => ['attributes' => [
        'payment_method' => $methodId,
        'client_key'     => $clientKey,
    ]]]);
    $qr = paymongo_qr_image((array) ($attached['data'] ?? []));
    if ($qr === null) {
        throw new PayMongoException('PayMongo did not return the QR Ph code.');
    }
    return $qr;
}

/**
 * Why the last card tried on a Payment Intent did not go through (declined,
 * or the bank's check failed), in PayMongo's words, or null.
 */
function paymongo_card_error(array $intent): ?string
{
    $error = $intent['attributes']['last_payment_error'] ?? null;
    if (!is_array($error)) {
        return null;
    }
    $message = trim((string) ($error['failed_message'] ?? $error['detail'] ?? ''));
    return $message !== '' ? $message : 'Your card was not charged.';
}

/**
 * A Payment Intent as PayMongo has it right now.
 */
function paymongo_get_intent(string $intentId, int $timeoutSeconds = 30): array
{
    if (!preg_match('/^pi_[A-Za-z0-9]+$/', $intentId)) {
        throw new PayMongoException('Not a payment intent id.');
    }
    $reply = paymongo_request('GET', 'payment_intents/' . $intentId, null, $timeoutSeconds);
    return (array) ($reply['data'] ?? []);
}

/**
 * The QR Ph code on a Payment Intent waiting to be scanned, as a data:
 * picture (the page's Content Security Policy allows those), or null.
 */
function paymongo_qr_image(array $intent): ?string
{
    $image = trim((string) ($intent['attributes']['next_action']['code']['image_url'] ?? ''));
    if (preg_match('~^data:image/(png|jpeg|gif|svg\+xml);base64,[A-Za-z0-9+/=]+$~', $image)) {
        return $image;
    }
    $bare = (string) preg_replace('/\s+/', '', $image);
    if ($bare !== '' && preg_match('~^[A-Za-z0-9+/=]+$~', $bare)) {
        return 'data:image/png;base64,' . $bare;
    }
    if ($image !== '') {
        error_log('[paymongo] QR Ph code in an unexpected form: ' . substr($image, 0, 60));
    }
    return null;
}

/**
 * A checkout session as PayMongo has it right now.
 */
function paymongo_get_checkout(string $checkoutId, int $timeoutSeconds = 30): array
{
    if (!preg_match('/^cs_[A-Za-z0-9]+$/', $checkoutId)) {
        throw new PayMongoException('Not a checkout session id.');
    }
    $reply = paymongo_request('GET', 'checkout_sessions/' . $checkoutId, null, $timeoutSeconds);
    return (array) ($reply['data'] ?? []);
}

/**
 * Closes a checkout session so it can no longer be paid. Quietly does
 * nothing if PayMongo has already closed it.
 */
function paymongo_expire_checkout(string $checkoutId, int $timeoutSeconds = 30): void
{
    if (!preg_match('/^cs_[A-Za-z0-9]+$/', $checkoutId)) {
        return;
    }
    try {
        paymongo_request('POST', 'checkout_sessions/' . $checkoutId . '/expire', null, $timeoutSeconds);
    } catch (PayMongoException $e) {
        // Already closed is what was wanted, so only other problems are logged
        if (stripos($e->getMessage(), 'already expired') === false) {
            error_log('[paymongo] could not expire ' . $checkoutId . ': ' . $e->getMessage());
        }
    }
}

/**
 * The paid payment inside a checkout session or a Payment Intent (both list
 * their payments the same way), as ['id' => 'pay_...', 'amount' => pesos],
 * or null if nothing is paid yet.
 */
function paymongo_paid_payment(array $checkout): ?array
{
    foreach (($checkout['attributes']['payments'] ?? []) as $payment) {
        $status = $payment['attributes']['status'] ?? '';
        $id = (string) ($payment['id'] ?? '');
        if ($status === 'paid' && $id !== '') {
            return [
                'id'     => $id,
                'amount' => intdiv((int) ($payment['attributes']['amount'] ?? 0), 100),
            ];
        }
    }
    return null;
}

/**
 * Sends money back for a payment. Returns PayMongo's refund id ('ref_...').
 */
function paymongo_refund(string $paymentId, int $amountPesos, string $reason, string $notes = ''): string
{
    if (!preg_match('/^pay_[A-Za-z0-9]+$/', $paymentId)) {
        throw new PayMongoException('Not a payment id.');
    }
    $allowed = ['duplicate', 'fraudulent', 'requested_by_customer', 'others'];
    $attributes = [
        'amount'     => $amountPesos * 100,
        'payment_id' => $paymentId,
        'reason'     => in_array($reason, $allowed, true) ? $reason : 'others',
    ];
    if ($notes !== '') {
        $attributes['notes'] = mb_substr($notes, 0, 255);
    }
    $reply = paymongo_request('POST', 'refunds', ['data' => ['attributes' => $attributes]]);
    $id = (string) ($reply['data']['id'] ?? '');
    if ($id === '') {
        throw new PayMongoException('PayMongo did not confirm the refund.');
    }
    return $id;
}

/**
 * Checks a webhook really came from PayMongo. The Paymongo-Signature header
 * is 't=<time>,te=<test sig>,li=<live sig>'; the signature is an HMAC-SHA256
 * of '<time>.<raw body>' made with the webhook's secret. Old deliveries (over
 * five minutes) are refused so a captured one cannot be replayed later.
 */
function paymongo_verify_webhook(string $payload, string $header): bool
{
    $secret = (string) config('paymongo.webhook_secret');
    if ($secret === '' || $header === '') {
        return false;
    }
    $parts = [];
    foreach (explode(',', $header) as $piece) {
        $pair = explode('=', trim($piece), 2);
        if (count($pair) === 2) {
            $parts[$pair[0]] = $pair[1];
        }
    }
    $time = $parts['t'] ?? '';
    if (!ctype_digit($time) || abs(time() - (int) $time) > 300) {
        return false;
    }
    $given = $parts[paymongo_is_live() ? 'li' : 'te'] ?? '';
    if ($given === '') {
        return false;
    }
    $expected = hash_hmac('sha256', $time . '.' . $payload, $secret);
    return hash_equals($expected, $given);
}
