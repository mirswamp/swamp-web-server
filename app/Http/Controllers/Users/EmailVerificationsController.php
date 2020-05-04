<?php
/******************************************************************************\
|                                                                              |
|                       EmailVerificationsController.php                       |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a controller for email verifications.                    |
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

use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Utilities\Uuids\Guid;
use App\Models\Users\User;
use App\Models\Users\EmailVerification;
use App\Models\Users\UserAccount;
use App\Http\Controllers\BaseController;

class EmailVerificationsController extends BaseController
{
	// create
	//
	public function postCreate(Request $request): EmailVerification {

		// parse parameters
		//
		$userUid = $request->input('user_uid');
		$email = filter_var($request->input('email'), FILTER_VALIDATE_EMAIL);

		// create new email verification
		//
		$emailVerification = new EmailVerification([
			'user_uid' => $userUid,
			'verification_key' => Guid::create(),
			'email' => $email
		]);
		$emailVerification->save();
		$emailVerification->send($request->input('verify_route'));

		return $emailVerification;
	}

	// get by key
	//
	public function getIndex(string $verificationKey): ?EmailVerification {
		$emailVerification = EmailVerification::where('verification_key', '=', $verificationKey)->first();

		// append user
		//
		$user = User::getIndex($emailVerification->user_uid);
		$user['user_uid'] = $emailVerification->user_uid;
		$emailVerification->user = $user;

		return $emailVerification;
	}

	// verify by key
	//
	public function putVerify(Request $request, string $verificationKey) {
		$emailVerification = EmailVerification::where('verification_key', '=', $verificationKey)->first();
		$emailVerification->verify_date = new DateTime();

		$userAccount = UserAccount::where('user_uid', '=', $emailVerification->user_uid)->first();

		$user = User::getIndex($emailVerification->user_uid);
		$username = $user->username;
		$oldEmail = $user->email;
		$user->email = $emailVerification->email;

		unset( $user->owner );
		unset( $user->username );

		$errors = [];

		if (!$user->hasBeenVerified() || $user->isValid($request, $errors)) {
			$user->username = $username;
			$user->modify();

		} else {
			$message = "This request could not be processed due to the following:<br/><br/>";
			$message .= implode('<br/>',$errors);
			$message .= "<br/><br/>If you believe this to be in error or a security issue, please contact the SWAMP immediately.";
			return response($message, 400);
		}

		// send email to notify that email is being verified
		//
		if (config('mail.enabled')) {
			if ($user && $user->email && filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
				if (!$user->hasBeenVerified()) {

					// send welcome email
					//
					$user->welcome();
				} else {

					// send notification if email has changed
					//
					if (filter_var($oldEmail, FILTER_VALIDATE_EMAIL)) {
						$data = [
							'fullName' => $user->getFullName(), 
							'oldEmail' => $oldEmail
						];
						Mail::send('emails.email-verification-oldemail', [
							'user' => $user,
							'logo' => config('app.cors_url').'/images/logos/swamp-logo-small.png'
						], function($message) use($data) {
							$message->to($data['oldEmail'], $data['fullName']);
							$message->subject('SWAMP Email Changed');
						});
					}
				}
			}
		}

		// update user account
		//
		$userAccount->email_verified_flag = true;
		$userAccount->save();

		// save email verification
		//
		$emailVerification->save();

		return response('This email address has been verified.', 200);
	}

	// resend by username, password
	//
	public function postResend(Request $request) {

		// parse parameters
		//
		$username = $request->input('username');
		$password = $request->input('password');

		// validate user
		//
		$user = User::getByUsername($username);
		if ($user) {
			if ($user->isAuthenticated($password)) {

				// get email verification
				//
				$emailVerification = $user->getEmailVerification();

				// if missing email verification, create one on-the-fly
				//
				if (is_null($emailVerification)) {
					$emailVerification = new EmailVerification([
						'user_uid' => $user->user_uid,
						'verification_key' => Guid::create(),
						'email' => $user->email
					]);
					$emailVerification->save();
				}

				// resend
				//
				$emailVerification->send('#register/verify-email');
			}
		}
	}

	// delete by key
	//
	public function deleteIndex(string $verificationKey) {
		$emailVerification = EmailVerification::where('verification_key', '=', $verificationKey)->first();
		$emailVerification->delete();
		return $emailVerification;
	}
}
