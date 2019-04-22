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
|        Copyright (C) 2012-2019 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\Users;

use PDO;

use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Auth\UserInterface;
use Illuminate\Auth\Reminders\RemindableInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
use App\Utilities\Security\Password;
use App\Utilities\Ldap\Ldap;

class User extends TimeStamped
{
	// database attributes
	//
	protected $table = 'user';
	protected $primaryKey = 'user_id';

	// mass assignment policy
	//
	protected $fillable = [
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
	];

	// array / json conversion whitelist
	//
	protected $visible = [
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
		'ssh_access_flag',
		'has_linked_account',
		'has_projects',

		// user account attributes
		//
		'user_type',
		'ultimate_login_date', 
		'penultimate_login_date',

		// timestamp attributes
		//
		'create_date',
		'update_date'
	];

	// array / json appended model attributes
	//
	protected $appends = [
		'enabled_flag',
		'admin_flag',
		'email_verified_flag',
		'forcepwreset_flag',
		'hibernate_flag',
		'ssh_access_flag',
		'has_linked_account',
		'has_projects',
		'user_type',
		'ultimate_login_date', 
		'penultimate_login_date',

		// timestamp attributes
		//
		'create_date',
		'update_date'
	];

	// attribute types
	//
	protected $casts = [
		'enabled_flag' => 'boolean',
		'admin_flag' => 'boolean',
		'email_verified_flag' => 'boolean',
		'forcepwreset_flag' => 'boolean',
		'hibernate_flag' => 'boolean',
		'ssh_access_flag' => 'boolean',
		'has_linked_account' => 'boolean',
		'ultimate_login_date' => 'datetime',
		'penultimate_login_date' => 'datetime',
		'create_date' => 'datetime',
		'update_date' => 'datetime'
	];

	//
	// accessor methods
	//

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

	public function getSshAccessFlagAttribute() {
		$sshAccessPermission = UserPermission::where('user_uid', '=', $this->user_uid)->where('permission_code', '=', 'ssh-access')->first();
		return $sshAccessPermission ? $sshAccessPermission->getStatus() == 'granted' : false;
	}

	public function getHasLinkedAccountAttribute() {
		return LinkedAccount::where('user_uid', '=', $this->user_uid)->exists();
	}

