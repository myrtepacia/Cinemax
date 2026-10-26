<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';

// Sign-up email check (signup-otp.js): {"action": "send", "email": ...}
// emails a 6-digit code; {"action": "verify", "email": ..., "code": ...}
// checks it. Only a fingerprint of the code is kept, in the session.

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    json_response(['ok' => false, 'error' => 'Only POST requests are accepted here.'], 405);
}
verify_csrf(true);
if (current_user() !== null) {
    json_response(['ok' => false, 'error' => 'You are already signed in.'], 400);
}

$body = json_decode((string) file_get_contents('php://input', false, null, 0, 2048), true);
$action = is_array($body) && is_string($body['action'] ?? null) ? $body['action'] : '';
$email = is_array($body) && is_string($body['email'] ?? null) ? mb_strtolower(trim($body['email'])) : '';

if ($email === '' || strlen($email) > 190 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    json_response(['ok' => false, 'error' => 'Enter a valid email address, like juandelacruz@gmail.com.'], 422);
}

/** The code's fingerprint for this email (the code itself is never kept). */
function signup_code_hash(string $email, string $code): string
{
    return hash_hmac('sha256', 'signup|' . $email . '|' . $code, app_secret());
}

if ($action === 'send') {
    if (db_value('SELECT 1 FROM users WHERE email = ?', [$email]) !== null) {
        json_response(['ok' => false, 'error' => 'This email is already registered. Sign in instead.'], 409);
    }

    $last = $_SESSION['signup_otp'] ?? null;
    if (is_array($last) && ($last['email'] ?? '') === $email) {
        $wait = (int) $last['sent_at'] + SIGNUP_CODE_RESEND_SECONDS - time();
        if ($wait > 0) {
            json_response(['ok' => false, 'error' => "Please wait $wait seconds before asking for a new code.", 'resendIn' => $wait], 429);
        }
    }

    $code = sprintf('%06d', random_int(0, 999999));
    try {
        send_signup_code_email($email, $code);
    } catch (Throwable $e) {
        error_log('[signup-otp] ' . $email . ': ' . $e->getMessage());
        json_response(['ok' => false, 'error' => 'We could not send the code just now. Please try again in a moment.'], 502);
    }

    unset($_SESSION['signup_verified_email']);
    $_SESSION['signup_otp'] = [
        'email'   => $email,
        'hash'    => signup_code_hash($email, $code),
        'expires' => time() + SIGNUP_CODE_SECONDS,
        'tries'   => 0,
        'sent_at' => time(),
    ];
    json_response(['ok' => true, 'email' => $email, 'resendIn' => SIGNUP_CODE_RESEND_SECONDS]);
}

if ($action === 'verify') {
    $code = is_string($body['code'] ?? null) ? $body['code'] : '';
    $pending = $_SESSION['signup_otp'] ?? null;

    if (!is_array($pending) || ($pending['email'] ?? '') !== $email) {
        json_response(['ok' => false, 'error' => 'Press Verify to get a code for this email.'], 409);
    }
    if ((int) $pending['expires'] < time()) {
        unset($_SESSION['signup_otp']);
        json_response(['ok' => false, 'error' => 'This code has expired. Ask for a new one.', 'expired' => true], 410);
    }
    if (preg_match('/^\d{6}$/', $code) && hash_equals((string) $pending['hash'], signup_code_hash($email, $code))) {
        unset($_SESSION['signup_otp']);
        $_SESSION['signup_verified_email'] = $email;
        json_response(['ok' => true, 'email' => $email]);
    }

    $tries = (int) $pending['tries'] + 1;
    if ($tries >= SIGNUP_CODE_TRIES) {
        unset($_SESSION['signup_otp']);
        json_response(['ok' => false, 'error' => 'Too many wrong codes. Ask for a new one.', 'expired' => true], 429);
    }
    $_SESSION['signup_otp']['tries'] = $tries;
    $left = SIGNUP_CODE_TRIES - $tries;
    json_response(['ok' => false, 'error' => 'That code is not right. ' . $left . ($left === 1 ? ' try' : ' tries') . ' left.'], 422);
}

json_response(['ok' => false, 'error' => 'Unknown action.'], 400);
