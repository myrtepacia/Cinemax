<?php
declare(strict_types=1);

// Sign out. Only the Sign Out form (POST with CSRF token) can do it, so other
// sites cannot sign anyone out.

require __DIR__ . '/includes/bootstrap.php';

require_post();
verify_csrf();

logout_user();
redirect('index.php');
