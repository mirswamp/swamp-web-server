<?php
/******************************************************************************\
|                                                                              |
|                               UserAccount.php                                |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a model of user's account information.                   |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2016 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\Users;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use App\Models\TimeStamps\UserStamped;

class UserAccount extends UserStamped {

	/**
	 * database attributes
	 */
	protected $table = 'user_account';
	protected $primaryKey = 'user_uid';

	/**
	 * mass assignment policy
	 */
	protected $fillable = array(
		'user_uid',
		'enabled_flag',
		'admin_flag',
		'email_verified_flag', 
		'forcepwreset_flag',
		'hibernate_flag',
		'user_type',
		'ldap_profile_update_date', 
		'ultimate_login_date', 
		'penultimate_login_date',
		'promo_code_id'
	);

	/**
	 * array / json conversion whitelist
	 */
	protected $visible = array(
		'user_uid',
		'enabled_flag',
		'admin_flag',
		'email_verified_flag', 
		'forcepwreset_flag',
		'hibernate_flag',
		'user_type',
		'ldap_profile_update_date', 
		'ultimate_login_date', 
		'penultimate_login_date',
		'promo_code_id'
	);

	/**
	 * querying methods
	 */

	public function isPasswordResetRequired() {
		return $this->forcepwreset_flag;
	}

	public function isHibernating() {
		return $this->hibernate_flag;
	}

	/**
	 * setting methods
	 */

	public function setAttributes($attributes, $user) {

		// send email notification of changes in account status
		//
		if (Config::get('mail.enabled')) {

			// check to see if enabled flag has changed
			//
			if (array_key_exists('enabled_flag', $attributes) && $attributes['enabled_flag'] != $this->enabled_flag) {

				// send account change notification email
				//
				switch ($attributes['enabled_flag']) {

					// notify user that account has been disabled
					//
					case 0:
						Mail::send('emails.user-account-disabled', array( 
							'user' => $user
						), function($message) use ($user) {
							$message->to($user->email, $user->getFullName());
							$message->subject('SWAMP User Account Disabled');
						});
						break;

					// notify user that account has been enabled
					//
					case 1:
						Mail::send('emails.user-account-enabled', array( 
							'user' => $user
						), function($message) use ($user) {
							$message->to($user->email, $user->getFullName());
							$message->subject('SWAMP User Account Enabled');
						});
						break;
				}

			// check to see if email verified flag has changed
			// which indicates transition from pending to enabled
			//
			} else if (array_key_exists('email_verified_flag', $attributes) && $attributes['email_verified_flag'] != $this->email_verified_flag) {

				// send welcome email
				//
				if ($this->email_verified_flag != 1) {


					Mail::send('emails.welcome', array(
						'user'		=> $user,
						'logo'		=> Config::get('app.cors_url') . '/images/logos/swamp-logo-small.png',
						'manual'	=> Config::get('app.cors_url') . 'https://continuousassurance.org/swamp/SWAMP-User-Manual.pdf',
					), function($message) use ($user) {
						$message->to($user->email, $user->getFullName());
						$message->subject('Welcome to the Software Assurance Marketplace');
					});
				}
			}
		}

		// set attributes
		//
		$this->ldap_profile_update_date = gmdate('Y-m-d H:i:s');
		if (array_key_exists('user_type', $attributes)) {
			$this->user_type = $attributes['user_type'];
		}
		if (array_key_exists('admin_flag', $attributes)) {
			$this->admin_flag = $attributes['admin_flag'] ? 1 : 0;
		}
		if (array_key_exists('enabled_flag', $attributes)) {
			$this->enabled_flag = $attributes['enabled_flag'] ? 1 : 0;
		}
		if (array_key_exists('email_verified_flag', $attributes)) {
			$this->email_verified_flag = $attributes['email_verified_flag'] ? 1 : 0;
		}
		if (array_key_exists('forcepwreset_flag', $attributes)) {
			$this->forcepwreset_flag = $attributes['forcepwreset_flag'] ? 1 : 0;
		}
		if (array_key_exists('hibernate_flag', $attributes)) {
			$this->hibernate_flag = $attributes['hibernate_flag'] ? 1 : 0;
		}

		// save model
		//
		$this->save();
	 }	
}