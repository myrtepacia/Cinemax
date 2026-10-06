<?php
declare(strict_types=1);

// PayMongo (https://developers.paymongo.com). QR Ph is shown on pay.php,
// cards are paid on card.php (card details go straight to PayMongo), and
// Maya opens Maya's own page. A payment is only trusted after asking
// PayMongo directly; webhook.php hears it too. PayMongo counts in centavos:
// 220 pesos is 22000.

if (!defined('CINEMAX_BOOTSTRAPPED')) {
    http_response_code(404);
    exit;
}

class PayMongoException extends RuntimeException
{
}

/**
 * PayMongo has no such payment (HTTP 404): it was made with the other keys
 * (test or live).
 */
class PayMongoNotFoundException extends PayMongoException
{
}

/** One PayMongo API call. Returns the reply or throws PayMongoException. */
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

/** The customer's billing details, empty ones left out. */
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

/** Starts PayMongo's checkout page. Returns ['id', 'checkout_url']. */
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
        'payment_method_types' => array_values((array) config('paymongo.payment_methods', ['card'])),
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

/** True when 'qrph' is a way to pay (the code is shown on pay.php). */
function paymongo_uses_qrph(): bool
{
    return in_array('qrph', (array) config('paymongo.payment_methods', []), true);
}

/** True with live keys (real money). */
function paymongo_is_live(): bool
{
    return strpos((string) config('paymongo.secret_key'), 'sk_live_') === 0;
}

/** A short id of the keys in use, so a switch (test/live) can be noticed. */
function paymongo_keys_id(): string
{
    return substr(hash('sha256', (string) config('paymongo.secret_key')), 0, 16);
}

/**
 * The ways to pay PayMongo has turned on for this account, kept for an hour.
 * Empty for five minutes if PayMongo cannot be asked.
 */
function paymongo_enabled_methods(): array
{
    $file = APP_ROOT . '/storage/cache/payment-methods.json';
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

// The e-wallets, paid on the wallet's own page
const PAYMONGO_WALLETS = ['paymaya'];

/** The other ways to pay under the QR code (method => name), card first. */
function paymongo_other_methods(): array
{
    $names = ['card' => 'Card', 'paymaya' => 'Maya'];
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
 * Starts a payment (Payment Intent) for a booking. Returns ['id',
 * 'client_key'].
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
 * Starts a QR Ph payment whose code ends after $expirySeconds. Returns ['id',
 * 'qr'].
 */
function paymongo_create_qrph(array $booking, array $customer, int $expirySeconds): array
{
    $intent = paymongo_create_intent($booking, ['qrph']);
    return ['id' => $intent['id'], 'qr' => paymongo_attach_qrph($intent['id'], $intent['client_key'], $customer, $expirySeconds)];
}

/**
 * Starts an e-wallet payment (Maya). Returns the wallet's page to send the
 * customer to.
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
 * Makes a QR Ph code (60 to 9000 seconds) and attaches it. Returns the
 * picture.
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

/** Why the last card did not go through, or null. */
function paymongo_card_error(array $intent): ?string
{
    $error = $intent['attributes']['last_payment_error'] ?? null;
    if (!is_array($error)) {
        return null;
    }
    $message = trim((string) ($error['failed_message'] ?? $error['detail'] ?? ''));
    return $message !== '' ? $message : 'Your card was not charged.';
}

/** A Payment Intent as PayMongo has it now. */
function paymongo_get_intent(string $intentId, int $timeoutSeconds = 30): array
{
    if (!preg_match('/^pi_[A-Za-z0-9]+$/', $intentId)) {
        throw new PayMongoException('Not a payment intent id.');
    }
    $reply = paymongo_request('GET', 'payment_intents/' . $intentId, null, $timeoutSeconds);
    return (array) ($reply['data'] ?? []);
}

/** The QR Ph code as a data: picture, or null. */
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

/** A checkout session as PayMongo has it now. */
function paymongo_get_checkout(string $checkoutId, int $timeoutSeconds = 30): array
{
    if (!preg_match('/^cs_[A-Za-z0-9]+$/', $checkoutId)) {
        throw new PayMongoException('Not a checkout session id.');
    }
    $reply = paymongo_request('GET', 'checkout_sessions/' . $checkoutId, null, $timeoutSeconds);
    return (array) ($reply['data'] ?? []);
}

/** Closes a checkout session; does nothing if already closed. */
function paymongo_expire_checkout(string $checkoutId, int $timeoutSeconds = 30): void
{
    if (!preg_match('/^cs_[A-Za-z0-9]+$/', $checkoutId)) {
        return;
    }
    try {
        paymongo_request('POST', 'checkout_sessions/' . $checkoutId . '/expire', null, $timeoutSeconds);
    } catch (PayMongoException $e) {
        if (stripos($e->getMessage(), 'already expired') === false) {
            error_log('[paymongo] could not expire ' . $checkoutId . ': ' . $e->getMessage());
        }
    }
}

/** The paid payment ['id', 'amount' in pesos], or null. */
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

/** Refunds a payment. Returns the refund id. */
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
 * Checks a webhook really came from PayMongo (signed, and under five minutes
 * old).
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
