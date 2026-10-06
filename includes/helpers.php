<?php
declare(strict_types=1);

// Small tools used everywhere: settings, links, escaping, money, dates,
// redirects and safe form input.

if (!defined('CINEMAX_BOOTSTRAPPED')) {
    http_response_code(404);
    exit;
}

/** A setting from config/config.php: config('db.host'). */
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

/** The site's folder on the server, like '/cinemax'. */
function app_path(): string
{
    $path = (string) parse_url((string) config('app_url'), PHP_URL_PATH);
    return rtrim($path, '/');
}

/**
 * A page path as visitors see it: no '.php', a film's page is just its name
 * and a booking's pages end in its reference. 'book.php?movie=hush' becomes
 * 'hush', 'pay.php?ref=CMX-ABC234' 'pay/CMX-ABC234', 'terms.php' 'terms',
 * 'index.php' '' and 'admin/index.php' 'admin/'. The .htaccess finds the
 * .php file again.
 */
function clean_path(string $path): string
{
    if (preg_match('~^book(?:\.php)?\?movie=([a-z0-9-]+)(?:&(.*))?$~', $path, $m)) {
        return $m[1] . (isset($m[2]) && $m[2] !== '' ? '?' . $m[2] : '');
    }
    if (preg_match('~^(pay|card|ticket|payment-success|payment-cancel)(?:\.php)?\?ref=([A-Za-z0-9-]+)(?:&(.*))?$~', $path, $m)) {
        return $m[1] . '/' . $m[2] . (isset($m[3]) && $m[3] !== '' ? '?' . $m[3] : '');
    }

    $query = '';
    $mark = strpos($path, '?');
    if ($mark !== false) {
        $query = substr($path, $mark);
        $path = substr($path, 0, $mark);
    }
    if (preg_match('~(^|/)index\.php$~', $path)) {
        $path = substr($path, 0, -strlen('index.php'));
    } elseif (substr($path, -4) === '.php') {
        $path = substr($path, 0, -4);
    }
    return $path . $query;
}

/** A link inside the site: url('admin/movies.php') gives '/admin/movies'. */
function url(string $path = ''): string
{
    return app_path() . '/' . ltrim(clean_path($path), '/');
}

/**
 * A full address with the host, for links that leave the site and come back.
 */
function absolute_url(string $path = ''): string
{
    // Use the address the visitor used (a phone on 192.168.x.x comes back
    // there, not to localhost); the config's when there is no visitor
    if (PHP_SAPI !== 'cli' && !empty($_SERVER['HTTP_HOST']) && function_exists('request_origin')) {
        return request_origin() . url($path);
    }
    return rtrim((string) config('app_url'), '/') . '/' . ltrim(clean_path($path), '/');
}

/** A file link with its modified time, so browsers load changed files. */
function asset(string $path): string
{
    $file = APP_ROOT . '/' . ltrim($path, '/');
    $version = is_file($file) ? (string) filemtime($file) : '1';
    return url($path) . '?v=' . $version;
}

/** Makes text safe for HTML. */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}

/** Redirects to a page of the site and stops. Only paths inside the site. */
function redirect(string $path, int $status = 303): void
{
    header('Location: ' . url($path), true, $status);
    exit;
}

/** Redirects to PayMongo's own pages only (checkout, Maya). */
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

/** A return path from the query string, only if it stays inside the site. */
function safe_return_path($path): ?string
{
    if (!is_string($path) || $path === '' || strlen($path) > 300) {
        return null;
    }
    // 'hush', 'pay/CMX-ABC234', 'admin/' or the older 'book.php?movie=x'
    if (!preg_match('~^[a-z0-9][A-Za-z0-9/_-]*(\.php)?(\?[A-Za-z0-9_=&%.-]*)?$~', $path)) {
        return null;
    }
    if (strpos($path, '..') !== false || strpos($path, '//') !== false) {
        return null;
    }
    return clean_path($path);
}

