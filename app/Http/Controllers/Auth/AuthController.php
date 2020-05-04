<?php
/******************************************************************************\
|                                                                              |
|                              AuthController.php                              |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a controller used for handling registration and          |
|        authentication with third party identity providers.                   |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|          Copyright (C) 2012 - 2020, Morgridge Institute for Research         |
\******************************************************************************/

namespace App\Http\Controllers\Auth;

use Socialite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redirect;
use App\Models\Users\User;
use App\Models\Users\UserAccount;
use App\Models\Users\LinkedAccount;
use App\Models\Users\LinkedAccountProvider;
use App\Models\Users\UserClassMembership;
use App\Http\Controllers\BaseController;
use App\Http\Controllers\Auth\AuthController;
use App\Utilities\Uuids\Guid;
use App\Utilities\Identity\IdentityProvider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessToken;
use ErrorException;

class AuthController extends BaseController
{
	/**
	 * Redirect the user to the provider's authentication page.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function registerWithProvider(Request $request, string $provider)
	{
		// parse parameters
		//
		$classCode = $request->input('class_code');

		// save action for callback
		//
		self::put('class_code', $classCode);

		// redirect to provider
		//
		return self::redirect($request, $provider, 'registering');
	}

	/**
	 * Redirect the user to the provider's authentication page.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function addLoginWithProvider(Request $request, string $provider)
	{
		// redirect to provider
		//
		return self::redirect($request, $provider, 'adding-provider');
	}

	/**
	 * Redirect the user to the provider's authentication page.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function loginWithProvider(Request $request, string $provider)
	{
		// redirect to provider
		//
		return self::redirect($request, $provider, 'authenticating');
	}

	/**
	 * Handle callbacks from the selected identity provider.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function handleProviderCallback(Request $request, string $provider)
	{
		// add provider if it doesn't already exist
		//
		if (!LinkedAccountProvider::where('linked_account_provider_code', '=', $provider)->exists()) {
			$linkedAccountProvider = new LinkedAccountProvider(array(
				'linked_account_provider_code' => strtolower($provider),
				'title' => ucfirst(strtolower($provider)),
				'description' => ucfirst(strtolower($provider)) . ' Provider',
				'enabled_flag' => 1,
				'create_date' => gmdate('Y-m-d H:i:s')
			));
			$linkedAccountProvider->save();
		}

		// check to see that provider is not disabled
		//
		$linkedAccountProvider = LinkedAccountProvider::find($provider);
		if ($linkedAccountProvider && !$linkedAccountProvider->enabled_flag) {
			return redirect(config('app.cors_url') . '#providers/' . $providerCode . '/register/error/provider-disabled');
		}

		// get user info from identity provider
		//
		// $info = Socialite::driver($provider)->user();
		$info = Socialite::driver($provider)->stateless()->user();

		// get action
		//
		$action = self::get('action');

		// perform callback action
		//
		switch ($action) {
			case 'registering':
				return $this->addNewUser($request, $info, $provider);

			case 'adding-provider':
				return $this->addProvider($request, $info, $provider);

			case 'authenticating':
				return $this->authenticateUser($request, $info, $provider);
		}
	}

	/**
	 * Obtain the user information from GitHub.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function handleOAuthCallback(Request $request) {

		// check for oauth2 state
		//
		if (!self::has('oauth2_state')) {
			return Redirect::to(config('app.cors_url') . '/#providers/cilogon/sign-in/error');
		}

		// save oauth2 access token
		//
		$code = $request->input('code', '');
		if (!$code) {
			return Redirect::to(config('app.cors_url') . '/#providers/cilogon/sign-in/error');
		}

		// check state of oauth server
		//
		$state = self::pull('oauth2_state');
		$receivedState = $request->input('state');
		if (0 != strcasecmp($state, $receivedState)) {
			return Redirect::to(config('app.cors_url') . '/#providers/cilogon/sign-in/error');
		}

		// get user info from identity provider
		//
		$oauth2User = self::getOAuth2User($code);
		$oauth2Data = $oauth2User->toArray();
		$info = self::oAuth2DataToInfo($oauth2Data);	

		// get provider from oauth2 user
		//
		$provider = $oauth2Data['idp'];

		// get action
		//
		$action = self::get('action');

		// perform callback action
		//
		switch ($action) {
			case 'registering':
				return $this->addNewUser($request, $info, 'cilogon', $provider);

			case 'adding-provider':
				return $this->addProvider($request, $info, 'cilogon', $provider);

			case 'authenticating':
				return $this->authenticateUser($request, $info, 'cilogon', $provider);
		}
	}

	//
	// static methods
	//

	/**
	 * Get an oauth2 user from an authorization code.
	 *
	 * @param Request $request
	 */
	static function getOAuth2User($code) {
		$idp = new IdentityProvider();

		// get token from authorization code
		//
		$token = $idp->provider->getAccessToken('authorization_code', [
			'code' => $code
		]);

		// save token
		//
		self::putAll([
			'oauth2_access_token' => $token->getToken(),
			'oauth2_access_time' => gmdate('U')
		]);

		return $idp->provider->getResourceOwner($token);
	}

