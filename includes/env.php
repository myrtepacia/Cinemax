<?php
declare(strict_types=1);

if (!defined('CSFLIX_BOOTSTRAPPED')) {
    http_response_code(404);
    exit;
}

/**
 * A setting from the .env file in the main folder: env('DB_PASS'). A real
 * environment variable of the same name wins (Docker sets DB_HOST). Unset or
 * empty gives $default.
 */
function env(string $key, string $default = ''): string
{
    $real = getenv($key);
    if ($real !== false && $real !== '') {
        return $real;
    }
    $file = env_file();
    return isset($file[$key]) && $file[$key] !== '' ? $file[$key] : $default;
}

/** env('APP_DEBUG') as true or false: true, 1, yes and on are true. */
function env_bool(string $key, bool $default = false): bool
{
    $value = strtolower(env($key));
    return $value === '' ? $default : in_array($value, ['true', '1', 'yes', 'on'], true);
}

/**
 * The .env file as KEY => value, read once. Lines are KEY=value; blank lines
 * and lines starting with # are skipped, and quotes around a value are removed.
 */
function env_file(): array
{
    static $values = null;
    if ($values !== null) {
        return $values;
    }
    $values = [];
    $lines = @file(dirname(__DIR__) . '/.env', FILE_IGNORE_NEW_LINES);
    foreach ($lines ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
            continue;
        }
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        $quote = $value[0] ?? '';
        if (strlen($value) >= 2 && ($quote === '"' || $quote === "'") && substr($value, -1) === $quote) {
            $value = substr($value, 1, -1);
        }
        $values[$key] = $value;
    }
    return $values;
}
