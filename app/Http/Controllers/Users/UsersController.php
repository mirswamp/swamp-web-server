<?php
/******************************************************************************\
|                                                                              |
|                             UsersController.php                              |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a controller for platforms.                              |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2016 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Http\Controllers\Users;

use PDO;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Mail;
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
use App\Http\Controllers\BaseController;
use App\Utilities\Filters\DateFilter;
use App\Utilities\Filters\LimitFilter;
use App\Models\Utilities\Configuration;

class UsersController extends BaseController {

	// create
	//
	public function postCreate() {
		$user = new User(array(
			'first_name' => Input::get('first_name'),
			'last_name' => Input::get('last_name'),
			'preferred_name' => Input::get('preferred_name'),
			'username' => Input::get('username'),
			'password' => Input::get('password'),
			'user_uid' => Guid::create(),
			'email' => Input::get('email'),
			'address' => Input::get('address'),
			'phone' => Input::get('phone'),
			'affiliation' => Input::get('affiliation')
		));

		// For LDAP extended error messages, check the exception message for the
		// ldap_* method and check for pattern match. If so, then rather than
		// returning the user object, return a new JSON object with the 
		// encoded LDAP extended error message.
		//
		try {
			$user->add();
			$user->isNew = true;
		} catch (\ErrorException $e) {
			if (preg_match('/^Constraint violation:/',$e->getMessage())) {
				return response()->json(array('error' => $e->getMessage()), 409);
			} else {
			  throw $e;
			}
		}

		// return response
		//
		return $user;
	}

	// check validity
	//
	public function postValidate() {
		$user = new User(array(
			'first_name' => Input::get('first_name'),
			'last_name' => Input::get('last_name'),
			'preferred_name' => Input::get('preferred_name'),
			'username' => Input::get('username'),
			'password' => Input::get('password'),
			'user_uid' => Guid::create(),
			'email' => Input::get('email'),
			'address' => Input::get('address'),
			'phone' => Input::get('phone'),
			'affiliation' => Input::get('affiliation')
		));
		$errors = array();

		// return response
		//
		if ($user->isValid($errors)) {
			return response()->json(array('success' => true));
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
			$userUid = Session::get('user_uid');
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

		// get parameters
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

		// get parameters
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

		// get parameters
		//
		$email = Input::get('email');

		// query database
		//
		$this->user = User::getByEmail($email);

		// send email notification
		//
		if (Config::get('mail.enabled')) {
			if ($this->user != null) {
				Mail::send('emails.request-username', array( 'user' => $this->user ), function($message) {
					$message->to( $this->user->email, $this->user->getFullName() );
					$message->subject('SWAMP Username Request');
				});
			}
		}

		// return response
		//
		return response()->json(array('success' => true));
	}

	// get all
	//
	public function getAll($userUid) {
		$user = User::getIndex($userUid);
		if ($user) {
			if ($user->isAdmin()) {

				// check to see if we are to use LDAP
				//
				if (Config::get('ldap.enabled')) {

					// use LDAP
					//
					$users = User::getAll();

					// sort by date
					//			
					$users = $users->sortByDesc('create_date');

					// add filters
					//
					$users = self::filterByUserType($users);
					$users = self::filterByDate($users);
					$users = self::filterByLastLoginDate($users);

					// add limit filter
					//
					$limit = Input::get('limit');
					if ($limit != '') {
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

		// get model
		//
		$user = User::getIndex($userUid);
		if (!$user) {
			return Response('Could not find user.', 400);
		}

		// send verification email
		//
		if ($user->email != Input::get('email')) {
			$emailVerification = new EmailVerification(array(
				'user_uid' => $user->user_uid,
				'verification_key' => Guid::create(),
				'email' => Input::get('email')
			));
			$emailVerification->save();
			$emailVerification->send('#verify-email', true); 
		}

		// update attributes
		//
		$user->first_name = Input::get('first_name');
		$user->last_name = Input::get('last_name');
		$user->preferred_name = Input::get('preferred_name');
		$user->address = Input::get('address');
		$user->phone = Input::get('phone');
		$user->affiliation = Input::get('affiliation');

		// save changes
		//
		$changes = $user->getDirty();
		$user->modify();

		// update user's meta attributes (admin only)
		//
		$currentUser = User::getIndex(Session::get('user_uid'));
		if ($currentUser && $currentUser->isAdmin()) {

			// get meta attributes
			//
			$attributes = array(
				'enabled_flag' => Input::get('enabled_flag'),
				'admin_flag' => Input::get('admin_flag'),
				'email_verified_flag' => Input::get('email_verified_flag'),
				'forcepwreset_flag' => Input::get('forcepwreset_flag'),
				'hibernate_flag' => Input::get('hibernate_flag'),
				'owner_flag' => Input::get('owner_flag'),
				'user_type' => Input::get('user_type')
			);

			// update user account
			//
			$userAccount = $user->getUserAccount();
			if ($userAccount) {
				$userAccount->setAttributes($attributes, $user);
			}
		}

		// append change date to changes
		//
		$changes = array_merge($changes, array(
			'update_date' => $user->update_date
		));

		// return changes
		//
		return $changes;
	}

	// change password
	//
	public function changePassword($userUid) {
		$currentUser = User::getIndex(Session::get('user_uid'));
		$user = User::getIndex($userUid);

		// current user is an admin
		//
		if ($currentUser->isAdmin() && ($userUid != $currentUser->user_uid)) {
			$newPassword = Input::get('new_password');

			// For LDAP extended error messages, check the exception message for the
			// ldap_* method and check for pattern match. If so, then rather than
			// returning the user object, return a new JSON object with the 
			// encoded LDAP extended error message.
			//
			try {
				$user->modifyPassword($newPassword);
			} catch (\ErrorException $e) {
				if (preg_match('/^Constraint violation:/',$e->getMessage())) {
					return response()->json(array('error' => $e->getMessage()), 409);
				} else {
				  throw $e;
				}
			}

			// alert user via email that password has changed
			//
			if (Config::get('mail.enabled')) {
				$cfg = array(
					'url' => Config::get('app.cors_url') ?: '',
					'user' => $user
				);
				Mail::send('emails.password-changed', $cfg, function($message) use ($user) {
					$message->to($user->email, $user->getFullName());
					$message->subject('SWAMP Password Changed');
				});
			}

			// return success
			//
			return response()->json(array('success' => true));

		// current user is not an admin
		//
		} else if ($userUid == $currentUser->user_uid) {
			$oldPassword = Input::get('old_password');
			if (User::isValidPassword($oldPassword, $currentUser->password)) {
				$newPassword = Input::get('new_password');

				// For LDAP extended error messages, check the exception message for the
				// ldap_* method and check for pattern match. If so, then rather than
				// returning the user object, return a new JSON object with the 
				// encoded LDAP extended error message.
				//
				try {
					$currentUser->modifyPassword($newPassword);
				} catch (\ErrorException $e) {
					if (preg_match('/^Constraint violation:/',$e->getMessage())) {
						return response()->json(array('error' => $e->getMessage()), 409);
					} else {
					  throw $e;
					}
				}

				// alert user via email that password has changed
				//
				if (Config::get('mail.enabled')) {
					$cfg = array(
						'url' => Config::get('app.cors_url') ?: '',
						'user' => $user
					);
					Mail::send('emails.password-changed', $cfg, function($message) use ($user) {
						$message->to($user->email, $user->getFullName());
						$message->subject('SWAMP Password Changed');
					});
				}

				// return success
				//
				return response()->json(array('success' => true));	
			} else {

				// old password is not valid
				//
				return response('Old password is incorrect.', 404);
			}

		// current user is not the target user
		//
		} else {
			return response("You must be an admin to change a user's password", 403);
		}
	}

	// update multiple
	//
	public function updateAll() {
		$input = Input::all();
		$collection = new Collection;
		for ($i = 0; $i < sizeOf($input); $i++) {
			UsersController::updateIndex( $item[$i]['user_uid'] );	
		}
		return $collection;
	}

	// delete by index
	//
	public function deleteIndex($userUid) {
		$user = User::getIndex($userUid);

		// call stored procedure to remove all project associations
		//
		/*
		$connection = DB::connection('mysql');
		$pdo = $connection->getPdo();
		$stmt = $pdo->prepare("CALL remove_user_from_all_projects(:userUuidIn, @returnString);");
		$stmt->bindParam(':userUuidIn', $userUuid, PDO::PARAM_STR, 45);
		$stmt->execute();

		$select = $pdo->query('SELECT @returnString;');
		$returnString = $select->fetchAll( PDO::FETCH_ASSOC )[0]['@returnString'];
		$select->nextRowset();
		*/

		// delete project memberships
		//
		ProjectMembership::deleteByUser($user);

		// update user account
		//
		$userAccount = $user->getUserAccount();
		if ($userAccount) {
			$userAccount->setAttributes(array(
				'enabled_flag' => false
			), $user);
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
		$userType = Input::get('type');
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

		// get input parameters
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

		// get input parameters
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
