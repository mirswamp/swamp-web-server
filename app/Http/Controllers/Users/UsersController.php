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
|        Copyright (C) 2012-2020 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Http\Controllers\Users;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use App\Utilities\Uuids\Guid;
use App\Models\Users\User;
use App\Models\Users\UserAccount;
use App\Models\Users\UserInfo;
use App\Models\Users\EmailVerification;
use App\Models\Users\PasswordReset;
use App\Models\Users\LinkedAccount;
use App\Models\Projects\Project;
use App\Models\Projects\ProjectMembership;
use App\Models\Projects\ProjectInvitation;
use App\Models\Admin\AdminInvitation;
use App\Models\Users\UserClassMembership;
use App\Http\Controllers\BaseController;
use App\Utilities\Filters\DateFilter;
use App\Utilities\Filters\LimitFilter;
use App\Utilities\Filters\UserFilters;
use App\Utilities\Filters\NameFilter;
use App\Utilities\Filters\UsernameFilter;
use App\Models\Utilities\Configuration;

class UsersController extends BaseController
{
	// create
	//
	public function postCreate(Request $request) {

		// return if user account registration is not enabled
		//
		if (!config('app.sign_up_enabled')) {
			return response('User account registration has not been enabled.', 400);
		}

		// parse parameters
		//
		$firstName = $request->input('first_name');
		$lastName = $request->input('last_name');
		$preferredName = $request->input('preferred_name');
		$username = $request->input('username');
		$password = $request->input('password');
		$email = filter_var($request->input('email'), FILTER_VALIDATE_EMAIL);
		$affiliation = $request->input('affiliation');
		$classCode = $request->input('class_code');

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
			$user->add($request);
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
	public function postValidate(Request $request) {

		// parse parameters
		//
		$userUid = $request->input('user_uid');
		$firstName = $request->input('first_name');
		$lastName = $request->input('last_name');
		$preferredName = $request->input('preferred_name');
		$username = $request->input('username');
		$password = $request->input('password');
		$email = filter_var($request->input('email'), FILTER_VALIDATE_EMAIL);
		$affiliation =  $request->input('affiliation');

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
		if ($user->isValid($request, $errors)) {
			return response()->json([
				'success' => true
			]);
		} else {
			return response()->json($errors, 409);
		}
	}

	// get by index
	//
	public function getIndex(string $userUid): ?User {

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

			// append application configuration info to user info
			//
			if ($user && $current) {
				$user->config = new Configuration();
			} 

			return $user;
		} else {
			return null;
		}
	}

	public function getInfo(string $userUid): UserInfo {
		return new UserInfo([
			'user_uid' => $userUid
		]);
	}

	// get by username
	//
	public function getUserByUsername(Request $request): ?User {

		// parse parameters
		//
		$username = $request->input('username');

		// query database
		//
		return User::getByUsername($username);
	}

	// get by email address
	//
	public function getUserByEmail(Request $request): ?User {

		// parse parameters
		//
		$email = $request->input('email');

		// query database
		//
		return User::getByEmail($email);
	}

	// request an email containing the username for a given email address
	//
	public function requestUsername(Request $request) {

		// parse parameters
		//
		$email = $request->input('email');

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
	public function getAll(Request $request): Collection {
		
		// get current user
		//
		$currentUser = User::current();
		if (!$currentUser || !$currentUser->isAdmin()) {
			return response('Administrator authorization is required.', 400);
		}

		// parse parameters
		//
		$limit = filter_var($request->input('limit'), FILTER_VALIDATE_INT);

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
			$users = UserFilters::filterByName($request, $users);
			$users = UserFilters::filterByUsername($request, $users);
			$users = UserFilters::filterByUserType($request, $users);
			$users = UserFilters::filterByDate($request, $users);
			$users = UserFilters::filterByLastLoginDate($request, $users);

			// add limit filter
			//
			if ($limit != null) {
				$users = $users->slice(0, $limit);
			}
		} else {

			// use SQL
			//
			$query = User::orderBy('create_date', 'DESC');

			// add filters
			//
			$query = NameFilter::apply($request, $query);
			$query = UsernameFilter::apply($request, $query);
			$query = DateFilter::apply($request, $query);
			$query = LimitFilter::apply($request, $query);

			// perform query
			//
			$users = $query->get();

			// filter results
			//
			$users = UserFilters::filterByLastLoginDate($request, $users);
			$users = UserFilters::filterByUserType($request, $users);
		}

		return $users;
	}

