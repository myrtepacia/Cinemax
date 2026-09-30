<?php
declare(strict_types=1);

// The dashboard's "has anything changed?" check, asked every few seconds by
// assets/js/dashboard.js. It answers with a short fingerprint of the
// figures and the snack queue; when that differs from the one the page was
// drawn with, the page fetches itself again and swaps in the new parts.
//
// Admin only. It only reads, and it returns no names or amounts.

require __DIR__ . '/../includes/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    json_response(['ok' => false, 'error' => 'Only GET requests are accepted here.'], 405);
}

$user = current_user();
if ($user === null) {
    json_response(['ok' => false, 'error' => 'Please sign in again.'], 401);
}
if ($user['role'] !== 'admin') {
    json_response(['ok' => false, 'error' => 'Not allowed.'], 403);
}

json_response(['ok' => true, 'version' => dashboard_version(dashboard_stats(), snack_queue())]);
