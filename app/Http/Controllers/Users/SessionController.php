<?php
/******************************************************************************\
|                                                                              |
|                            SessionController.php                             |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a controller for sessions.                               |
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
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use App\Utilities\Uuids\Guid;
use App\Models\Users\User;
use App\Models\Users\UserAccount;
use App\Models\Users\UserEvent;
use App\Models\Users\EmailVerification;
use App\Models\Users\PasswordReset;
use App\Models\Users\UserClassMembership;
use App\Models\Viewers\Viewer;
use App\Http\Controllers\BaseController;
use App\Http\Controllers\Users\AppPasswordsController;
use App\Http\Controllers\Results\AssessmentResultsController;

class SessionController extends BaseController
{
	// control pre-launch of CodeDX viewer on login
	// 
	const preLaunchCodeDX = false;


	// initial login of user
	//
	public function postLogin(Request $request) {

		// parse parameters
		//
		$username = $request->input('username');
		$password = $request->input('password');

		// check if we can authenticate
		//
		$authenticatable = User::isAuthenticatable();
		if  ($authenticatable !== true) {
			return response("Authentication error. " . $authenticatable, 401);
		}

		// authenticate / validate user
		//
		$user = User::getByUsername($username);
		if ($user) {
			if ($user->isAuthenticated($password, true)) {
				$userAccount = $this->getOrCreateUserAccount($user);

				// check email verification
				//
				if ($user->hasBeenVerified()) {
					if ($user->isEnabled()) {

						if ($userAccount->isHibernating()) {

							// reactivate user's password
							//
							return $this->reactivatePassword($user);
						} else if ($userAccount->isPasswordResetRequired()) {

							// reset user's password
							//
							return $this->resetPassword($user);
						} else {

							// create new session
							//
							$request->session()->regenerate();

							// store user id in session
							//
							$user->setSession();

							// update login dates
							//
							$userAccount->updateDates();

							Log::info("Login attempt succeeded.");

							// pre-launch CodeDX viewer
							//
							if (self::preLaunchCodeDX) {
								$defaultViewer = Viewer::where('name', '=', 'CodeDX')->first();
								$trialProject = $user->getTrialProject();
								if ($defaultViewer && $trialProject) {	
									AssessmentResultsController::launchViewer('none', $defaultViewer->viewer_uuid, $trialProject->project_uid);
								}	
							}

							return response()->json([
								'user_uid' => $user->user_uid
							]);
						}
					} else {
						return response('User has not been approved.', 401);
					}
				} else {
					return response('User email has not been verified.', 401);
				}
			} else {
				Log::info("Login attempt failed.");
				return response('Incorrect username or password.', 401);
			}
		} else {
			return response('Incorrect username or password.', 401);
		}
	}

	// final logout of user
	//
	public function postLogout(Request $request) {

		// log the logout event
		//
		Log::info("User logout.");

		// destroy session cookies
		//
		Session::flush();
		Session::save();
		return response('SESSION_DESTROYED');

		//Auth::logout();
	}

	//
	// private utility methods
	//

	private function reactivatePassword(User $user) {

		// delete previous password resets belonging to this user
		//
		PasswordReset::where('user_uid', '=', $user->user_uid)->delete();

		// create new password reset
		//
		$passwordResetNonce = $nonce = Guid::create();
		$passwordReset = new PasswordReset([
			'password_reset_uuid' => Guid::create(),
			'password_reset_key' => Hash::make($passwordResetNonce),
			'user_uid' => $user->user_uid
		]);
		$passwordReset->save();

		// send password reset email
		//
		$passwordReset->send($nonce);

		// return response message
		//
		$contactEmail = config('mail.contact.address');
		return response("Due to a prolonged period of user account inactivity, we request that you select a new password for your account. SWAMP has sent a message containing a password reset link to your registered email address. Contact $contactEmail if you do not receive the message.", 409);
	}

	private function resetPassword(User $user) {

		// delete previous password resets belonging to this user
		//
		PasswordReset::where('user_uid', '=', $user->user_uid)->delete();

		// create new password reset
		//
		$passwordResetNonce = $nonce = Guid::create();
		$passwordReset = new PasswordReset([
			'password_reset_uuid' => Guid::create(),
			'password_reset_key' => Hash::make($passwordResetNonce),
			'user_uid' => $user->user_uid
		]);
		$passwordReset->save();

		// delete all app passwords for the user
		//
		$app_password_con = new AppPasswordsController();
		$app_password_con->deleteByUser($user->user_uid);

		// send password reset email
		//
		$passwordReset->send($nonce);

		// return response message
		//
		$contactEmail = config('mail.contact.address');
		return response("As part of our operations procedures, we request that you select a new password for your account. SWAMP has sent a message containing a password reset link to your registered email address. Contact $contactEmail if you do not receive the message.", 409);
	}

	/**
	 * When LDAP is enabled, the corresponding UserAccount object
	 * may need to be created on-the-fly for when the user first
	 * logs in, via either username/password or OAuth2. This function
	 * first tries to get an existing UserAccount account for the
	 * passed-in $user, or creates one if needed when LDAP is
	 * configured read-only.
	 */
	private function getOrCreateUserAccount(User $user) {
		$userAccount = $user->getUserAccount();

		// When LDAP is enabled for users, the user may
		// authenticate with LDAP but not have a corresponding
		// UserAccount. So create one now. Since we trust the LDAP
		// email address, set email_verified_flag to true.
		//
		if (config('ldap.enabled') && !$userAccount) {
				$userAccount = new UserAccount([
					'ldap_profile_update_date' => gmdate('Y-m-d H:i:s'),
					'user_uid' => $user->user_uid,
					'promo_code_id' => null,
					'enabled_flag' => true,
					'admin_flag' => false,
					'email_verified_flag' => true,
					'forcepwreset_flag' => false,
					'hibernate_flag' => false,
					'email_verified_flag' => config('mail.enabled')? 1 : -1
				]);
				$userAccount->save();
		}
		return $userAccount;
	}
}
