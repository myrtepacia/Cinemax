<?php
declare(strict_types=1);

// Small tools used on every page: settings, links, escaping, money and dates,
// redirects, one-time messages and reading form input safely.

if (!defined('CINEMAX_BOOTSTRAPPED')) {
    http_response_code(404);
    exit;
}

/**
 * A setting from config/config.php, by dotted name: config('db.host').
 */
function config(string $key, $default = null)
{
    static $settings = null;
    if ($settings === null) {
        $settings = require APP_ROOT . '/config/config.php';
    }
    $value = $settings;
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}

/**
 * The folder the site lives in on the server, like '/cinemax' ('' at the root).
 */
function app_path(): string
{
    $path = (string) parse_url((string) config('app_url'), PHP_URL_PATH);
    return rtrim($path, '/');
}

/**
 * A link to a page or file of the site: url('admin/movies.php') gives
 * '/cinemax/admin/movies.php'. Works the same from any folder.
 */
function url(string $path = ''): string
{
    return app_path() . '/' . ltrim($path, '/');
}

/**
 * The full address, with http://host, for links that leave the site and come
 * back, such as PayMongo's return pages.
 */
function absolute_url(string $path = ''): string
{
    // The address the visitor is really using, so a phone that opened the
    // site at http://192.168.1.5/cinemax comes back there from PayMongo,
    // not to "localhost" (which on a phone is the phone itself). With no
    // visitor (the PayMongo webhook, the command line) it is the configured
    // address.
    if (PHP_SAPI !== 'cli' && !empty($_SERVER['HTTP_HOST']) && function_exists('request_origin')) {
        return request_origin() . app_path() . '/' . ltrim($path, '/');
    }
    return rtrim((string) config('app_url'), '/') . '/' . ltrim($path, '/');
}

/**
 * A CSS, JS or image link with its modified time added, so browsers pick up
 * a changed file straight away instead of using an old cached copy.
 */
function asset(string $path): string
{
    $file = APP_ROOT . '/' . ltrim($path, '/');
    $version = is_file($file) ? (string) filemtime($file) : '1';
    return url($path) . '?v=' . $version;
}

/**
 * Makes text safe to put inside HTML, including inside attribute quotes.
 */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}

/**
 * Sends the browser to another page of the site and stops. Only ever takes
 * a path inside the site, never a full address, so it cannot be turned into
 * an open redirect.
 */
function redirect(string $path, int $status = 303): void
{
    header('Location: ' . url($path), true, $status);
    exit;
}

/**
 * Sends the browser to an outside address (PayMongo's checkout page only).
 */
function redirect_external(string $address): void
{
    $host = (string) parse_url($address, PHP_URL_HOST);
    $scheme = (string) parse_url($address, PHP_URL_SCHEME);
    if ($scheme !== 'https' || !preg_match('/(^|\.)paymongo\.com$/i', $host)) {
        throw new RuntimeException('Refusing to redirect to ' . $address);
    }
    header('Location: ' . $address, true, 303);
    exit;
}

/**
 * A return path from a query string, kept only if it points inside the site.
 * Anything else (another site, '//evil.test', 'javascript:') gives null.
 */
function safe_return_path($path): ?string
{
    if (!is_string($path) || $path === '' || strlen($path) > 300) {
        return null;
    }
    // Only plain page paths with an optional simple query string
    if (!preg_match('~^[a-z0-9][a-z0-9/_-]*\.php(\?[A-Za-z0-9_=&%.-]*)?$~', $path)) {
        return null;
    }
    if (strpos($path, '..') !== false || strpos($path, '//') !== false) {
        return null;
    }
    return $path;
}

/**
 * The page being viewed, relative to the site folder, with its query
 * string: 'book.php?movie=joker'. Used as a sign-in return path.
 */
function current_path(): string
{
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
    $path = (string) parse_url($uri, PHP_URL_PATH);
    $query = (string) parse_url($uri, PHP_URL_QUERY);
    $base = app_path() . '/';
    if ($base !== '/' && strpos($path, $base) === 0) {
        $path = substr($path, strlen($base));
    }
    $path = ltrim($path, '/');
    return $query === '' ? $path : $path . '?' . $query;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

/**
 * Stops anything but a POST request. Every change to data goes through POST
 * with a CSRF token; links and GET requests only ever read.
 */
function require_post(): void
{
    if (!is_post()) {
        header('Allow: POST');
        render_error_page(405, 'That address only accepts form submissions.');
    }
}

/**
 * A text value from a form or query string. Arrays and anything that is not
 * text come back as '', surrounding spaces are trimmed, control characters
 * are removed and the length is capped.
 */
function input_string(array $source, string $key, int $maxLength = 255): string
{
    $value = $source[$key] ?? '';
    if (!is_string($value)) {
        return '';
    }
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
    $value = trim($value);
    if (mb_strlen($value) > $maxLength) {
        $value = mb_substr($value, 0, $maxLength);
    }
    return $value;
}

/**
 * A whole number from a form, or null when it is missing or not a number.
 */
function input_int(array $source, string $key, int $min = PHP_INT_MIN, int $max = PHP_INT_MAX): ?int
{
    $value = $source[$key] ?? null;
    if (is_int($value)) {
        $number = $value;
    } elseif (is_string($value) && preg_match('/^-?\d{1,10}$/', trim($value))) {
        $number = (int) trim($value);
    } else {
        return null;
    }
    return ($number < $min || $number > $max) ? null : $number;
}

/**
 * A 'YYYY-MM-DD' date that really exists, or null.
 */
function input_date(array $source, string $key): ?string
{
    $value = input_string($source, $key, 10);
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m)) {
        return null;
    }
    return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? $value : null;
}

