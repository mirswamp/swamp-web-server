<?php
/******************************************************************************\
|                                                                              |
|                            EmailVerification.php                             |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a model of an email verification record.                 |
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
use App\Models\TimeStamps\CreateStamped;
use App\Models\Users\User;

class EmailVerification extends CreateStamped
{
	/**
	 * The table associated with the model.
	 *
	 * @var string
	 */
	protected $table = 'email_verification';

	/**
	 * The primary key associated with the table.
	 *
	 * @var string
	 */
	protected $primaryKey = 'email_verification_id';

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
		'verification_key', 
		'email',

		// timestamp attributes
		//
		'verify_date'
	];

	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array
	 */
	protected $visible = [
		'user',

		// timestamp attributes
		//
		'verify_date'
	];

	/**
	 * The accessors to append to the model's array form.
	 *
	 * @var array
	 */
	protected $appends = [
		'user'
	];

	//
	// accessor methods
	//

	public function getUserAttribute() {
		return User::getIndex($this->user_uid)->toArray();
	}

	//
	// querying methods
	//

	public function isVerified() {
		return ($this->verify_date != null);
	}

	//
	// invitation sending / emailing method
	//

	public function send(string $verifyRoute, bool $changed = false) {

		// check to see that mail is enabled
		//
		if (!config('mail.enabled')) {
			return;
		}

		// send email verification email
		//
		if ($this->email && filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
			$template = $changed ? 'emails.email-verification' : 'emails.user-verification';
			$this->subject  = $changed ? 'SWAMP Email Verification'  : 'SWAMP User Verification';
			$this->recipient = User::getIndex($this->user_uid);
			Mail::send($template, [
				'user' => User::getIndex($this->user_uid),
				'verification_key' => $this->verification_key,
				'verify_url' => config('app.cors_url').'/'.$verifyRoute
			], function($message) {
			    $message->to($this->email, $this->recipient->getFullName());
			    $message->subject($this->subject);
			});
		}
	}
}
