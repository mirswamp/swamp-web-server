<?php

return array(

	/*
	|--------------------------------------------------------------------------
	| LDAP Enbled
	|--------------------------------------------------------------------------
	|
	| If LDAP is not enabled, we will use the SQL database instead.
	|
	*/

	'enabled' => env('LDAP_ENABLED', false),

	/*
	|--------------------------------------------------------------------------
	| LDAP Connection
	|--------------------------------------------------------------------------
	|
	| Here is the LDAP connection and the associated users to use.
	|
	*/

	'connection' => array(
		'host' => env('LDAP_HOST'),
		'port' => env('LDAP_PORT', 636),
		'users' => array(
			'web_user' => array(
				'user' => env('LDAP_WEB_USER'),
				'password' => env('LDAP_WEB_USER_PASSWORD')
			),
			'data_change_user' => array(
				'user' => env('LDAP_DATA_CHANGE_USER'),
				'password' => env('LDAP_DATA_CHANGE_USER_PASSWORD')
			),
			'password_set_user' => array(
				'user' => env('LDAP_PASSWORD_SET_USER'),
				'password' => env('LDAP_PASSWORD_SET_USER_PASSWORD')
			)
		)
	)
);
