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

class Configuration extends BaseModel {

	/**
	 * array / json appended model attributes
	 */
	protected $appends = array(
		'email_enabled',
		'use_promo_code',
		'federated_authentication_enabled',
		'github_authentication_enabled',
		'google_authentication_enabled',
		'api_explorer_enabled'
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

	public function getFederatedAuthenticationEnabledAttribute() {
		return Config::get('app.federated_authentication_enabled');
	}

	public function getGithubAuthenticationEnabledAttribute() {
		return Config::get('app.github_authentication_enabled');
	}

	public function getGoogleAuthenticationEnabledAttribute() {
		return Config::get('app.google_authentication_enabled');
	}

	public function getApiExplorerEnabledAttribute() {
		return Config::get('app.api_explorer_enabled');
	}
}
