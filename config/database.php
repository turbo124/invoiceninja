<?php

return [

    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST1', env('DB_HOST', '127.0.0.1')),
            'database' => env('DB_DATABASE1', env('DB_DATABASE', 'forge')),
            'username' => env('DB_USERNAME1', env('DB_USERNAME', 'forge')),
            'password' => env('DB_PASSWORD1', env('DB_PASSWORD', '')),
            'port' => env('DB_PORT1', env('DB_PORT', '3306')),
            'unix_socket' => env('DB_SOCKET1', env('DB_SOCKET', '')),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => env('DB_STRICT', false),
            'engine' => 'InnoDB',
        ],

        'db-ninja-01' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST1', env('DB_HOST', '127.0.0.1')),
            'database' => env('DB_DATABASE1', env('DB_DATABASE', 'forge')),
            'username' => env('DB_USERNAME1', env('DB_USERNAME', 'forge')),
            'password' => env('DB_PASSWORD1', env('DB_PASSWORD', '')),
            'port' => env('DB_PORT1', env('DB_PORT', '3306')),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => env('DB_STRICT', false),
            'engine' => 'InnoDB ROW_FORMAT=DYNAMIC',
            'options' => [],
        ],

        'db-ninja-01a' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST1', env('DB_HOST', '127.0.0.1')),
            'database' => env('DB_DATABASE2', env('DB_DATABASE', 'forge')),
            'username' => env('DB_USERNAME2', env('DB_USERNAME', 'forge')),
            'password' => env('DB_PASSWORD2', env('DB_PASSWORD', '')),
            'port' => env('DB_PORT1', env('DB_PORT', '3306')),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => env('DB_STRICT', false),
            'engine' => 'InnoDB ROW_FORMAT=DYNAMIC',
            'options' => [],
        ],

        'db-ninja-02' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST2', env('DB_HOST', '127.0.0.1')),
            'database' => env('DB_DATABASE2', env('DB_DATABASE', 'forge')),
            'username' => env('DB_USERNAME2', env('DB_USERNAME', 'forge')),
            'password' => env('DB_PASSWORD2', env('DB_PASSWORD', '')),
            'port' => env('DB_PORT2', env('DB_PORT', '3306')),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => env('DB_STRICT', false),
            'engine' => 'InnoDB ROW_FORMAT=DYNAMIC',
            'options' => [],
        ],

        'db-ninja-02a' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST2', env('DB_HOST', '127.0.0.1')),
            'database' => env('DB_DATABASE1', env('DB_DATABASE', 'forge')),
            'username' => env('DB_USERNAME1', env('DB_USERNAME', 'forge')),
            'password' => env('DB_PASSWORD1', env('DB_PASSWORD', '')),
            'port' => env('DB_PORT2', env('DB_PORT', '3306')),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => env('DB_STRICT', false),
            'engine' => 'InnoDB ROW_FORMAT=DYNAMIC',
            'options' => [],
        ],
    ],

    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => false, // disable to preserve original behavior for existing applications
    ],

    'redis' => [

        'client' => 'predis',

        'default' => [
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD', null),
            'port' => env('REDIS_PORT', 6379),
            'database' => env('REDIS_DB', 0),
        ],

        'cache' => [
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD', null),
            'port' => env('REDIS_PORT', 6379),
            'database' => env('REDIS_CACHE_DB', 1),
        ],

        'sentinel-default' => array_merge(
            array_map(
                function ($a, $b) {
                    return ['host' => $a, 'port' => $b];
                },
                explode(',', env('REDIS_HOST', 'localhost')),
                explode(',', env('REDIS_PORT', 26379))
            ),
            ['options' => [
                'replication' => 'sentinel',
                'service' => env('REDIS_SENTINEL_SERVICE', 'mymaster'),
                'sentinel_timeout' => 3.0,
                // 'load_balancing' => false,
                'parameters' => [
                    'password' => env('REDIS_PASSWORD', null),
                    'database' => env('REDIS_DB', 0),
                ],
            ]]
        ),

        'sentinel-cache' => array_merge(
            array_map(
                function ($a, $b) {
                    return ['host' => $a, 'port' => $b];
                },
                explode(',', env('REDIS_HOST', 'localhost')),
                explode(',', env('REDIS_PORT', 26379))
            ),
            ['options' => [
                'replication' => 'sentinel',
                'service' => env('REDIS_SENTINEL_SERVICE', 'mymaster'),
                'sentinel_timeout' => 3.0,
                'parameters' => [
                    'password' => env('REDIS_PASSWORD', null),
                    'database' => env('REDIS_CACHE_DB', 1),
                ],
            ]]
        ),

    ],

];
