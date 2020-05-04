<?php
/******************************************************************************\
|                                                                              |
|                         PasswordResetsController.php                         |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a controller for password resets.                        |
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

use \DateTime;
use \DateTimeZone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use App\Utilities\Uuids\Guid;
use App\Models\Users\PasswordReset;
use App\Models\Users\User;
use App\Http\Controllers\BaseController;
use App\Http\Controllers\Users\AppPasswordsController;

class PasswordResetsController extends BaseController
{
	// create
	//
	public function postCreate(Request $request) {

		// parse parameters
		//
		$username = $request->input('username');
		$email = filter_var($request->input('email'), FILTER_VALIDATE_EMAIL);

		// find current user by username or email
		//
		if ($username) {
			$user = User::getByUsername($username);
		} else if ($email) {
			$user = User::getByEmail($email);
		}
		
		if (!$user) {
			return response()->json([
				'success' => true
			]);
		}

		// delete previous password resets belonging to this user
		//
		PasswordReset::where('user_uid', '=', $user->user_uid)->delete();

		// create and send password reset
		//
		$nonce = uniqid('', true);
		$passwordReset = new PasswordReset([
			'password_reset_uuid' => Guid::create(),
			'password_reset_key' => Hash::make($nonce),
			'user_uid' => $user->user_uid
		]);
		$passwordReset->save();

		// send password reset email
		//
		$passwordReset->send($nonce);

		return response()->json([
			'success' => true
		]);
	}

	// get by index
	//
	public function getIndex(string $passwordResetUuid): ?PasswordReset {
		return PasswordReset::find($passwordResetUuid);
	}

	// get by key
	//
	public function getByKey(string $passwordResetKey): ?PasswordReset {
		return PasswordReset::where('password_reset_key', '=', $passwordResetKey)->first();
	}

	// get by index and nonce
	//
	public function getByIndexAndNonce(string $passwordResetUuid, string $passwordResetNonce) {
		$passwordReset = $this->getIndex($passwordResetUuid);

		if (!$passwordReset) {
			return response('Password reset key not found.', 401);
		}

		if (!Hash::check($passwordResetNonce, $passwordReset->password_reset_key)) {
			return response('Password reset key invalid.', 401);
		}

		$time = new DateTime($passwordReset->create_date, new DateTimeZone('GMT'));
		if ((gmdate('U') - $time->getTimestamp()) > 1800) {
			return response('Password reset key expired.', 401);
		}

		return $passwordReset;
	}

	// update password
	//
	public function updateIndex(Request $request) {

		// parse parameters
		//
		$password = $request->input('password');
		$passwordResetKey = $request->input('password_reset_key');

		// get models
		//
		$passwordReset = $this->getByKey($passwordResetKey);
		$user = User::getIndex($passwordReset->user_uid);

		if ($user) {
			
			// For LDAP extended error messages, check the exception message for the
			// ldap_* method and check for pattern match. If so, then rather than
			// returning the user object, return a new JSON object with the 
			// encoded LDAP extended error message.
			//
			try {
				$user->modifyPassword($password);
			} catch (\ErrorException $exception) {
				if (preg_match('/^Constraint violation:/',$exception->getMessage())) {
					return response()->json([
						'error' => $exception->getMessage()
					], 409);
				} else {
					throw $exception;
				}
			}

			// delete all app passwords for the user
			//
			$app_password_con = new AppPasswordsController();
			$app_password_con->deleteByUser($user->user_uid);

			// destroy password reset if present
			//
			$passwordReset->delete();

			// send email notification of password change
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

			// unmark account as requiring a reset
			//
			$userAccount = $user->getUserAccount();
			$userAccount->setAttributes([
				'forcepwreset_flag' => false,
				'hibernate_flag' => false
			], $user);

			// log the password reset event
			//
			Log::info("Password reset complete.", [
				'reset_user_uid' => $user->user_uid,
			]);
		}

		// return response
		//
		return response()->json([
			'success' => true
		]);
	}

	// delete by index
	//
	public function deleteIndex(string $passwordResetUuid) {
		$passwordReset = $this->getIndex($passwordResetUuid);
		$passwordReset->delete();
		return $passwordReset;
	}
}
