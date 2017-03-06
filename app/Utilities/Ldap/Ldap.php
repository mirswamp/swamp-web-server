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
|        Copyright (C) 2012-2017 Software Assurance Marketplace (SWAMP)        |
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

	// $userUid corresponds to the database project.user_account(user_uid)
	// entry. In LDAP terms, it matches against the configured swamp_uid_attr
	// which defaults to swampUuid. This method returns the matching LDAP
	// entry if found (or null if not found). False is returned for LDAP
	// connectivity problems.
	public static function getIndex($userUid) {

		$ldapConnectionConfig = Config::get('ldap.connection');
		$ldapHost = $ldapConnectionConfig['host'];
		$ldapPort = $ldapConnectionConfig['port'];
		$ldapConnection = ldap_connect($ldapHost, $ldapPort);
		if ($ldapConnection) {
			ldap_set_option($ldapConnection, LDAP_OPT_PROTOCOL_VERSION, 3);
			$ldapUser = $ldapConnectionConfig['users']['web_user'];
			$ldapbind = ldap_bind($ldapConnection, $ldapUser['user'], $ldapUser['password']);
			$basedn = $ldapConnectionConfig['base_dn'];
			// Match the passed-in $userUid against the LDAP swamp_uid_attr
			// key (which defaults to 'swampUuid'). 
			$filter = $ldapConnectionConfig['swamp_uid_attr'] . '=' . $userUid;
			$searchResults = ldap_search($ldapConnection, $basedn, $filter);
			$entries = ldap_get_entries($ldapConnection, $searchResults);
			ldap_close($ldapConnection);

			// convert LDAP entry to user
			if ($entries['count'] > 0) {
				return self::entrytoUser($entries[0]);
			} else {
				return null;
			}
		} else {
			return false;
		}
	}

	// $username corresponds to the value the user inputs when logging in with
	// username/password. In LDAP terms, it matches against the configured
	// username_attr which defaults to uid. This method returns the matching
	// LDAP entry if found (or null if not found). False is returned for LDAP
	// connectivity issues.
	public static function getByUsername($username) {

		$ldapConnectionConfig = Config::get('ldap.connection');
		$ldapHost = $ldapConnectionConfig['host'];
		$ldapPort = $ldapConnectionConfig['port'];
		$ldapConnection = ldap_connect($ldapHost, $ldapPort);
		if ($ldapConnection) {
			ldap_set_option($ldapConnection, LDAP_OPT_PROTOCOL_VERSION, 3);
			$ldapUser = $ldapConnectionConfig['users']['web_user'];
			$ldapbind = ldap_bind($ldapConnection, $ldapUser['user'], $ldapUser['password']);
			$basedn = $ldapConnectionConfig['base_dn'];
			// Match the passed-in $username against the LDAP username_attr key
			// (which defaults to 'uid').
			$filter = $ldapConnectionConfig['username_attr'] . '=' . 
				LdapSanitize::escapeQueryValue($username);
			$searchResults = ldap_search($ldapConnection, $basedn, $filter);
			$entries = ldap_get_entries($ldapConnection, $searchResults);
			ldap_close($ldapConnection);

			// convert LDAP entry to user
			if ($entries['count'] > 0) {
				return self::entrytoUser($entries[0]);
			} else {
				return null;
			}
		} else {
			return false;
		}	
	}

	// $email corresponds to the user's email address. For LDAP, this is
	// matched against the configured email_attr which defaults to mail. 
	// This method returns the matching LDAP entry if found (or null if 
	// not found). False is returned for LDAP connectivity issues.
	public static function getByEmail($email) {

		$ldapConnectionConfig = Config::get('ldap.connection');
		$ldapHost = $ldapConnectionConfig['host'];
		$ldapPort = $ldapConnectionConfig['port'];
		$ldapConnection = ldap_connect($ldapHost, $ldapPort);
		if ($ldapConnection) {
			ldap_set_option($ldapConnection, LDAP_OPT_PROTOCOL_VERSION, 3);
			$ldapUser = $ldapConnectionConfig['users']['web_user'];
			$ldapbind = ldap_bind($ldapConnection, $ldapUser['user'], $ldapUser['password']);
			$basedn = $ldapConnectionConfig['base_dn'];
			$filter = $ldapConnectionConfig['email_attr'] . '=' . 
				LdapSanitize::escapeQueryValue($email);
			$searchResults = ldap_search($ldapConnection, $basedn, $filter);
			$entries = ldap_get_entries($ldapConnection, $searchResults);
			ldap_close($ldapConnection);

			// convert LDAP entry to user
			if ($entries['count'] > 0) {
				return self::entrytoUser($entries[0]);
			} else {
				return null;
			}
		} else {
			return false;
		}
	}

	// This method returns ALL entries in LDAP that live under the configured
	// base_dn. It does this using LDAP pagination control (if supported
	// by the server) since it is possible that LDAP may contain more
	// entries than can be returned by a single ldap_search call. To save
	// on the amount of data returned, only the necessary LDAP attributes
	// are returned. 
	public static function getAll() {

		$ldapConnectionConfig = Config::get('ldap.connection');
		$ldapHost = $ldapConnectionConfig['host'];
		$ldapPort = $ldapConnectionConfig['port'];
		$ldapConnection = ldap_connect($ldapHost, $ldapPort);
		if ($ldapConnection) {
			$users = new Collection(); // Start with a emtpy collection of users
			ldap_set_option($ldapConnection, LDAP_OPT_PROTOCOL_VERSION, 3);
			$ldapUser = $ldapConnectionConfig['users']['web_user'];
			$ldapbind = ldap_bind($ldapConnection, $ldapUser['user'], $ldapUser['password']);
			$basedn = $ldapConnectionConfig['base_dn'];
			// Ask for only certain LDAP attributes
			$requested = array(
				$ldapConnectionConfig['user_rdn_attr'],
				$ldapConnectionConfig['swamp_uid_attr'],
				$ldapConnectionConfig['firstname_attr'],
				$ldapConnectionConfig['lastname_attr'],
				$ldapConnectionConfig['fullname_attr'],
				$ldapConnectionConfig['username_attr'],
				$ldapConnectionConfig['email_attr'],
				$ldapConnectionConfig['address_attr'],
				$ldapConnectionConfig['phone_attr'],
				$ldapConnectionConfig['org_attr']
			);
			// Eliminate any duplicates attributes from $requested
			$requested = array_keys(array_flip($requested));

			// Loop through the pages of LDAP results for all matching user rdns
			$filter = '(' . $ldapConnectionConfig['user_rdn_attr'] . '=*)';
			$pageSize = 100;
			$cookie = '';
			do {
				ldap_control_paged_result($ldapConnection,$pageSize,true,$cookie);
				try {
					$searchResults = ldap_search($ldapConnection,$basedn,$filter,$requested);
					$entries = ldap_get_entries($ldapConnection,$searchResults);
					
					// convert LDAP entries to users
					$users = $users->merge(self::entriesToUsers($entries));

					ldap_control_paged_result_response($ldapConnection,$searchResults,$cookie);
				} catch (\ErrorException $e) {
					// If we got here, then the LDAP server possibly returned 
					// "Adminlimit exceeded" error. So just return what we have.
					$cookie = '';
				}
			} while (($cookie !== null) && ($cookie != ''));

			ldap_close($ldapConnection);

			return $users; // Return the merged collection of users
		} else {
			return false;
		}
	}

	// Add a new user object to LDAP. Note this method only works when LDAP
	// read_only is false.
	public static function add($user) {

		$ldapConnectionConfig = Config::get('ldap.connection');
		if ($ldapConnectionConfig['read_only']) {
			throw new ErrorException(
				'Constraint violation: LDAP is configured read-only.');
		}

		$ldapHost = $ldapConnectionConfig['host'];
		$ldapPort = $ldapConnectionConfig['port'];
		$ldapConnection = ldap_connect($ldapHost, $ldapPort);
		if ($ldapConnection) {
			ldap_set_option($ldapConnection, LDAP_OPT_PROTOCOL_VERSION, 3);
			$ldapUser = $ldapConnectionConfig['users']['web_user'];
			$ldapbind = ldap_bind($ldapConnection, $ldapUser['user'], $ldapUser['password']);
			// Note here that this is the one place where we can't use
			// swampUidToUserDN since the user's entry does not yet exist in LDAP.
			// Instead, use the configured user_rdn_attr.
			$dn = $ldapConnectionConfig['user_rdn_attr'] .'='. $user->user_uid .','.
				$ldapConnectionConfig['base_dn'];
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

			ldap_close($ldapConnection);

			return $user;
		}
	}

	// Save existing user object to LDAP. Note this method only works when LDAP
	// read_only is false.
	public static function save($user) {

		$ldapConnectionConfig = Config::get('ldap.connection');
		if ($ldapConnectionConfig['read_only']) {
			throw new ErrorException(
				'Constraint violation: LDAP is configured read-only.');
		}

		$ldapHost = $ldapConnectionConfig['host'];
		$ldapPort = $ldapConnectionConfig['port'];
		$ldapConnection = ldap_connect($ldapHost, $ldapPort);
		if ($ldapConnection) {
			ldap_set_option($ldapConnection, LDAP_OPT_PROTOCOL_VERSION, 3);
			$ldapUser = $ldapConnectionConfig['users']['web_user'];
			$ldapbind = ldap_bind($ldapConnection, $ldapUser['user'], $ldapUser['password']);
			// Attempt to look up the existing user_uid in LDAP. If found, use
			// the $userdn returned by swampUidToUserDN. Otherwise, fall back to
			// forming the user dn from the user_rdn_attr plus base_dn.
			$dn = self::swampUidToUserDN($user->user_uid);
			if (($dn === false) || (strlen($dn) == 0)) {
				$dn = $ldapConnectionConfig['user_rdn_attr'] .'='. $user->user_uid .','.
					$ldapConnectionConfig['base_dn'];
			}
			$entry = self::userToEntry($user);

			// LDAP blank affiliation
			if ($user->affiliation == null) {

				// delete empty affiliation attribute
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
			if ($user->phone == null) {

				// delete empty phone attribute
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

			ldap_close($ldapConnection);

			return $user;
		}
	}

	// $userUid corresponds to the database project.user_account(user_uid)
	// entry. In LDAP terms, it matches against the configured swamp_uid_attr
	// which defaults to swampUuid. Note that this is probably NOT the same as
	// the LDAP configured username_attr (which corresponds to the username
	// entered by the user). So this method is validating a password against
	// the database swamp_uid. To do this, we first search LDAP for an
	// entry with a matching swamp_uid_attr $userUid. If we find a match, we
	// look up the configured user_rdn_attr for the entry, and prepend that
	// to the configured base_dn. This is the full 'dn' for the given user.
	// We then attempt to bind to LDAP with that dn and passed-in $password.
	// A successful bind means that the password is valid.
	public static function validatePassword($userUid,$password) {
		$retval = false;

		$userdn = self::swampUidToUserDN($userUid);
		if (strlen($userdn) > 0) {
			$ldapConnectionConfig = Config::get('ldap.connection');
			$ldapHost = $ldapConnectionConfig['host'];
			$ldapPort = $ldapConnectionConfig['port'];
			$ldapConnection = ldap_connect($ldapHost, $ldapPort);
			if ($ldapConnection) {
				ldap_set_option($ldapConnection, LDAP_OPT_PROTOCOL_VERSION, 3);
				$ldapbind = @ldap_bind($ldapConnection, $userdn, $password);
				if ($ldapbind) {
					$retval = true;
				}
				ldap_close($ldapConnection);
			}	
		}
		return $retval;
	}

	// Modify an existing user object's password in LDAP. Note this method
	// only works when LDAP read_only is false.
	public static function modifyPassword($user, $password) {
		$retval = false;

		$ldapConnectionConfig = Config::get('ldap.connection');
		if ($ldapConnectionConfig['read_only']) {
			throw new ErrorException(
				'Constraint violation: LDAP is configured read-only.');
		}

		$userdn = self::swampUidToUserDN($user->user_uid);
		if (strlen($userdn) > 0) {
			$ldapHost = $ldapConnectionConfig['host'];
			$ldapPort = $ldapConnectionConfig['port'];
			$ldapConnection = ldap_connect($ldapHost, $ldapPort);
			if ($ldapConnection) {
				ldap_set_option($ldapConnection, LDAP_OPT_PROTOCOL_VERSION, 3);
				$ldapUser = $ldapConnectionConfig['users']['password_set_user'];
				$ldapbind = ldap_bind($ldapConnection, $ldapUser['user'], $ldapUser['password']);
				try {
					$response = ldap_modify($ldapConnection, $userdn, array(
						$ldapConnectionConfig['password_attr'] => $password
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
				ldap_close($ldapConnection);

				// update user_account entry
				$userAccount = UserAccount::where('user_uid', '=', $user->user_uid)->first();
				$userAccount->ldap_profile_update_date = gmdate('Y-m-d H:i:s');
				$userAccount->save();

				$retval = $user;
			}
		}
		return $retval;
	}

	//
	// conversion methods
	//

	// This method takes in a $userUid corresponding to the database
	// project.user_account(user_uid), looks in LDAP for a matching entry,
	// and returns the full 'dn' for that entry. This can be used to
	// bind against LDAP for a particular user. Upon error, false is returned.
	private static function swampUidToUserDN($userUid) {
		$retval = false;

		$ldapConnectionConfig = Config::get('ldap.connection');
		$ldapHost = $ldapConnectionConfig['host'];
		$ldapPort = $ldapConnectionConfig['port'];
		$ldapConnection = ldap_connect($ldapHost, $ldapPort);
		if ($ldapConnection) {
			// Using the passed-in $userUid (which is the swamp identifier stored in
			// the database), search for an entry with a matching swamp_uid_attr.
			ldap_set_option($ldapConnection, LDAP_OPT_PROTOCOL_VERSION, 3);
			$ldapUser = $ldapConnectionConfig['users']['web_user'];
			$ldapbind = ldap_bind($ldapConnection, $ldapUser['user'], $ldapUser['password']);
			$basedn = $ldapConnectionConfig['base_dn'];
			$filter = $ldapConnectionConfig['swamp_uid_attr'] . '=' . $userUid;
			$searchResults = ldap_search($ldapConnection, $basedn, $filter);
			$entries = ldap_get_entries($ldapConnection, $searchResults);
			ldap_close($ldapConnection);

			// If we found a matching entry, use the configured 'user_rdn_attr'
			// value in the entry (plus the base_dn) as the full dn to return.
			if ($entries['count'] > 0) {
				$rdnkey = $ldapConnectionConfig['user_rdn_attr'];
				$rdnval = @$entries[0][strtolower($rdnkey)][0];
				if (strlen($rdnval) > 0) {
					$retval = "$rdnkey=$rdnval,$basedn";
				}
			}
		}	
		return $retval;
	}

	private static function entryToUser($entry) {
		// Active Directory street addresses are Base64 encoded
		$address = self::searchEntryForAttr($entry,'address_attr');
		/*
		if (self::isBase64Encoded($address)) {
			$address = base64_decode($address);
		}
		*/
		$user =  new User(array(
			'user_uid' => self::searchEntryForAttr($entry,'swamp_uid_attr'),
			'first_name' => self::searchEntryForAttr($entry,'firstname_attr'),
			'last_name' => self::searchEntryForAttr($entry,'lastname_attr'),
			'preferred_name' => self::searchEntryForAttr($entry,'fullname_attr'),
			'username' => self::searchEntryForAttr($entry,'username_attr'),
			'password' => null, // Don't need LDAP password for user authn
			'email' => self::searchEntryForAttr($entry,'email_attr'),
			'address' => $address,
			'phone' => self::searchEntryForAttr($entry,'phone_attr'),
			'affiliation' => self::searchEntryForAttr($entry,'org_attr'),
		));
		return $user;
	}

	// Used by entryToUser, this helper function searches $entry (which has
	// all lowercase keys) for a key matching $attr as configured for LDAP. 
	// If found, it returns the value of that $attr. Otherwise, it returns null.
	private static function searchEntryForAttr($entry,$attr) {
		$retval = null;
		$ldapConnectionConfig = Config::get('ldap.connection');
		$ldapAttr = strtolower($ldapConnectionConfig[$attr]);
		if (array_key_exists($ldapAttr,$entry)) {
			$retval = $entry[$ldapAttr][0];
		}
		return $retval;
	}

	private static function entriesToUsers($entries) {
		$users = new Collection();
		for ($i = 0; $i < $entries['count']; $i++) {
			$users->push(self::entryToUser($entries[$i]));
		}
		return $users;
	}

	private static function newUserToEntry($user) {
		// prepare LDAP info
		$ldapConnectionConfig = Config::get('ldap.connection');
		return array(
			$ldapConnectionConfig['swamp_uid_attr'] => $user->user_uid,
			$ldapConnectionConfig['firstname_attr'] => $user->first_name ?: 'none',
			$ldapConnectionConfig['lastname_attr'] => $user->last_name ?: 'none',
			$ldapConnectionConfig['fullname_attr'] => $user->preferred_name ?: 'none',
			$ldapConnectionConfig['username_attr'] => $user->username,
			$ldapConnectionConfig['password_attr'] => $user->password,
			$ldapConnectionConfig['email_attr'] => $user->email,
			'enabled' => 'TRUE',
			$ldapConnectionConfig['address_attr'] => $user->address,
		);
	}

	public static function userToEntry($user) {
		// prepare LDAP info
		$ldapConnectionConfig = Config::get('ldap.connection');
		return array(
			$ldapConnectionConfig['swamp_uid_attr'] => $user->user_uid,
			$ldapConnectionConfig['firstname_attr'] => $user->first_name,
			$ldapConnectionConfig['lastname_attr'] => $user->last_name,
			$ldapConnectionConfig['fullname_attr'] => $user->preferred_name ?: 'none',
			$ldapConnectionConfig['username_attr'] => $user->username,
			$ldapConnectionConfig['email_attr'] => $user->email,
			'enabled' => 'TRUE',
			$ldapConnectionConfig['address_attr'] => $user->address,
			$ldapConnectionConfig['phone_attr'] => $user->phone,
			$ldapConnectionConfig['org_attr'] => $user->affiliation,
		);
	}

	// Taken from http://stackoverflow.com/a/34982057 , find out if
	// a string is base64 encoded.
	public static function isBase64Encoded($data) {
		$retval = false;
		if (preg_match('%^[a-zA-Z0-9/+]*={0,2}$%', $data)) {
			$retval = true;
		}
		return $retval;
	}

}
