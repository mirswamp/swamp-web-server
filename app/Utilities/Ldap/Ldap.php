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
|        Copyright (C) 2012-2020 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Utilities\Ldap;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use App\Models\Users\User;
use App\Models\Users\UserAccount;
use App\Utilities\Sanitization\LdapSanitize;
use ErrorException;

if (!defined('LDAP_OPT_DIAGNOSTIC_MESSAGE')) {
	define('LDAP_OPT_DIAGNOSTIC_MESSAGE',0x0032);
}

class Ldap
{
	// This array holds the LDAP connections for the web_user and the
	// password_set_user. This allows the connections to be reused
	// without multiple ldap_bind() calls. The keys of the array are the
	// user (i.e., web_user or password_set_user) and the values of the
	// array are the ldap_connect() (and ldap_bind()) values.
	//
	protected static $ldapConnection = [];

	// $userUid corresponds to the database project.user_account(user_uid)
	// entry. In LDAP terms, it matches against the configured swamp_uid_attr
	// which defaults to swampUuid. This method returns the matching LDAP
	// entry if found (or null if not found). False is returned for LDAP
	// connectivity problems.
	//
	public static function getIndex(string $userUid) {

		// connect to LDAP
		//
		$ldapConnection = static::getLdapConnection('web_user');
		if ($ldapConnection) {
			$ldapConnectionConfig = config('ldap.connection');
			$basedn = $ldapConnectionConfig['base_dn'];

			// Match the passed-in $userUid against the LDAP swamp_uid_attr
			// key (which defaults to 'swampUuid').
			//
			$filter = $ldapConnectionConfig['swamp_uid_attr'] . '=' . $userUid;
			$searchResults = ldap_search($ldapConnection, $basedn, $filter);
			$entries = ldap_get_entries($ldapConnection, $searchResults);

			// convert LDAP entry to user
			//
			if ($entries['count'] > 0) {
				return static::entrytoUser($entries[0]);
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
	//
	public static function getByUsername(string $username) {

		// connect to LDAP
		//
		$ldapConnection = static::getLdapConnection('web_user');
		if ($ldapConnection) {
			$ldapConnectionConfig = config('ldap.connection');
			$basedn = $ldapConnectionConfig['base_dn'];

			// Match the passed-in $username against the LDAP username_attr key
			// (which defaults to 'uid').
			//
			$filter = $ldapConnectionConfig['username_attr'] . '=' .
				LdapSanitize::escapeQueryValue($username);
			$searchResults = ldap_search($ldapConnection, $basedn, $filter);
			$entries = ldap_get_entries($ldapConnection, $searchResults);

			// convert LDAP entry to user
			//
			if ($entries['count'] > 0) {
				return static::entrytoUser($entries[0]);
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
	//
	public static function getByEmail(string $email) {

		// connect to LDAP
		//
		$ldapConnection = static::getLdapConnection('web_user');
		if ($ldapConnection) {
			$ldapConnectionConfig = config('ldap.connection');
			$basedn = $ldapConnectionConfig['base_dn'];
			$filter = $ldapConnectionConfig['email_attr'] . '=' .
				LdapSanitize::escapeQueryValue($email);
			$searchResults = ldap_search($ldapConnection, $basedn, $filter);
			$entries = ldap_get_entries($ldapConnection, $searchResults);

			// convert LDAP entry to user
			//
			if ($entries['count'] > 0) {
				return static::entrytoUser($entries[0]);
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
	//
	public static function getAll() {

		// connect to LDAP
		//
		$ldapConnection = static::getLdapConnection('web_user');
		if ($ldapConnection) {
			$ldapConnectionConfig = config('ldap.connection');
			$basedn = $ldapConnectionConfig['base_dn'];

			// Ask for only certain LDAP attributes
			//
			$requested = [];
			$requested[] = $ldapConnectionConfig['user_rdn_attr'];
			$requested[] = $ldapConnectionConfig['swamp_uid_attr'];
			if ($ldapConnectionConfig['firstname_attr'] != 'ignore') {
				$requested[] = $ldapConnectionConfig['firstname_attr'];
			}
			if ($ldapConnectionConfig['lastname_attr'] != 'ignore') {
				$requested[] = $ldapConnectionConfig['lastname_attr'];
			}
			if ($ldapConnectionConfig['fullname_attr'] != 'ignore') {
				$requested[] = $ldapConnectionConfig['fullname_attr'];
			}
			$requested[] = $ldapConnectionConfig['username_attr'];
			if ($ldapConnectionConfig['email_attr'] != 'ignore') {
				$requested[] = $ldapConnectionConfig['email_attr'];
			}
			if ($ldapConnectionConfig['org_attr'] != 'ignore') {
				$requested[] = $ldapConnectionConfig['org_attr'];
			}

			// Eliminate any duplicates attributes from $requested
			//
			$requested = array_keys(array_flip($requested));

			// Start with a emtpy collection of users
			//
			$users = collect(); 

			// Loop through the pages of LDAP results for all matching user rdns
			//
			$filter = '(' . $ldapConnectionConfig['user_rdn_attr'] . '=*)';
			$pageSize = 100;
			$cookie = '';
			do {
				ldap_control_paged_result($ldapConnection,$pageSize,true,$cookie);
				try {
					$searchResults = ldap_search($ldapConnection,$basedn,$filter,$requested);
					$entries = ldap_get_entries($ldapConnection,$searchResults);
					
					// convert LDAP entries to users
					//
					$users = $users->merge(static::entriesToUsers($entries));

					ldap_control_paged_result_response($ldapConnection,$searchResults,$cookie);
				} catch (\ErrorException $e) {

					// If we got here, then the LDAP server possibly returned
					// "Adminlimit exceeded" error. So just return what we have.
					//
					$cookie = '';
				}
			} while (($cookie !== null) && ($cookie != ''));

			// Turn off pagination control for future LDAP operations
			//
			ldap_control_paged_result($ldapConnection,0,false);

			// Return the merged collection of users
			//
			return $users;
		} else {
			return false;
		}
	}

	// Add a new user object to LDAP. Note this method only works when LDAP
	// read_only is false.
	//
	public static function add(User $user) {

		// check that LDAP is writeable
		//
		$ldapConnectionConfig = config('ldap.connection');
		if ($ldapConnectionConfig['read_only']) {
			throw new ErrorException(
				'Constraint violation: LDAP is configured read-only.');
		}

		// connect to LDAP
		//
		$ldapConnection = static::getLdapConnection('web_user');
		if ($ldapConnection) {

			// Note here that this is the one place where we can't use
			// swampUidToUserDN since the user's entry does not yet exist in LDAP.
			// Instead, use the configured user_rdn_attr.
			//
			$dn = $ldapConnectionConfig['user_rdn_attr'] .'='. $user->user_uid .','.
				$ldapConnectionConfig['base_dn'];
			$entry = static::newUserToEntry($user);

			// set object class
			//
			$entry['objectclass'] = preg_split('/\s*,\s*/', $ldapConnectionConfig['objectclass']);

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

			return $user;
		}
	}

	// Save existing user object to LDAP. Note this method only works when LDAP
	// read_only is false.
	public static function save(User $user) {

		// check that LDAP is writeable
		//
		$ldapConnectionConfig = config('ldap.connection');
		if ($ldapConnectionConfig['read_only']) {
			throw new ErrorException(
				'Constraint violation: LDAP is configured read-only.');
		}

		// connect to LDAP
		//
		$ldapConnection = static::getLdapConnection('web_user');
		if ($ldapConnection) {

			// Attempt to look up the existing user_uid in LDAP. If found, use
			// the $userdn returned by swampUidToUserDN. Otherwise, fall back to
			// forming the user dn from the user_rdn_attr plus base_dn.
			//
			$dn = static::swampUidToUserDN($user->user_uid);
			if (($dn === false) || (strlen($dn) == 0)) {
				$dn = $ldapConnectionConfig['user_rdn_attr'] .'='. $user->user_uid .','.
					$ldapConnectionConfig['base_dn'];
			}
			$entry = static::userToEntry($user);

			// modify attributes
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
	//
	public static function validatePassword(string $userUid, string $password) {
		$retval = false;

		$userdn = static::swampUidToUserDN($userUid);
		if (strlen($userdn) > 0) {

			// Note that this method MUST bind with the user's username
			// and password. Do not use the 'web_user' for the connection.
			//
			$ldapConnectionConfig = config('ldap.connection');
			$ldapHost = $ldapConnectionConfig['host'];
			$ldapPort = $ldapConnectionConfig['port'];

			// connect to LDAP
			//
			$ldapConnection = ldap_connect($ldapHost, $ldapPort);
			if ($ldapConnection) {
				ldap_set_option($ldapConnection, LDAP_OPT_PROTOCOL_VERSION, 3);
				$ldapbind = @ldap_bind($ldapConnection, $userdn, $password);
				if ($ldapbind) {
					Log::info("LDAP bind authenticated.", [
						'user_uid' => $userUid,
						'userdn' => $userdn,
					]);
					$retval = true;
				}
				ldap_close($ldapConnection);
			}	
		}
		return $retval;
	}

	// Modify an existing user object's password in LDAP. Note this method
	// only works when LDAP read_only is false.
	//
	public static function modifyPassword(User $user, string $password) {
		$retval = false;

		// check that LDAP is writeable
		//
		$ldapConnectionConfig = config('ldap.connection');
		if ($ldapConnectionConfig['read_only']) {
			throw new ErrorException(
				'Constraint violation: LDAP is configured read-only.');
		}

		$userdn = static::swampUidToUserDN($user->user_uid);
		if (strlen($userdn) > 0) {

			// connect to LDAP
			//
			$ldapConnection = static::getLdapConnection('password_set_user');
			if ($ldapConnection) {
				try {
					$response = ldap_modify($ldapConnection, $userdn, [
						$ldapConnectionConfig['password_attr'] => $password
					]);
				} catch (\ErrorException $e) {
					if ($e->getMessage() == "ldap_modify(): Modify: Constraint violation") {
						$errstr = ldap_error($ldapConnection);
						ldap_get_option($ldapConnection,LDAP_OPT_DIAGNOSTIC_MESSAGE,$extended_error);
						throw new ErrorException("$errstr: $extended_error");
					} else {
						throw $e;
					}
				}

				// update user_account entry
				//
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
	//
	private static function swampUidToUserDN(string $userUid) {
		$retval = false;

		// connect to LDAP
		//
		$ldapConnection = static::getLdapConnection('web_user');
		if ($ldapConnection) {
			$ldapConnectionConfig = config('ldap.connection');

			// Using the passed-in $userUid (which is the swamp identifier stored in
			// the database), search for an entry with a matching swamp_uid_attr.
			//
			$basedn = $ldapConnectionConfig['base_dn'];
			$filter = $ldapConnectionConfig['swamp_uid_attr'] . '=' . $userUid;
			$searchResults = ldap_search($ldapConnection, $basedn, $filter);
			$entries = ldap_get_entries($ldapConnection, $searchResults);

			// If we found a matching entry, use the configured 'user_rdn_attr'
			// value in the entry (plus the base_dn) as the full dn to return.
			//
			if ($entries['count'] > 0) {
				/*
				$rdnkey = $ldapConnectionConfig['user_rdn_attr'];
				$rdnval = @$entries[0][strtolower($rdnkey)][0];
				if (strlen($rdnval) > 0) {
					$retval = "$rdnkey=$rdnval,$basedn";
				}
				*/
				$retval = $entries[0]['dn'];
			}

		}	
		return $retval;
	}

	private static function entryToUser(array $entry) {
		return new User([
			'user_uid' => static::searchEntryForAttr($entry, 'swamp_uid_attr'),
			'first_name' => static::searchEntryForAttr($entry, 'firstname_attr'),
			'last_name' => static::searchEntryForAttr($entry, 'lastname_attr'),
			'preferred_name' => static::searchEntryForAttr($entry, 'fullname_attr'),
			'username' => static::searchEntryForAttr($entry, 'username_attr'),
			
			// If LDAP is not doing the password validation, then return the
			// password from LDAP so it can be validated in the application.
			//
			'password' => !config('ldap.password_validation')? static::searchEntryForAttr($entry,'password_attr') : null,

			// For email address and affiliation, remove any leading/trailing spaces
			//
			'email' => trim(static::searchEntryForAttr($entry, 'email_attr')),
			'affiliation' => trim(static::searchEntryForAttr($entry, 'org_attr'))
		]);
	}

	// Used by entryToUser, this helper function searches $entry (which has
	// all lowercase keys) for a key matching $attr as configured for LDAP.
	// If found, it returns the value of that $attr. Otherwise, it returns null.
	//
	private static function searchEntryForAttr(array $entry, string $attr) {
		$retval = null;

		// connect to LDAP
		//
		$ldapConnectionConfig = config('ldap.connection');
		if ($ldapConnectionConfig[$attr] != 'ignore') {
			$ldapAttr = strtolower($ldapConnectionConfig[$attr]);
			if (array_key_exists($ldapAttr,$entry)) {
				$retval = $entry[$ldapAttr][0];
			}
		}
		return $retval;
	}

	private static function entriesToUsers(array $entries) {
		$users = collect();
		for ($i = 0; $i < $entries['count']; $i++) {
			$users->push(static::entryToUser($entries[$i]));
		}
		return $users;
	}

	private static function newUserToEntry(User $user) {
		return static::userToEntry($user, true);
	}

	/**
	 * Converts an App\Models\Users\User object into an array suitable for
	 * writing to LDAP.
	 * @param $user The App\Models\Users\User object to convert
	 * @param $newuser Set to true for a 'new' user entry, which adds the
	 *        'password' entry to the returned array. Defaults to false.
	 * @return An array containing the parameters of the passed-in user object
	 */
	public static function userToEntry(User $user, bool $newuser=false): array {
		$retarr = [];
		$ldapConnConf = config('ldap.connection');
		$mir_swamp = config('ldap.mir_swamp');
		$retarr[$ldapConnConf['swamp_uid_attr']] = $user->user_uid;
		if ($ldapConnConf['firstname_attr'] != 'ignore') {
			$retarr[$ldapConnConf['firstname_attr']] = $user->first_name ?: 'none';
		}
		if ($ldapConnConf['lastname_attr'] != 'ignore') {
			$retarr[$ldapConnConf['lastname_attr']] = $user->last_name ?: 'none';
		}
		if ($ldapConnConf['fullname_attr'] != 'ignore') {
			$retarr[$ldapConnConf['fullname_attr']] = $user->preferred_name ?: 'none';
		}
		$retarr[$ldapConnConf['username_attr']] = $user->username;
		if ($newuser) {
			$retarr[$ldapConnConf['password_attr']] = $user->password;
		}
		if ($ldapConnConf['email_attr'] != 'ignore') {
			$retarr[$ldapConnConf['email_attr']] = $user->email ?: ' ';
		}
		if ($mir_swamp) { // Only add 'enabled' attribute for mir-swamp.org servers
			$retarr['enabled'] = 'TRUE';
		}
		if ($ldapConnConf['org_attr'] != 'ignore') {
			$retarr[$ldapConnConf['org_attr']] = $user->affiliation ?: ' ';
		}
		return $retarr;
	}

	// This function determines whether or not an LDAP connection can be made.
	//
	public static function checkLdapConnection() {
		try {

			// Try to set up the LDAP connection for the $user
			//
			$ldapConnectionConfig = config('ldap.connection');
			$ldapHost = $ldapConnectionConfig['host'];
			$ldapPort = $ldapConnectionConfig['port'];

			// connect to LDAP
			//
			$ldapConnection = ldap_connect($ldapHost, $ldapPort);
			if ($ldapConnection) {
				ldap_set_option($ldapConnection, LDAP_OPT_PROTOCOL_VERSION, 3);
				$ldapUser = $ldapConnectionConfig['users']['web_user'];
				$ldapbind = @ldap_bind($ldapConnection, $ldapUser['user'], $ldapUser['password']);
				if ($ldapbind) {
					return true;
				} else {
					return "Can not bind with LDAP user.";
				}
			} else {
				return "Can not create LDAP connection.";
			}
		} catch (\ErrorException $e) {
			return "Can not create LDAP connection.";
		}
	}

	// This function gets/sets the static class $ldapConnection array
	// variable which holds the LDAP connection (for both the web_user and
	// the password_set_user) which has been set up with ldap_connect() /
	// ldap_bind(). This allows the LDAP connection to be used multiple
	// times without having to bind more than once. Note that when using
	// this method, DO NOT call ldap_close() for the LDAP connection.
	//
	protected static function getLdapConnection(string $user) {
		$retval = null;

		// Check if we have already set up the LDAP connection for the
		// passed-in $user. If so, simply return it.
		//
		if ((array_key_exists($user,static::$ldapConnection)) &&
			(!is_null(static::$ldapConnection[$user]))
		) {
			$retval = static::$ldapConnection[$user];
		} else { 

			// Need to set up the LDAP connection for the $user
			//
			$ldapConnectionConfig = config('ldap.connection');
			$ldapHost = $ldapConnectionConfig['host'];
			$ldapPort = $ldapConnectionConfig['port'];

			// connect to LDAP
			//
			$ldapConnection = ldap_connect($ldapHost, $ldapPort);
			if ($ldapConnection) {
				ldap_set_option($ldapConnection, LDAP_OPT_PROTOCOL_VERSION, 3);
				$ldapUser = $ldapConnectionConfig['users'][$user];
				$ldapbind = @ldap_bind($ldapConnection, $ldapUser['user'], $ldapUser['password']);
				if ($ldapbind) {

					// Save it for later
					//
					static::$ldapConnection[$user] = $ldapConnection;
					$retval = $ldapConnection;
				}
			}
		}

		return $retval;
	}
}