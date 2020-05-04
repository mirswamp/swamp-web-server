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
|        Copyright (C) 2012-2020 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\Users;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;
use App\Models\BaseModel;
use App\Models\Users\User;

class PasswordReset extends BaseModel
{
	/**
	 * The table associated with the model.
	 *
	 * @var string
	 */
	protected $table = 'password_reset';

	/**
	 * The primary key associated with the table.
	 *
	 * @var string
	 */
	public $primaryKey = 'password_reset_uuid';

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
		'password_reset_uuid',
		'password_reset_key',
		'user_uid'
	];

	/**
	 * The attributes that should be visible in serialization.
	 *
	 * @var array
	 */
	protected $visible = [
		'password_reset_uuid',
		'password_reset_key',
		'username'
	];

	/**
	 * The accessors to append to the model's array form.
	 *
	 * @var array
	 */
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
	
	public function send(string $passwordResetNonce) {

		// check to see that mail is enabled
		//
		if (!config('mail.enabled')) {
			return;
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
