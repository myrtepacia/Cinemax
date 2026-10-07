<?php

return [
    'app_url' => 'http://localhost/cinemax',

    'debug' => false,

    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'cinemax',
        'user' => 'cinemax_app',
        'pass' => '3ab3c4b2fb81091acf7fb937615b68cf7e2151ee2bd50f46',
    ],

    'paymongo' => [
        'secret_key' => 'sk_test_KwcB8do5Z1Lbd5NLvsXBFKbY',
        'public_key' => 'pk_test_G53BQpjfiSnQebhPrHNgXV5b',
        'webhook_secret' => '',
        'payment_methods' => ['qrph', 'card', 'paymaya'],
    ],

    'booking_hold_minutes' => 10,
];
