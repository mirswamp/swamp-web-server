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
|        Copyright (C) 2012-2020 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\Users;

use Illuminate\Support\Facades\Mail;
use App\Models\TimeStamps\TimeStamped;

class UserAccount extends TimeStamped
{
	/**
	 * The table associated with the model.
	 *
	 * @var string
	 */
	protected $table = 'user_account';

	/**
	 * The primary key associated with the table.
	 *
	 * @var string
	 */
	protected $primaryKey = 'user_uid';

	/**
	 * Indicates if the IDs are auto-incrementing.
	 *
	 * @var bool
	 */
	public $incrementing = false;

	/**
	 * The "type" of the auto-incrementing ID.
	 *
	 * @var string
	 */
	protected $keyType = 'string';
	
	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array
	 */
	protected $fillable = [
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
	];

	/**
	 * The attributes that should be visible in serialization.
	 *
	 * @var array
	 */
	protected $visible = [
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
	];

	/**
	 * The attributes that should be cast to native types.
	 *
	 * @var array
	 */
	protected $casts = [
		'enabled_flag' => 'boolean',
		'admin_flag' => 'boolean',
		'email_verified_flag' => 'boolean',
		'forcepwreset_flag' => 'boolean',
		'hibernate_flag' => 'boolean',
		'ldap_profile_update_date' => 'datetime',
		'ultimate_login_date' => 'datetime',
		'penultimate_login_date' => 'datetime'
	];

	//
	// querying methods
	//

	public function isPasswordResetRequired(): bool {
		return $this->forcepwreset_flag;
	}

	public function isEnabled(): bool {
		return $this->enabled_flag;
	}

	public function isHibernating(): bool {
		return $this->hibernate_flag;
	}

	//
	// updating methods
	//

	public function updateDates() {
		$this->penultimate_login_date = $this->ultimate_login_date;
		$this->ultimate_login_date = gmdate('Y-m-d H:i:s');
		$this->save();
	}

	/**
	 * setting methods
	 * @param array $attributes Which attributes to be set
	 * @param User $user Which user to set attributes for
	 * @param bool $currentuser If true, then send the user-specific email.
	 *        If false, then send the more general "from admin" email.
	 *        Defaults to false.
	 */

	public function setAttributes(array $attributes, User $user, bool $currentuser = false) {

		// send email notification of changes in account status
		//
		if (config('mail.enabled')) {

			// check to see if enabled flag has changed
			//
			if (array_key_exists('enabled_flag', $attributes) && $attributes['enabled_flag'] != $this->enabled_flag) {

				// send account change notification email
				//
				if ($user && $user->email && filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
					switch ($attributes['enabled_flag']) {

						// notify user that account has been disabled
						//
						case 0:
							$emailtemplate = 'emails.user-account-disabled';
							$emailsubject = 'SWAMP User Account Disabled';
							if ($currentuser) {
								$emailtemplate = 'emails.user-account-deleted';
								$emailsubject = 'SWAMP User Account Deleted';
							}
							Mail::send($emailtemplate, [
								'user' => $user
							], function($message) use ($user, $emailsubject) {
								$message->to($user->email, $user->getFullName());
								$message->subject($emailsubject);
							});
							break;

						// notify user that account has been enabled
						//
						case 1:
							Mail::send('emails.user-account-enabled', [
								'user' => $user
							], function($message) use ($user) {
								$message->to($user->email, $user->getFullName());
								$message->subject('SWAMP User Account Enabled');
							});
							break;
					}
				}

			// check to see if email verified flag has changed
			// which indicates transition from pending to enabled
			//
			} else if (array_key_exists('email_verified_flag', $attributes) && $attributes['email_verified_flag'] != $this->email_verified_flag) {
				if ($this->email_verified_flag != 1) {

					// send welcome email
					//
					if ($user && $user->email && filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
						$user->welcome();
					}
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
