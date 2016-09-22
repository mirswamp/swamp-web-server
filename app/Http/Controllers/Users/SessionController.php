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
|        Copyright (C) 2012-2016 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Http\Controllers\Users;

use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use App\Utilities\Uuids\Guid;
use App\Models\Users\User;
use App\Models\Users\UserAccount;
use App\Models\Users\LinkedAccount;
use App\Models\Users\LinkedAccountProvider;
use App\Models\Users\UserEvent;
use App\Models\Users\EmailVerification;
use App\Models\Users\PasswordReset;
use App\Http\Controllers\BaseController;

/* require_once app_path().'/lib/httpful.phar'; */

class SessionController extends BaseController {

	// initial login of user
	//
	public function postLogin() {

		// get input parameters
		//
		$username = Input::get('username');
		$password = Input::get('password');

		// authenticate / validate user
		//
		$user = User::getByUsername($username);
		if ($user) {
			if (User::isValidPassword($password, $user->password)) {
				if ($user->hasBeenVerified()) {
					if ($user->isEnabled()) {
						$userAccount = $user->getUserAccount();
						if ($userAccount->isHibernating()) {

							// reactivate user's password
							//
							return $this->reactivatePassword($user);
						} else if ($userAccount->isPasswordResetRequired()) {

							// reset user's password
							//
							return $this->resetPassword($user);
						} else {

							// update login dates
							//
							$userAccount->penultimate_login_date = $userAccount->ultimate_login_date;
							$userAccount->ultimate_login_date = gmdate('Y-m-d H:i:s');

							// log in user
							//
							$userAccount->save();
							$response = response()->json(array('user_uid' => $user->user_uid));
							Session::set('timestamp', time());
							Session::set('user_uid', $user->user_uid);
							Session::save();
							return $response;
						}
					} else {
						return response('User has not been approved.', 401);
					}
				} else {
					return response('User email has not been verified.', 401);
				}
			} else {
				return response('Incorrect username or password.', 401);
			}
		} else {
			return response('Incorrect username or password.', 401);
		}
	}

	// final logout of user
	//
	public function postLogout() {

		// update last url visited
		//
		//$this->postUpdate();

		// destroy session cookies
		//
		Session::flush();
		return response('SESSION_DESTROYED');

		//Auth::logout();
	}

	// GitHub OAuth Callbacks
	//
	public function github() {

		if (!Session::has('github_state')) {
			return response('Unauthorized GitHub access.', 401);
		}

		$state = Session::get('github_state');
		$receivedState = Input::get('state'); // The state returned from github
		if (0 != strcasecmp($state, $receivedState)) { 
			$code = Input::get('code');
            if (empty($code)) {
                Log::notice("redirect from entering github");
                return Redirect::to(Config::get('app.url').'/github?code='.$code.'&state='.$receivedState);
            }
		}

		// get access token from github
		//
		$ch = curl_init('https://github.com/login/oauth/access_token');
		curl_setopt($ch, CURLOPT_POSTFIELDS, array(
			'client_id' =>  Config::get('github.client_id'),
			'client_secret' => Config::get('github.client_secret'),
			'code' => Input::get('code')
		));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
		curl_setopt($ch, CURLOPT_USERAGENT, 'SWAMP');
		$response = curl_exec($ch);

		$status = array();
		parse_str($response, $status);

		// a GitHub access_token has been granted
		//
		if (array_key_exists('access_token', $status)) {
			Session::set('github_access_token', $status["access_token"]);
			Session::set('github_access_time', gmdate('U'));
			Session::save();

			// get github user data
			//
			$ch = curl_init('https://api.github.com/user');
			curl_setopt($ch, CURLOPT_HTTPHEADER, array( "Authorization: token $status[access_token]" ));
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
			curl_setopt($ch, CURLOPT_USERAGENT, 'SWAMP');
			$response = curl_exec($ch);
			$github_user = json_decode($response);
			$account = LinkedAccount::where('user_external_id', '=', $github_user->id)->where('linked_account_provider_code','=','github')->first();

			// linked account record exists for this github user
			//
			if ($account) {

				// a SWAMP user account for the github user exists
				//
				$user = User::getIndex($account->user_uid);

				if ($user) {
					if ($user->hasBeenVerified()) {
						if ($user->isEnabled()) {

							// update user account
							//
							$userAccount = $user->getUserAccount();
							$userAccount->penultimate_login_date = $userAccount->ultimate_login_date;
							$userAccount->ultimate_login_date = gmdate('Y-m-d H:i:s');
							$userAccount->save();

							// set session info
							//
							Session::set('timestamp', time());
							Session::set('user_uid', $user->user_uid);
							Session::save();

							// github linked account disabled?
							//
							if (LinkedAccountProvider::where('linked_account_provider_code','=','github')->first()->enabled_flag != '1') {
								return Redirect::to( Config::get('app.cors_url') . '/#github/error/github-auth-disabled' );
							}

							// github authentication disabled?
							//
							if ($account->enabled_flag != '1') {
								return Redirect::to( Config::get('app.cors_url') . '/#github/error/github-account-disabled' );
							}

							return Redirect::to( Config::get('app.cors_url') );

						} else {
							return Redirect::to( Config::get('app.cors_url') . '/#github/error/not-enabled' );
						}
					} else {
						return Redirect::to( Config::get('app.cors_url') . '/#github/error/not-verified' );
					}
				} else {

					// SWAMP user not found for existing linked account.
					//
					LinkedAccount::where('user_external_id','=',$github_user->id)->where('linked_account_provider_code','=','github')->delete();
					return Redirect::to( Config::get('app.cors_url') . "/#github/prompt" );
				}
			} else {
				Session::save();
				return Redirect::to( Config::get('app.cors_url') . "/#github/prompt" );
			}

		// a GitHub access_token has not been granted
		//
		} else {
			return response('Unable to authenticate with GitHub.', 401);
		}
	}

