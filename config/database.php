<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Database Connection Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the database connections below you wish
    | to use as your default connection for all database work. Of course
    | you may use many connections at once using the Database library.
    |
    */

    'default' => env('DB_CONNECTION', 'dbp'),

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | Here are each of the database connections setup for your application.
    | Of course, examples of configuring each database platform that is
    | supported by Laravel is shown below to make development simple.
    |
    |
    | All database work in Laravel is done through the PHP PDO facilities
    | so make sure you have the driver for your particular database of
    | choice installed on your machine before you begin development.
    |
    */

    'connections' => [

        'mysql' => [
            'driver'    => 'mysql',
            'host'      => env('DBP_USERS_HOST', '127.0.0.1'),
            'port'      => env('DBP_USERS_PORT', '3306'),
            'database'  => env('DBP_USERS_DATABASE', 'blankstart_dbp_users'),
            'username'  => env('DBP_USERS_USERNAME', 'root'),
            'password'  => env('DBP_USERS_PASSWORD', ''),
            'charset'   => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix'    => '',
            'strict'    => false,
            'engine'    => null,
        ],

        'dbp' => [
            'driver' => 'mysql',
            'host' => env('DBP_HOST', '127.0.0.1'),
            'port' => env('DBP_PORT', '3306'),
            'database' => env('DBP_DATABASE', 'blankstart_dbp_prod'),
            'username' => env('DBP_USERNAME', 'root'),
            'password' => env('DBP_PASSWORD', ''),
            'unix_socket' => env('DBP_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => false,
            'engine' => null,
        ],

        'dbp_users' => [
            'driver'    => 'mysql',
            'host'      => env('DBP_USERS_HOST', '127.0.0.1'),
            'port'      => env('DBP_USERS_PORT', '3306'),
            'database'  => env('DBP_USERS_DATABASE', 'dbp_users'),
            'username'  => env('DBP_USERS_USERNAME', 'root'),
            'password'  => env('DBP_USERS_PASSWORD', ''),
            'charset'   => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix'    => '',
            'strict'    => false,
            'engine'    => null,
        ],

        'dbp_users_v2' => [
            'driver'    => 'mysql',
            'host'      => env('DBP_USERS_V2_HOST', '127.0.0.1'),
            'port'      => env('DBP_USERS_V2_PORT', '3306'),
            'database'  => env('DBP_USERS_V2_DATABASE', 'dbp_users_v2'),
            'username'  => env('DBP_USERS_V2_USERNAME', 'root'),
            'password'  => env('DBP_USERS_V2_PASSWORD', ''),
            'charset'   => 'latin1',
            'collation' => 'latin1_swedish_ci',
            'prefix'    => '',
            'strict'    => false,
            'engine'    => null,
        ],

        'dbp_users_v2_utf8' => [
            'driver'    => 'mysql',
            'host'      => env('DBP_USERS_V2_HOST', '127.0.0.1'),
            'port'      => env('DBP_USERS_V2_PORT', '3306'),
            'database'  => env('DBP_USERS_V2_DATABASE', 'dbp_users_v2'),
            'username'  => env('DBP_USERS_V2_USERNAME', 'root'),
            'password'  => env('DBP_USERS_V2_PASSWORD', ''),
            'charset'   => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix'    => '',
            'strict'    => false,
            'engine'    => null,
        ],

        'dbp_v2' => [
            'driver'    => 'mysql',
            'host'      => env('DBP_USERS_V2_HOST', '127.0.0.1'),
            'port'      => env('DBP_USERS_V2_PORT', '3306'),
            'database'  => env('DBP_USERS_V2_DATABASE', 'dbp_users_v2'),
            'username'  => env('DBP_USERS_V2_USERNAME', 'root'),
            'password'  => env('DBP_USERS_V2_PASSWORD', ''),
            'charset'   => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix'    => '',
            'strict'    => false,
            'engine'    => null,
        ],
        
        'livebibleis_users' => [
          'driver'    => 'mysql',
          'host'      => env('LIVEBIBLEIS_USERS_HOST', '127.0.0.1'),
          'port'      => env('LIVEBIBLEIS_USERS_PORT', '3306'),
          'database'  => env('LIVEBIBLEIS_USERS_DATABASE', 'livebibleis_users'),
          'username'  => env('LIVEBIBLEIS_USERS_USERNAME', 'root'),
          'password'  => env('LIVEBIBLEIS_USERS_PASSWORD', ''),
          'charset'   => 'utf8mb4',
          'collation' => 'utf8mb4_unicode_ci',
          'prefix'    => '',
          'strict'    => false,
          'engine'    => null,
      ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Repository Table
    |--------------------------------------------------------------------------
    |
    | This table keeps track of all the migrations that have already run for
    | your application. Using this information, we can determine which of
    | the migrations on disk haven't actually been run in the database.
    |
    */

    'migrations' => 'migrations',

    /*
    |--------------------------------------------------------------------------
    | Redis Databases
    |--------------------------------------------------------------------------
    |
    | Redis is an open source, fast, and advanced key-value store that also
    | provides a richer set of commands than a typical key-value systems
    | such as APC or Memcached. Laravel makes it easy to dig right in.
    |
    */

    'redis' => [

        'client' => 'predis',

        'default' => [
            'host'     => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD', null),
            'port'     => env('REDIS_PORT', 6379),
            'database' => 0,
        ],

    ],

];
