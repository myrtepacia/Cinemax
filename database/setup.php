<?php
declare(strict_types=1);

// Creates the Cinemax database, its own limited MySQL user, the tables and
// the starting data (films, snacks, the admin and scanner accounts).
//
// Run it from a terminal, with XAMPP's MySQL started:
//
//   C:\xampp\php\php.exe C:\xampp\htdocs\cinemax\database\setup.php
//
// Options:
//   --fresh              drop an existing cinemax database first (deletes all bookings!)
//   --root-user=NAME     MySQL admin account to set things up with (default: root)
//   --root-pass=SECRET   its password (default: none, as XAMPP ships)
//
// It never runs from a browser.

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$options = getopt('', ['fresh', 'root-user::', 'root-pass::']);
$rootUser = (string) ($options['root-user'] ?? 'root');
$rootPass = (string) ($options['root-pass'] ?? '');
$fresh = array_key_exists('fresh', $options);

$config = require dirname(__DIR__) . '/config/config.php';
$db = $config['db'];
$dbName = (string) $db['name'];
$appUser = (string) $db['user'];
$appPass = (string) $db['pass'];

if (!preg_match('/^[A-Za-z0-9_]+$/', $dbName) || !preg_match('/^[A-Za-z0-9_]+$/', $appUser)) {
    fwrite(STDERR, "The database and user names in config/config.php may only use letters, digits and _.\n");
    exit(1);
}
if (strlen($appPass) < 16) {
    fwrite(STDERR, "Set a long random 'pass' for the database user in config/config.php first.\n");
    exit(1);
}

try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $db['host'], (int) $db['port']),
        $rootUser,
        $rootPass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    fwrite(STDERR, "Could not connect to MySQL as '$rootUser'. Is MySQL started in XAMPP?\n" . $e->getMessage() . "\n");
    exit(1);
}

$exists = (bool) $pdo->query('SELECT 1 FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ' . $pdo->quote($dbName))->fetchColumn();
if ($exists && !$fresh) {
    fwrite(STDERR, "The '$dbName' database already exists. Run again with --fresh to wipe and rebuild it.\n");
    exit(1);
}
if ($exists) {
    echo "Dropping the old '$dbName' database...\n";
    $pdo->exec("DROP DATABASE `$dbName`");
}

echo "Creating the '$dbName' database...\n";
$pdo->exec("CREATE DATABASE `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

// The site's own account: it can read and change rows in this one database
// and nothing else. It cannot drop tables, make users or see other databases.
echo "Creating the '$appUser' database user...\n";
foreach (['localhost', '127.0.0.1'] as $host) {
    $account = $pdo->quote($appUser) . '@' . $pdo->quote($host);
    $pdo->exec("DROP USER IF EXISTS $account");
    $pdo->exec("CREATE USER $account IDENTIFIED BY " . $pdo->quote($appPass));
    $pdo->exec("GRANT SELECT, INSERT, UPDATE, DELETE ON `$dbName`.* TO $account");
}
$pdo->exec('FLUSH PRIVILEGES');

$pdo->exec("USE `$dbName`");

foreach (['schema.sql', 'seed.sql'] as $file) {
    echo "Loading $file...\n";
    $sql = (string) file_get_contents(__DIR__ . '/' . $file);
    // Drop comment lines, then run one statement at a time
    $sql = (string) preg_replace('/^\s*--.*$/m', '', $sql);
    foreach (preg_split('/;\s*(\r?\n|$)/', $sql) as $statement) {
        if (trim($statement) !== '') {
            $pdo->exec($statement);
        }
    }
}

$counts = [];
foreach (['users', 'movies', 'showtimes', 'snacks'] as $table) {
    $counts[] = $table . ': ' . $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
}
echo "Done. " . implode(', ', $counts) . "\n";
echo "Open " . $config['app_url'] . "/ in your browser.\n";
