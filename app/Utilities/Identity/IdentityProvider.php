<?php
/******************************************************************************\
|                                                                              |
|                             IdentityProvider.php                             |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a utility for creating and managing federated            |
|        authentication identity providers.                                    |
|                                                                              |
|        Author(s): Terry Fleury                                               |
|                                                                              |
|        Copyright (C) 2012-2016 SWAMP - Software Assurance Marketplace        |
|        Morgridge Institute for Research                                      |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2019 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Utilities\Identity;

use Illuminate\Support\Facades\Session;
use League\OAuth2\Client\Provider\Github;
use League\OAuth2\Client\Provider\Google;
use CILogon\OAuth2\Client\Provider\CILogon;
use App\Http\Controllers\Users\SessionController;
use App\Http\Controllers\Utilities\IdentitiesController;
use App\Models\Users\LinkedAccountProvider;

class IdentityProvider
{
	public $provider = null; 		// Member variable for OAuth 2.0 PHP provider object
	public $authzUrlOpts = []; 		// Params for getAuthorizationUrl()
	public $linked_provider = '';   // github, google, or univ. entityId

	/** 
	 * Class constructor. Initialize the class variables using the passed-in
	 * Identity Provider ($idp).
	 * 
	 * @param $idp The Identity Provider to use for OAuth 2 connection. If
	 *             empty, then check the session 'oauth2_idp' variable and
	 *             initialize the $provider with that.
	 *
	 * Side-effects: Sets the class variables 'provider' (the OAuth2 Client
	 *               library provider object)'authzUrlOpts' (for use with 
	 *               getAuthorizationUrl()), 'linked_provider' (which is one
	 *               'github', 'google', or the university's entityId).
	 */
	public function __construct($idp='') {

		$selectedidp = $this->getSelectedIdP($idp);

		// use the $selectedidp to initialize the class $provider.
		//
		if (strlen($selectedidp) > 0) {
			$client_id = '';
			$client_secret = '';
			$classname = '';

			// set the client id and secret for the $selectedidp
			//
			if ($selectedidp == 'GitHub') {
				$client_id     = config('oauth2.github_client_id');
				$client_secret = config('oauth2.github_client_secret');
				$classname     = 'League\OAuth2\Client\Provider\Github';
				$this->authzUrlOpts = [ 'scope' => ['user:email'] ];
				$this->linked_provider = 'github';
			} elseif ($selectedidp == 'Google') {
				$client_id     = config('oauth2.google_client_id');
				$client_secret = config('oauth2.google_client_secret');
				$classname     = 'League\OAuth2\Client\Provider\Google';
				$this->authzUrlOpts = [ 'scope' => ['openid','email','profile'] ];
				$this->linked_provider = 'google';
			} else { 

				// the rest of the IdPs are CILogon
				//
				$client_id     = config('oauth2.cilogon_client_id');
				$client_secret = config('oauth2.cilogon_client_secret');
				$classname = 'CILogon\OAuth2\Client\Provider\CILogon';
				$this->authzUrlOpts = [
					'scope' => ['openid','email','profile','org.cilogon.userinfo'],
					'selected_idp' => $selectedidp,
					'skin' => config('oauth2.cilogon_skin')
				];
				$this->linked_provider = $selectedidp;
			}

			if ((strlen($client_id) > 0) && (strlen($client_secret) > 0)) {
				$this->provider = new $classname([
					'clientId'     => $client_id,
					'clientSecret' => $client_secret,
					'redirectUri'  => config('app.url') . '/oauth2',
				]);
			}
		}
	}

	/**
	 * Return the selected IdP, which is either the passed-in 
	 * idp verified against the list of available IdPs, or a
	 * an idp previously saved to the session.
	 *
	 * @param $idp The Identity Provider to use for OAuth2 connection. If
	 *             empty, then check the session 'oauth2_idp' variable.
	 *
	 * @return The selected and verified Identity Provider to use for
	 *         the OAuth2 connection.
	 */
	public function getSelectedIdP($idp='') {

		$selectedidp = '';

		if (strlen($idp) > 0) {

			// if $idp was passed in, verify that the idp is avaiable in 
			// the list of configured IdPs. If so, save the idp to the session
			// variable 'oauth2_idp' for later use when $idp is not passed in.
			//
			$idpjson = IdentitiesController::getProvidersList();
			$idparray = json_decode($idpjson,true);
			foreach ($idparray as $arr) {
				if ($arr['entityid'] == $idp) {
					$selectedidp = $arr['entityid'];
					SessionController::put('oauth2_idp', $selectedidp);
					$this->addLinkedAccountProvider($selectedidp,$idp);
					break;
				}
			}
		} else {

			// if the $idp was not passed in, check the session variable
			// 'oauth2_idp' for a previously set (and verified) idp.
			//
			if (SessionController::has('oauth2_idp')) {
				$selectedidp = SessionController::get('oauth2_idp');
			}
		}

		return $selectedidp;
	}

	/**
	 * Check if the $entityid is currently in the linked_account_provider
	 * table. If not, create a new entry using the $entityid and the full name
	 * of the $idp. Set the 'description' field to be 
	 * "(GitHub|Google|CILogon) Provider" for simplicity.
	 *
	 * @param $entityid The entityid of the selected Identiity Provider, e.g.,
	 *        'GitHub', 'Google', or 'urn:mace:incommon:uiuc.edu' (for UIUC).
	 * @param $idp The pretty-print name of the Identity Provider, e.g.,
	 *        'GitHub', 'Google', or 'Univesity of Illinois at Urbana-Champaign'.
	 */
	protected function addLinkedAccountProvider($entityid,$idp) {

		// see if the entityid is already a LinkedAccountProvider
		// If not, then add a new entry.
		//
		if (!LinkedAccountProvider::where('linked_account_provider_code','=',$entityid)->first()) {
			$lap = new LinkedAccountProvider(array(
				'linked_account_provider_code' => strtolower($entityid),
				'title' => $idp,
				'description' => (($entityid == 'GitHub') || ($entityid == 'Google') ?
					$entityid . ' Provider' : 'CILogon Provider'),
				'enabled_flag' => 1,
				'create_date' => gmdate('Y-m-d H:i:s')
			));
			$lap->save();
		}

	}
}