	public function getHasProjectsAttribute() {
		return sizeof($this->getProjects($this->user_uuid)) > 1;
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
	// user comparison method (can't use default because of LDAP)
	//

	public function isSameAs($user) {
		return $user && $this['user_uid'] == $user['user_uid'];
	}

	public function isNew() {
		return $this['user_uid'] == null;
	}

	//
	// querying methods
	//

	public function getFullName() {
		return $this->first_name.' '.$this->last_name;
	}

	public function isCurrent() {
		return $this->user_uid == session('user_uid');
	}

	public function isAdmin() {
		$userAccount = $this->getUserAccount();
		return $userAccount && $userAccount->admin_flag;
	}

	public function isOwner() {
		return $this->getOwnerFlagAttribute();
	}

	public function isEnabled() {
		$userAccount = $this->getUserAccount();
		return $userAccount && $userAccount->enabled_flag;
	}

	public function getUserAccount() {
		return UserAccount::where('user_uid', '=', $this->user_uid)->first();
	}

	public function getTrialProject() {
		return Project::where('project_owner_uid', '=', $this->user_uid)->where('trial_project_flag', '=', 1)->first();
	}

	public function getProjects() {
		if (config('model.database.use_stored_procedures')) {

			// execute stored procedure
			//
			return $this->PDOListProjectsByMember();
		} else {

			// execute SQL query
			//
			$projectMemberships = ProjectMembership::where('user_uid', '=', $this->user_uid)->
				whereNull('delete_date')->get();
			$projects = new Collection;
			
			// add projects of which user is a member
			//
			for ($i = 0; $i < sizeOf($projectMemberships); $i++) {
				$projectMembership = $projectMemberships[$i];
				$projectUid = $projectMembership['project_uid'];
				$project = Project::where('project_uid', '=', $projectUid)->first();
				if ($project != null && !$project->isTrialProject() && $project->isActive()) {
					$projects->push($project);
				}
			}

			// add trial project
			//
			$trialProject = $this->getTrialProject();
			if ($trialProject) {
				$projects->push($trialProject);
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

		// check to see that project exists
		//
		if (!$project) {
			return false;
		}

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

	//
	// user validation methods
	//

	public static function emailInUse($email) {
		$values = [];
		if (preg_match("/(\w*)(\+.*)(@.*)/", $email, $values)) {
			$email = $values[1] . $values[3];
		}

		foreach (self::getAll() as $registered_user) {
			$values = [];
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

		// parse parameters
		//
		$promoCode = Input::get('promo', null);
		$emailVerification = Input::get('email-verification', null);
		$userExternalId = Input::has('user_external_id', null);

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
		$isValidPromoCode = false;
		if ($promoCode) {
			$pdo = DB::connection('mysql')->getPdo();
			$sth = $pdo->prepare('SELECT * FROM project.promo_code WHERE promo_code = :promo AND expiration_date > NOW()');
			$sth->execute([
				':promo' => $promoCode
			]);
			$result = $sth->fetchAll(PDO::FETCH_ASSOC);
			if (($result == false) || (sizeof($result) < 1)) {
				if (!$emailVefification) {
					$errors[] = '"' . $promoCode . '" is not a valid SWAMP promotional code or has expired.';
				}
			} else {
				$isValidPromoCode = true;
			}
		}

		// check to see if the domain name is valid
		//
		if (!$isValidPromoCode && ! $userExternalId && ($anyEmail !== true)) {
			$domain = User::getEmailDomain($this->email);
			if (!User::isValidEmailDomain($domain)) {
				$errors[] = 'Email addresses from "'.$domain.'" are not allowed.';
			}
		}

		return (sizeof($errors) == 0);
	}

	//
	// user verification methods
	//

	public function getEmailVerification() {
		return EmailVerification::where('user_uid', '=', $this->user_uid)->first();
	}

	public function hasBeenVerified() {
		return boolval($this->email_verified_flag) || $this->email_verified_flag == '-1';
	}

	//
	// permission methods
	//

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
				return response()->json([
					'status' => 'granted',
					'user_permission_uid' => $userPermission->user_permission_uid
				], 200);
			} else {
				return response()->json([
					'status' => 'granted'
				], 200);
			}
		} else {
			return response()->json([
				'status' => 'no_user_policy',
				'policy' => $permission->policy,
				'policy_code' => $permission->policy_code
			], 404);
		}
	}

	//
	// access control methods
	//

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

	public function setSession() {
		session([
			'user_uid' => $this->user_uid,
			'timestamp' => time()
		]);
		Session::save();
	}

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

	public static function isAuthenticatable() {
		if (config('ldap.enabled') && config('ldap.password_validation')) {
			return Ldap::checkLdapConnection();
		} else {
			return true;
		}
	}

	public function isAuthenticated($password, $checkapppass = false) {

		// If LDAP is enabled, and application level password encryption is disabled 
		// then validate the password by binding to LDAP with username and password.
		//
		if (config('ldap.enabled') && config('ldap.password_validation')) {
			if (Ldap::validatePassword($this->user_uid, $password)) {

				// Log successful password hash authentications
				//
				Log::info("Password authenticated by LDAP.", [
					'user_uid' => $this->user_uid
				]);

				return true;
			}
		} else {

			// check password against stored password or password hash
			//
			if (Password::isValid($password, $this->password)) {

				// Log successful password hash authentications
				//
				Log::info("Password hash authenticated.", [
					'user_uid' => $this->user_uid
				]);

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
					Log::info("App password authenticated.", [
						'user_uid' => $this->user_uid
					]);

					return true;	
				}
			}
		}

		return false;
	}

	//
	// static querying methods
	//

	public static function getIndex($userUid) {

		// check to see if we are to use LDAP
		//
		if (config('ldap.enabled')) {

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
		if (config('ldap.enabled')) {

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
		if (config('ldap.enabled')) {

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
		if (config('ldap.enabled')) {

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

		// parse parameters
		//
		$promoCode = Input::get('promo');
		$userExternalId = Input::has('user_external_id')? Input::get('user_external_id') : null;
		$linkedAccountProviderCode = Input::has('linked_account_provider_code')? Input::get('linked_account_provider_code') : null;

		// encrypt password
		//
		$encryption = config('app.password_encryption_method');
		if (strcasecmp($encryption, 'NONE') != 0) {

			// encrypt password
			//
			$this->password = Password::getEncrypted($this->password, $encryption);
		}

		// check to see if we are to use LDAP
		//
		if (config('ldap.enabled')) {

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
		if ($promoCode && $promoCode != '') {
			$pdo = DB::connection('mysql')->getPdo();
			$sth = $pdo->prepare('SELECT * FROM project.promo_code WHERE promo_code = :promo AND expiration_date > NOW()');
			$sth->execute([
				':promo' => $promoCode
			]);
			$result = $sth->fetchAll(PDO::FETCH_ASSOC);
			$promoCodeId = ($result != false) && (sizeof($result) > 0) ? $result[0]['promo_code_id'] : null;
		}

		// create new user account
		//
		$userAccount = new UserAccount([
			'ldap_profile_update_date' => gmdate('Y-m-d H:i:s'),
			'user_uid' => $this->user_uid,
			'promo_code_id' => $promoCodeId,
			'enabled_flag' => 1,
			'admin_flag' => 0,
			'email_verified_flag' => config('mail.enabled')? 0 : -1
		]);
		$userAccount->save();

		// create linked account
		//
		if ($userExternalId && $linkedAccountProviderCode) {
			$linkedAccount = new LinkedAccount([
				'user_external_id' => $userExternalId,
				'linked_account_provider_code' => $linkedAccountProviderCode,
				'enabled_flag' => 1,
				'user_uid' => $this->user_uid,
				'create_date' => gmdate('Y-m-d H:i:s')
			]);
			$linkedAccount->save();
			$idp = new IdentityProvider();
			$userEvent = new UserEvent([
				'user_uid' => $this->user_uid,
				'event_type' => 'linkedAccountCreated',
				'value' => json_encode([
					'linked_account_provider_code' => $idp->linked_provider, 
					'user_external_id' => $linkedAccount->user_external_id, 
					'user_ip' => $_SERVER['REMOTE_ADDR']
				])
			]);
			$userEvent->save();
		}

		return $this;
	}

	public function modify() {

		// check to see if we are to use LDAP
		//
		if (config('ldap.enabled')) {

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
		$encryption = config('app.password_encryption_method');

		// encrypt password
		//
		if (strcasecmp($encryption, 'NONE') != 0) {
			Log::info('encrypting password');

			// encrypt password
			//
			$password = Password::getEncrypted($password, $encryption);
		}

		// set user attributes
		//
		$this->password = $password;

		// check to see if we are to use LDAP
		//
		if (config('ldap.enabled')) {

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
		$results = [];

		do {
			foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
				$results[] = $row;
			}
		} while ($stmt->nextRowset());

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
