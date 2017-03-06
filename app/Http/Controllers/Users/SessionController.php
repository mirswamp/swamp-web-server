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
|        Copyright (C) 2012-2017 Software Assurance Marketplace (SWAMP)        |
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
use App\Models\Utilities\Configuration;
use App\Http\Controllers\BaseController;
use App\Utilities\Identity\IdentityProvider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use ErrorException;

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
			if (User::isValidPassword($user, $password, $user->password)) {

				$userAccount = $user->getUserAccount();

				// In the case of local read-only LDAP, the user may
				// authenticate with LDAP but not have a corresponding 
				// UserAccount. So create one now. Since we trust the LDAP
				// email address, set email_verified_flag to '1'.
				$configuration = new Configuration;
				if (($configuration->getLdapReadOnlyAttribute()) &&
					(count($userAccount) == 0)) {
						$userAccount = new UserAccount(array(
							'ldap_profile_update_date' => gmdate('Y-m-d H:i:s'),
							'user_uid' => $user->user_uid,
							'promo_code_id' => null,
							'enabled_flag' => 1,
							'owner_flag' => 0,
							'admin_flag' => 0,
							'email_verified_flag' => Config::get('mail.enabled')? 1 : -1
						));
						$userAccount->save();
				}

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

							// update login dates
							//
							$userAccount->penultimate_login_date = $userAccount->ultimate_login_date;
							$userAccount->ultimate_login_date = gmdate('Y-m-d H:i:s');
							$userAccount->save();

							// log in user
							//
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

		// destroy session cookies
		//
		Session::flush();
		return response('SESSION_DESTROYED');

		//Auth::logout();
	}

	// OAuth 2.0 Callbacks
	//
	public function oauth2() {

		if (!Session::has('oauth2_state')) {
			return response('Unauthorized OAuth2 access.', 401);
		}

		$state = Session::get('oauth2_state');
		$receivedState = Input::get('state'); // The state returned from OAuth server
		if (0 != strcasecmp($state, $receivedState)) { 
			return response('Mismatched state. Unauthorized OAuth2 access.', 401);
		}

		// get access token from OAuth server
		//
		$idp = new IdentityProvider();
		try {
			$token = $idp->provider->getAccessToken('authorization_code', [
				'code' => Input::get('code')
			]);

			Session::set('oauth2_access_token', $token);
			Session::set('oauth2_access_time', gmdate('U'));
			Session::save();

			$oauth2_user = $idp->provider->getResourceOwner($token);
			$account = LinkedAccount::where('user_external_id','=',$oauth2_user->getID())->where('linked_account_provider_code','=',$idp->linked_provider)->first();

			if ($account) { // linked account record exists for this oauth2 user

				// a SWAMP user account for the oauth2 user exists
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

							// oauth2 linked account disabled?
							//
							if (LinkedAccountProvider::where('linked_account_provider_code','=',$idp->linked_provider)->first()->enabled_flag != '1') {
								return Redirect::to(Config::get('app.cors_url').'/#linked-account/error/oauth2-auth-disabled');
							}

							// oauth2 authentication disabled?
							//
							if ($account->enabled_flag != '1') {
								return Redirect::to(Config::get('app.cors_url').'/#linked-account/error/oauth2-account-disabled');
							}

							return Redirect::to(Config::get('app.cors_url'));

						} else {
							return Redirect::to(Config::get('app.cors_url').'/#linked-account/error/not-enabled');
						}
					} else {
						return Redirect::to(Config::get('app.cors_url').'/#linked-account/error/not-verified');
					}
				} else {

					// SWAMP user not found for existing linked account.
					//
					LinkedAccount::where('user_external_id','=',$oauth2_user->getId())->where('linked_account_provider_code','=',$idp->linked_provider)->delete();
					return Redirect::to( Config::get('app.cors_url').'/#linked-account/prompt');
				}
			} else {
				Session::save();
				return Redirect::to( Config::get('app.cors_url').'/#linked-account/prompt');
			}

		} catch (IdentityProviderException $e) {
			return response('Unable to authenticate with your identity provider.  Your session may have timed out.  Please try again.', 401);
		} catch (Exception $e) {
			return response('Unable to authenticate with your identity provider.  Your session may have timed out.  Please try again.', 401);
		} catch (Error $err) {
			return response('Unable to authenticate with your identity provider.  Your session may have timed out.  Please try again.', 401);
		}
	}

	// retrieves and returns information about the currently logged in oauth2 user
	//
	public function oauth2User() {

		if (!Session::has('oauth2_access_token')) {
			return response('Unauthorized OAuth2 access.', 401); 
		}

		$idp = new IdentityProvider();
		$token = Session::get('oauth2_access_token');
		try {
			$oauth2_user = $idp->provider->getResourceOwner($token);
			$user = array(
				'user_external_id' => $oauth2_user->getId(),
				'username'         => $this->getUsername($oauth2_user),
				'email'            => $oauth2_user->getEmail(),
			);
			return $user;
		} catch (IdentityProviderException $e) {
			return response('Unable to authenticate with your identity provider.  Your session may have timed out.  Please try again.', 401);
		} catch (Exception $e) {
			return response('Unable to authenticate with your identity provider.  Your session may have timed out.  Please try again.', 401);
		} catch (Error $err) {
			return response('Unable to authenticate with your identity provider.  Your session may have timed out.  Please try again.', 401);
		}
	}

	public function registerOAuth2User() {

		if (!Session::has('oauth2_access_token')) {
			return response('Unauthorized OAuth2 access.', 401);
		}

		$idp = new IdentityProvider();
		$token = Session::get('oauth2_access_token');
		try {
			// Get user info
			$oauth2_user = $idp->provider->getResourceOwner($token);

			$oauth2_email = '';
			$primary_verified = false;
			if ($idp->linked_provider == 'github') {
				// Get verified email info from GitHub
				$request = $idp->provider->getAuthenticatedRequest(
					'GET',
					'https://api.github.com/user/emails',
					$token
				);
				$github_emails = $idp->provider->getResponse($request);

				foreach ($github_emails as $email) {
				  if (($email['primary'] == '1') && ($email['verified'] == '1')) {
						$primary_verified = true;
						$oauth2_email = $email['email'];
						break;
					}
					elseif ($email['primary'] == '1') {
						$oauth2_email = $email['email'];
						break;
					}
				}
			} else { // For Google and CILogon, use the primary email address
				$oauth2_email = $oauth2_user->getEmail();
				// For now, assume Google/CILogon email addresses are verified.
				$primary_verified = true;
			}

			list($firstname,$lastname,$fullname) = $this->getNames($oauth2_user);
			$username = $this->getUsername($oauth2_user);
			$oauth2_user_array = $oauth2_user->toArray();

			$user = new User(array(
				'first_name'     => $firstname,
				'last_name'      => $lastname,
				'preferred_name' => $fullname,
				'username'       => $username,
				'password'       => md5(uniqid()).strtoupper(md5(uniqid())),
				'user_uid'       => Guid::create(),
				'email'          => $oauth2_email,
				'address'        => array_key_exists('location', $oauth2_user_array) ? $oauth2_user_array['location'] : ''
			));

			// Attempt username permutations
			//
			$maxusernames = 500; // Maximum number of usernames to try
			for ($i = 1; $i <= $maxusernames; $i++) {
				$errors = array();
				// If user is valid, then everything is okay to proceed
				if ($user->isValid($errors, true)) {
					break;
				}

				$errorstr = implode('<br/>', $errors);
				// Check for 'email address already in use' message.
				// No need to check any more usernames; user should 'link' instead.
				if (preg_match('/The email address .* is already in use/',$errorstr)) {
					$i = $maxusernames;
				}
				
				// If we have tried the max number of usernames, give up.
				if ($i == $maxusernames) {
					return response('Unable to generate SWAMP user:<br/><br/>'.$errorstr, 401);
				}
				$user->username = $username . $i;
			}

			$user->add();

			// create linked account record
			//
			$linkedAccount = new LinkedAccount(array(
				'user_uid' => $user->user_uid,
				'user_external_id' => $oauth2_user->getId(),
				'linked_account_provider_code' => $idp->linked_provider,
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
		} catch (IdentityProviderException $e) {
			return response('Unable to authenticate with your identity provider.  Your session may have timed out.  Please try again.', 401);
		} catch (Exception $e) {
			return response('Unable to authenticate with your identity provider.  Your session may have timed out.  Please try again.', 401);
		} catch (Error $err) {
			return response('Unable to authenticate with your identity provider.  Your session may have timed out.  Please try again.', 401);
		}
	}

	public function oauth2Redirect() {
		$entityid = Input::get('entityid');
		$idp = new IdentityProvider($entityid);
		if (is_null($idp->provider)) {
			return response('Invalid Identity Provider "' . $entityid . '".', 401);
		} else {
			$authUrl = $idp->provider->getAuthorizationUrl($idp->authzUrlOpts);
			Session::set('oauth2_state', $idp->provider->getState());
			Session::save();
			return Redirect::to($authUrl);
		}
	}

	private function linkOAuth2Account($user) {

		// Attempt to load the oauth2 account the user is currently logged in as.
		//
		if ((!Session::has('oauth2_access_token')) || (!Session::has('oauth2_access_time'))) {
			return response('Unauthorized Oauth2 access.', 401);
		}

		// check for expired oauth2 access
		//
		Log::notice("oauth2 access time " . Session::get('oauth2_access_time'));
		Log::notice("gm date U " . gmdate('U'));

		// check oauth2 access token via oauth2
		//
		$idp = new IdentityProvider();
		$token = Session::get('oauth2_access_token');
		try {
			$oauth2_user = $idp->provider->getResourceOwner($token);
			$oauth2_id = $oauth2_user->getId();
			$oauth2_login = $this->getUsername($oauth2_user);
		} catch (IdentityProviderException $e) {
			return response('Unable to authenticate with your identity provider.  Your session may have timed out.  Please try again.', 401);
		} catch (Exception $e) {
			return response('Unable to authenticate with your identity provider.  Your session may have timed out.  Please try again.', 401);
		} catch (Error $err) {
			return response('Unable to authenticate with your identity provider.  Your session may have timed out.  Please try again.', 401);
		}

		// make sure they don't already have an account
		//
		$account = LinkedAccount::where('user_uid','=',$user->user_uid)->where('linked_account_provider_code','=',$idp->linked_provider)->first();
		if ($account && !(Input::has('confirmed') && Input::get('confirmed') === 'true' )) {
			return response()->json(array(
				'error' => 'EXISTING_ACCOUNT',
				'username' => $user->username,
				'login' => $oauth2_login
			), 401);
		}

		// verify they are logged in as the account they are attempting to link to.
		//
		if ($oauth2_id != Input::get('oauth2_id')) {
			return response('Unauthorized OAuth2 access.', 401);
		}

		// remove any old entries
		//
		LinkedAccount::where('user_uid','=',$user->user_uid)->where('linked_account_provider_code','=',$idp->linked_provider)->delete();

		// link the accounts
		//
		$linkedAccount = new LinkedAccount(array(
			'linked_account_provider_code' => $idp->linked_provider,
			'user_external_id' => Input::get('oauth2_id'),
			'enabled_flag' => 1,
			'user_uid' => $user->user_uid,
			'create_date' => gmdate('Y-m-d H:i:s')
		));
		$linkedAccount->save();
		$userEvent = new UserEvent(array(
			'user_uid' => $user->user_uid,
			'event_type' => 'linkedAccountCreated',
			'value' => json_encode(array(
				'linked_account_provider_code' => $idp->linked_provider,
				'user_external_id' => $linkedAccount->user_external_id,
				'user_ip' => $_SERVER['REMOTE_ADDR']
			))
		));
		$userEvent->save();
		return response('User account linked!');
	}

	public function oauth2Link() {

		if (gmdate('U') - Session::get('oauth2_access_time') > 
			(Config::get('oauth2.session_expiration') * 60)) {
			return response('OAuth2 access has expired.  If you would like to link an external account to an existing SWAMP account, please click "Sign In" and select an external Identity Provider.', 401);
		}

		// get input parameters
		//
		$username = Input::get('username');
		$password = Input::get('password');

		// authenticate / validate user
		//
		$user = User::getByUsername($username);
		if ($user) {
			if (User::isValidPassword($user, $password, $user->password)) {
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

							// link user's oauth2 account
							//
							return $this->linkOAuth2Account($user);
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

	public function oauth2Login() {

		if (!Session::has('oauth2_access_token')) {
			return response('Unauthorized OAuth2 access.', 401);
		}

		$idp = new IdentityProvider();
		if (strlen($idp->linked_provider) == 0) {
			return response('Invalid Identity Provider.', 401);
		}
		$token = Session::get('oauth2_access_token');

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
							'linked_account_provider_code' 	=> $idp->linked_provider,
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

	/**
	 * This utility function calculates a 'username' for the OAuth2
	 * user. For GitHub logins, simply use the 'login' value returned
	 * as getNickname(). For Google/CILogon logins, use the first
	 * part of the email address before the '@'. 
	 */
	private function getUsername($oauth2_user) {
		$login = '';
		if (method_exists($oauth2_user,'getNickname')) {
			try {
				$login = $oauth2_user->getNickname();
			} catch (\Exception $e) {
			} catch (Error $err) {
			}
		}
		if (strlen($login) > 0) { // For GitHub, use the 'login'
			return $login;
		} else { // For Google/CILogon, use email address before '@'
			return strtolower(explode('@',$oauth2_user->getEmail())[0]);
		}
	}

	/**
	 * This utility function returns the first, last, and full names of
	 * the passed in $oauth2_user object. For GitHub, there is just a
	 * single "full name", so break that up into first and last names
	 * by splitting on a space character. For Google and CILogon, 
	 * first, last, and full names may be available. 
	 * 
	 * The names are returned in an array, so call as:
	 *   list($first,$last,$full) = $this->getNames($oauth2_user);
	 */
	private function getNames($oauth2_user) {
		$firstname = '';
		$lastname  = '';
		$fullname  = '';

		if ($oauth2_user) {

			// See if we can get full name from CILogon/Google/GitHub
			//
			if (method_exists($oauth2_user, 'getName')) {
				try {
					$fullname = $oauth2_user->getName();
				} catch (Exception $e) {
				} catch (Error $err) {
				}
			}

			// See if we can get first name from CILogon/Google
			//
			if (method_exists($oauth2_user, 'getFirstName')) {
				try {
					$firstname = $oauth2_user->getFirstName();
				} catch (Exception $e) {
				} catch (Error $err) {
				}
			}

			// See if we can get last name from CILogon/Google
			//
			if (method_exists($oauth2_user, 'getLastName')) {
				try { 
					$lastname = $oauth2_user->getLastName();
				} catch (Exception $e) {
				} catch (Error $err) {
				}
			}
		}

		// If we got full name, check if first or last name was missing
		// and fill in by splitting full name on 'space char'.
		if (strlen($fullname) > 0) {
			$names = preg_split('/\s+/',$fullname,2);
			if (strlen($firstname) == 0) {
				$firstname = @$names[0];
			}
			if (strlen($lastname) == 0) {
				$lastname =  @$names[1];
			}
		} else { // If full name is missing, concat first and last name
			$fullname = $firstname . ' ' . $lastname;
		}

		// Return names as an array (i.e., use list($first,last,$full)=...)
		return array($firstname,$lastname,$fullname);
	}
}
