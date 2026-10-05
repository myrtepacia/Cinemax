<?php
declare(strict_types=1);

// Every page starts here: loads settings, error handling, security headers,
// the session and the shared code.

if (PHP_VERSION_ID < 80000) {
    http_response_code(500);
    exit('Cinemax needs PHP 8.0 or newer.');
}

define('APP_ROOT', dirname(__DIR__));
define('CINEMAX_BOOTSTRAPPED', true);

date_default_timezone_set('Asia/Manila');
mb_internal_encoding('UTF-8');

require APP_ROOT . '/includes/helpers.php';

// Errors go to storage/logs, never to the visitor (unless debug)
error_reporting(E_ALL);
ini_set('display_errors', config('debug') ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', APP_ROOT . '/storage/logs/php-errors.log');
// Keep passed values (like the database password) out of error logs
ini_set('zend.exception_ignore_args', '1');

// Warnings become exceptions, so a broken request stops
set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    if ($severity === E_DEPRECATED || $severity === E_USER_DEPRECATED) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(function (Throwable $e): void {
    error_log(sprintf(
        "[%s] %s: %s in %s:%d\n%s",
        date('c'),
        get_class($e),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        $e->getTraceAsString()
    ));
    if (config('debug')) {
        if (!headers_sent()) {
            header('Content-Type: text/plain; charset=utf-8');
        }
        echo $e;
        return;
    }
    render_error_page(500, 'Something went wrong on our side. Please try again in a moment.');
});

require APP_ROOT . '/includes/db.php';
require APP_ROOT . '/includes/security.php';
require APP_ROOT . '/includes/auth.php';
require APP_ROOT . '/includes/layout.php';
require APP_ROOT . '/includes/paymongo.php';
require APP_ROOT . '/includes/movies.php';
require APP_ROOT . '/includes/bookings.php';

// The webhook is server-to-server: no session
if (!defined('CINEMAX_NO_SESSION')) {
    send_security_headers();
    start_session();
}
