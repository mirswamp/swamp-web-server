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
|        Copyright (C) 2012-2019 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\Utilities;

use App\Models\BaseModel;
use App\Models\Users\UserClass;
use Illuminate\Support\Facades\Request;

class Configuration extends BaseModel
{
	// array / json appended model attributes
	//
	protected $appends = [
		'email_enabled',
		'use_promo_code',
		'classes_enabled',
		'sign_up_enabled',
		'linked_accounts_enabled',
		'github_authentication_enabled',
		'google_authentication_enabled',
		'ci_logon_authentication_enabled',
		'stats_enabled',
		'contact_form_enabled',
		'client_ip',
		'ldap_readonly',
		'app_password_max',
		'max_upload_size',
		'notification'
	];

	//
	// accessor methods
	//

	public function getEmailEnabledAttribute() {
		return config('mail.enabled');
	}

	public function getUsePromoCodeAttribute() {
		return config('app.use_promo_code');
	}

	public function getClassesEnabledAttribute() {
		return UserClass::exists();
	}

	public function getSignUpEnabledAttribute() {
		return config('app.sign_up_enabled');
	}

	public function getLinkedAccountsEnabledAttribute() {
		return config('app.github_authentication_enabled') ||
			config('app.google_authentication_enabled') || 
			config('app.ci_logon_authentication_enabled');
	}

	public function getGitHubAuthenticationEnabledAttribute() {
		return config('app.github_authentication_enabled');
	}

	public function getGoogleAuthenticationEnabledAttribute() {
		return config('app.google_authentication_enabled');
	}

	public function getCILogonAuthenticationEnabledAttribute() {
		return config('app.ci_logon_authentication_enabled');
	}

	public function getStatsEnabledAttribute() {
		return config('app.stats_enabled');
	}

	public function getContactFormEnabledAttribute() {
		return config('app.contact_form_enabled');
	}

	public function getClientIpAttribute() {
		return Request::ip();
	}

	public function getLdapReadOnlyAttribute() {
		$ldapEnabled = config('ldap.enabled');
		$ldapConnectionConfig = config('ldap.connection');
		$ldapReadOnly = $ldapConnectionConfig['read_only'];
		return ($ldapEnabled && $ldapReadOnly);
	}

	// Returns the maximum number of app passwords allowed as an int.
	// 0 means app passwords are disabled. Hardcoded max of 100 per user.
	//
	public function getAppPasswordMaxAttribute() {
		$app_password_max = config('app.app_password_max');
		if ($app_password_max < 0) {
			$app_password_max = 0; 		// 0 (or less) means app passwords disabled
		}
		if ($app_password_max > 100) {
			$app_password_max = 100; 	// Global maximum of 100 app passwords per user
		}
		return intval($app_password_max);
	}

	public function getMaxUploadSizeAttribute() {
		//return ini_get('upload_max_filesize');
		return ini_get('post_max_size');
	}

	public function getNotificationAttribute() {
		return null;
	}
}