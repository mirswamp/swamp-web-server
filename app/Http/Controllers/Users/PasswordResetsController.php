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
|        Copyright (C) 2012-2017 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Http\Controllers\Users;

use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use App\Utilities\Uuids\Guid;
use App\Models\Users\PasswordReset;
use App\Models\Users\User;
use App\Http\Controllers\BaseController;

use \DateTime;
use \DateTimeZone;

class PasswordResetsController extends BaseController {

	// create
	//
	public function postCreate() {

		// find current user by username or email
		//
		$user = User::getByUsername(Input::get('username'));
		$user = $user ? $user : User::getByEmail(Input::get('email')); 
		if (!$user) {
			return response()->json(array('success' => true));
		}

		// delete previous password resets belonging to this user
		//
		PasswordReset::where('user_uid', '=', $user->user_uid)->delete();

		// create new password reset
		//
		$passwordResetNonce = $nonce = Guid::create();
		$passwordReset = new PasswordReset(array(
			'password_reset_key' => Hash::make($passwordResetNonce),
			'user_uid' => $user->user_uid
		));
		$passwordReset->save();

		// send password reset email
		//
		$passwordReset->send($nonce);

		return response()->json(array(
			'success' => true
		));
	}

	// get by key
	//
	public function getIndex($passwordResetNonce, $passwordResetId){
		$passwordReset = PasswordReset::where('password_reset_id', '=', $passwordResetId)->first();

		if (!$passwordReset) {
			return response('Password reset key not found.', 401);
		}

		if (!Hash::check($passwordResetNonce, $passwordReset->password_reset_key)) {
			return response('Password reset key invalid.', 401);
		}

		$time = new DateTime( $passwordReset->create_date, new DateTimeZone('GMT') );
		if ((gmdate('U') - $time->getTimestamp()) > 1800) {
			return response('Password reset key expired.', 401);
		}

		unset($passwordReset->user_uid );
		unset($passwordReset->email );
		unset($passwordReset->create_date);
		unset($passwordReset->password_reset_key);

		return $passwordReset;
	}

	// update password
	//
	public function updateIndex($passwordResetId) {

		// get input parameters
		//
		$password = Input::get('password');

		// get models
		//
		$passwordReset = PasswordReset::where('password_reset_id', '=', $passwordResetId)->first();
		$user = User::getIndex($passwordReset->user_uid);

		// For LDAP extended error messages, check the exception message for the
		// ldap_* method and check for pattern match. If so, then rather than
		// returning the user object, return a new JSON object with the 
		// encoded LDAP extended error message.
		//
		try {
			$user->modifyPassword($password);
		} catch (\ErrorException $exception) {
			if (preg_match('/^Constraint violation:/',$exception->getMessage())) {
				return response()->json(array('error' => $exception->getMessage()), 409);
			} else {
			  throw $exception;
			}
		}

		// destroy password reset if present
		//
		$passwordReset->delete();

		// send email notification of password change
		//
		if (Config::get('mail.enabled')) {
			$data = array(
				'url' => Config::get('app.cors_url') ?: '',
				'user' => $user
			);
			Mail::send('emails.password-changed', $data, function($message) use ($user) {
				$message->to($user->email, $user->getFullName());
				$message->subject('SWAMP Password Changed');
			});
		}

		// unmark account as requiring a reset
		//
		$userAccount = $user->getUserAccount();
		$userAccount->setAttributes(array(
			'forcepwreset_flag' => false,
			'hibernate_flag' => false
		), $user);

		// return response
		//
		return response()->json(array('success' => true));
	}

	// delete by key
	//
	public function deleteIndex($passwordResetNonce) {
		$passwordReset = PasswordReset::where('password_reset_key', '=', Hash::make( $passwordResetNonce ))->first();
		$passwordReset->delete();
		return $passwordReset;
	}
}
