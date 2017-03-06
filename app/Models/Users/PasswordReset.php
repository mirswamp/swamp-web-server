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
|        Copyright (C) 2012-2017 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\Users;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use App\Models\BaseModel;
use App\Models\Users\User;

class PasswordReset extends BaseModel {

	/**
	 * database attributes
	 */
	protected $table = 'password_reset';
	protected $primaryKey = 'password_reset_id';

	/**
	 * mass assignment policy
	 */
	protected $fillable = array(
		'password_reset_key',
		'user_uid'
	);

	/**
	 * constructor
	 */
	public function __construct(array $attributes = array()) {

		// call superclass constructor
		//
		BaseModel::__construct($attributes);
	}

	/**
	 * invitation sending / emailing method
	 */
	public function send($passwordResetNonce) {

		// return an error if email has not been enabled
		//
		if (!Config::get('mail.enabled')) {
			return response('Email has not been enabled.', 400); 
		}

		// send password reset notification email
		//
		$this->user = User::getIndex($this->user_uid);
		$data = array(
			'user' => $this->user,
			'password_reset' => $this,
			'password_reset_url' => Config::get('app.cors_url').'/#reset-password/'.$passwordResetNonce.'/'.$this->password_reset_id
		);
		Mail::send(array('text' => 'emails.reset-password-plaintext'), $data, function($message) {
		    $message->to($this->user->email, $this->user->getFullName());
		    $message->subject('SWAMP Password Reset');
		});
	}
}
