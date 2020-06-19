<?php
/******************************************************************************\
|                                                                              |
|                             AdminsController.php                             |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a controller for administrator priviledges.              |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2020 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Http\Controllers\Admin;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Models\Users\UserAccount;
use App\Models\Users\User;
use App\Mail\AdminMail;
use App\Http\Controllers\BaseController;
use App\Utilities\Filters\DateFilter;
use App\Utilities\Filters\LimitFilter;
use App\Utilities\Filters\UserFilters;
use App\Utilities\Filters\NameFilter;
use App\Utilities\Filters\UsernameFilter;

class AdminsController extends BaseController
{
	//
	// methods
	//

	// get by index
	//
	public function getIndex(string $userUid): ?UserAccount {
		$userAccount = UserAccount::where('user_uid', '=', $userUid)->first();
		if ($userAccount->admin_flag) {
			return $userAccount;
		} else {
			return null;
		}
	}

	// get all
	//
	public function getAll(): Collection {
		$admins = UserAccount::where('admin_flag', '=', 1)->get();
		$users = collect();
		foreach ($admins as $admin) {
			$user = User::getIndex($admin->user_uid);
			if ($user) {
				$users->push($user);
			}
		}
		return $users;
	}

	// update by index
	//
	public function updateIndex(string $userUid) {

		// get model
		//
		$userAccount = UserAccount::where('user_uid', '=', $userUid)->first();

		// update attributes
		//
		$userAccount->admin_flag = false;

		// save and return changes
		//
		$changes = $userAccount->getDirty();
		$userAccount->save();
		return $changes;
	}

	// delete by index
	//
	public function deleteIndex(string $userUid) {
		$userAccount = UserAccount::where('user_uid', '=', $userUid)->first();
		$userAccount->admin_flag = false;
		$userAccount->save();
		return $userAccount;
	}

	//
	// system email functions
	//

	public function getEmail(Request $request) {
		$showInactive = filter_var($request->input('show_inactive'), FILTER_VALIDATE_BOOLEAN);

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

		$info = array();
		for ($i = 0; $i < count($users); $i++) {
			$user = $users[$i];
			if ($user->isEnabled()) {
				if ($showInactive) {
					array_push($info, array(
						'user_uid' =>$user->user_uid,
						'username' => $user->username,
						'first_name' => $user->first_name,
						'last_name' => $user->last_name,
						'preferred_name' => $user->preferred_name,
						'email' => $user->email,
						'hibernate_flag' => $user->hibernate_flag
					));
				} else if (!$user->isHibernating()) {
					array_push($info, array(
						'user_uid' =>$user->user_uid,
						'username' => $user->username,
						'first_name' => $user->first_name,
						'last_name' => $user->last_name,
						'preferred_name' => $user->preferred_name,
						'email' => $user->email
					));
				}
			}
		}

		// sort items by email
		//
		usort($info, function($a, $b) {
			return strcmp($a['email'], $b['email']);
		});

		return $info;
	}

	// send emails
	//
	public function sendEmail(Request $request) {

		// parse parameters
		//
		$subject = $request->input('subject');
		$body = $request->input('body');
		$include = $request->input('include');
		$exclude = $request->input('exclude');

		// check if system emails are disabled
		//
		if (config('app.system_email_enabled') == false) {
			sleep(1);
			return [
				'sent' => count($include),
				'failures' => []
			];
		}

		// return if email is not enabled
		//
		if (!config('mail.enabled')) {
			return response('Email has not been enabled.', 400);
		}

		if (!$subject) {
			return response('Missing subject field.', 400);
		} elseif (!$body) {
			return response('Missing body field.', 400);
		}

		$this->subject = $subject;
		if (($this->subject == '') || ($body == '')) {
			return response('The email subject and body fields must not be empty.', 400);
		}

		// find list of recipients
		//
		if ($include) {
			$users = collect();
			for ($i = 0; $i < count($include); $i++) {
				$email = $include[$i];
				$user = User::getByEmail($email);
				if ($user) {
					$users->push($user);
				}
			}
		} else if ($exclude) {
			$users = User::getAll();
			for ($i = 0; $i < count($exclude); $i++) {
				$email = $exclude[$i];
				$item = $recipients->where('email', $email);
				if ($item) {
					$users->remove($item);
				}
			}
		} else {
			$users = User::getAll();
		}

		// send emails to list of recipients
		//
		$count = 0;
		$failures = collect();
		foreach ($users as $user) {
			if ($user && $user->isEnabled()) {

				// check body for php signature
				//
				$this->secure = false;
				if ((strpos($body, 'END PGP SIGNATURE') != false) || (strpos($body, 'END GPG SIGNATURE') != false)) {
					$this->secure = true;
				}

				// make sure user's email is valid
				//
				if ($user->email && filter_var($user->email, FILTER_VALIDATE_EMAIL)) {

					try {
						$user_email = $user->email;
						$user_fullname = trim($user->getFullName());

						// attempt to send mail
						//
						Mail::to($user->email)->send(new AdminMail($user, $subject, $body));

						// increment count if successful
						//
						$count++;

					} catch (Exception $e) {

						// email failed to send
						//
						$failures->push([
							'username' => $user->username, 
							'name' => trim($user->getFullName()), 
							'email' => $user->email
						]);			
					}
				} else {

					// email address is malformed
					//
					$failures->push([
						'username' => $user->username, 
						'name' => trim($user->getFullName()), 
						'email' => $user->email
					]);
				}
			}
		}

		return [
			'sent' => $count,
			'failures' => $failures
		];
	}
}
