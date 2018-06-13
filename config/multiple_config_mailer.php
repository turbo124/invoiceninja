<?php

return [
    'mandrill' => [
        'driver' => 'mandrill',
        'from' => ['address' => env('MANDRILL_MAIL_FROM_ADDRESS'), 'name' => env('MANDRILL_MAIL_FROM_NAME')],
        'pretend' => false,
    ],
    'mailgun' => [
        'driver' => 'mailgun',
        'from' => ['address' => env('MAILGUN_MAIL_FROM_ADDRESS'), 'name' => env('MAILGUN_MAIL_FROM_NAME')],
        'pretend' => false,
    ],
];