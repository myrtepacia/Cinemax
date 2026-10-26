<?php

return [
    'app_url' => 'http://localhost/csflix',
    'debug' => false,

    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'csflix',
        'user' => 'csflix_app',
        'pass' => '7105552b047eeecfddc9145abf12b839587df5e6d155986d',
    ],

    'paymongo' => [
        'secret_key' => 'sk_test_KwcB8do5Z1Lbd5NLvsXBFKbY',
        'public_key' => 'pk_test_G53BQpjfiSnQebhPrHNgXV5b',
        'payment_methods' => ['qrph', 'card', 'gcash'],
    ],

    'mail' => [
        'resend_api_key' => 're_GdLfqUxK_ETdjCrZWKrENZR72cgaBzw1o',
        'from' => 'CSFlix Cinema <no-reply@csflix.app>',
        'support_email' => 'csflixsupport@gmail.com',
    ],

    'booking_hold_minutes' => 10,

    // Who runs CSFlix, shown on the policy pages and in the footer.
    // 'student_project' => true shows the "school project, demo only" notice
    // on every page: keep it on unless CSFlix becomes a registered business.
    'site' => [
        'name' => 'CSFlix',
        'contact_email' => 'csflixsupport@gmail.com',
        'student_project' => true,
    ],
];
