<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

require_post();
verify_csrf();

logout_user();
redirect('index.php');
