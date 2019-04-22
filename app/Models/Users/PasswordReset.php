<?php
/******************************************************************************\
|                                                                              |
|                              PasswordReset.php                               |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a model of password reset request record.                |
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

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;
use App\Models\BaseModel;
use App\Models\Users\User;

class PasswordReset extends BaseModel
{
	// database attributes
	//
	protected $table = 'password_reset';
	public $primaryKey = 'password_reset_uuid';
	public $incrementing = false;

	// mass assignment policy
	//
	protected $fillable = [
		'password_reset_uuid',
		'password_reset_key',
		'user_uid'
	];

	// array / json conversion whitelist
	//
	protected $visible = [
		'password_reset_uuid',
		'password_reset_key',
		'username'
	];

	// array / json appended model attributes
	//
	protected $appends = [
		'username'
	];

	//
	// accessor methods
	//

	public function getUsernameAttribute() {
		$user = User::getIndex($this->user_uid);
		if ($user) {
			return $user->username;
		}
	}

	//
	// invitation sending / emailing method
	//
	
	public function send($passwordResetNonce) {

		// return an error if email has not been enabled
		//
		if (!config('mail.enabled')) {
			return response('Email has not been enabled.', 400); 
		}

		// send password reset notification email
		//
		$this->user = User::getIndex($this->user_uid);
		if ($this->user && $this->user->email && filter_var($this->user->email, FILTER_VALIDATE_EMAIL)) {
			Mail::send([
				'text' => 'emails.reset-password-plaintext'
			], [
				'user' => $this->user,
				'password_reset' => $this,
				'password_reset_url' => config('app.cors_url').'/#reset-password/'.$this->password_reset_uuid.'/'.$passwordResetNonce
			], function($message) {
			    $message->to($this->user->email, $this->user->getFullName());
			    $message->subject('SWAMP Password Reset');
			});
		}
	}
}
