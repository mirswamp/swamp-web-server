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
|        Copyright (C) 2012-2019 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Http\Controllers\Admin;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Config;
use App\Models\Users\UserAccount;
use App\Models\Users\User;
use App\Http\Controllers\BaseController;

class AdminsController extends BaseController {

	// get by index
	//
	public function getIndex($userUid) {
		$userAccount = UserAccount::where('user_uid', '=', $userUid)->first();
		if ($userAccount->admin_flag) {
			return $userAccount;
		} else {
			return response('User is not an administrator.', 401);
		}
	}

	// get all
	//
	public function getAll() {
		$admins = UserAccount::where('admin_flag', '=', 1)->get();
		$users = new Collection;
		foreach ($admins as $admin) {
			$user = User::getIndex($admin->user_uid);
			if ($user) {
				$users[] = $user;
			}
		}
		return $users;
	}

	// update by index
	//
	public function updateIndex($userUid) {

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
	public function deleteIndex($userUid) {
		$userAccount = UserAccount::where('user_uid', '=', $userUid)->first();
		$userAccount->admin_flag = false;
		$userAccount->save();
		return $userAccount;
	}

	public function sendEmail() {

		// parse parameters
		//
		$subject = Input::get('subject');
		$body = Input::get('body');
		$recipients = Input::get('recipients');

		// return if email is not enabled
		//
		if (!config('mail.enabled')) {
			return response('Email has not been enabled.', 400);
		}

		if (!$subject) {
			return response('Missing subject field.', 400);
		} elseif (!$body) {
			return response('Missing body field.', 400);
		} elseif (!$recipients) {
			return response('Missing recipients field.', 400);
		}

		$this->subject = $subject;
		if (($this->subject == '') || ($body == '')) {
			return response('The email subject and body fields must not be empty.', 400);
		}
		if (sizeof($recipients) < 1) {
			return response('The email must have at least one recipient.', 400);	
		}

		$failures = new Collection();
		foreach ($recipients as $email) {
			$user = User::getByEmail($email);
			if (!$user) {
				return response("Could not load user: $email", 400);	
			}

			// check body for php signature
			//
			$this->secure = false;
			if ((strpos($body, 'END PGP SIGNATURE') != FALSE) || (strpos($body, 'END GPG SIGNATURE') != FALSE)) {
				$this->secure = true;
			}

			$user_email = '';

			// make sure user's email is valid
			//
			if (filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
				$user_email = $user->email;
			}
			
			$user_fullname = trim($user->getFullName());
			if (strlen($user_email) > 0) {
				Mail::send([
					'text' => 'emails.admin'
				], [
					'user' => $user,
					'body' => $body
				], function($message) use ($user_email, $user_fullname) {
					$message->to($user_email, $user_fullname);
					$message->subject($this->subject);

					if ($this->secure) {
						$message->from(config('mail.security.address'));

					}
				});
			} else {
				$failures->push([
					'user' => $user->toArray(), 
					'email' => $email
				]);
			}
		}

		return $failures;
	}
}
