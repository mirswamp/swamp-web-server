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
|        Copyright (C) 2012-2016 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Http\Controllers\Users;

use DateTime;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use App\Utilities\Uuids\Guid;
use App\Models\Users\User;
use App\Models\Users\EmailVerification;
use App\Models\Users\UserAccount;
use App\Http\Controllers\BaseController;

class EmailVerificationsController extends BaseController {

	// create
	//
	public function postCreate() {
		$emailVerification = new EmailVerification(array(
			'user_uid' => Input::get('user_uid'),
			'verification_key' => Guid::create(),
			'email' => Input::get('email')
		));
		$emailVerification->save();
		$emailVerification->send(Input::get('verify_route'));
		return $emailVerification;
	}

	// get by key
	//
	public function getIndex($verificationKey) {
		$emailVerification = EmailVerification::where('verification_key', '=', $verificationKey)->first();
		$user = User::getIndex($emailVerification->user_uid);
		$user['user_uid'] = $emailVerification->user_uid;
		$emailVerification->user = $user;
		return $emailVerification;
	}

	// update by key
	//
	public function updateIndex($verificationKey) {

		// get model
		//
		$emailVerification = EmailVerification::where('verification_key', '=', $verificationKey)->first();
		
		// update attributes
		//
		$emailVerification->user_uid = Input::get('user_uid');
		$emailVerification->verification_key = Input::get('verification_key');
		$emailVerification->email = Input::get('email');
		$emailVerification->verify_date = Input::get('verify_date');

		// save changes
		//
		$changes = $emailVerification->getDirty();
		$emailVerification->save();

		// update user account
		//
		$userAccount = UserAccount::where('user_uid', '=', $emailVerification->user_uid)->first();
		$userAccount->email_verified_flag = $emailVerification->verify_date ? 1 : 0;
		$userAccount->save();

		// return changes
		//
		return $changes;
	}

	// verify by key
	//
	public function putVerify($verificationKey) {
		$emailVerification = EmailVerification::where('verification_key', '=', $verificationKey)->first();
		$emailVerification->verify_date = new DateTime();

		$userAccount = UserAccount::where('user_uid', '=', $emailVerification->user_uid)->first();

		$user = User::getIndex($emailVerification->user_uid);
		$username = $user->username;
		$oldEmail = $user->email;
		$user->email = $emailVerification->email;

		unset( $user->owner );
		unset( $user->username );

		$errors = array();

		if (!$user->hasBeenVerified() || $user->isValid($errors)){
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
		if (Config::get('mail.enabled')) {
			if (!$user->hasBeenVerified()) {

				// automatically send welcome email
				//	
				Mail::send('emails.welcome', array(
					'user'		=> $user,
					'logo'		=> Config::get('app.cors_url') . '/images/logos/swamp-logo-small.png'
				), function($message) use ($user) {
					$message->to($user->email, $user->getFullName());
					$message->subject('Welcome to the Software Assurance Marketplace');
				});
			} else {

				// send notification if email has changed
				//
				$data = array('fullName' => $user->getFullName(), 'oldEmail' => $oldEmail);

				Mail::send('emails.email-verification-oldemail', array(
					'user'		=> $user,
					'logo'		=> Config::get('app.cors_url') . '/images/logos/swamp-logo-small.png'
				), function($message) use($data) {
				    $message->to($data['oldEmail'], $data['fullName']);
				    $message->subject('SWAMP Email Changed');
				});
			}
		}

		// update user account
		//
		$userAccount->email_verified_flag = 1;
		$userAccount->save();

		// save email verification
		//
		$emailVerification->save();

		return response('This email address has been verified.', 200);
	}

	// resend by username, password
	//
	public function postResend() {

		// get input parameters
		//
		$username = Input::get('username');
		$password = Input::get('password');

		// validate user
		//
		$user = User::getByUsername($username);
		if ($user) {
			if (User::isValidPassword($password, $user->password)) {

				// get email verification
				//
				$emailVerification = $user->getEmailVerification();

				// resend
				//
				$emailVerification->send('#register/verify-email');
			}
		}
	}

	// delete by key
	//
	public function deleteIndex($verificationKey) {
		$emailVerification = EmailVerification::where('verification_key', '=', $verificationKey)->first();
		$emailVerification->delete();
		return $emailVerification;
	}
}