	/**
	 * Get a Socialite compatible info block from an oauth2 user.
	 *
	 * @param Request $request
	 */
	static function oAuth2DataToInfo($data) {
		return (object) [
			'token' => null,
			'refreshToken' => null,
			'expiresIn' => null,
			'id' => $data['sub'],
			'nickname' => null,
			'name' => ucwords(strtolower($data['name'])),
			'email' => strtolower($data['email']),
			'avatar' => null,
			'user' => [
				'sub' => $data['sub'],
				'name' => ucwords(strtolower($data['name'])),
				'given_name' => ucwords(strtolower($data['given_name'])),
				'family_name' => ucwords(strtolower($data['family_name'])),
				'picture' => null,
				'email' => strtolower($data['email']),
				'email_verified' => 1,
				'locale' => 'en',
				'hd' => null,
				'id' => $data['idp_name'],
				'verified_email' => 1,
				'link' => null
			]
		];
	}

	/**
	 * Redirect to the specified identity provider.
	 *
	 * @return \Illuminate\Http\Response
	 */
	static function redirect(Request $request, string $provider, string $action) {

		// check that provider is supported
		//
		if (!in_array($provider, ['google', 'github', 'cilogon'])) {
			return redirect(config('app.cors_url') . '#providers/' . $provider . '/register/error/no-provider');
		}

		// parse optional parameters
		//
		if ($provider == 'cilogon') {
			$entityId = $request->input('entityid');
		}

		// check to see that provider is not disabled
		//
		$providerCode = $provider != 'cilogon'? $provider : $entityId;
		$linkedAccountProvider = LinkedAccountProvider::find($providerCode);
		if ($linkedAccountProvider && !$linkedAccountProvider->enabled_flag) {
			return redirect(config('app.cors_url') . '#providers/' . $providerCode . '/register/error/provider-disabled');
		}

		// save action for callback
		//
		self::put('action', $action);

		// redirect to provider
		//
		if ($provider != 'cilogon') {
			return Socialite::driver($provider)->redirect();
		} else {
			return self::redirectTo($entityId);
		}
	}

	/**
	 * Redirect to the specified identity provider.
	 *
	 * @return \Illuminate\Http\Response
	 */
	static function redirectTo($entityId) {

		// check identity provider
		//
		$idp = new IdentityProvider($entityId);
		if (is_null($idp->provider)) {
			return response('Invalid Identity Provider "' . $entityId . '".', 401);
		} else {
			$authUrl = $idp->provider->getAuthorizationUrl($idp->authzUrlOpts);
			self::put('oauth2_state', $idp->provider->getState());
			return Redirect::to($authUrl);
		}
	}

	/**
	 * Put the specified key value pair.
	 *
	 * @return void
	 */
	static function put(string $key, $value) {
		Cache::put($key, $value, 600);
	}

	/**
	 * Put multiple key value pairs.
	 *
	 * @return void
	 */
	static function putAll(array $items) {
		foreach ($items as $key => $value) {
			self::put($key, $value);
		}
	}	

	/**
	 * Check for the specified key value pair.
	 *
	 * @return void
	 */
	static function has(string $key) {
		return Cache::has($key);
	}

	/**
	 * Get the specified key value pair.
	 *
	 * @return void
	 */
	static function get(string $key) {
		return Cache::get($key);	
	}

	/**
	 * Pull the specified key value pair.
	 *
	 * @return void
	 */
	static function pull(string $key) {
		return Cache::pull($key);
	}

	//
	// private methods
	//

