<?php
declare(strict_types=1);

if (PHP_VERSION_ID < 80000) {
    http_response_code(500);
    exit('CSFlix needs PHP 8.0 or newer.');
}

define('APP_ROOT', dirname(__DIR__));
define('CSFLIX_BOOTSTRAPPED', true);

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

// HTML pages go out without comments or indentation (tidy_html_output);
// debug mode keeps them readable
if (!config('debug') && PHP_SAPI !== 'cli') {
    ob_start(static function (string $html): string {
        return tidy_html_output($html);
    });
    define('CSFLIX_OUTPUT_LEVEL', ob_get_level());
}

// What the "Something went wrong" (500) page says
const SERVER_ERROR_MESSAGE = 'Our server ran into a problem. Please try again in a few minutes.';

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
    // Throw away the half-built page so only the error page is sent
    if (defined('CSFLIX_OUTPUT_LEVEL') && ob_get_level() >= CSFLIX_OUTPUT_LEVEL) {
        while (ob_get_level() > CSFLIX_OUTPUT_LEVEL) {
            ob_end_clean();
        }
        ob_clean();
    }
    render_error_page(500, SERVER_ERROR_MESSAGE);
});

// A fatal error (out of memory, a broken file) skips the handler above and
// would leave a blank page: show the same "Something went wrong" page
register_shutdown_function(function (): void {
    $error = error_get_last();
    if ($error === null || !in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        return;
    }
    if (config('debug') || headers_sent()) {
        return;
    }
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    render_error_page(500, SERVER_ERROR_MESSAGE);
});

require APP_ROOT . '/includes/db.php';
require APP_ROOT . '/includes/security.php';
require APP_ROOT . '/includes/auth.php';
require APP_ROOT . '/includes/layout.php';
require APP_ROOT . '/includes/paymongo.php';
require APP_ROOT . '/includes/movies.php';
require APP_ROOT . '/includes/snacks.php';
require APP_ROOT . '/includes/bookings.php';
require APP_ROOT . '/includes/mail.php';

send_security_headers();
start_session();
clear_old_remember_cookie();
