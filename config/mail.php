<?php

return [

    'mailers' => [
        'smtp' => [
            'transport' => 'smtp',
            'host' => env('MAIL_HOST', 'smtp.mailgun.org'),
            'port' => env('MAIL_PORT', 587),
            'encryption' => env('MAIL_ENCRYPTION', 'tls'),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'timeout' => null,
            'local_domain' => env('MAIL_EHLO_DOMAIN'),
            'verify_peer' => env('MAIL_VERIFY_PEER', true),
        ],

        'mailgun' => [
            'transport' => 'mailgun',
        ],

        'brevo' => [
            'transport' => 'brevo',
        ],

        'gmail' => [
            'transport' => 'gmail',
        ],

        'office365' => [
            'transport' => 'office365',
        ],
    ],

];