	/**
	 * Register a new user with the specified identity provider.
	 *
	 * @return \Illuminate\Http\Response
	 */
	private function addNewUser(Request $request, $info, string $provider, string $entityid = null) {

		// check if linked account already exists
		//
		$linkedAccount = LinkedAccount::where('user_external_id', '=', $info->id)->first();
		if ($linkedAccount) {
			return redirect(config('app.cors_url') . '#providers/' . $provider . '/register/error/account-exists' . ($entityid ? '?entityid=' . urlencode($entityid) : ''));
		}

		// check if user with this email already exists
		//
		if (User::getByEmail($info->email)) {
			return redirect(config('app.cors_url') . '#providers/' . $provider . '/register/error/email-exists' . ($entityid ? '?entityid=' . urlencode($entityid) : ''));
		}
		
		// find suggested username
		//
		if (array_key_exists('given_name', $info->user) && 
			array_key_exists('family_name', $info->user)) {
			$username = substr($info->user['given_name'], 0, 1) . $info->user['family_name'];
		} else if ($info->name) {
			$username = $info->name;
		} else if ($info->nickname) {
			$username = $info->nickname;
		} else {
			$username = 'anonymous';
		}

		// format username
		//
		$username = strtolower($username);
		$username = str_replace(' ', '', $username);

		// find unique username
		//
		$username = User::getUniqueUsername($username);
		if (!$username) {
			return redirect(config('app.client_url') . '#providers/' . $provider . '/register/error/general-error' . ($entityid ? '?entityid=' . urlencode($entityid) : ''));
		}

		// find first and last name
		//
		$firstName = array_key_exists('given_name', $info->user)? $info->user['given_name'] : null;
		$lastName = array_key_exists('family_name', $info->user)? $info->user['family_name'] : null;
		if (!$firstName && !$lastName && $info->name) {
			$parts = explode(' ', $info->name);
			$firstName = $parts[0];
			if (count($parts) > 1) {
				$lastName = $parts[1];
			}
		}

		// create new user record
		//
		$user = new User([
			'first_name' => ucwords(strtolower($firstName)),
			'last_name' => ucwords(strtolower($lastName)),
			'preferred_name' => ucwords(strtolower(ucfirst($info->nickname))),
			'username' => $username,
			'password' => Hash::make(uniqid()),
			'user_uid' => Guid::create(),
			'email' => $info->email
		]);
		$user->add($request);

		// mark account as having been verified
		//
		$userAccount = UserAccount::find($user->user_uid);
		$userAccount->email_verified_flag = true;
		$userAccount->save();

		// create new linked account record
		//
		$linkedAccount = new LinkedAccount([
			'user_uid' => $user->user_uid,
			'user_external_id' => $info->id,
			'linked_account_provider_code' => $entityid ? $entityid : $provider,
			'enabled_flag' => true
		]);
		$linkedAccount->save();

		// add user class membership
		//
		if (self::has('class_code')) {
			$classCode = self::pull('class_code');

			// create new class membership
			//
			$membership = new UserClassMembership([
				'class_user_uuid' => Guid::create(),
				'user_uid' => $user->user_uid,
				'class_code' => $classCode
			]);

			// save new class membership
			//
			$membership->save();
		}
			
		// set session info
		//
		session([
			'user_uid' => $user->user_uid,
			'timestamp' => time()
		]);

		// send welcome email
		//
		$user->welcome();

		// update login dates
		//
		$userAccount->updateDates();

		// redirect to home page
		//
		return redirect(config('app.cors_url'));
	}

	/**
	 * Add identity provider to an existing user.
	 *
	 * @return \Illuminate\Http\Response
	 */
	private function addProvider(Request $request, $info, string $provider, string $entityid = null) {

		// check if linked account already exists
		//
		$linkedAccount = LinkedAccount::where('user_external_id', '=', $info->id)->first();
		if ($linkedAccount) {
			return redirect(config('app.cors_url') . '#providers/' . $provider . '/sign-in/error/account-exists' . ($entityid ? '?entityid=' . urlencode($entityid) : ''));
		}

		// create new linked account record
		//
		$linkedAccount = new LinkedAccount([
			'user_uid' => session('user_uid'),
			'user_external_id' => $info->id,
			'linked_account_provider_code' => $entityid ? $entityid : $provider,
			'enabled_flag' => true
		]);
		$linkedAccount->save();

		// redirect to account page
		//
		return redirect(config('app.cors_url') . '#my-account');
	}

	/**
	 * Authentiate a new user with the specified identity provider.
	 *
	 * @return \Illuminate\Http\Response
	 */
	private function authenticateUser(Request $request, $info, string $provider, string $entityid = null) {

		// find user
		//
		$linkedAccount = LinkedAccount::where('user_external_id', '=', $info->id)->first();
		$user = $linkedAccount? User::getIndex($linkedAccount->user_uid) : null;
		if (!$user) {
			return redirect(config('app.cors_url') . '#providers/' . $provider . '/sign-in/error/no-account' . ($entityid ? '?entityid=' . urlencode($entityid) : ''));
		}

		// check if user has been enabled
		//
		if (!$user->isEnabled()) {
			return redirect(config('app.cors_url') . '#providers/' . $provider . '/sign-in/error/not-enabled' . ($entityid ? '?entityid=' . urlencode($entityid) : ''));
		}

		// set session info
		//
		session([
			'user_uid' => $user->user_uid,
			'timestamp' => time()
		]);

		// update login dates
		//
		$user->getUserAccount()->updateDates();

		// redirect to home page
		//
		return redirect(config('app.cors_url'));
	}
}