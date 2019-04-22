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
|        Copyright (C) 2012-2019 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\Users;

use Illuminate\Support\Facades\Mail;
use App\Models\TimeStamps\CreateStamped;
use App\Models\Users\User;

class EmailVerification extends CreateStamped
{
	// database attributes
	//
	protected $table = 'email_verification';
	protected $primaryKey = 'email_verification_id';

	// mass assignment policy
	//
	protected $fillable = [
		'user_uid', 
		'verification_key', 
		'email',

		// timestamp attributes
		//
		'verify_date'
	];

	// array / json conversion whitelist
	//
	protected $visible = [

		// appended fields
		//
		'user',

		// timestamp attributes
		//
		'verify_date'
	];

	// array / json appended model attributes
	//
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

	public function send($verifyRoute, $changed = false) {

		// return an error if email has not been enabled
		//
		if (!config('mail.enabled')) {
			return response('Email has not been enabled.', 400); 
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
