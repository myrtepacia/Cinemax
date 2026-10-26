<?php
declare(strict_types=1);

if (!defined('CSFLIX_BOOTSTRAPPED')) {
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

/** The site's folder on the server, like '/csflix'. */
function app_path(): string
{
    $path = (string) parse_url((string) config('app_url'), PHP_URL_PATH);
    return rtrim($path, '/');
}

/**
 * A page path as visitors see it: no '.php', a film's page is just its name
 * and a booking's pages end in its reference. 'book.php?movie=hush' becomes
 * 'hush', 'pay.php?ref=CSF-ABC234' 'pay/CSF-ABC234', 'terms.php' 'terms',
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

/** Redirects to PayMongo's own pages only (checkout, card, GCash). */
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
    // 'hush', 'ticket/CSF-ABC234', 'admin/' or the older 'book.php?movie=x'
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
    return site_path((string) ($_SERVER['REQUEST_URI'] ?? ''));
}

/** The page the visitor came from on this site, or null (another site, or none). */
function referer_path(): ?string
{
    $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
    $host = (string) parse_url($referer, PHP_URL_HOST);
    $port = parse_url($referer, PHP_URL_PORT);
    $ours = (string) ($_SERVER['HTTP_HOST'] ?? '');
    if ($referer === '' || strcasecmp($host . ($port !== null ? ':' . $port : ''), $ours) !== 0) {
        return null;
    }
    return site_path($referer);
}

/** An address's path and query string inside the site ('hush', 'ticket/CSF-ABC234'). */
function site_path(string $uri): string
{
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

/** 'October 25, 2026'. */
function format_date_long(string $date): string
{
    return (new DateTimeImmutable($date))->format('F j, Y');
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
 * Output filter for HTML pages (bootstrap.php): drops the developer comments
 * and squashes the indentation, so Inspect Element and View Source show
 * compact code with no internal notes. Pages look the same, because browsers
 * already treat any run of spaces and new lines as one space. <textarea> and
 * <pre> keep their text exactly. JSON and other responses pass untouched.
 */
function tidy_html_output(string $html): string
{
    foreach (headers_list() as $header) {
        if (stripos($header, 'Content-Type:') === 0 && stripos($header, 'text/html') === false) {
            return $html;
        }
    }
    // Any step that fails (a pattern limit on a huge page) sends the page as
    // it was, never a blank one
    $original = $html;
    $kept = [];
    $html = preg_replace_callback('~<(textarea|pre)\b.*?</\1>~is', function (array $m) use (&$kept): string {
        $kept[] = $m[0];
        return "\x00" . (count($kept) - 1) . "\x00";
    }, $html);
    $html = $html === null ? null : preg_replace('~<!--.*?-->~s', '', $html);
    $html = $html === null ? null : preg_replace('~\s+~', ' ', $html);
    $html = $html === null ? null : preg_replace_callback("~\x00(\d+)\x00~", function (array $m) use ($kept): string {
        return $kept[(int) $m[1]];
    }, trim($html));
    return $html ?? $original;
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
    $css = function_exists('asset') ? asset('assets/css/csflix.css') : '';
    $home = function_exists('url') ? url('index.php') : '/';
    // A server problem is usually brief, so that page offers to try again
    // (an empty link opens the same page again) as well as going home
    $buttons = '<a class="button button-red" href="' . e($status >= 500 ? '' : $home) . '">'
        . ($status >= 500 ? 'Try again' : 'Back to the home page') . '</a>';
    if ($status >= 500) {
        $buttons .= '<a class="button button-outline" href="' . e($home) . '">Home page</a>';
    }
    // Centred like offline.html, with the status code large above the title
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
        . '<title>' . e($status . ' ' . $title) . '</title>'
        . '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&amp;display=swap" rel="stylesheet">'
        . ($css !== '' ? '<link rel="stylesheet" href="' . e($css) . '">' : '')
        . '</head><body><main class="status-page">'
        . '<a class="status-brand" href="' . e($home) . '">CSFLIX</a>'
        . '<p class="status-code" aria-hidden="true">' . e($status) . '</p>'
        . '<h1>' . e($title) . '</h1>'
        . '<p>' . e($message) . '</p>'
        . '<div class="status-actions">' . $buttons . '</div>'
        . '</main></body></html>';
    exit;
}

/** Stops with an error page: abort(404), abort(403). */
function abort(int $status, string $message = ''): void
{
    $defaults = [
        403 => 'You do not have access to that page.',
        404 => 'The page you are looking for does not exist or has moved. Check the address, or find a film from the home page.',
        429 => 'Too many tries. Please wait a few minutes and try again.',
    ];
    render_error_page($status, $message !== '' ? $message : ($defaults[$status] ?? 'Something went wrong.'));
}
