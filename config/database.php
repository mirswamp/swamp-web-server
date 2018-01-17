<?php

return array(

	/*
	|--------------------------------------------------------------------------
	| PDO Fetch Style
	|--------------------------------------------------------------------------
	|
	| By default, database results will be returned as instances of the PHP
	| stdClass object; however, you may desire to retrieve records in an
	| array format for simplicity. Here you can tweak the fetch style.
	|
	*/

	'fetch' => PDO::FETCH_CLASS,

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

	'default' => 'project',

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
			'driver'   => 'sqlite',
			'database' => storage_path().'/database.sqlite',
			'prefix'   => '',
		],

		'project' => array(
			'driver'    => 'mysql',
			'host'      => env('PROJECT_DB_HOST', '127.0.0.1'),
			'port'      => env('PROJECT_DB_PORT', '3306'),
			'database'  => env('PROJECT_DB_DATABASE', 'project'),
			'username'  => env('PROJECT_DB_USERNAME', 'root'),
			'password'  => env('PROJECT_DB_PASSWORD', 'root'),
			'charset'   => 'utf8',
			'collation' => 'utf8_unicode_ci',
			'prefix'    => '',
		),

		'package_store' => array(
			'driver'    => 'mysql',
			'host'      => env('PACKAGE_DB_HOST', '127.0.0.1'),
			'port'      => env('PACKAGE_DB_PORT', '3306'),
			'database'  => env('PACKAGE_DB_DATABASE', 'package_store'),
			'username'  => env('PACKAGE_DB_USERNAME', 'root'),
			'password'  => env('PACKAGE_DB_PASSWORD', 'root'),
			'charset'   => 'utf8',
			'collation' => 'utf8_unicode_ci',
			'prefix'    => '',
		),

		'tool_shed' => array(
			'driver'    => 'mysql',
			'host'      => env('TOOL_DB_HOST', '127.0.0.1'),
			'port'      => env('TOOL_DB_PORT', '3306'),
			'database'  => env('TOOL_DB_DATABASE', 'tool_shed'),
			'username'  => env('TOOL_DB_USERNAME', 'root'),
			'password'  => env('TOOL_DB_PASSWORD', 'root'),
			'charset'   => 'utf8',
			'collation' => 'utf8_unicode_ci',
			'prefix'    => '',
		),

		'platform_store' => array(
			'driver'    => 'mysql',
			'host'      => env('PLATFORM_DB_HOST', '127.0.0.1'),
			'port'      => env('PLATFORM_DB_PORT', '3306'),
			'database'  => env('PLATFORM_DB_DATABASE', 'platform_store'),
			'username'  => env('PLATFORM_DB_USERNAME', 'root'),
			'password'  => env('PLATFORM_DB_PASSWORD', 'root'),
			'charset'   => 'utf8',
			'collation' => 'utf8_unicode_ci',
			'prefix'    => '',
		),

		'assessment' => array(
			'driver'    => 'mysql',
			'host'      => env('ASSESSMENT_DB_HOST', '127.0.0.1'),
			'port'      => env('ASSESSMENT_DB_PORT', '3306'),
			'database'  => env('ASSESSMENT_DB_DATABASE', 'assessment'),
			'username'  => env('ASSESSMENT_DB_USERNAME', 'root'),
			'password'  => env('ASSESSMENT_DB_PASSWORD', 'root'),
			'charset'   => 'utf8',
			'collation' => 'utf8_unicode_ci',
			'prefix'    => '',
		),

		'viewer_store' => array(
			'driver'    => 'mysql',
			'host'      => env('VIEWER_DB_HOST', '127.0.0.1'),
			'port'      => env('VIEWER_DB_PORT', '3306'),
			'database'  => env('VIEWER_DB_DATABASE', 'viewer_store'),
			'username'  => env('VIEWER_DB_USERNAME', 'root'),
			'password'  => env('VIEWER_DB_PASSWORD', 'root'),
			'charset'   => 'utf8',
			'collation' => 'utf8_unicode_ci',
			'prefix'    => '',
		),

		'pgsql' => [
			'driver'   => 'pgsql',
			'host'     => env('DB_HOST', 'localhost'),
			'port'     => env('DB_PORT', '3306'),
			'database' => env('DB_DATABASE', 'forge'),
			'username' => env('DB_USERNAME', 'forge'),
			'password' => env('DB_PASSWORD', ''),
			'charset'  => 'utf8',
			'prefix'   => '',
			'schema'   => 'public',
		],

		'sqlsrv' => [
			'driver'   => 'sqlsrv',
			'host'     => env('DB_HOST', 'localhost'),
			'port'     => env('DB_PORT', '3306'),
			'database' => env('DB_DATABASE', 'forge'),
			'username' => env('DB_USERNAME', 'forge'),
			'password' => env('DB_PASSWORD', ''),
			'prefix'   => '',
		],

	],

	/*
	|--------------------------------------------------------------------------
	| Migration Repository Table
	|--------------------------------------------------------------------------
	|
	| This table keeps track of all the migrations that have already run for
	| your application. Using this information, we can determine which of
	| the migrations on disk have not actually be run in the databases.
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

	'redis' => array(

		'cluster' => true,

		'default' => array(
			'host'     => '127.0.0.1',
			'port'     => 6379,
			'database' => 0,
		),

	),

);