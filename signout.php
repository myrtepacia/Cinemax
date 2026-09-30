<?php
declare(strict_types=1);

// Signing out. Only the Sign Out button's form (a POST with the CSRF token)
// can do it, so a link or image on another site cannot sign anyone out.

require __DIR__ . '/includes/bootstrap.php';

require_post();
verify_csrf();

logout_user();

// The old session is gone; a fresh, empty one carries the goodbye message.
start_session();
flash('success', 'You are signed out.');
redirect('index.php');
