<?php
declare(strict_types=1);

// Snack categories and snacks. Staff add and delete them (admin/snacks.php).
// Nothing is ever deleted from the database: past orders point at their
// snacks, so a removed snack or category is only hidden (is_active = 0).

if (!defined('CSFLIX_BOOTSTRAPPED')) {
    http_response_code(404);
    exit;
}

const SNACK_CATEGORY_NAME_MAX = 50;
const SNACK_NAME_MAX = 100;
const SNACK_DETAIL_MAX = 150;
const SNACK_PRICE_MAX = 10000;
// The booking page shows this many snacks per category (one row); View more
// shows the rest
const SNACKS_SHOWN = 2;

/** The snacks on sale, by category name, in menu order. Empty ones left out. */
function snacks_by_category(): array
{
    $groups = [];
    $rows = db_all(
        'SELECT s.id, s.name, s.detail, s.price, c.name AS category
         FROM snacks s JOIN snack_categories c ON c.id = s.category_id
         WHERE s.is_active = 1 AND c.is_active = 1
         ORDER BY c.sort_order, c.id, s.sort_order, s.id'
    );
    foreach ($rows as $snack) {
        $groups[$snack['category']][] = $snack;
    }
    return $groups;
}

/** The categories in menu order, each with how many snacks it has on sale. */
function snack_categories(): array
{
    return db_all(
        'SELECT c.id, c.name, COUNT(s.id) AS snack_count
         FROM snack_categories c
         LEFT JOIN snacks s ON s.category_id = c.id AND s.is_active = 1
         WHERE c.is_active = 1
         GROUP BY c.id, c.name, c.sort_order
         ORDER BY c.sort_order, c.id'
    );
}

/** A category still in use, or null. */
function find_snack_category(int $id): ?array
{
    return db_one('SELECT id, name FROM snack_categories WHERE id = ? AND is_active = 1', [$id]);
}

/**
 * Adds a category at the end of the menu. Returns an error message, or ''
 * when added.
 */
function create_snack_category(string $name): string
{
    if ($name === '') {
        return 'Type a name for the category.';
    }
    // Names are the headings on the booking page, so no two alike
    if (db_value('SELECT 1 FROM snack_categories WHERE is_active = 1 AND LOWER(name) = LOWER(?)', [$name]) !== null) {
        return 'There is already a category called ' . $name . '.';
    }
    db_exec(
        'INSERT INTO snack_categories (name, sort_order)
         SELECT ?, COALESCE(MAX(sort_order), 0) + 1 FROM snack_categories',
        [$name]
    );
    return '';
}

/** Takes a category and its snacks off the menu. Past orders stay as they are. */
function delete_snack_category(int $id): bool
{
    return (bool) db_transaction(function () use ($id): bool {
        $changed = db_exec('UPDATE snack_categories SET is_active = 0 WHERE id = ? AND is_active = 1', [$id]) === 1;
        if ($changed) {
            db_exec('UPDATE snacks SET is_active = 0 WHERE category_id = ?', [$id]);
        }
        return $changed;
    });
}

/** The snacks on sale in one category, in menu order. */
function category_snacks(int $categoryId): array
{
    return db_all(
        'SELECT id, name, detail, price FROM snacks
         WHERE category_id = ? AND is_active = 1
         ORDER BY sort_order, id',
        [$categoryId]
    );
}

/**
 * Adds a snack at the end of its category. Returns an error message, or ''
 * when added.
 */
function add_snack(int $categoryId, string $name, string $detail, ?int $price): string
{
    if ($name === '') {
        return 'Type a name for the snack.';
    }
    if ($price === null) {
        return 'Enter a price from ₱1 to ₱' . number_format(SNACK_PRICE_MAX) . ', in whole pesos.';
    }
    if (db_value('SELECT 1 FROM snacks WHERE category_id = ? AND is_active = 1 AND LOWER(name) = LOWER(?)', [$categoryId, $name]) !== null) {
        return 'This category already has a snack called ' . $name . '.';
    }
    db_exec(
        'INSERT INTO snacks (name, detail, category_id, price, sort_order)
         SELECT ?, ?, ?, ?, COALESCE(MAX(sort_order), 0) + 1 FROM snacks',
        [$name, $detail, $categoryId, $price]
    );
    return '';
}

/** Takes a snack off the menu. Past orders stay as they are. */
function remove_snack(int $snackId, int $categoryId): bool
{
    return db_exec('UPDATE snacks SET is_active = 0 WHERE id = ? AND category_id = ? AND is_active = 1', [$snackId, $categoryId]) === 1;
}
