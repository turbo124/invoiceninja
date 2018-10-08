<?php

return [

    // The default gateway to use
    'default' => 'paypal',

    // Add in each gateway here
    'gateways' => [
        'paypal' => [
            'driver' => 'Paypal_Express',
            'options' => [
                'solutionType' => '',
                'landingPage' => '',
                'headerImageUrl' => '',
            ],
        ],
    ],
];
