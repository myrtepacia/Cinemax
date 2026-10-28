<?php

require_once dirname(__DIR__) . '/includes/env.php';

return [
    'app_url' => env('APP_URL', 'http://localhost/csflix'),
    'debug' => env_bool('APP_DEBUG'),

    'db' => [
        'host' => env('DB_HOST', '127.0.0.1'),
        'port' => (int) env('DB_PORT', '3306'),
        'name' => env('DB_NAME', 'csflix'),
        'user' => env('DB_USER', 'root'),
        'pass' => env('DB_PASS'),
    ],

    'paymongo' => [
        'secret_key' => env('PAYMONGO_SECRET_KEY'),
        'public_key' => env('PAYMONGO_PUBLIC_KEY'),
        'payment_methods' => ['qrph', 'card', 'gcash'],
    ],

    'mail' => [
        'resend_api_key' => env('RESEND_API_KEY'),
        'from' => env('MAIL_FROM', 'CSFlix Cinema <onboarding@resend.dev>'),
        'support_email' => env('SUPPORT_EMAIL'),
    ],

    // Minutes seats are held while paying
    'booking_hold_minutes' => 10,
    'site' => [
        'name' => 'CSFlix',
        'contact_email' => 'csflixsupport@gmail.com',
        'student_project' => true,
    ],
];
