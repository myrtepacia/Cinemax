<?php
// Cinemax settings template. Copy it to config.php in this folder and fill in
// the database password and PayMongo keys. config.php is kept out of git and
// blocked from the web.

return [
    // The site address, no slash at the end. PayMongo sends customers back
    // here.
    'app_url' => 'http://localhost/cinemax',

    // true shows PHP errors on the page. Keep false unless debugging.
    'debug' => false,

    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'cinemax',
        // The cinemax database user made by database/setup.php with this
        // password (not root). Use 16 or more random characters.
        'user' => 'cinemax_app',
        'pass' => 'change-me-to-a-long-random-password',
    ],

    'paymongo' => [
        // Live keys (sk_live_, pk_live_) take real money; test keys
        // (sk_test_, pk_test_) do not. Use both from the same mode, and
        // switch only while nobody is paying.
        'secret_key' => 'sk_test_...',
        'public_key' => 'pk_test_...',
        // Webhook secret (whsk_...) from registering webhook.php with
        // PayMongo. Empty: webhook.php refuses every call and payments are
        // confirmed when the customer comes back.
        'webhook_secret' => '',
        // qrph shows the QR code on the pay page; gcash and card appear under
        // Choose another payment. With live keys, a way PayMongo has not
        // turned on yet asks the customer to scan the QR code instead.
        'payment_methods' => ['qrph', 'gcash', 'card'],
    ],

    // Minutes seats are held while paying
    'booking_hold_minutes' => 10,
];
