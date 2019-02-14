<?php
/******************************************************************************\
|                                                                              |
|                             UsersController.php                              |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a controller for handling user models.                   |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2019 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Http\Controllers\Users;

use PDO;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use App\Utilities\Uuids\Guid;
use App\Models\Users\User;
use App\Models\Users\UserAccount;
use App\Models\Users\EmailVerification;
use App\Models\Users\PasswordReset;
use App\Models\Projects\Project;
use App\Models\Projects\ProjectMembership;
use App\Models\Projects\ProjectInvitation;
use App\Models\Admin\AdminInvitation;
use App\Models\Users\UserClassMembership;
use App\Http\Controllers\BaseController;
use App\Utilities\Filters\DateFilter;
use App\Utilities\Filters\LimitFilter;
use App\Models\Utilities\Configuration;

class UsersController extends BaseController {

	// create
	//
	public function postCreate() {

		// return if user account registration is not enabled
		//
		if (!config('app.sign_up_enabled')) {
			return response('User account registration has not been enabled.', 400);
		}

		// parse parameters
		//
		$firstName = Input::get('first_name');
		$lastName = Input::get('last_name');
		$preferredName = Input::get('preferred_name');
		$username = Input::get('username');
		$password = Input::get('password');
		$email = filter_var(Input::get('email'), FILTER_VALIDATE_EMAIL);
		$affiliation = Input::get('affiliation');
		$classCode = Input::get('class_code');

		// create new user
		//
		$user = new User([
			'user_uid' => Guid::create(),
			'first_name' => $firstName,
			'last_name' => $lastName,
			'preferred_name' => $preferredName,
			'username' => $username,
			'password' => $password,
			'email' => $email,
			'affiliation' => $affiliation
		]);

		// For LDAP extended error messages, check the exception message for the
		// ldap_* method and check for pattern match. If so, then rather than
		// returning the user object, return a new JSON object with the 
		// encoded LDAP extended error message.
		//
		try {
			$user->add();
			$user->isNew = true;
		} catch (\ErrorException $exception) {
			if (preg_match('/^Constraint violation:/',$exception->getMessage())) {
				return response()->json([
					'error' => $exception->getMessage()
				], 409);
			} else {
			  throw $exception;
			}
		}

		// create new class membership
		//
		if ($classCode && $classCode != '') {
			$classMembership = new UserClassMembership([
				'class_user_uuid' => Guid::create(),
				'user_uid' => $user->user_uid,
				'class_code' => $classCode
			]);
			$classMembership->save();
		}

		// return response
		//
		return $user;
	}

	// check validity
	//
	public function postValidate() {

		// parse parameters
		//
		$userUid = Input::get('user_uid');
		$firstName = Input::get('first_name');
		$lastName = Input::get('last_name');
		$preferredName = Input::get('preferred_name');
		$username = Input::get('username');
		$password = Input::get('password');
		$email = filter_var(Input::get('email'), FILTER_VALIDATE_EMAIL);
		$affiliation =  Input::get('affiliation');

		// create new (temporary) user
		//
		$user = new User([
			'user_uid' => $userUid,
			'first_name' => $firstName,
			'last_name' => $lastName,
			'preferred_name' => $preferredName,
			'username' => $username,
			'password' => $password,
			'email' => $email,
			'affiliation' => $affiliation
		]);
		$errors = [];

		// return response
		//
		if ($user->isValid($errors)) {
			return response()->json([
				'success' => true
			]);
		} else {
			return response()->json($errors, 409);
		}
	}

	// get by index
	//
	public function getIndex($userUid) {

		// get current user
		//
		if ($userUid == 'current') {
			$current = true;
			$userUid = session('user_uid');
		} else {
			$current = false;
		}

		if ($userUid) {
			$user = User::getIndex($userUid);

			// return response
			//
			if ($user != null) {

				// append application configuration info to user info
				//
				if ($current) {
					$array = $user->toArray();
					$array['config'] = new Configuration();
					return $array;
				} else {
					return $user;
				}
			} else {
				return response('User not found.', 404);
			}
		} else {
			return response('No current user.', 404);
		}
	}

	// get by username
	//
	public function getUserByUsername() {

		// parse parameters
		//
		$username = Input::get('username');

		// query database
		//
		$user = User::getByUsername($username);

		// return response
		//
		if ($user != null) {
			return $user;
		} else {
			return response('Could not find a user associated with the username: '.$username, 404);
		}
	}

	// get by email address
	//
	public function getUserByEmail() {

		// parse parameters
		//
		$email = Input::get('email');

		// query database
		//
		$user = User::getByEmail($email);

		// return response
		//
		if ($user != null) {
			return $user;
		} else {
			return response('Could not find a user associated with the email address: '.$email, 404);
		}
	}

	// request an email containing the username for a given email address
	//
	public function requestUsername() {

		// parse parameters
		//
		$email = Input::get('email');

		// query database
		//
		$this->user = User::getByEmail($email);

		// send email notification
		//
		if (config('mail.enabled')) {
			if ($this->user && $this->user->email && filter_var($this->user->email, FILTER_VALIDATE_EMAIL)) {
				Mail::send('emails.request-username', [
					'user' => $this->user
				], function($message) {
					$message->to( $this->user->email, $this->user->getFullName());
					$message->subject('SWAMP Username Request');
				});

				// log the username request event
				//
				Log::info("Username requested.", [
					'requested_user_uid' => $this->user->user_uid,
					'email' => $email
				]);
			}
		}

		// return response
		//
		return response()->json([
			'success' => true
		]);
	}

	// get all
	//
	public function getAll($userUid) {

		// parse parameters
		//
		$limit = filter_var(Input::get('limit'), FILTER_VALIDATE_INT);

		// get users
		//
		$user = User::getIndex($userUid);
		if ($user) {
			if ($user->isAdmin()) {

				// check to see if we are to use LDAP
				//
				if (config('ldap.enabled')) {

					// use LDAP
					//
					$users = User::getAll();

					// sort by date
					//			
					$users = $users->sortByDesc('create_date')->values();

					// add filters
					//
					$users = self::filterByUserType($users);
					$users = self::filterByDate($users);
					$users = self::filterByLastLoginDate($users);

					// add limit filter
					//
					if ($limit != null) {
						$users = $users->slice(0, $limit);
					}
				} else {

					// use SQL
					//
					$usersQuery = User::orderBy('create_date', 'DESC');

					// add filters
					//
					$usersQuery = DateFilter::apply($usersQuery);
					$usersQuery = LimitFilter::apply($usersQuery);

					$users = $usersQuery->get();
					$users = self::filterByLastLoginDate($users);
					$users = self::filterByUserType($users);
				}

				return $users;
			} else {
				return response('This user is not an administrator.', 400);
			}
		} else {
			return response('Administrator authorization is required.', 400);
		}
	}

	// update by index
	//
	public function updateIndex($userUid) {

		// parse parameters
		//
		$firstName = Input::get('first_name');
		$lastName = Input::get('last_name');
		$preferredName = Input::get('preferred_name');
		$username = Input::get('username');
		$email = filter_var(trim(Input::get('email')), FILTER_VALIDATE_EMAIL);
		$affiliation = Input::get('affiliation');

		// get model
		//
		$user = User::getIndex($userUid);
		if (!$user) {
			return Response('Could not find user.', 400);
		}

		// send verification email if email address has changed
		//
		$userEmail = trim($user->email);
		if ($email) {
			if (config('mail.enabled')) {
				if ($email != $userEmail) {
					$emailVerification = new EmailVerification([
						'user_uid' => $user->user_uid,
						'verification_key' => Guid::create(),
						'email' => $email
					]);
					$emailVerification->save();
					$emailVerification->send('#verify-email', true); 
				}
			}
		}

		// update attributes
		//
		$user->first_name = $firstName;
		$user->last_name = $lastName;
		$user->preferred_name = $preferredName;
		$user->username = $username;
		$user->affiliation = $affiliation;

		// save changes
		//
		$changes = $user->getDirty();
		$user->modify();

		// update user's meta attributes (admin only)
		//
		$currentUser = User::getIndex(session('user_uid'));
		if ($currentUser && $currentUser->isAdmin()) {

			// parse meta attributes
			//
			$attributes = [
				'enabled_flag' => filter_var(Input::get('enabled_flag'), FILTER_VALIDATE_BOOLEAN),
				'admin_flag' => filter_var(Input::get('admin_flag'), FILTER_VALIDATE_BOOLEAN),
				'email_verified_flag' => filter_var(Input::get('email_verified_flag'), FILTER_VALIDATE_BOOLEAN),
				'forcepwreset_flag' => filter_var(Input::get('forcepwreset_flag'), FILTER_VALIDATE_BOOLEAN),
				'hibernate_flag' => filter_var(Input::get('hibernate_flag'), FILTER_VALIDATE_BOOLEAN),
				'user_type' => Input::get('user_type')
			];

			// update user account
			//
			$userAccount = $user->getUserAccount();
			if ($userAccount) {
				$userAccount->setAttributes($attributes, $user);
			}
		}

		// append original email to changes (email change is still pending)
		//
		if (strlen($email) > 0) {
			$changes = array_merge($changes, [
				'email' => $userEmail
			]);
		}

		// append change date to changes
		//
		$changes = array_merge($changes, [
			'update_date' => $user->update_date
		]);

		// log the update user event
		//
		Log::info("User account updated.", [
			'Updated_user_uid' => $userUid,
			'update_date' => $user->update_date,
		]);

		// return changes
		//
		return $changes;
	}

	// change password
	//
	public function changePassword($userUid) {

		// parse parameters
		//
		$oldPassword = Input::get('old_password');
		$newPassword = Input::get('new_password');

		// get user
		//
		$currentUser = User::getIndex(session('user_uid'));
		$user = User::getIndex($userUid);

		// The target password being changed is the current user's password
		//
		if ($userUid == $currentUser->user_uid) {
			if ($currentUser->isAuthenticated($oldPassword)) {
				
				// For LDAP extended error messages, check the exception message for the
				// ldap_* method and check for pattern match. If so, then rather than
				// returning the user object, return a new JSON object with the 
				// encoded LDAP extended error message.
				//
				try {
					$currentUser->modifyPassword($newPassword);
				} catch (\ErrorException $e) {
					if (preg_match('/^Constraint violation:/',$e->getMessage())) {
						return response()->json([
							'error' => $e->getMessage()
						], 409);
					} else {
					  throw $e;
					}
				}

				// alert user via email that password has changed
				//
				if (config('mail.enabled')) {
					if ($user && $user->email && filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
						Mail::send('emails.password-changed', [
							'url' => config('app.cors_url') ?: '',
							'user' => $user
						], function($message) use ($user) {
							$message->to($user->email, $user->getFullName());
							$message->subject('SWAMP Password Changed');
						});
					}
				}

				// log the password change event
				//
				Log::info("Password changed.", [
					'changed_user_uid' => $userUid,
				]);

				// return success
				//
				return response()->json([
					'success' => true
				]);	
			} else {

				// old password is not valid
				//
				return response('Old password is incorrect.', 404);
			}

		// current user is an admin - can change any password
		//
		} elseif ($currentUser->isAdmin()) {

			// For LDAP extended error messages, check the exception message for the
			// ldap_* method and check for pattern match. If so, then rather than
			// returning the user object, return a new JSON object with the 
			// encoded LDAP extended error message.
			//
			try {
				$user->modifyPassword($newPassword);
			} catch (\ErrorException $exception) {
				if (preg_match('/^Constraint violation:/',$exception->getMessage())) {
					return response()->json([
						'error' => $exception->getMessage()
					], 409);
				} else {
				  throw $exception;
				}
			}

			// alert user via email that password has changed
			//
			if (config('mail.enabled')) {
				if ($user && $user->email && filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
					Mail::send('emails.password-changed', [
						'url' => config('app.cors_url') ?: '',
						'user' => $user
					], function($message) use ($user) {
						$message->to($user->email, $user->getFullName());
						$message->subject('SWAMP Password Changed');
					});
				}
			}

			// log the password change event
			//
			Log::info("Password changed by admin.", [
				'changed_user_uid' => $userUid,
				'admin_user_uid' => $currentUser->user_uid,
			]);

			// return success
			//
			return response()->json([
				'success' => true
			]);

		// current user is not the target user nor admin user
		//
		} else {
			return response("You must be an admin to change a user's password", 403);
		}
	}

	// update multiple
	//
	public function updateAll() {

		// parse parameters
		//
		$input = Input::all();

		// update users
		//
		$collection = new Collection;
		for ($i = 0; $i < sizeOf($input); $i++) {
			UsersController::updateIndex($item[$i]['user_uid']);	
		}

		return $collection;
	}

	// delete by index
	//
	public function deleteIndex($userUid) {
		$user = User::getIndex($userUid);

		// delete project memberships
		//
		ProjectMembership::deleteByUser($user);

		// update user account
		//
		$userAccount = $user->getUserAccount();
		if ($userAccount) {
			$currentUserUid = session('user_uid');
			$userAccount->setAttributes([
				'enabled_flag' => false
			], $user, ($userUid == $currentUserUid));

			// log the user account delete event
			//
			Log::info("User account deleted.", [
				'deleted_user_uid' => $userUid,
			]);
		}


		// return response
		//
		return $user;
	}

	// get projects by id
	//
	public function getProjects($userUid) {
		$user = User::getIndex($userUid);
		$projects = null;
		$results = new Collection();
		if ($user != null) {
			$projects = $user->getProjects();
			foreach ($projects as $project) {
				if ($project != NULL && !$project->deactivation_date) {
					$results->push($project);
				}
			}
		}
		return $results;
	}

	public function getNumProjects($userUid) {
		return sizeof($this->getProjects($userUid));
	}

	// get memberships by id
	//
	public function getProjectMemberships($userUid) {
		return ProjectMembership::where('user_uid', '=', $userUid)->get();
	}

	//
	// private filtering utilities
	//

	private static function filterByUserType($items) {

		// parse parameters
		//
		$userType = Input::get('type');

		// filter users
		//
		if ($userType != NULL && $userType != '') {
			$filteredItems = new Collection();
			foreach ($items as $item) {
				$userAccount = UserAccount::where('user_uid', '=', $item->user_uid)->first();
				if ($userAccount && $userAccount->user_type == $userType) {
					$filteredItems->push($item);
				}
			}
			$items = $filteredItems;
		}

		return $items;
	}

	//
	// create date filtering utilities
	//

	private static function filterByAfterDate($items, $after, $attributeName) {
		if ($after != NULL && $after != '') {
			$afterDate = new \DateTime($after);
			$afterDate->setTime(0, 0);
			$filteredItems = new Collection();
			foreach ($items as $item) {
				if ($item[$attributeName] != NULL) {
					$date = new \DateTime($item[$attributeName]);
					if ($date->getTimestamp() >= $afterDate->getTimestamp()) {
						$filteredItems->push($item);
					}
				}
			}
			$items = $filteredItems;
		}
		return $items;
	}

	private static function filterByBeforeDate($items, $before, $attributeName) {
		if ($before != NULL && $before != '') {
			$beforeDate = new \DateTime($before);
			$beforeDate->setTime(0, 0);
			$filteredItems = new Collection();
			foreach ($items as $item) {
				if ($item[$attributeName] != NULL) {
					$date = new \DateTime($item[$attributeName]);
					if ($date->getTimestamp() <= $beforeDate->getTimestamp()) {
						$filteredItems->push($item);
					}
				}
			}
			$items = $filteredItems;
		}
		return $items;
	}

	private static function filterByDate($items) {

		// parse parameters
		//
		$after = Input::get('after');
		$before = Input::get('before');

		// perform filtering
		//
		$items = self::filterByAfterDate($items, $after, 'create_date');
		$items = self::filterByBeforeDate($items, $before, 'create_date');
		return $items;
	}

	private static function filterByLastLoginDate($items) {

		// parse parameters
		//
		$after = Input::get('login-after');
		$before = Input::get('login-before');

		// perform filtering
		//
		$items = self::filterByAfterDate($items, $after, 'ultimate_login_date');
		$items = self::filterByBeforeDate($items, $before, 'ultimate_login_date');
		return $items;
	}
}
