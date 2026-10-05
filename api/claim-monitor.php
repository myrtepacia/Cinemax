<?php
declare(strict_types=1);

// Lists for the Snacks Claim monitor (claim-monitor.js): orders being
// prepared and orders ready to pick up. Admin only, read-only, no names or
// amounts.

require __DIR__ . '/../includes/bootstrap.php';

require_admin_get();

json_response(['ok' => true] + claim_monitor_orders());
