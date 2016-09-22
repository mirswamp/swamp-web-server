<?php
/******************************************************************************\
|                                                                              |
|                                   Ldap.php                                   |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a utility for connecting to LDAP servers and             |
|        reading and writing personal user information to the LDAP             |
|        database.                                                             |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        Copyright (C) 2012-2016 SWAMP - Software Assurance Marketplace        |
|        Morgridge Institute for Research                                      |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2016 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Utilities\Ldap;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\App;
use App\Models\Users\User;
use App\Models\Users\UserAccount;
use App\Utilities\Sanitization\LdapSanitize;
use ErrorException;

if (!defined('LDAP_OPT_DIAGNOSTIC_MESSAGE')) {
	define('LDAP_OPT_DIAGNOSTIC_MESSAGE',0x0032);
}

class Ldap {

	//
	// querying methods
	//

	public static function getIndex($userUid) {

		// create LDAP connection
		//
		$ldapConnectionConfig = Config::get('ldap.connection');
		$ldapHost = $ldapConnectionConfig['host'];
		$ldapPort = $ldapConnectionConfig['port'];
		$ldapConnection = ldap_connect($ldapHost, $ldapPort);
		if ($ldapConnection) {

			// query LDAP for user info
			//
			ldap_set_option($ldapConnection, LDAP_OPT_PROTOCOL_VERSION, 3);
			$ldapUser = $ldapConnectionConfig['users']['web_user'];
			$ldapbind = ldap_bind($ldapConnection, $ldapUser['user'], $ldapUser['password']);
			$dn = 'ou=people,o=SWAMP,dc=cosalab,dc=org';
			$filter = 'swampUuid='.$userUid;
			$searchResults = ldap_search($ldapConnection, $dn, $filter);
			$entries = ldap_get_entries($ldapConnection, $searchResults);

			// close LDAP connection
			//
			ldap_close($ldapConnection);

			// convert LDAP entry to user
			//
			if (sizeof($entries) > 1) {
				return self::entrytoUser($entries[0]);
			} else {
				return null;
			}
		} else {
			return false;
		}
	}

	public static function getByUsername($username) {

		// create LDAP connection
		//
		$ldapConnectionConfig = Config::get('ldap.connection');
		$ldapHost = $ldapConnectionConfig['host'];
		$ldapPort = $ldapConnectionConfig['port'];
		$ldapConnection = ldap_connect($ldapHost, $ldapPort);
		if ($ldapConnection) {

			// query LDAP for user info
			//
			ldap_set_option($ldapConnection, LDAP_OPT_PROTOCOL_VERSION, 3);
			$ldapUser = $ldapConnectionConfig['users']['web_user'];
			$ldapbind = ldap_bind($ldapConnection, $ldapUser['user'], $ldapUser['password']);
			$dn = 'ou=people,o=SWAMP,dc=cosalab,dc=org';
			$filter = 'uid='.LdapSanitize::escapeQueryValue($username);
			$searchResults = ldap_search($ldapConnection, $dn, $filter);
			$entries = ldap_get_entries($ldapConnection, $searchResults);

			// close LDAP connection
			//
			ldap_close($ldapConnection);

			// convert LDAP entry to user
			//
			if (sizeof($entries) > 1) {
				return self::entrytoUser($entries[0]);
			} else {
				return null;
			}
		} else {
			return false;
		}	
	}

	public static function getByEmail($email) {

		// create LDAP connection
		//
		$ldapConnectionConfig = Config::get('ldap.connection');
		$ldapHost = $ldapConnectionConfig['host'];
		$ldapPort = $ldapConnectionConfig['port'];
		$ldapConnection = ldap_connect($ldapHost, $ldapPort);
		if ($ldapConnection) {

			// query LDAP for user info
			//
			ldap_set_option($ldapConnection, LDAP_OPT_PROTOCOL_VERSION, 3);
			$ldapUser = $ldapConnectionConfig['users']['web_user'];
			$ldapbind = ldap_bind($ldapConnection, $ldapUser['user'], $ldapUser['password']);
			$dn = 'ou=people,o=SWAMP,dc=cosalab,dc=org';
			$filter = 'mail='.LdapSanitize::escapeQueryValue($email);
			$searchResults = ldap_search($ldapConnection, $dn, $filter);
			$entries = ldap_get_entries($ldapConnection, $searchResults);

			// close LDAP connection
			//
			ldap_close($ldapConnection);

			// convert LDAP entry to user
			//
			if (sizeof($entries) > 1) {
				return self::entrytoUser($entries[0]);
			} else {
				return null;
			}
		} else {
			return false;
		}
	}

	public static function getAll() {

		// create LDAP connection
		//
		$ldapConnectionConfig = Config::get('ldap.connection');
		$ldapHost = $ldapConnectionConfig['host'];
		$ldapPort = $ldapConnectionConfig['port'];
		$ldapConnection = ldap_connect($ldapHost, $ldapPort);
		if ($ldapConnection) {

			// query LDAP for user info
			//
			ldap_set_option($ldapConnection, LDAP_OPT_PROTOCOL_VERSION, 3);
			$ldapUser = $ldapConnectionConfig['users']['web_user'];
			$ldapbind = ldap_bind($ldapConnection, $ldapUser['user'], $ldapUser['password']);
			$dn = 'ou=people,o=SWAMP,dc=cosalab,dc=org';
			$filter = 'sn=*';
			$searchResults = ldap_search($ldapConnection, $dn, $filter);
			$entries = ldap_get_entries($ldapConnection, $searchResults);

			// close LDAP connection
			//
			ldap_close($ldapConnection);

			// convert LDAP entries to users
			//
			return self::entriesToUsers($entries);

		} else {
			return false;
		}
	}

	public static function add($user) {

		// create LDAP Connection
		//
		$ldapConnectionConfig = Config::get('ldap.connection');
		$ldapHost = $ldapConnectionConfig['host'];
		$ldapPort = $ldapConnectionConfig['port'];
		$ldapConnection = ldap_connect($ldapHost, $ldapPort);
		if ($ldapConnection) {

			// query LDAP for user info
			//
			ldap_set_option($ldapConnection, LDAP_OPT_PROTOCOL_VERSION, 3);
			$ldapUser = $ldapConnectionConfig['users']['web_user'];
			$ldapbind = ldap_bind($ldapConnection, $ldapUser['user'], $ldapUser['password']);
			$dn = 'swampUuid='.$user->user_uid.',ou=people,o=SWAMP,dc=cosalab,dc=org';
			$entry = self::newUserToEntry($user);

			// set object class
			//
			$entry['objectclass'][0] = "top";
			$entry['objectclass'][1] = "person";
			$entry['objectclass'][2] = "organizationalPerson";
			$entry['objectclass'][3] = "inetOrgPerson";
			$entry['objectclass'][4] = "eduPerson";
			$entry['objectclass'][5] = "swampEntity";

			// add new object
			//
			try {
				$response = ldap_add($ldapConnection, $dn, $entry);
			} catch (\ErrorException $e) {
				if ($e->getMessage() == "ldap_add(): Add: Constraint violation") {
					$errstr = ldap_error($ldapConnection);
					ldap_get_option($ldapConnection,LDAP_OPT_DIAGNOSTIC_MESSAGE,$extended_error);
					throw new ErrorException("$errstr: $extended_error");
				} else {
					throw $e;
				}
			}

			// close LDAP connection
			//
			ldap_close($ldapConnection);

			return $user;
		}
	}

	public static function save($user) {

		// create LDAP connection
		//
		$ldapConnectionConfig = Config::get('ldap.connection');
		$ldapHost = $ldapConnectionConfig['host'];
		$ldapPort = $ldapConnectionConfig['port'];
		$ldapConnection = ldap_connect($ldapHost, $ldapPort);
		if ($ldapConnection) {

			// query LDAP for user info
			//
			ldap_set_option($ldapConnection, LDAP_OPT_PROTOCOL_VERSION, 3);
			$ldapUser = $ldapConnectionConfig['users']['web_user'];
			$ldapbind = ldap_bind($ldapConnection, $ldapUser['user'], $ldapUser['password']);
			$dn = 'swampUuid='.$user->user_uid.',ou=people,o=SWAMP,dc=cosalab,dc=org';
			$entry = self::userToEntry($user);

			// LDAP blank affiliation
			//
			if ($user->affiliation == null) {

				// delete empty affiliation attribute
				//
				unset($entry["o"]);

				try {
					$response = ldap_mod_del($ldapConnection, $dn, array('o' => array()));
				} 
				catch(\ErrorException $e) {

					// trying to clear out attribute that is already cleared
					//
					if ($e->getMessage() != "ldap_mod_del(): Modify: No such attribute") {
						throw $e;
					}
				}
			}

			// LDAP blank telephone
			//
			if ($user->phone == null) {

				// delete empty phone attribute
				//
				unset($entry["telephoneNumber"]);

				try {
					$response = ldap_mod_del($ldapConnection, $dn, array('telephoneNumber' => array()));
				}
				catch(\ErrorException $e) {

					// trying to clear out attribute that is already cleared
					//
					if ($e->getMessage() != "ldap_mod_del(): Modify: No such attribute") {
						throw $e;
					}
				}
			}

			// modify remaining attributes
			//
			try {
				$response = ldap_modify($ldapConnection, $dn, $entry);
			} catch (\ErrorException $e) {
				if ($e->getMessage() == "ldap_modify(): Modify: Constraint violation") {
					$errstr = ldap_error($ldapConnection);
					ldap_get_option($ldapConnection,LDAP_OPT_DIAGNOSTIC_MESSAGE,$extended_error);
					throw new ErrorException("$errstr: $extended_error");
				} else {
					throw $e;
				}
			}

			// close LDAP connection
			//
			ldap_close($ldapConnection);

			return $user;
		}
	}

	public static function modifyPassword($user, $password) {

		// create LDAP connection
		//
		$ldapConnectionConfig = Config::get('ldap.connection');
		$ldapHost = $ldapConnectionConfig['host'];
		$ldapPort = $ldapConnectionConfig['port'];
		$ldapConnection = ldap_connect($ldapHost, $ldapPort);
		if ($ldapConnection) {

			// query LDAP for user info
			//
			ldap_set_option($ldapConnection, LDAP_OPT_PROTOCOL_VERSION, 3);
			$ldapUser = $ldapConnectionConfig['users']['password_set_user'];
			$ldapbind = ldap_bind($ldapConnection, $ldapUser['user'], $ldapUser['password']);
			$dn = 'swampUuid='.$user->user_uid.',ou=people,o=SWAMP,dc=cosalab,dc=org';
			try {
				$response = ldap_modify($ldapConnection, $dn, array(
					'userPassword' => $password
				));
			} catch (\ErrorException $e) {
				if ($e->getMessage() == "ldap_modify(): Modify: Constraint violation") {
					$errstr = ldap_error($ldapConnection);
					ldap_get_option($ldapConnection,LDAP_OPT_DIAGNOSTIC_MESSAGE,$extended_error);
					throw new ErrorException("$errstr: $extended_error");
				} else {
					throw $e;
				}
			}

			// close LDAP connection
			//
			ldap_close($ldapConnection);

			// update user_account entry
			//
			$userAccount = UserAccount::where('user_uid', '=', $user->user_uid)->first();
			$userAccount->ldap_profile_update_date = gmdate('Y-m-d H:i:s');
			$userAccount->save();

			return $user;
		}
	}

	//
	// conversion methods
	//

	private static function entryToUser($entry) {
		$user =  new User(array(
			'user_uid' => $entry['swampuuid'][0],
			'first_name' => array_key_exists('givenname', $entry)? $entry['givenname'][0] : null,
			'last_name' => array_key_exists('sn', $entry)? $entry['sn'][0] : null,
			'preferred_name' => array_key_exists('cn', $entry)? $entry['cn'][0] : null,
			'username' => array_key_exists('uid', $entry)? $entry['uid'][0] : null,
			'password' => array_key_exists('userpassword', $entry)? $entry['userpassword'][0] : null,
			'email' => array_key_exists('mail', $entry)? $entry['mail'][0] : null,
			'address' => array_key_exists('postaladdress', $entry)? $entry['postaladdress'][0] : null,
			'phone' => array_key_exists('telephonenumber', $entry)? $entry['telephonenumber'][0] : null,
			'affiliation' => array_key_exists('o', $entry)? $entry['o'][0] : null,
		));
		return $user;
	}

	private static function entriesToUsers($entries) {
		$users = new Collection();
		for ($i = 0; $i < sizeof($entries)- 1; $i++) {
			$users->push(self::entryToUser($entries[$i]));
		}
		return $users;
	}

	private static function newUserToEntry($user) {

		// prepare LDAP info
		//
		return array(
			'swampUuid' => $user->user_uid,
			'givenName' => $user->first_name ?: 'none',
			'sn' => $user->last_name ?: 'none',
			'cn' => 'none',
			'uid' => $user->username,
			'userPassword' => $user->password,
			'mail' => $user->email,
			'enabled' => 'TRUE',
			'postalAddress' => ''
		);
	}

	public static function userToEntry($user) {

		// prepare LDAP info
		//
		return array(
			'swampUuid' => $user->user_uid,
			'givenName' => $user->first_name,
			'sn' => $user->last_name,
			'cn' => $user->preferred_name ? $user->preferred_name : 'none',
			'uid' => $user->username,
			'mail' => $user->email,
			'enabled' => 'TRUE',
			'postalAddress' => $user->address,
			'telephoneNumber' => $user->phone,
			'o' => $user->affiliation
		);
	}
}
