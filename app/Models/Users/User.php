<?php
/******************************************************************************\
|                                                                              |
|                                  User.php                                    |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a model of user's personal information.                  |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2017 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\Users;

use PDO;

use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Auth\UserInterface;
use Illuminate\Auth\Reminders\RemindableInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Session;
use App\Models\BaseModel;
use App\Models\TimeStamps\TimeStamped;
use App\Models\Users\AppPassword;
use App\Models\Users\EmailVerification;
use App\Models\Users\Permission;
use App\Models\Users\UserPermission;
use App\Models\Users\UserAccount;
use App\Models\Users\UserEvent;
use App\Models\Users\LinkedAccount;
use App\Models\Admin\RestrictedDomain;
use App\Models\Projects\Project;
use App\Models\Projects\ProjectMembership;
use App\Models\Utilities\Configuration;
use App\Utilities\Identity\IdentityProvider;
use App\Utilities\Ldap\Ldap;

class User extends TimeStamped {

	/**
	 * database attributes
	 */
	protected $table = 'user';
	protected $primaryKey = 'user_id';

	/**
	 * mass assignment policy
	 */
	protected $fillable = array(
		'user_uid',

		// personal attributes
		//
		'first_name', 
		'last_name', 
		'preferred_name', 
		'username', 
		'password',
		'email', 
		'affiliation'
	);

	/**
	 * array / json conversion whitelist
	 */
	protected $visible = array(
		'user_uid',

		// personal attributes
		//
		'first_name', 
		'last_name', 
		'preferred_name', 
		'username', 
		'email', 
		'affiliation',

		// boolean flag attributes
		//
		'enabled_flag',
		'admin_flag',		
		'email_verified_flag',
		'forcepwreset_flag',
		'hibernate_flag',
		'owner_flag',
		'ssh_access_flag',
		'has_linked_account',

		// user account attributes
		//
		'user_type',
		'ultimate_login_date', 
		'penultimate_login_date',
		'create_date',
		'update_date'
	);

	/**
	 * array / json appended model attributes
	 */
	protected $appends = array(
		'enabled_flag',
		'admin_flag',
		'email_verified_flag',
		'forcepwreset_flag',
		'hibernate_flag',
		'owner_flag',
		'ssh_access_flag',
		'has_linked_account',
		'user_type',
		'ultimate_login_date', 
		'penultimate_login_date',
		'create_date',
		'update_date'
	);

	/**
	 * user comparison method (can't use default because of LDAP)
	 */

	public function isSameAs($user) {
		return $user && $this['user_uid'] == $user['user_uid'];
	}

	public function isNew() {
		return $this['user_uid'] == NULL;
	}

	/**
	 * Get the unique identifier for the user.
	 *
	 * @return mixed
	 */
	public function getFullName() {
		return $this->first_name.' '.$this->last_name;
	}

	/**
	 * user validation method
	 */

	public static function emailInUse($email) {
		$values = array();
		if (preg_match("/(\w*)(\+.*)(@.*)/", $email, $values)) {
			$email = $values[1] . $values[3];
		}

		foreach (self::getAll() as $registered_user) {
			$values = array();
			if (preg_match("/(\w*)(\+.*)(@.*)/", $registered_user->email, $values)) {
				$registered_user->email = $values[1] . $values[3];
			}
			if (strtolower($email) == strtolower( $registered_user->email)) {
				return true;
			}
		}
		return false;		
	}

	public function isValid(&$errors, $anyEmail = false) {

		// check username
		//
		if ($this->isNew()) {

			// check to see if username has been taken
			//
			if ($this->username) {
				if (User::getByUsername($this->username)) {
					$errors[] = 'The username "'.$this->username.'" is already in use.';
				}
			}
		} else {

			// check to see if username has changed
			//
			$user = User::getIndex($this->user_uid);
			if ($user && $this->username != $user->username) {

				// check to see if username has been taken
				//
				if ($this->username) {
					if (User::getByUsername($this->username)) {
						$errors[] = 'The username "'.$this->username.'" is already in use.';
					}
				}
			}
		}

		// check email address
		//
		if ($this->isNew()) {

			// check to see if email has been taken
			//
			if ($this->email) {
				if (self::emailInUse($this->email)) {
					$errors[] = 'The email address "'.$this->email.'" is already in use. Try linking to the account instead.';
				}
			}
		} else {

			// check to see if email has changed
			//
			$user = User::getIndex($this->user_uid);
			if ($user && $this->email != $user->email) {

				// check to see if email has been taken
				//
				if ($this->email) {
					if (self::emailInUse($this->email)) {
						$errors[] = 'The email address "'.$this->email.'" is already in use. Try linking to the account instead.';
					}
				}
			}
		}

		// promo code presence check
		//
		$promo_found = false;
		if (Input::has('promo')) {
			$pdo = DB::connection('mysql')->getPdo();
			$sth = $pdo->prepare('SELECT * FROM project.promo_code WHERE promo_code = :promo AND expiration_date > NOW()');
			$sth->execute(array(':promo' => Input::get('promo')));
			$result = $sth->fetchAll(PDO::FETCH_ASSOC);
			if (($result == false) || (sizeof($result) < 1)) {
				if (!Input::has('email-verification')) {
					$errors[] = '"'. Input::get('promo') . '" is not a valid SWAMP promotional code or has expired.';
				}
			} else {
				$promo_found = true;
			}
		}

		// user_external_id presense check
		//
		$user_external_id = Input::has('user_external_id');

		// check to see if the domain name is valid
		//
		if (!$promo_found && ! $user_external_id && ($anyEmail !== true)) {
			$domain = User::getEmailDomain($this->email);
			if (!User::isValidEmailDomain($domain)) {
				$errors[] = 'Email addresses from "'.$domain.'" are not allowed.';
			}
		}

		return (sizeof($errors) == 0);
	}

	/**
	 * user verification methods
	 */

	public function getEmailVerification() {
		return EmailVerification::where('user_uid', '=', $this->user_uid)->first();
	}

	public function hasBeenVerified() {
		return $this->email_verified_flag == '1' || $this->email_verified_flag == '-1';
	}

	/**
	 * querying methods
	 */

	public function isCurrent() {
		return $this->user_uid == Session::get('user_uid');
	}

	public function isAdmin() {
		$userAccount = $this->getUserAccount();
		return ($userAccount && (strval($userAccount->admin_flag) == '1'));
	}

	public function isOwner() {
		return $this->getOwnerFlagAttribute();
	}

	public function isEnabled() {
		$userAccount = $this->getUserAccount();
		return ($userAccount && (strval($userAccount->enabled_flag) == '1'));
	}

	public function getUserAccount() {
		return UserAccount::where('user_uid', '=', $this->user_uid)->first();
	}

	public function hasOwnerPermission() {
		return UserPermission::where('user_uid', '=', $this->user_uid)->where('permission_code', '=', 'project-owner')->exists();
	}

	public function getOwnerPermission() {
		return UserPermission::where('user_uid', '=', $this->user_uid)->where('permission_code', '=', 'project-owner')->first();
	}

	public function getTrialProject() {
		return Project::where('project_owner_uid', '=', $this->user_uid)->where('trial_project_flag', '=', 1)->first();
	}

	public function getProjects() {
		if (Config::get('model.database.use_stored_procedures')) {

			// execute stored procedure
			//
			return $this->PDOListProjectsByMember();
		} else {

			// execute SQL query
			//
			$projectMemberships = ProjectMembership::where('user_uid', '=', $this->user_uid)->
				whereNull('delete_date')->get();
			$projects = new Collection;

			// add trial project
			//
			$trialProject = $this->getTrialProject();
			if ($trialProject) {
				$projects->push($trialProject);
			}
			
			// add projects of which user is a member
			//
			for ($i = 0; $i < sizeOf($projectMemberships); $i++) {
				$projectMembership = $projectMemberships[$i];
				$projectUid = $projectMembership['project_uid'];
				$project = Project::where('project_uid', '=', $projectUid)->first();
				if ($project != null && !$project->isSameAs($trialProject) && $project->isActive()) {
					$projects->push($project);
				}
			}
			
			$projects = $projects->reverse();
			return $projects;
		}
	}

	public function hasProjectMembership($projectMembershipUid) {
		$projectMembership = ProjectMembership::where('user_uid', '=', $this->user_uid)->where('membership_uid', '=', $projectMembershipUid)->first();
		if ($projectMembership) {
			if (!$projectMembership->delete_date) {
				return true;
			}
		}
		return false;
	}

	public function isMemberOf($project) {

		// check to see if user is the owner
		//
		if ($project->isOwnedBy($this)) {
			return true;
		}

		// check project memberships for this user
		//
		$projectMemberships = ProjectMembership::where('user_uid', '=', $this->user_uid)->get();
		foreach($projectMemberships as $projectMembership) {
			if ($projectMembership->project_uid == $project->project_uid) {
				if ($projectMembership->isActive()) {
					return true;
				}
			}
		}
		return false;
	}

	public function isProjectAdmin($projectUid) {

		// check project membership for this user
		//
		$projectMemberships = ProjectMembership::where('user_uid', '=', $this->user_uid)->get();
		foreach ($projectMemberships as $projectMembership) {
			if (($projectMembership->project_uid == $projectUid) && ($projectMembership->admin_flag == 1)) {
				if (!$projectMembership->delete_date) {
					return true;
				}	
			}	
		}
		return false;
	}

	/**
	 * permission methods
	 */

	public function getPolicy($policyCode) {
		return UserPolicy::where('user_uid', '=', $this->user_uid)->where('policy_code', '=', $policyCode)->where('accept_flag', '=', 1)->first();
	}

	public function getPermission($permissionCode) {
		return UserPermission::where('user_uid', '=', $this->user_uid)->where('permission_code', '=', $permissionCode)->first();
	}

	public function getPolicyPermission($permissionCode) {
		$userPermission = $this->getPermission($permissionCode);

		if (!$userPermission) {
			return 'no_permission';
		} else {
			// get permission from permission code
			//
			$permission = Permission::where('permission_code', '=', $permissionCode)->first();

			// check for user policy
			//
			if ($this->getPolicy($permission->policy_code)) {
				return $userPermission->getStatus();
			} else {
				return 'no_user_policy';
			}
		}
	}

	public function getPolicyPermissionStatus($permissionCode, $userPermission) {
		$permission = Permission::where('permission_code', '=', $permissionCode)->first();

		// check for user policy
		//
		if ($this->getPolicy($permission->policy_code)) {
			if ($userPermission) {
				return response()->json(array(
					'status' => 'granted',
					'user_permission_uid' => $userPermission->user_permission_uid
				), 200);
			} else {
				return response()->json(array(
					'status' => 'granted'
				), 200);
			}
		} else {
			return response()->json(array(
				'status' => 'no_user_policy',
				'policy' => $permission->policy,
				'policy_code' => $permission->policy_code
			), 404);
		}
	}

	/**
	 * access control methods
	 */

	public function isReadableBy($user) {
		if ($user->isAdmin()) {
			return true;
		} else if ($this->isSameAs($user)) {
			return true;
		} else {
			return false;
		}
	}
	
	public function isWriteableBy($user) {
		if ($user->isAdmin()) {
			return true;
		} else if ($this->isSameAs($user)) {
			return true;
		} else {
			return false;
		}
	}

	//
	// authorization methods
	//

	/**
	 * Get the unique identifier for the user.
	 *
	 * @return mixed
	 */
	public function getAuthIdentifier() {
		return $this->getKey();
	}

	/**
	 * Get the password for the user.
	 *
	 * @return string
	 */
	public function getAuthPassword() {
		return $this->password;
	}

	//
	// email related methods
	//

	/**
	 * Get the e-mail address where password reminders are sent.
	 *
	 * @return string
	 */
	public function getReminderEmail() {
		return $this->email;
	}

	public function getRememberToken() {
	}

	public function setRememberToken($value) {
	}

	public function getRememberTokenName() {
	}

	//
	// utility functions
	//

	static function getEmailDomain($email) {
		$domain = implode('.',
			array_slice( preg_split("/(\.|@)/", $email), -2)
		);
		return strtolower($domain);
	}

	static function isValidEmailDomain($domain) {
		$restrictedDomainNames = RestrictedDomain::getRestrictedDomainNames();
		return !in_array($domain, $restrictedDomainNames);
	}

	public static function setUserUidInSession($userUid) {
		Session::set('timestamp', time());
		Session::set('user_uid', $userUid);
		Session::save();
	}

	//
	// password encrypting functons
	//

	public static function getEncryptedPassword($password, $encryption, $hash='') {
		switch ($encryption) {

			case '{MD5}':
				return '{MD5}'.base64_encode(md5($password, TRUE));
				break;

			case '{SHA1}':
				return '{SHA1}'.base64_encode(sha1($password, TRUE ));
				break;

			case '{SSHA}':
				$salt = substr(base64_decode(substr($hash, 6)), 20);
				return '{SSHA}'.base64_encode(sha1($password.$salt, TRUE ).$salt);
				break;

			case '{BCRYPT}':
			default:
				return '{BCRYPT}'.password_hash($password, PASSWORD_BCRYPT);
				break;
		}
	}

	public function isValidPassword($password) {

		// no password
		//
		if ($this->password == '') {
			return false;
		}

		// plaintext password
		//
		if ($this->password{0} != '{') {
			return ($this->password == $password);
		} else {

			// find hash type
			//
			$i = 1;
			while ($this->password[$i] != '}' && $i < strlen($this->password)) {
				$i++;
			}
			$hash = substr($this->password, 1, $i - 1);

			// crypt
			//
			if ($hash == 'CRYPT') {
				return (crypt($password, substr($this->password, 7)) == substr($this->password, 7));

			// md5
			//
			} elseif ($hash == 'MD5') {
				$encryptedPassword = User::getEncryptedPassword($password, '{MD5}');

			// sha1
			//
			} elseif ($hash == 'SHA1') {
				$encryptedPassword = User::getEncryptedPassword($password, '{SHA1}');

			// ssha
			//
			} elseif ($hash == 'SSHA') {
				$encryptedPassword = User::getEncryptedPassword($password, '{SSHA}', $this->password);

			// bcrypt
			//
			} elseif ($hash == 'BCRYPT') {
				return password_verify($password, substr($this->password, 8));

			// unsupported
			//
			} else {
				echo "Unsupported password hash format: ".$hash.". ";
				return false;
			}

			return ($this->password == $encryptedPassword);
		}
	}

	public function isAuthenticated($password, $checkapppass = false) {

		// If LDAP is enabled, and application level password encryption is disabled 
		// then validate the password by binding to LDAP with username and password.
		//
		if (Config::get('ldap.enabled') && Config::get('ldap.password_validation')) {
			if (Ldap::validatePassword($this->user_uid, $password)) {

				// Log successful password hash authentications
				//
				Log::info("Password authenticated by LDAP.",
					array(
						'user_uid' => $this->user_uid,
					)
				);

				return true;
			}
		} else {

			// check password against stored password or password hash
			//
			if ($this->isValidPassword($password)) {

				// Log successful password hash authentications
				//
				Log::info("Password hash authenticated.",
					array(
						'user_uid' => $this->user_uid,
					)
				);

				return true;
			}
		}

		// Still not authenticated at this point? If we are allowed to check app
		// passwords AND app passwords are enabled, try to find a matching app
		// password for the user.
		//
		if ($checkapppass) {
			$configuration = new Configuration;
			if ($configuration->getAppPasswordMaxAttribute() > 0) {
				if (AppPassword::validatePassword($this->user_uid, $password)) {

					// Log successful app password authentications
					//
					Log::info("App password authenticated.",
						array(
							'user_uid' => $this->user_uid,
						)
					);

					return true;	
				}
			}
		}

		return false;
	}

	//
	// querying methods
	//

	public static function getIndex($userUid) {

		// check to see if we are to use LDAP
		//
		if (Config::get('ldap.enabled')) {

			// use LDAP
			//
			return Ldap::getIndex($userUid);
		} else {

			// use SQL / Eloquent
			//
			return User::where('user_uid', '=', $userUid)->first();
		}
	}

	public static function getByUsername($username) {

		// check to see if we are to use LDAP
		//
		if (Config::get('ldap.enabled')) {

			// use LDAP
			//
			return Ldap::getByUsername($username);
		} else {

			// use SQL / Eloquent
			//
			return User::where('username', '=', $username)->first();
		}
	}

	public static function getByEmail($email) {

		// check to see if we are to use LDAP
		//
		if (Config::get('ldap.enabled')) {

			// use LDAP
			//
			return Ldap::getByEmail($email);
		} else {

			// use SQL / Eloquent
			//
			return User::where('email', '=', $email)->first();
		}
	}

	public static function getAll() {

		// check to see if we are to use LDAP
		//
		if (Config::get('ldap.enabled')) {

			// use LDAP
			//
			return Ldap::getAll();
		} else {

			// use SQL / Eloquent
			//
			return User::all();
		}
	}

	//
	// overridden LDAP model methods
	//

	public function add() {
		$encryption = Config::get('app.password_encryption_method');

		// encrypt password
		//
		if (strcasecmp($encryption, 'NONE') != 0) {

			// encrypt password
			//
			$this->password = $this->getEncryptedPassword($this->password, '{'.$encryption.'}');
		}

		// check to see if we are to use LDAP
		//
		if (Config::get('ldap.enabled')) {

			// use LDAP
			//
			Ldap::add($this);
		} else {

			// use SQL / Eloquent
			//
			$this->save();
		}

		// check for promo code information 
		//
		$promoCodeId = null;
		if (Input::has('promo')) {
			$pdo = DB::connection('mysql')->getPdo();
			$sth = $pdo->prepare('SELECT * FROM project.promo_code WHERE promo_code = :promo AND expiration_date > NOW()');
			$sth->execute(array(':promo' => Input::get('promo')));
			$result = $sth->fetchAll(PDO::FETCH_ASSOC);
			$promoCodeId = ($result != false) && (sizeof($result) > 0) ? $result[0]['promo_code_id'] : null;
		}

		// create new user account
		//
		$userAccount = new UserAccount(array(
			'ldap_profile_update_date' => gmdate('Y-m-d H:i:s'),
			'user_uid' => $this->user_uid,
			'promo_code_id' => $promoCodeId,
			'enabled_flag' => 1,
			'owner_flag' => 0,
			'admin_flag' => 0,
			'email_verified_flag' => Config::get('mail.enabled')? 0 : -1
		));
		$userAccount->save();

		// create linked account
		//
		if (Input::has('user_external_id') && Input::has('linked_account_provider_code')) {
			$linkedAccount = new LinkedAccount(array(
				'user_external_id' => Input::get('user_external_id'),
				'linked_account_provider_code' => Input::get('linked_account_provider_code'),
				'enabled_flag' => 1,
				'user_uid' => $this->user_uid,
				'create_date' => gmdate('Y-m-d H:i:s')
			));
			$linkedAccount->save();
			$idp = new IdentityProvider();
			$userEvent = new UserEvent(array(
				'user_uid' => $this->user_uid,
				'event_type' => 'linkedAccountCreated',
				'value' => json_encode(array( 
					'linked_account_provider_code' => $idp->linked_provider, 
					'user_external_id' => $linkedAccount->user_external_id, 
					'user_ip' => $_SERVER['REMOTE_ADDR']
				))
			));
			$userEvent->save();
		}

		return $this;
	}

	public function modify() {

		// check to see if we are to use LDAP
		//
		if (Config::get('ldap.enabled')) {

			// use LDAP
			//
			return Ldap::save($this);
		} else {

			// use SQL / Eloquent
			//
			$this->save();

			return $this;
		}
	}

	public function modifyPassword($password) {
		$encryption = Config::get('app.password_encryption_method');

		// encrypt password
		//
		if (strcasecmp($encryption, 'NONE') != 0) {
			Log::info('encrypting password');

			// encrypt password
			//
			$password = $this->getEncryptedPassword($password, '{'.$encryption.'}');
		}

		// set user attributes
		//
		$this->password = $password;

		// check to see if we are to use LDAP
		//
		if (Config::get('ldap.enabled')) {

			// use LDAP
			//
			return Ldap::modifyPassword($this, $this->password);
		} else {

			// use SQL / Eloquent
			//
			$this->save();
			return $this;
		}
	}

	/**
	 * accessor methods
	 */

	public function getEnabledFlagAttribute() {
		$userAccount = $this->getUserAccount();
		if ($userAccount) {
			return $userAccount->enabled_flag;
		} else {
			return false;
		}
	}

	public function getAdminFlagAttribute() {
		$userAccount = $this->getUserAccount();
		if ($userAccount) {
			return $userAccount->admin_flag;
		}
	}

	public function getEmailVerifiedFlagAttribute() {
		$userAccount = $this->getUserAccount();
		if ($userAccount) {
			return $userAccount->email_verified_flag;
		}
	}

	public function getForcePWResetFlagAttribute() {
		$userAccount = $this->getUserAccount();
		if ($userAccount) {
			return $userAccount->forcepwreset_flag;
		}
	}

	public function getHibernateFlagAttribute() {
		$userAccount = $this->getUserAccount();
		if ($userAccount) {
			return $userAccount->hibernate_flag;
		}
	}

	public function getUserTypeAttribute() {
		$userAccount = $this->getUserAccount();
		if ($userAccount) {
			return $userAccount->user_type;
		}
	}

	public function getUltimateLoginDateAttribute() {
		$userAccount = $this->getUserAccount();
		if ($userAccount) {
			return $userAccount->ultimate_login_date;
		}
	}

	public function getPenultimateLoginDateAttribute() {
		$userAccount = $this->getUserAccount();
		if ($userAccount) {
			return $userAccount->penultimate_login_date;
		}
	}

	public function getOwnerFlagAttribute() {
		$ownerPermission = $this->getOwnerPermission();
		return $ownerPermission ? ($ownerPermission->getStatus() == 'granted' ? 1 : 0) : 0;
	}

	public function getSshAccessFlagAttribute() {
		$sshAccessPermission = UserPermission::where('user_uid', '=', $this->user_uid)->where('permission_code', '=', 'ssh-access')->first();
		return $sshAccessPermission ? ($sshAccessPermission->getStatus() == 'granted' ? 1 : 0) : 0;
	}

	public function getHasLinkedAccountAttribute() {
		return LinkedAccount::where('user_uid', '=', $this->user_uid)->exists();
	}

	public function getCreateDateAttribute() {
		$userAccount = $this->getUserAccount();
		if ($userAccount) {
			return $userAccount->create_date;
		}
	}

	public function getUpdateDateAttribute() {
		$userAccount = $this->getUserAccount();
		if ($userAccount) {
			return $userAccount->update_date;
		}
	}

	//
	// PDO methods
	//

	private function PDOListProjectByMember() {

		// create stored procedure call
		//
		$connection = DB::connection('mysql');
		$pdo = $connection->getPdo();
		$userUuidIn = $this->user_uid;
		$stmt = $pdo->prepare("CALL list_projects_by_member(:userUuidIn, @returnString);");
		$stmt->bindParam(':userUuidIn', $userUuidIn, PDO::PARAM_STR, 45);
		$stmt->execute();
		$results = array();

		do {
			foreach( $stmt->fetchAll( PDO::FETCH_ASSOC ) as $row )
				$results[] = $row;
		} while ( $stmt->nextRowset() );

		$select = $pdo->query('SELECT @returnString;');
		$returnString = $select->fetchAll( PDO::FETCH_ASSOC )[0]['@returnString'];
		$select->nextRowset();

		$projects = new Collection();
		if ($returnString == 'SUCCESS') {
			foreach( $results as $result ) {
				$project = Project::where('project_uid', '=', $result['project_uid'])->first();
				$projects->push($project);
			}
		}
		return $projects;
	}
}
