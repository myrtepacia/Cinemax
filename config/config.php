<?php
// Cinemax settings and secrets (database password, PayMongo keys). Never
// share or commit this file. The .htaccess here blocks web access to it.

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
        // The cinemax database user made by database/setup.php (not root).
        'user' => 'cinemax_app',
        'pass' => '3ab3c4b2fb81091acf7fb937615b68cf7e2151ee2bd50f46',
    ],

    'paymongo' => [
        // Live keys (sk_live_, pk_live_) take real money; test keys
        // (sk_test_, pk_test_) do not. Use both from the same mode, and
        // switch only while nobody is paying.
        'secret_key' => 'sk_live_d9NZn7B17miVGpjwnrJfjVRL',
        'public_key' => 'pk_live_zgXobEUNCpFgSgaDB1DBNbup',
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
