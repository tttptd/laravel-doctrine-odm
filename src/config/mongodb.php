<?php
declare(strict_types=1);

return [
    // https://github.com/chefsplate/laravel-doctrine-odm/blob/master/config/database.php

    'connection' => [

        'server' => env('DB_MONGO_SERVER', 'mongodb://localhost:27017'),
        'options' => [
            'db' => env('DB_MONGO_DATABASE', 'database'),
        ],

    ],

    'cache' => [
        'metadata' => [
            'driver' => env('DOCTRINE_METADATA_CACHE', 'array'), // redis|memcached|file|phpfile|array
            'connection' => env('DOCTRINE_METADATA_CACHE_CONNECTION', 'default'),
            'prefix' => env('DOCTRINE_METADATA_CACHE_PREFIX', 'doctrine_metadata_'),
            'ttl' => (int)env('DOCTRINE_METADATA_CACHE_TTL', 3600),
            'path' => storage_path('framework/cache/doctrine'),
        ],
    ],

    'paths' => [

        /* A list of entities */
        'documents' => [
            base_path('app/Documents'),
        ],

        'proxies' => [
            'namespace' => 'Proxies',
            'path' => storage_path('mongo_proxies'),

            // only AUTOGENERATE_FILE_NOT_EXISTS and AUTOGENERATE_EVAL are supported.
            // Configuration::AUTOGENERATE_FILE_NOT_EXISTS = 2
            // Configuration::AUTOGENERATE_EVAL = 3
            'auto_generate' => env(
                'DOCTRINE_PROXY_AUTOGENERATE',
                \Doctrine\ODM\MongoDB\Configuration::AUTOGENERATE_FILE_NOT_EXISTS,
            ),
        ],

        'hydrators' => [
            'namespace' => 'Hydrators',
            'path' => storage_path('mongo_hydrators'),
        ],

        // TODO: support multiple metadata implementations
        'meta' => env('DOCTRINE_METADATA', 'annotations'),

    ],

    // По умолчанию flush() просто выполняет накопленные операции батчами,
    // но без мульти-документной транзакции (каждая запись атомарна, но целиком серия — нет).
    // (только на replica set/sharded кластере; на standalone транзакций нет)
    'use_transactional_flush' => false,

    // // Document Mapper Settings specific to our Laravel implementation
    // 'laravel_dm' => [
    //
    //     'soft_deletes' => [
    //         'enabled'    => true,
    //         'field_name' => 'deleted_at'
    //     ]
    //
    // ],
];
