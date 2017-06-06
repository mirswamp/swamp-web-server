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
|        Copyright (C) 2012-2017 Software Assurance Marketplace (SWAMP)        |
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
		'client_ip',
		'ldap_readonly',
		'app_password_max'
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

	/**
	 * Returns true if LDAP is enabled and set to read-only.
	 */
	public function getLdapReadOnlyAttribute() {
		$ldapEnabled = Config::get('ldap.enabled');
		$ldapConnectionConfig = Config::get('ldap.connection');
		$ldapReadOnly = $ldapConnectionConfig['read_only'];
		return ($ldapEnabled && $ldapReadOnly);
	}

	/**
	 * Returns the maximum number of app passwords allowed as an int.
	 * 0 means app passwords are disabled. Hardcoded max of 100 per user.
	 */
	public function getAppPasswordMaxAttribute() {
		$app_password_max = Config::get('app.app_password_max');
		if ($app_password_max < 0) {
			$app_password_max = 0; // 0 (or less) means app passwords disabled
		}
		if ($app_password_max > 100) {
			$app_password_max = 100; // Global maximum of 100 app passwords per user
		}
		return intval($app_password_max);
	}

}
