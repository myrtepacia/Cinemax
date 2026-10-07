<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';

require_admin_get();

json_response(['ok' => true] + claim_monitor_orders());
