<?php
// Cinemax settings: the template.
//
// Copy this file to config.php in this folder and fill in the database
// password and your PayMongo test keys. config.php holds secrets, so it is
// left out of git (see .gitignore) and blocked from the web (.htaccess).

return [
    // The address the site is reached at, with no slash at the end. PayMongo
    // sends customers back here after they pay.
    'app_url' => 'http://localhost/cinemax',

    // true shows PHP errors on the page. Keep false except while debugging.
    'debug' => false,

    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'cinemax',
        // A database user that can only read and write the cinemax tables,
        // made by database/setup.php with this password. It is not the MySQL
        // root account. Use a long random password (16 characters or more).
        'user' => 'cinemax_app',
        'pass' => 'change-me-to-a-long-random-password',
    ],

    'paymongo' => [
        // Test keys: no real money moves. Swap for live keys to go live.
        'secret_key' => 'sk_test_...',
        'public_key' => 'pk_test_...',
        // Filled in after registering webhook.php with PayMongo (whsk_...).
        // Left empty, webhook.php refuses every request, and payments are
        // confirmed when the customer returns from PayMongo instead.
        'webhook_secret' => '',
        // The ways customers may pay on PayMongo's checkout page. In live
        // mode, list only the ones PayMongo has activated on your account,
        // otherwise the checkout page says no payment methods are available.
        // ['qrph'] alone (QR Ph, scanned with GCash, Maya or a bank app)
        // shows the code on Cinemax's own page (pay.php) instead, with a
        // countdown as long as the seat hold below.
        'payment_methods' => ['card', 'gcash', 'paymaya', 'grab_pay'],
    ],

    // How long seats are held while a customer is paying, in minutes
    'booking_hold_minutes' => 10,
];
