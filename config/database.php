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

    'default' => env('DB_CONNECTION', 'project'),

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

        'sqlite' => [
            'driver' => 'sqlite',
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix' => '',
        ],

        'mysql' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'forge'),
            'username' => env('DB_USERNAME', 'forge'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => null,
        ],

        'pgsql' => [
            'driver' => 'pgsql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'forge'),
            'username' => env('DB_USERNAME', 'forge'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8',
            'prefix' => '',
            'schema' => 'public',
            'sslmode' => 'prefer',
        ],

        'sqlsrv' => [
            'driver' => 'sqlsrv',
            'host' => env('DB_HOST', 'localhost'),
            'port' => env('DB_PORT', '1433'),
            'database' => env('DB_DATABASE', 'forge'),
            'username' => env('DB_USERNAME', 'forge'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8',
            'prefix' => '',
        ],

        //
        // custom database connections
        //

        'project' => [
            'driver' => 'mysql',
            'host' => env('PROJECT_DB_HOST', '127.0.0.1'),
            'port' => env('PROJECT_DB_PORT', '3306'),
            'database' => env('PROJECT_DB_DATABASE', 'forge'),
            'username' => env('PROJECT_DB_USERNAME', 'forge'),
            'password' => env('PROJECT_DB_PASSWORD', ''),
            'unix_socket' => env('PROJECT_DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => null,
        ],


        'package_store' => array(
            'driver' => 'mysql',
            'host'  => env('PACKAGE_DB_HOST', '127.0.0.1'),
            'port' => env('PACKAGE_DB_PORT', '3306'),
            'database' => env('PACKAGE_DB_DATABASE', 'package_store'),
            'username' => env('PACKAGE_DB_USERNAME', 'root'),
            'password' => env('PACKAGE_DB_PASSWORD', 'root'),
            'unix_socket' => env('PACKAGE_DB_SOCKET', ''),
            'charset' => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => null,
        ),

        'tool_shed' => array(
            'driver' => 'mysql',
            'host' => env('TOOL_DB_HOST', '127.0.0.1'),
            'port' => env('TOOL_DB_PORT', '3306'),
            'database' => env('TOOL_DB_DATABASE', 'tool_shed'),
            'username' => env('TOOL_DB_USERNAME', 'root'),
            'password' => env('TOOL_DB_PASSWORD', 'root'),
            'unix_socket' => env('TOOL_DB_SOCKET', ''),
            'charset' => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => null,
        ),

        'platform_store' => array(
            'driver' => 'mysql',
            'host' => env('PLATFORM_DB_HOST', '127.0.0.1'),
            'port' => env('PLATFORM_DB_PORT', '3306'),
            'database' => env('PLATFORM_DB_DATABASE', 'platform_store'),
            'username' => env('PLATFORM_DB_USERNAME', 'root'),
            'password' => env('PLATFORM_DB_PASSWORD', 'root'),
            'unix_socket' => env('PLATFORM_DB_SOCKET', ''),
            'charset' => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => null,
        ),

        'assessment' => array(
            'driver'  => 'mysql',
            'host' => env('ASSESSMENT_DB_HOST', '127.0.0.1'),
            'port' => env('ASSESSMENT_DB_PORT', '3306'),
            'database' => env('ASSESSMENT_DB_DATABASE', 'assessment'),
            'username' => env('ASSESSMENT_DB_USERNAME', 'root'),
            'password' => env('ASSESSMENT_DB_PASSWORD', 'root'),
            'unix_socket' => env('ASSESSMENT_DB_SOCKET', ''),
            'charset' => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => null,
        ),

        'viewer_store' => array(
            'driver' => 'mysql',
            'host' => env('VIEWER_DB_HOST', '127.0.0.1'),
            'port' => env('VIEWER_DB_PORT', '3306'),
            'database' => env('VIEWER_DB_DATABASE', 'viewer_store'),
            'username' => env('VIEWER_DB_USERNAME', 'root'),
            'password' => env('VIEWER_DB_PASSWORD', 'root'),
            'unix_socket' => env('VIEWER_DB_SOCKET', ''),
            'charset' => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => null,
        ),

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
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD', null),
            'port' => env('REDIS_PORT', 6379),
            'database' => 0,
        ],

    ],

];