	// get all signed in users
	//
	public function getSignedIn(Request $request): Collection {

		// check if current user is an admin
		//
		$currentUser = User::current();
		if (!$currentUser || !$currentUser->isAdmin()) {
			return collect();
		}

		// parse parameters
		//
		$limit = filter_var($request->input('limit'), FILTER_VALIDATE_INT);

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
			$users = UserFilters::filterByName($request, $users);
			$users = UserFilters::filterByUsername($request, $users);
			$users = UserFilters::filterByUserType($request, $users);
			$users = UserFilters::filterByDate($request, $users);
			$users = UserFilters::filterByLastLoginDate($request, $users);
			$users = UserFilters::filterBySignedIn($users);

			// add limit filter
			//
			if ($limit != null) {
				$users = $users->slice(0, $limit);
			}
		} else {

			// create query
			//
			$query = User::orderBy('create_date', 'DESC');

			// add filters
			//
			$query = NameFilter::apply($request, $query);
			$query = UsernameFilter::apply($request, $query);
			$query = DateFilter::apply($request, $query);
			$query = LimitFilter::apply($request, $query);

			// perform query
			//
			$users = $query->get();

			// filter results
			//
			$users = UserFilters::filterByLastLoginDate($request, $users);
			$users = UserFilters::filterByUserType($request, $users);
			$users = UserFilters::filterBySignedIn($users);
		}

		return $users;
	}

	// get all enabled users
	//
	public function getEnabled(Request $request): Collection {

		// check if current user is an admin
		//
		$currentUser = User::current();
		if (!$currentUser || !$currentUser->isAdmin()) {
			return collect();
		}

		// parse parameters
		//
		$limit = filter_var($request->input('limit'), FILTER_VALIDATE_INT);
		$userType = $request->input('type');

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
			$users = UserFilters::filterByName($request, $users);
			$users = UserFilters::filterByUsername($request, $users);
			$users = UserFilters::filterByUserType($request, $users);
			$users = UserFilters::filterByDate($request, $users);
			$users = UserFilters::filterByLastLoginDate($request, $users);
			$users = UserFilters::filterByEnabled($users);

			// add limit filter
			//
			if ($limit != null) {
				$users = $users->slice(0, $limit);
			}
		} else {

			// create query
			//
			$query = User::orderBy('create_date', 'DESC');

			// add filters
			//
			$query = NameFilter::apply($request, $query);
			$query = UsernameFilter::apply($request, $query);
			$query = DateFilter::apply($request, $query);
			$query = LimitFilter::apply($request, $query);

			// perform query
			//
			$users = $query->get();

			// filter results
			//
			$users = UserFilters::filterByLastLoginDate($request, $users);
			$users = UserFilters::filterByUserType($request, $users);
			$users = UserFilters::filterByEnabled($users);
		}

		return $users;
	}

	// update by index
	//
	public function updateIndex(Request $request, string $userUid) {

		// parse parameters
		//
		$firstName = $request->input('first_name');
		$lastName = $request->input('last_name');
		$preferredName = $request->input('preferred_name');
		$username = $request->input('username');
		$email = filter_var(trim($request->input('email')), FILTER_VALIDATE_EMAIL);
		$affiliation = $request->input('affiliation');

		// get model
		//
		$user = User::getIndex($userUid);
		if (!$user) {
			return response('Could not find user.', 400);
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
		$currentUser = User::current();
		if ($currentUser && $currentUser->isAdmin()) {

			// parse meta attributes
			//
			$attributes = [
				'enabled_flag' => filter_var($request->input('enabled_flag'), FILTER_VALIDATE_BOOLEAN),
				'admin_flag' => filter_var($request->input('admin_flag'), FILTER_VALIDATE_BOOLEAN),
				'email_verified_flag' => filter_var($request->input('email_verified_flag'), FILTER_VALIDATE_BOOLEAN),
				'forcepwreset_flag' => filter_var($request->input('forcepwreset_flag'), FILTER_VALIDATE_BOOLEAN),
				'hibernate_flag' => filter_var($request->input('hibernate_flag'), FILTER_VALIDATE_BOOLEAN),
				'user_type' => $request->input('user_type')
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
	public function changePassword(Request $request, string $userUid) {

		// parse parameters
		//
		$oldPassword = $request->input('old_password');
		$newPassword = $request->input('new_password');

		// get user
		//
		$currentUser = User::current();
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

	// delete by index
	//
	public function deleteIndex(string $userUid) {
		$user = User::getIndex($userUid);

		// delete project memberships
		//
		ProjectMembership::deleteByUser($user);

		// delete linked accounts
		//
		LinkedAccount::where('user_uid', '=', $userUid)->delete();

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
	public function getProjects(string $userUid): Collection {
		$user = User::getIndex($userUid);
		$projects = null;
		$results = collect();
		if ($user != null) {
			$projects = $user->getProjects();
			foreach ($projects as $project) {
				if ($project != null && !$project->deactivation_date) {
					$results->push($project);
				}
			}
		}
		return $results;
	}

	public function getNumProjects(Request $request, string $userUid): int {
		return sizeof($this->getProjects($userUid));
	}

	// get memberships by id
	//
	public function getProjectMemberships(string $userUid): Collection {
		return ProjectMembership::where('user_uid', '=', $userUid)->get();
	}
}
