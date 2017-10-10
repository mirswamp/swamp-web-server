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
use App\Http\Controllers\BaseController;
use App\Http\Controllers\Users\AppPasswordsController;
use App\Utilities\Identity\IdentityProvider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessToken;
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

							// update login dates
							//
							$userAccount->penultimate_login_date = $userAccount->ultimate_login_date;
							$userAccount->ultimate_login_date = gmdate('Y-m-d H:i:s');
							$userAccount->save();

							// Successful login - set user_uid session var
							User::setUserUidInSession($user->user_uid);

							Log::info("Login attempt succeeded.");

							return response()->json(array('user_uid' => $user->user_uid));
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
	public function postLogout() {
		// Log the logout event
		Log::info("User logout.");

		// destroy session cookies
		//
		Session::flush();
		Session::save();
		return response('SESSION_DESTROYED');

		//Auth::logout();
	}

	// OAuth 2.0 Callbacks
	//

	/**
	 * oauth2() is the route called by an external OAuth2 Identity Provider
	 * (IdP) after successful authentication at that IdP, a.k.a. the callback
	 * URL or "redirect_uri". There will be a "?code=..." query parameter
	 * which can be used * to get an access token for the user.
	 */
	public function oauth2() {

		if (!Session::has('oauth2_state')) {
			return Redirect::to(Config::get('app.cors_url').'/#linked-account/error/linked-account-general-error');
		}

		$state = Session::pull('oauth2_state'); // Forget it afterwards
		$code = Input::get('code','');
		$receivedState = Input::get('state'); // The state returned from OAuth server

		if (0 != strcasecmp($state, $receivedState)) {
			return Redirect::to(Config::get('app.cors_url').'/#linked-account/error/linked-account-general-error');
		}

		if (strlen($code) == 0) {
			return Redirect::to(Config::get('app.cors_url').'/#linked-account/error/linked-account-general-error');
		}

		// get access token from OAuth server
		//
		$idp = new IdentityProvider();
		try {
			$token = $idp->provider->getAccessToken('authorization_code', [
				'code' => $code
			]);

			Session::set('oauth2_access_token', $token->getToken());
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

							// oauth2 linked account disabled?
							//
							if (LinkedAccountProvider::where('linked_account_provider_code','=',$idp->linked_provider)->first()->enabled_flag != '1') {
								return Redirect::to(Config::get('app.cors_url').'/#linked-account/error/linked-account-auth-disabled');
							}

							// oauth2 authentication disabled?
							//
							if ($account->enabled_flag != '1') {
								return Redirect::to(Config::get('app.cors_url').'/#linked-account/error/linked-account-disabled');
							}

							// Successful login - set user_uid session var
							User::setUserUidInSession($user->user_uid);

							// Log the successful OAauth2 account login event
							Log::info("Linked account login success.",
								array(
									'linked_account_provider_code' => $idp->linked_provider,
									'user_external_id' => $account->user_external_id,
								)
							);

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
				return Redirect::to( Config::get('app.cors_url').'/#linked-account/prompt');
			}

		} catch (IdentityProviderException $e) {
			return Redirect::to(Config::get('app.cors_url').'/#linked-account/error/linked-account-login-error');
		} catch (Exception $e) {
			return Redirect::to(Config::get('app.cors_url').'/#linked-account/error/linked-account-login-error');
		} catch (Error $err) {
			return Redirect::to(Config::get('app.cors_url').'/#linked-account/error/linked-account-login-error');
		}
	}

	// retrieves and returns information about the currently logged in oauth2 user
	//
	public function oauth2User() {

		$token = $this->getAccessTokenFromSession();
		if (is_null($token)) {
			return response('Unauthorized OAuth2 access.', 401);
		}

		$idp = new IdentityProvider();
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

		$token = $this->getAccessTokenFromSession();
		if (is_null($token)) {
			return response('Unauthorized OAuth2 access.', 401);
		}

		$idp = new IdentityProvider();
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
				$github_emails = json_decode(
					$idp->provider->getResponse($request)->getBody());

				foreach ($github_emails as $email) {
				  if (($email->primary == 1) && ($email->verified == 1)) {
						$primary_verified = true;
						$oauth2_email = $email->email;
						break;
					}
					elseif ($email->primary == 1) {
						$oauth2_email = $email->email;
						break;
					}
				}

				// If email is not configured as enabled, then there's no way to verify
				// the github address. So as a fallback, set $primary_verified to true.
				if (!Config::get('mail.enabled')) {
					$primary_verified = true;
				}
			} else { // For Google and CILogon, use the primary email address
				$oauth2_email = $oauth2_user->getEmail();
				// For now, assume Google/CILogon email addresses are verified.
				$primary_verified = true;
			}

			if (strlen($oauth2_email) == 0) {
				return response('Unable to generate SWAMP user:<br/><br/>Missing email address.', 401);
			}

			list($firstname,$lastname,$fullname) = $this->getNames($oauth2_user);
			$username = $this->getUsername($oauth2_user);
			$oauth2_user_array = $oauth2_user->toArray();

			// In order for the $user->isValid() checks to work properly, we must set
			// user_uid=null here so that isValid() performs the checks for both existing
			// username and email address. If isValid() succeeds, we can set user_uid.
			$user = new User(array(
				'first_name'     => $firstname,
				'last_name'      => $lastname,
				'preferred_name' => $fullname,
				'username'       => $username,
				'password'       => Hash::make(uniqid()),
				'user_uid'       => null,
				'email'          => $oauth2_email
			));

			// Attempt username permutations
			//
			$maxtries = 500; // Maximum number of usernames to try
			for ($i = 1; $i <= $maxtries; $i++) {
				$errors = array();
				// If user is valid, then everything is okay to proceed
				if ($user->isValid($errors, true)) {
					// NOW we can set the user_uid.
					$user['user_uid'] = Guid::create();
					break;
				}

				$errorstr = implode('<br/>', $errors);
				// Check for 'email address already in use' message.
				// No need to check any more usernames; user should 'link' instead.
				if (preg_match('/The email address .* is already in use/',$errorstr)) {
					$i = $maxtries;
				}
				
				// If we have tried the max number of usernames, give up.
				if ($i == $maxtries) {
					return response('Unable to generate SWAMP user:<br/><br/>'.$errorstr, 401);
				}
				$user->username = $username . $i;
			}

			// Try to add the user catching password problems due to cracklib
			for ($i = 1; $i <= $maxtries; $i++) {
				try {
					$user->add();
					break;
				} catch (\ErrorException $e) {
					$i++;
					$user->password = Hash::make(uniqid());
				}

				// If we have tried the max number of tries, give up.
				if ($i == $maxtries) {
					return response('Unable to generate SWAMP user:<br/><br/> Problem generating password', 401);
				}
			}

			// create linked account record
			//
			$linkedAccount = new LinkedAccount(array(
				'user_uid' => $user->user_uid,
				'user_external_id' => $oauth2_user->getId(),
				'linked_account_provider_code' => $idp->linked_provider,
				'enabled_flag' => 1
			));
			$linkedAccount->save();

			// Log the successful OAauth2 account creation event
			Log::info("Account created via linked account.",
				array(
					'user_uid' => $user->user_uid,
					'linked_account_provider_code' => $idp->linked_provider,
					'user_external_id' => $linkedAccount->user_external_id,
					'primary_verified' => $primary_verified,
				)
			);

			if ($primary_verified) {

				// mark user account email verified flag
				//
				$userAccount = $user->getUserAccount();
				$userAccount->email_verified_flag = 1;
				$userAccount->save();

				// send welcome email
				//
				if (Config::get('mail.enabled')) {
					if ($user && $user->email && filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
						Mail::send('emails.welcome', array(
							'user'		=> $user,
							'logo'		=> Config::get('app.cors_url') . '/images/logos/swamp-logo-small.png',
							'manual'	=> Config::get('app.cors_url') . '/documentation/SWAMP-UserManual.pdf',
						), function($message) use ($user) {
							$message->to($user->email, $user->getFullName());
							$message->subject('Welcome to the Software Assurance Marketplace');
						});
					}
				}

				// Successful login - set user_uid session var
				User::setUserUidInSession($user->user_uid);

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

	/**
	 * This method is called when the user selects an external Identity
	 * Provider (IdP) from the Sign In popup box. The selected IdP will be in
	 * "?entityid=..." query parameter. (If this is missing, attemp to use
	 * a previously selected IdP.) The user's browser is then redirected
	 * to the IdP's authentication endpoint for logging in.
	 */
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

	/**
	 * This method is called by oauth2Link to do the actual work of linking
	 * an external Identity Provider (IdP) with an existing SWAMP user
	 * account.
	 */
	private function linkOAuth2Account($user) {

		// Attempt to load the oauth2 account the user is currently logged in as.
		//
		$token = $this->getAccessTokenFromSession();
		if (is_null($token)) {
			return response('Unauthorized Oauth2 access.', 401);
		}

		Log::info("Linked account begin.",
			array(
				'oauth2_access_time' => Session::get('oauth2_access_time'),
				'gmdateU' => gmdate('U'),
			)
		);

		// check oauth2 access token via oauth2
		//
		$idp = new IdentityProvider();
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

		// Successful login - set user_uid session var
		User::setUserUidInSession($user->user_uid);

		// Log the successful OAauth2 account linking event
		Log::info("Linked account success.",
			array(
				'linked_account_provider_code' => $idp->linked_provider,
				'user_external_id' => $linkedAccount->user_external_id,
			)
		);

		return response('User account linked!');
	}

	/**
	 * This method is called when the user selects that she wants to link
	 * an external Identity Provider (IdP) account with a SWAMP user account.
	 * The input username/password is verified, and then the external
	 * IdP is linked to that user account.
	 */
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
			if ($user->isAuthenticated($password)) {

				$userAccount = $this->getOrCreateUserAccount($user);

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
				Log::info("Login attempt failed during account linking.");
				return response('Incorrect username or password.', 401);
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
			'password_reset_uuid' => Guid::create(),
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
		return response("Due to a prolonged period of user account inactivity, we request that you select a new password for your account. SWAMP has sent a message containing a password reset link to your registered email address. Contact $contactEmail if you do not receive the message.", 409);
	}

	private function resetPassword($user) {

		// delete previous password resets belonging to this user
		//
		PasswordReset::where('user_uid', '=', $user->user_uid)->delete();

		// create new password reset
		//
		$passwordResetNonce = $nonce = Guid::create();
		$passwordReset = new PasswordReset(array(
			'password_reset_uuid' => Guid::create(),
			'password_reset_key' => Hash::make($passwordResetNonce),
			'user_uid' => $user->user_uid
		));
		$passwordReset->save();

		// Delete all app passwords for the user
		$app_password_con = new AppPasswordsController();
		$app_password_con->deleteByUser($user->user_uid);

		// send password reset email
		//
		$passwordReset->send($nonce);

		// return response message
		//
		$contactEmail = Config::get('mail.contact.address');
		return response("As part of our operations procedures, we request that you select a new password for your account. SWAMP has sent a message containing a password reset link to your registered email address. Contact $contactEmail if you do not receive the message.", 409);
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

	/**
	 * Using the session variable oauth2_access_token (which is the
	 * access token value), get a new OAuth2 AccessToken object which can
	 * be used with the OAuth2 library functions. If the new access
	 * token object could not be created properly, return null.
	 */
	private function getAccessTokenFromSession() {
		$token = null;

		if (Session::has('oauth2_access_token')) {
			$access_token = Session::get('oauth2_access_token');
			try {
				$token = new AccessToken(['access_token' => $access_token]);
			} catch (IdentityProviderException $e) {
			} catch (Exception $e) {
			} catch (Error $err) {
			}
		}

		return $token;
	}

	/**
	 * When LDAP is enabled, the corresponding UserAccount object
	 * may need to be created on-the-fly for when the user first
	 * logs in, via either username/password or OAuth2. This function
	 * first tries to get an existing UserAccount account for the
	 * passed-in $user, or creates one if needed when LDAP is
	 * configured read-only.
	 */
	private function getOrCreateUserAccount($user) {
		$userAccount = $user->getUserAccount();

		// When LDAP is enabled for users, the user may
		// authenticate with LDAP but not have a corresponding
		// UserAccount. So create one now. Since we trust the LDAP
		// email address, set email_verified_flag to '1'.
		if ((Config::get('ldap.enabled')) &&
			(count($userAccount) == 0)) {
				$userAccount = new UserAccount(array(
					'ldap_profile_update_date' => gmdate('Y-m-d H:i:s'),
					'user_uid' => $user->user_uid,
					'promo_code_id' => null,
					'enabled_flag' => 1,
					'admin_flag' => 0,
					'email_verified_flag' => Config::get('mail.enabled')? 1 : -1
				));
				$userAccount->save();
		}
		return $userAccount;
	}

}
