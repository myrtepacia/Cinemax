<?php
declare(strict_types=1);

// The dashboard's "has anything changed?" check, asked every few seconds by
// assets/js/dashboard.js. It answers with a short fingerprint of the
// figures and the snack queue; when that differs from the one the page was
// drawn with, the page fetches itself again and swaps in the new parts.
//
// Admin only. It only reads, and it returns no names or amounts.

require __DIR__ . '/../includes/bootstrap.php';

require_admin_get();

json_response(['ok' => true, 'version' => dashboard_version(dashboard_stats(), snack_queue())]);
