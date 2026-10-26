<?php

return [

    'app_url' => 'http://localhost/csflix',
    'debug' => false,

    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'csflix',
        'user' => 'csflix_app',
        'pass' => 'change-me-to-a-long-random-password',
    ],

    'paymongo' => [
        // Live keys (sk_live_, pk_live_) take real money; test keys
        // (sk_test_, pk_test_) do not. Use both from the same mode, and
        // switch only while nobody is paying.
        'secret_key' => 'sk_test_...',
        'public_key' => 'pk_test_...',
        'payment_methods' => ['qrph', 'card', 'gcash'],
    ],

    // Receipts emailed after payment, sent by Resend (resend.com). With
    // 'resend_api_key' empty, none is sent. 'from' must use a domain verified
    // in Resend; onboarding@resend.dev only reaches your own Resend email.
    // Replies to the emails go to 'support_email'.
    'mail' => [
        'resend_api_key' => '',
        'from' => 'CSFlix Cinema <onboarding@resend.dev>',
        'support_email' => '',
    ],

    // Minutes seats are held while paying
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
