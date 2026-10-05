<?php
declare(strict_types=1);

// Asked every few seconds by dashboard.js: a short fingerprint of the figures
// and the snack queue. When it changes, the page reloads its parts. Admin
// only, read-only, no names or amounts.

require __DIR__ . '/../includes/bootstrap.php';

require_admin_get();

json_response(['ok' => true, 'version' => dashboard_version(dashboard_stats(), snack_queue())]);
