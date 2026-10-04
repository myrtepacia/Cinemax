<?php
// Cinemax settings and secrets.
//
// This file holds the database password and the PayMongo secret key, so it
// must never be shared, committed, or served. The .htaccess in this folder
// blocks web access to it, and it only ever returns a value, so even a
// direct request would print nothing.

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
        // made by database/setup.php. It is not the MySQL root account.
        'user' => 'cinemax_app',
        'pass' => '3ab3c4b2fb81091acf7fb937615b68cf7e2151ee2bd50f46',
    ],

    'paymongo' => [
        // Test keys: no real money moves. Swap for live keys to go live.
        'secret_key' => 'sk_test_KwcB8do5Z1Lbd5NLvsXBFKbY',
        'public_key' => 'pk_test_G53BQpjfiSnQebhPrHNgXV5b',
        // Filled in after registering webhook.php with PayMongo (whsk_...).
        // Left empty, webhook.php refuses every request, and payments are
        // confirmed when the customer returns from PayMongo instead.
        'webhook_secret' => '',
        // The ways customers may pay on PayMongo's checkout page
        'payment_methods' => ['card', 'gcash', 'paymaya', 'grab_pay'],
    ],

    // How long seats are held while a customer is paying, in minutes
    'booking_hold_minutes' => 10,
];
