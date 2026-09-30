<?php
declare(strict_types=1);

// The Snacks Claim monitor's lists, asked for every few seconds by
// assets/js/claim-monitor.js: the reference numbers being prepared and the
// ones ready to pick up.
//
// Admin only. It only reads, and it returns no names, snacks or amounts.

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

json_response(['ok' => true] + claim_monitor_orders());
