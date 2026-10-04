<?php
declare(strict_types=1);

// The Snacks Claim monitor's lists, asked for every few seconds by
// assets/js/claim-monitor.js: the reference numbers being prepared and the
// ones ready to pick up.
//
// Admin only. It only reads, and it returns no names, snacks or amounts.

require __DIR__ . '/../includes/bootstrap.php';

require_admin_get();

json_response(['ok' => true] + claim_monitor_orders());
