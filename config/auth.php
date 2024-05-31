<?php

return [

    'guards' => [
        'api' => [
            'driver' => 'token',
            'provider' => 'users',
            'hash' => false,
        ],

        'user' => [
            'driver' => 'session',
            'provider' => 'users',
        ],

        'contact' => [
            'driver' => 'session',
            'provider' => 'contacts',
        ],

        'vendor' => [
            'driver' => 'session',
            'provider' => 'vendors',
        ],
    ],

    'providers' => [
        'contacts' => [
            'driver' => 'eloquent',
            'model' => App\Models\ClientContact::class,
        ],

        'vendors' => [
            'driver' => 'eloquent',
            'model' => App\Models\VendorContact::class,
        ],
    ],

    'passwords' => [
        'contacts' => [
            'provider' => 'contacts',
            'table' => 'password_reset_tokens',
            'expire' => 60,
        ],

        'vendors' => [
            'provider' => 'vendors',
            'table' => 'password_reset_tokens',
            'expire' => 60,
        ],
    ],

];