/** The page being viewed with its query string, as a sign-in return path. */
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

/** Stops anything that is not a POST. */
function require_post(): void
{
    if (!is_post()) {
        header('Allow: POST');
        render_error_page(405, 'That address only accepts form submissions.');
    }
}

/** A trimmed, length-capped text value from a form, or ''. */
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

/** A whole number from a form, or null. */
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

/** A real 'YYYY-MM-DD' date, or null. */
function input_date(array $source, string $key): ?string
{
    $value = input_string($source, $key, 10);
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m)) {
        return null;
    }
    return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? $value : null;
}

/**
 * The visitor's address. Forwarding headers are ignored (anyone can fake
 * them).
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

/** Pesos as shown: 1140 becomes '₱1,140'. */
function peso(int $amount): string
{
    return "\u{20B1}" . number_format($amount);
}

/** A number with its word: '1 snack', '3 snacks'. */
function count_label(int $count, string $one, string $many): string
{
    return number_format($count) . ' ' . ($count === 1 ? $one : $many);
}

/** A running time: 112 becomes '1h 52min'. */
function duration_tag(int $minutes): string
{
    $hours = intdiv($minutes, 60);
    $rest = $minutes % 60;
    $parts = [];
    if ($hours > 0) {
        $parts[] = $hours . 'h';
    }
    if ($rest > 0 || $hours === 0) {
        $parts[] = $rest . 'min';
    }
    return implode(' ', $parts);
}

/**
 * A showing as tickets print it: 'Cinema 2, 4:00 PM, Saturday, September 12,
 * 2026'.
 */
function format_showing(string $date, string $time, ?int $cinema = null): string
{
    $when = (new DateTimeImmutable($date . ' ' . $time))->format('g:i A, l, F j, Y');
    return $cinema !== null ? cinema_label($cinema) . ', ' . $when : $when;
}

function cinema_label(int $cinema): string
{
    return 'Cinema ' . $cinema;
}

/** '17:00:00' becomes '5:00 PM'. */
function format_time(string $time): string
{
    return (new DateTimeImmutable('2000-01-01 ' . $time))->format('g:i A');
}

/** 'Oct 25, 2026'. */
function format_date_short(string $date): string
{
    return (new DateTimeImmutable($date))->format('M j, Y');
}

/** 'Sun, Oct 25, 2026'. */
function format_day(string $date): string
{
    return (new DateTimeImmutable($date))->format('D, M j, Y');
}

/** A database date and time as people read it. */
function format_datetime(string $datetime): string
{
    return (new DateTimeImmutable($datetime))->format('g:i A, l, F j, Y');
}

function today(): string
{
    return date('Y-m-d');
}

/** Replies with JSON and stops. */
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
 * A plain error page, then stop. Only standard status codes (Apache turns
 * unknown ones like 419 into 500).
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

    // Kept self-contained: the error may come before the layout code loads
    $css = function_exists('asset') ? asset('assets/css/cinemax.css') : '';
    $home = function_exists('url') ? url('index.php') : '/';
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
        . '<title>' . e($title) . '</title>'
        . '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&amp;display=swap" rel="stylesheet">'
        . ($css !== '' ? '<link rel="stylesheet" href="' . e($css) . '">' : '')
        . '</head><body><main class="terms-page"><h1>' . e($title) . '</h1>'
        . '<p class="terms-intro">' . e($message) . '</p>'
        . '<a class="button button-red" href="' . e($home) . '">Back to the home page</a>'
        . '</main></body></html>';
    exit;
}

/** Stops with an error page: abort(404), abort(403). */
function abort(int $status, string $message = ''): void
{
    $defaults = [
        403 => 'You do not have access to that page.',
        404 => 'We could not find that page.',
        429 => 'Too many tries. Please wait a few minutes and try again.',
    ];
    render_error_page($status, $message !== '' ? $message : ($defaults[$status] ?? 'Something went wrong.'));
}