	// retrieves and returns information about the currently logged in github user
	//
	public function githubUser() {

		if (!Session::has('github_access_token')) {
			// get acces token from github
			//
			return response('Unauthorized GitHub access.', 401); 
		}

		// get user info from github
		//
		$token = Session::get('github_access_token');
		$ch = curl_init('https://api.github.com/user');
		curl_setopt($ch, CURLOPT_HTTPHEADER, array( "Authorization: token $token" ));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
		curl_setopt($ch, CURLOPT_USERAGENT, 'SWAMP');
		$response = curl_exec($ch);
		$github_user = json_decode($response, true);
		$github_user['email'] = array_key_exists( 'email', $github_user ) ? $github_user['email'] : '';
		$user = array(
			'user_external_id' 	=> $github_user['id'],
			'username' 			=> $github_user['login'],
			'email'    			=> $github_user['email']
		);

		return $user;
	}

	public function registerGithubUser() {

		if (!Session::has('github_access_token')) {
			return response('Unauthorized GitHub access.', 401);
		}

		// get user info from github
		//
		$token = Session::get('github_access_token');
		$ch = curl_init('https://api.github.com/user');
		curl_setopt($ch, CURLOPT_HTTPHEADER, array( "Authorization: token $token" ));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
		curl_setopt($ch, CURLOPT_USERAGENT, 'SWAMP');
		$response = curl_exec($ch);
		$github_user = json_decode( $response, true );

		// Append email information
		//
		$ch = curl_init('https://api.github.com/user/emails');
		curl_setopt($ch, CURLOPT_HTTPHEADER, array( "Authorization: token $token" ));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
		curl_setopt($ch, CURLOPT_USERAGENT, 'SWAMP');
		$response = curl_exec($ch);

		$github_emails = json_decode( $response );

		$github_user['email'] = '';
		$primary_verified = false;
		foreach( $github_emails as $email ){
			if( ( $email->primary == '1' ) && ( $email->verified == '1' ) ){
				$primary_verified = true;
				$github_user['email'] = $email->email;
			}
			else if( $email->primary == '1' ){
				$github_user['email'] = $email->email;
			}
		}

		$names = array_key_exists('name', $github_user) ? explode(' ', $github_user['name']) : array('','');

		$user = new User(array(
			'first_name' => array_key_exists( 0, $names ) ? $names[0] : '',
			'last_name' => array_key_exists( 1, $names ) ? $names[1] : '',
			'preferred_name' => array_key_exists( 'name', $github_user ) ? $github_user['name'] : '',
			'username' => $github_user['login'],
			'password' => md5(uniqid()).strtoupper(md5(uniqid())),
			'user_uid' => Guid::create(),
			'email' => $github_user['email'],
			'address' => array_key_exists( 'location', $github_user ) ? $github_user['location'] : ''
		));

		// attempt username permutations
		//
		for ($i = 1; $i < 21; $i++) {
			$errors = array();
			if ($user->isValid($errors, true)) {
				break;
			}
			if ( $i == 20 ) {
				return response('Unable to generate SWAMP GitHub user:<br/><br/>'.implode( '<br/>', $errors ), 401);
			}
			$user->username = $github_user['login'].$i;
		}

		$user->add();

		// create linked account record
		//
		$linkedAccount = new LinkedAccount(array(
			'user_uid' => $user->user_uid,
			'user_external_id' => $github_user['id'],
			'linked_account_provider_code' => 'github',
			'enabled_flag' => 1
		));
		$linkedAccount->save();

		if ($primary_verified) {

			// mark user account email verified flag
			//
			$userAccount = $user->getUserAccount();
			$userAccount->email_verified_flag = 1;
			$userAccount->save();

			// send welcome email
			//
			if (Config::get('mail.enabled')) {
				Mail::send('emails.welcome', array(
					'user'		=> $user,
					'logo'		=> Config::get('app.cors_url') . '/images/logos/swamp-logo-small.png',
					'manual'	=> Config::get('app.cors_url') . '/documentation/SWAMP-UserManual.pdf',
				), function($message) use ($user) {
					$message->to($user->email, $user->getFullName());
					$message->subject('Welcome to the Software Assurance Marketplace');
				});
			}

			return response()->json(array(
				'primary_verified' => true,
				'user' => $user
			));
		} else {

			// create email verification record
			//
			$emailVerification = new EmailVerification(array(
				'user_uid' => $user->user_uid,
				'verification_key' => Guid::create(),
				'email' => $user->email
			));
			$emailVerification->save();

			// send email verification
			//
			$emailVerification->send('#register/verify-email');

			return response()->json(array(
				'primary_verified' => false,
				'user' => $user
			));
		}
	}

