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
|        Copyright (C) 2012-2020 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\Users;

use PDO;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Auth\UserInterface;
use Illuminate\Auth\Reminders\RemindableInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Mail;
use App\Models\BaseModel;
use App\Models\TimeStamps\TimeStamped;
use App\Models\Users\UserSession;
use App\Models\Users\AppPassword;
use App\Models\Users\EmailVerification;
use App\Models\Users\Permission;
use App\Models\Users\UserPermission;
use App\Models\Users\UserPolicy;
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
	/**
	 * The table associated with the model.
	 *
	 * @var string
	 */
	protected $table = 'user';

	/**
	 * The primary key associated with the table.
	 *
	 * @var string
	 */
	protected $primaryKey = 'user_id';

	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array
	 */
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
		'affiliation',

		// configuration attributes
		//
		'config'
	];

	/**
	 * The attributes that should be visible in serialization.
	 *
	 * @var array
	 */
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
		'signed_in_flag',
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

		// configuration attributes
		//
		'config',

		// timestamp attributes
		//
		'create_date',
		'update_date'
	];

	/**
	 * The accessors to append to the model's array form.
	 *
	 * @var array
	 */
	protected $appends = [
		'enabled_flag',
		'signed_in_flag',
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

	/**
	 * The attributes that should be cast to native types.
	 *
	 * @var array
	 */
	protected $casts = [
		'enabled_flag' => 'boolean',
		'signed_in_flag' => 'boolean',
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

	/**
	 * The maximum number of usernames to try when creating new linked accounts
	 *
	 * @var int
	 */
	const MAXTRIES = 500;
	
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

	public function getSignedInFlagAttribute() {
		return UserSession::where('user_id', '=', $this->user_uid)->exists();
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

	public function isSameAs($user): bool {
		return $user && $this['user_uid'] == $user['user_uid'];
	}

	public function isNew(): bool {
		return $this['user_uid'] == null;
	}

	//
	// querying methods
	//

	public function getFullName(): string {
		return $this->first_name . ' ' . $this->last_name;
	}

	public function isCurrent(): bool {
		return $this->user_uid == session('user_uid');
	}

	public function isAdmin(): bool {
		$userAccount = $this->getUserAccount();
		return $userAccount && $userAccount->admin_flag;
	}

	public function isOwner(): bool {
		return $this->getOwnerFlagAttribute();
	}

	public function isEnabled(): bool {
		$userAccount = $this->getUserAccount();
		return $userAccount && $userAccount->isEnabled();
	}

	public function isHibernating(): bool {
		$userAccount = $this->getUserAccount();
		return $userAccount && $userAccount->isHibernating();
	}

	public function isSignedIn(): bool {
		return UserSession::where('user_id', '=', $this->user_uid)->exists();
	}

	public function getUserAccount(): ?UserAccount {
		return UserAccount::where('user_uid', '=', $this->user_uid)->first();
	}

	public function getTrialProject(): ?Project {
		return Project::where('project_owner_uid', '=', $this->user_uid)->where('trial_project_flag', '=', 1)->first();
	}

	public function getProjects(): Collection {

		// execute SQL query
		//
		$projectMemberships = ProjectMembership::where('user_uid', '=', $this->user_uid)->
			whereNull('delete_date')->get();
		$projects = collect();
		
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
		
		return $projects->reverse();
	}

	public function hasProjectMembership(string $projectMembershipUid): bool {
		$projectMembership = ProjectMembership::where('user_uid', '=', $this->user_uid)->where('membership_uid', '=', $projectMembershipUid)->first();
		if ($projectMembership) {
			if (!$projectMembership->delete_date) {
				return true;
			}
		}
		return false;
	}

	public function isMemberOf(Project $project): bool {

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

	public function isProjectAdmin(string $projectUid): bool {

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

	public static function emailInUse(string $email): bool {
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

	public function isValid(Request $request, &$errors, bool $anyEmail = false): bool {

		// parse parameters
		//
		$promoCode = $request->input('promo', null);
		$emailVerification = $request->input('email-verification', null);
		$userExternalId = $request->input('user_external_id', null);

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

	public function getEmailVerification(): ?EmailVerification {
		return EmailVerification::where('user_uid', '=', $this->user_uid)->first();
	}

	public function hasBeenVerified(): bool {
		return boolval($this->email_verified_flag) || $this->email_verified_flag == '-1';
	}

	//
	// permission methods
	//

	public function getPolicy(?string $policyCode): ?UserPolicy {
		return UserPolicy::where('user_uid', '=', $this->user_uid)->where('policy_code', '=', $policyCode)->where('accept_flag', '=', 1)->first();
	}

	public function getPermission(?string $permissionCode): ?UserPermission {
		return UserPermission::where('user_uid', '=', $this->user_uid)->where('permission_code', '=', $permissionCode)->first();
	}

	public function getPolicyPermission(string $permissionCode): string {
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

	public function getPolicyPermissionStatus(string $permissionCode, ?UserPermission $userPermission): array {
		$permission = Permission::where('permission_code', '=', $permissionCode)->first();

		// check for user policy
		//
		if ($this->getPolicy($permission->policy_code)) {
			if ($userPermission) {
				return [
					'status' => 'granted',
					'user_permission_uid' => $userPermission->user_permission_uid
				];
			} else {
				return [
					'status' => 'granted'
				];
			}
		} else {
			return [
				'status' => 'no_user_policy',
				'policy' => $permission->policy,
				'policy_code' => $permission->policy_code
			];
		}
	}

	//
	// access control methods
	//

	public function isReadableBy(User $user): bool {
		if ($user->isAdmin()) {
			return true;
		} else if ($this->isSameAs($user)) {
			return true;
		} else {
			return false;
		}
	}
	
	public function isWriteableBy(User $user): bool {
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
	public function getAuthIdentifier(): string {
		return $this->getKey();
	}

	/**
	 * Get the password for the user.
	 *
	 * @return string
	 */
	public function getAuthPassword(): string {
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
	public function getReminderEmail(): string {
		return $this->email;
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

	static function getEmailDomain(string $email): string {
		$domain = implode('.',
			array_slice( preg_split("/(\.|@)/", $email), -2)
		);
		return strtolower($domain);
	}

	static function isValidEmailDomain(string $domain): string {
		$restrictedDomainNames = RestrictedDomain::getRestrictedDomainNames();
		return !in_array($domain, $restrictedDomainNames);
	}

	public static function isAuthenticatable(): bool {
		if (config('ldap.enabled') && config('ldap.password_validation')) {
			return Ldap::checkLdapConnection();
		} else {
			return true;
		}
	}

	public function isAuthenticated(string $password, bool $checkapppass = false): bool {

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

	public static function getIndex(?string $userUid) {

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

	public static function getByUsername(string $username) {

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

	public static function getByEmail(string $email) {

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

	public function add(Request $request) {

		// parse parameters
		//
		$promoCode = $request->input('promo');
		$userExternalId = $request->input('user_external_id', null);
		$linkedAccountProviderCode = $request->input('linked_account_provider_code', null);

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

	public function modifyPassword(string $password) {
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

	/**
	 * Send a welcome email to this user
	 *
	 * @return void
	 */
	public function welcome() {
		if (config('mail.enabled')) {
			$email = $this->email;
			$name = $this->getFullName();

			Mail::send('emails.welcome', [
				'name' => $name,
				'logo' => config('app.cors_url') . '/images/logos/swamp-logo-small.png',
				'manual' => config('app.cors_url').'https://continuousassurance.org/swamp/SWAMP-User-Manual.pdf',
			], function($message) use ($email, $name) {
				$message->to($email, $name);
				$message->subject('Welcome to the Software Assurance Marketplace');
			});
		}
	}

	//
	// static methods
	//

	/**
	 * Get the current user.
	 *
	 * @return User
	 */
	public static function current(): ?User {
		$userUid = session('user_uid');
		if ($userUid) {
			return User::getIndex($userUid);
		} else {
			return null;
		}
	}

	/**
	 * Get a unique username matching a particular pattern.
	 *
	 * @param string $username
	 * @return string
	 */
	public static function getUniqueUsername(string $username) {

		// check if username is taken
		//
		if (!self::getByUsername($username)) {
			return $username;
		}

		// attempt username permutations
		//
		for ($i = 1; $i <= self::MAXTRIES; $i++) {
			$uniqueName = $username . $i;

			if (!self::getByUsername($uniqueName)) {
				return $uniqueName;
			}
		}

		return false;
	}
}
