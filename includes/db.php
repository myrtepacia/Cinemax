<?php
declare(strict_types=1);

if (!defined('CINEMAX_BOOTSTRAPPED')) {
    http_response_code(404);
    exit;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        (string) config('db.host', '127.0.0.1'),
        (int) config('db.port', 3306),
        (string) config('db.name', 'cinemax')
    );

    $pdo = new PDO($dsn, (string) config('db.user'), (string) config('db.pass'), [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_STRINGIFY_FETCHES  => false,
        PDO::MYSQL_ATTR_MULTI_STATEMENTS => false,
    ]);

    $pdo->exec("SET time_zone = '+08:00'");
    $pdo->exec("SET SESSION sql_mode = 'STRICT_ALL_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");

    return $pdo;
}

function db_query(string $sql, array $params = []): PDOStatement
{
    $statement = db()->prepare($sql);
    foreach ($params as $name => $value) {
        $key = is_int($name) ? $name + 1 : $name;
        if (is_int($value)) {
            $statement->bindValue($key, $value, PDO::PARAM_INT);
        } elseif (is_bool($value)) {
            $statement->bindValue($key, $value ? 1 : 0, PDO::PARAM_INT);
        } elseif ($value === null) {
            $statement->bindValue($key, null, PDO::PARAM_NULL);
        } else {
            $statement->bindValue($key, (string) $value, PDO::PARAM_STR);
        }
    }
    $statement->execute();
    return $statement;
}

function db_one(string $sql, array $params = []): ?array
{
    $row = db_query($sql, $params)->fetch();
    return $row === false ? null : $row;
}

function db_all(string $sql, array $params = []): array
{
    return db_query($sql, $params)->fetchAll();
}

function db_value(string $sql, array $params = [])
{
    $value = db_query($sql, $params)->fetchColumn();
    return $value === false ? null : $value;
}

function db_exec(string $sql, array $params = []): int
{
    return db_query($sql, $params)->rowCount();
}

function db_transaction(callable $work)
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $result = $work($pdo);
        $pdo->commit();
        return $result;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function is_duplicate_key(Throwable $e): bool
{
    return $e instanceof PDOException
        && isset($e->errorInfo[1])
        && (int) $e->errorInfo[1] === 1062;
}