	public function githubRedirect() {
		$path = '/github';
		$redirectUri = urlencode(Config::get('app.url').$path);
		$gitHubClientId = Config::get('github.client_id');

		// create an unguessable state value that must be stored and later checked
		//
		$state = hash('sha256', substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 32));
		Session::set('github_state', $state);
		Session::save();
		
		// redirect to github
		//
		$redirectUri = urlencode(Config::get('app.url').$path);
		$gitHubClientId = Config::get('github.client_id');
		return Redirect::to('https://github.com/login/oauth/authorize?redirect_uri=' . $redirectUri . 
			'&client_id=' . $gitHubClientId . 
			'&scope=user:email' .
			'&state=' . $state);
	}

	private function linkGitHubAccount($user) {

		// Attempt to load the github account the user is currently logged in as.
		//
		if ((!Session::has('github_access_token')) || (!Session::has('github_access_time'))) {
			return response('Unauthorized GitHub access.', 401);
		}

		// check for expired github access
		//
		Log::notice("git hub access time " . Session::get('github_access_time'));
		Log::notice("gm date U " . gmdate('U'));

		// if (gmdate('U') - Session::get('github_access_time') > (1 * 60)) {
		// 	Log::notice("entered if statement.");
		// 	return response('GitHub access has expired.  If you would like to link a GitHub account to an existing SWAMP account, please click "Sign In" and select "Sign in With GitHub."', 401);
		// }

		// check github access token via github
		//
		$token = Session::get('github_access_token');
		$ch = curl_init('https://api.github.com/user');
		curl_setopt($ch, CURLOPT_HTTPHEADER, array( "Authorization: token $token" ));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
		curl_setopt($ch, CURLOPT_USERAGENT, 'SWAMP');
		$response = curl_exec($ch);
		$github_user = json_decode($response);
		if (!property_exists( $github_user, 'id')) {
			return response('Unable to authenticate with GitHub.', 401);
		}

		// make sure they don't already have an account
		//
		$account = LinkedAccount::where('user_uid','=',$user->user_uid)->where('linked_account_provider_code','=','github')->first();
		if( $account && ! ( Input::has('confirmed') && Input::get('confirmed') === 'true' ) ){
			return response()->json(array(
				'error' => 'EXISTING_ACCOUNT',
				'username' => $user->username,
				'login' => $github_user->login
			), 401);
		}

		// verify they are logged in as the account they are attempting to link to.
		//
		if ($github_user->id != Input::get('github_id')) {
			return response('Unauthorized GitHub access.', 401);
		}

		// remove any old entries
		//
		LinkedAccount::where('user_uid','=',$user->user_uid)->where('linked_account_provider_code','=','github')->delete();

		// link the accounts
		//
		$linkedAccount = new LinkedAccount(array(
			'linked_account_provider_code' => 'github',
			'user_external_id' => Input::get('github_id'),
			'enabled_flag' => 1,
			'user_uid' => $user->user_uid,
			'create_date' => gmdate('Y-m-d H:i:s')
		));
		$linkedAccount->save();
		$userEvent = new UserEvent(array(
			'user_uid' => $user->user_uid,
			'event_type' => 'linkedAccountCreated',
			'value' => json_encode(array(
				'linked_account_provider_code' 	=> 'github',
				'user_external_id' 				=> $linkedAccount->user_external_id,
				'user_ip' 						=> $_SERVER['REMOTE_ADDR']
			))
		));
		$userEvent->save();
		response('User account linked!');
	}

	public function githubLink() {

		if (gmdate('U') - Session::get('github_access_time') > (15 * 60)) {
			Log::notice("entered if statement.");
			return response('GitHub access has expired.  If you would like to link a GitHub account to an existing SWAMP account, please click "Sign In" and select "Sign in With GitHub."', 401);
		}

		// get input parameters
		//
		$username = Input::get('username');
		$password = Input::get('password');

		// authenticate / validate user
		//
		$user = User::getByUsername($username);
		if ($user) {
			if (User::isValidPassword($password, $user->password)) {
				if ($user->hasBeenVerified()) {
					if ($user->isEnabled()) {
						$userAccount = $user->getUserAccount();
						if ($userAccount->isHibernating()) {

							// reactivate user's password
							//
							return $this->reactivatePassword($user);

						} else if ($userAccount->isPasswordResetRequired()) {

							// reset user's password
							//
							return $this->resetPassword($user);
						} else {

							// link user's github account
							//
							return $this->linkGitHubAccount($user);
						}
					} else {
						return response('User has not been approved.', 401);
					}
				} else {
					return response('User email has not been verified.', 401);
				}
			} else {
				return response('Incorrect username or password.', 401);
			}
		} else {
			return response('Incorrect username or password.', 401);
		}
	}

	public function githubLogin() {

		// Attempt to load the github account the user is currently logged in as.
		//
		if( ! Session::has('github_access_token') )
			return response('Unauthorized GitHub access.', 401);

		$token = Session::get('github_access_token');

		$user = User::getIndex(Session::get('user_uid'));
		$account = LinkedAccount::where('user_uid', '=', $user->user_uid)->first();
		if ($user) {
			if ($user->hasBeenVerified()) {
				if ($user->isEnabled()) {

					// update login dates
					//
					$userAccount = $user->getUserAccount();
					$userAccount->penultimate_login_date = $userAccount->ultimate_login_date;
					$userAccount->ultimate_login_date = gmdate('Y-m-d H:i:s');
					$userAccount->save();

					// create new event
					//
					$userEvent = new UserEvent(array(
						'user_uid' 		=> $user->user_uid,
						'event_type' 	=> 'linkedAccountSignIn',
						'value' => json_encode(array(
							'linked_account_provider_code' 	=> 'github',
							'user_external_id' 				=> $account->user_external_id,
							'user_ip' 						=> $_SERVER['REMOTE_ADDR']
						))
					));
					$userEvent->save();

					$res = response()->json(array(
						'user_uid' => $user->user_uid
					));

					// set session info
					//
					Session::set('timestamp', time());
					Session::set('user_uid', $user->user_uid);
					Session::save();
					return $res;
				} else {
					return response('User has not been approved.', 401);
				}
			} else {
				return response('User email has not been verified.', 401);
			}
		} else {
			return response('Incorrect username or password.', 401);
		}
	}

	//
	// private utility methods
	//

	private function reactivatePassword($user) {

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

		// return response message
		//
		$contactEmail = Config::get('mail.contact.address');
		return response("Due to a prolonged period of user account inactivity, we request that you select a new password for your account. SWAMP has sent a message containing a password reset link to your registered email address. Contact support@continuousassurance.org if you do not receive the message.", 409);
	}

	private function resetPassword($user) {

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

		// return response message
		//
		$contactEmail = Config::get('mail.contact.address');
		return response("As part of our operations procedures, we request that you select a new password for your account. SWAMP has sent a message containing a password reset link to your registered email address. Contact support@continuousassurance.org if you do not receive the message.", 409);
	}
}
