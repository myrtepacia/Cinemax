<?php

return [

    'app_url' => 'http://localhost/cinemax',
    'debug' => false,

    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'cinemax',
        'user' => 'cinemax_app',
        'pass' => 'change-me-to-a-long-random-password',
    ],

    'paymongo' => [
        'secret_key' => 'sk_test_...',
        'public_key' => 'pk_test_...',
        'webhook_secret' => '',
        'payment_methods' => ['qrph', 'card', 'paymaya'],
    ],

    'booking_hold_minutes' => 10,
];
