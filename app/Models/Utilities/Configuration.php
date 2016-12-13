<?php
/******************************************************************************\
|                                                                              |
|                               Configuration.php                              |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a model of the server configuration information          |
|        that is communicated to the client.                                   |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2016 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\Utilities;

use App\Models\BaseModel;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Request;

class Configuration extends BaseModel {

	/**
	 * array / json appended model attributes
	 */
	protected $appends = array(
		'email_enabled',
		'use_promo_code',
		'linked_accounts_enabled',
		'github_authentication_enabled',
		'google_authentication_enabled',
		'ci_logon_authentication_enabled',
		'api_explorer_enabled',
		'client_ip'
	);

	/**
	 * accessor methods
	 */

	public function getEmailEnabledAttribute() {
		return Config::get('mail.enabled');
	}

	public function getUsePromoCodeAttribute() {
		return Config::get('app.use_promo_code');
	}

	public function getLinkedAccountsEnabledAttribute() {
		return Config::get('app.github_authentication_enabled') ||
			Config::get('app.google_authentication_enabled') || 
			Config::get('app.ci_logon_authentication_enabled');
	}

	public function getGitHubAuthenticationEnabledAttribute() {
		return Config::get('app.github_authentication_enabled');
	}

	public function getGoogleAuthenticationEnabledAttribute() {
		return Config::get('app.google_authentication_enabled');
	}

	public function getCILogonAuthenticationEnabledAttribute() {
		return Config::get('app.ci_logon_authentication_enabled');
	}

	public function getApiExplorerEnabledAttribute() {
		return Config::get('app.api_explorer_enabled');
	}

	public function getClientIpAttribute() {
		return Request::ip();
	}
}
