<?php
declare(strict_types=1);

if (!defined('CINEMAX_BOOTSTRAPPED')) {
    http_response_code(404);
    exit;
}

const SESSION_IDLE_SECONDS = 7200;
const SESSION_ROTATE_SECONDS = 900;

function send_security_headers(): void
{
    if (headers_sent()) {
        return;
    }
    header_remove('X-Powered-By');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Cross-Origin-Opener-Policy: same-origin');
    header('Permissions-Policy: camera=(self), microphone=(), geolocation=(), payment=()');
    header('Cache-Control: no-store, max-age=0');
    $connect = defined('CINEMAX_CARD_FORM') ? "'self' https://api.paymongo.com" : "'self'";
    header("Content-Security-Policy: default-src 'self'; "
        . "script-src 'self'; "
        . "style-src 'self' https://fonts.googleapis.com; "
        . "font-src 'self' https://fonts.gstatic.com; "
        . "img-src 'self' data: blob:; "
        . "connect-src " . $connect . "; "
        . "media-src 'self' blob:; "
        . "object-src 'none'; "
        . "base-uri 'self'; "
        . "form-action 'self' https://checkout.paymongo.com https://*.paymongo.com; "
        . "frame-ancestors 'none'");
    $host = strtolower((string) parse_url(request_origin(), PHP_URL_HOST));
    $isDomain = $host !== 'localhost' && filter_var(trim($host, '[]'), FILTER_VALIDATE_IP) === false;
    if (is_https() && $isDomain) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.sid_length', '48');
    ini_set('session.sid_bits_per_character', '6');
    ini_set('session.gc_maxlifetime', (string) SESSION_IDLE_SECONDS);

    session_name('CINEMAXSESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => app_path() === '' ? '/' : app_path() . '/',
        'secure'   => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();

    $now = time();
    if (isset($_SESSION['last_seen']) && $now - (int) $_SESSION['last_seen'] > SESSION_IDLE_SECONDS) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
    $_SESSION['last_seen'] = $now;

    if (!isset($_SESSION['rotated_at'])) {
        $_SESSION['rotated_at'] = $now;
    } elseif ($now - (int) $_SESSION['rotated_at'] > SESSION_ROTATE_SECONDS) {
        session_regenerate_id(true);
        $_SESSION['rotated_at'] = $now;
    }
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(bool $json = false): void
{
    $sent = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    $expected = $_SESSION['csrf_token'] ?? '';
    $tokenOk = is_string($sent) && is_string($expected) && $expected !== '' && hash_equals($expected, $sent);

    if (!$tokenOk || !same_origin_request()) {
        if ($json) {
            json_response(['ok' => false, 'error' => 'This page has expired. Refresh it and try again.'], 403);
        }
        render_error_page(403, 'This page was open too long. Go back, refresh and try again.', 'Page expired');
    }
}

function same_origin_request(): bool
{
    $allowed = [strtolower(site_origin()), strtolower(request_origin())];

    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if (is_string($origin) && $origin !== '' && $origin !== 'null') {
        return in_array(strtolower(rtrim($origin, '/')), $allowed, true);
    }
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    if (is_string($referer) && $referer !== '') {
        $parts = parse_url($referer);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return false;
        }
        $refOrigin = strtolower($parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : ''));
        return in_array($refOrigin, $allowed, true);
    }
    return true;
}

function request_origin(): string
{
    $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
    if ($host === '' || !preg_match('/^[A-Za-z0-9.\-]+(:\d{1,5})?$|^\[[0-9a-fA-F:]+\](:\d{1,5})?$/', $host)) {
        return site_origin();
    }
    return (is_https() ? 'https' : 'http') . '://' . $host;
}

function site_origin(): string
{
    $parts = parse_url((string) config('app_url'));
    $origin = ($parts['scheme'] ?? 'http') . '://' . ($parts['host'] ?? 'localhost');
    if (isset($parts['port'])) {
        $origin .= ':' . $parts['port'];
    }
    return $origin;
}

function record_attempt(string $bucket, string $identifier = ''): void
{
    db_exec(
        'INSERT INTO login_attempts (bucket, identifier, ip_address) VALUES (?, ?, ?)',
        [$bucket, mb_strtolower(mb_substr($identifier, 0, 190)), client_ip()]
    );
    if (random_int(1, 50) === 1) {
        db_exec('DELETE FROM login_attempts WHERE attempted_at < NOW() - INTERVAL 1 DAY');
    }
}

function too_many_attempts(string $bucket, string $identifier, int $max, int $windowSeconds, ?int $maxPerIp = null): bool
{
    if ($identifier !== '') {
        $count = (int) db_value(
            'SELECT COUNT(*) FROM login_attempts
             WHERE bucket = ? AND identifier = ? AND attempted_at > NOW() - INTERVAL ? SECOND',
            [$bucket, mb_strtolower($identifier), $windowSeconds]
        );
        if ($count >= $max) {
            return true;
        }
    }
    $ipLimit = $maxPerIp ?? ($identifier === '' ? $max : null);
    if ($ipLimit !== null) {
        $count = (int) db_value(
            'SELECT COUNT(*) FROM login_attempts
             WHERE bucket = ? AND ip_address = ? AND attempted_at > NOW() - INTERVAL ? SECOND',
            [$bucket, client_ip(), $windowSeconds]
        );
        if ($count >= $ipLimit) {
            return true;
        }
    }
    return false;
}

function clear_attempts(string $bucket, string $identifier): void
{
    db_exec('DELETE FROM login_attempts WHERE bucket = ? AND identifier = ?', [$bucket, mb_strtolower($identifier)]);
}

const SCAN_MAX_BODY_BYTES = 4096;
const SCAN_MAX_CODE_LENGTH = 300;
const SCAN_MAX_PER_MINUTE = 120;

function read_scan_request(string $bucket, array $actions): array
{
    if (!is_post()) {
        header('Allow: POST');
        json_response(['ok' => false, 'error' => 'Only POST requests are accepted here.'], 405);
    }

    $user = current_user();
    if (!is_staff($user)) {
        json_response(['ok' => false, 'error' => 'Please sign in again.'], 401);
    }

    verify_csrf(true);

    record_attempt($bucket);
    if (too_many_attempts($bucket, '', SCAN_MAX_PER_MINUTE, 60)) {
        json_response(['ok' => false, 'error' => 'Too many scans in a short time. Wait a minute, then try again.'], 429);
    }

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
    if (!is_string($action) || !in_array($action, $actions, true)
        || !is_string($code) || $code === '' || strlen($code) > SCAN_MAX_CODE_LENGTH) {
        json_response(['ok' => false, 'error' => 'That request was not understood.'], 400);
    }
    return [$user, $action, $code];
}