/**
 * The visitor's address, from the connection itself. Forwarding headers are
 * ignored on purpose: anyone can type those.
 */
function client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
}

/**
 * A one-time message shown on the next page, e.g. after a redirect.
 * $type is 'success', 'error' or 'notice'.
 */
function flash(string $type, string $message): void
{
    $_SESSION['flashes'][] = ['type' => $type, 'message' => $message];
}

/**
 * The waiting one-time messages, removed as they are read.
 */
function take_flashes(): array
{
    $flashes = $_SESSION['flashes'] ?? [];
    unset($_SESSION['flashes']);
    return is_array($flashes) ? $flashes : [];
}

/**
 * Pesos as the site shows them: 1140 becomes '₱1,140'.
 */
function peso(int $amount): string
{
    return "\u{20B1}" . number_format($amount);
}

/**
 * Minutes as a running time: 108 becomes '1h 48m'.
 */
function duration_label(int $minutes): string
{
    return intdiv($minutes, 60) . 'h ' . ($minutes % 60) . 'm';
}

/**
 * A showing as tickets print it: '4:00 PM, Saturday, September 12, 2026',
 * led by the cinema when given: 'Cinema 2, 4:00 PM, Saturday, ...'.
 */
function format_showing(string $date, string $time, ?int $cinema = null): string
{
    $when = (new DateTimeImmutable($date . ' ' . $time))->format('g:i A, l, F j, Y');
    return $cinema !== null ? cinema_label($cinema) . ', ' . $when : $when;
}

/**
 * 'Cinema 2'.
 */
function cinema_label(int $cinema): string
{
    return 'Cinema ' . $cinema;
}

/**
 * A clock time alone: '17:00:00' becomes '5:00 PM'.
 */
function format_time(string $time): string
{
    return (new DateTimeImmutable('2000-01-01 ' . $time))->format('g:i A');
}

/**
 * A short date: 'Oct 25, 2026'.
 */
function format_date_short(string $date): string
{
    return (new DateTimeImmutable($date))->format('M j, Y');
}

/**
 * A date and time from the database as people read it:
 * '4:02 PM, Saturday, September 12, 2026'.
 */
function format_datetime(string $datetime): string
{
    return (new DateTimeImmutable($datetime))->format('g:i A, l, F j, Y');
}

function today(): string
{
    return date('Y-m-d');
}

/**
 * Replies to a script's fetch() call with JSON and stops.
 */
function json_response(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    exit;
}

/**
 * A plain error page with the site's look, then stop. Used for 403, 404,
 * 405, 429 and 500, with $title to override the usual heading. The message
 * is shown as text, never as HTML.
 *
 * Only standard status codes are used: Apache turns ones it does not know,
 * such as 419, into 500 Internal Server Error.
 */
function render_error_page(int $status, string $message, ?string $title = null): void
{
    if (!headers_sent()) {
        http_response_code($status);
    }
    $titles = [
        403 => 'Not allowed',
        404 => 'Page not found',
        405 => 'Not allowed',
        429 => 'Too many tries',
        500 => 'Something went wrong',
    ];
    $title = $title ?? ($titles[$status] ?? 'Error');

    // The error may come before the layout code is loaded, or from inside
    // it, so this page is kept self-contained.
    $css = function_exists('asset') ? asset('assets/css/cinemax.css') : '';
    $home = function_exists('url') ? url('index.php') : '/';
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
        . '<title>' . e($title) . '</title>'
        . ($css !== '' ? '<link rel="stylesheet" href="' . e($css) . '">' : '')
        . '</head><body><main class="terms-page"><h1>' . e($title) . '</h1>'
        . '<p class="terms-intro">' . e($message) . '</p>'
        . '<a class="button button-red" href="' . e($home) . '">Back to the home page</a>'
        . '</main></body></html>';
    exit;
}

/**
 * Stops with an error page: abort(404) for a missing page, abort(403) for
 * one this person may not see.
 */
function abort(int $status, string $message = ''): void
{
    $defaults = [
        403 => 'You do not have access to that page.',
        404 => 'We could not find that page.',
        429 => 'Too many tries. Please wait a few minutes and try again.',
    ];
    render_error_page($status, $message !== '' ? $message : ($defaults[$status] ?? 'Something went wrong.'));
}
