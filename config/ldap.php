<?php

return array(

	/*
	|--------------------------------------------------------------------------
	| LDAP Enabled
	|--------------------------------------------------------------------------
	|
	| If LDAP is not enabled, we will use the SQL database instead.
	|
	*/

	'enabled' => env('LDAP_ENABLED', false),

	/*
	|--------------------------------------------------------------------------
	| LDAP Enabled
	|--------------------------------------------------------------------------
	|
	| If LDAP is not enabled, we will use the SQL database instead.
	|
	*/

	'password_validation' => env('LDAP_PASSWORD_VALIDATION', false),

	/*
	|--------------------------------------------------------------------------
	| LDAP at Production www.mir-swamp.org
	|--------------------------------------------------------------------------
	|
	| Set this to 'true' for the production LDAP server used by
	| www.mir-swamp.org. Essentially, this adds an 'enabled' attribute to
	| all user entries as required due to historical setup. For non-production
	| LDAP servers, this should be false, which will UNset the 'enabled'
	| attribute for user entries.
	|
	*/

	'mir_swamp' => env('LDAP_MIR_SWAMP', true),

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
		'read_only' => env('LDAP_READ_ONLY',false),
		'base_dn' => env('LDAP_BASE_DN','ou=people,o=SWAMP,dc=cosalab,dc=org'),
		'user_rdn_attr' => env('LDAP_USER_RDN_ATTR','swampUuid'),
		'swamp_uid_attr' => env('LDAP_SWAMP_UID_ATTR','swampUuid'),
		'firstname_attr' => env('LDAP_FIRSTNAME_ATTR','givenName'),
		'lastname_attr' => env('LDAP_LASTNAME_ATTR','sn'),
		'fullname_attr' => env('LDAP_FULLNAME_ATTR','cn'),
		'password_attr' => env('LDAP_PASSWORD_ATTR','userPassword'),
		'username_attr' => env('LDAP_USERNAME_ATTR','uid'),
		'email_attr' => env('LDAP_EMAIL_ATTR','mail'),
		'org_attr' => env('LDAP_ORG_ATTR','o'),
		'objectclass' => env('LDAP_OBJECTCLASS','top,person,organizationalPerson,inetOrgPerson,eduPerson,swampEntity'),
		'users' => array(
			'web_user' => array(
				'user' => env('LDAP_WEB_USER',null),
				'password' => env('LDAP_WEB_USER_PASSWORD',null)
			),
			'password_set_user' => array(
				'user' => env('LDAP_PASSWORD_SET_USER',null),
				'password' => env('LDAP_PASSWORD_SET_USER_PASSWORD',null)
			)
		)
	)
);
