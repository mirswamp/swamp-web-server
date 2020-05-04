<?php
/******************************************************************************\
|                                                                              |
|                              PersonalEvent.php                               |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a model of a personal (user) event.                      |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2020 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\Events;

use App\Models\Events\Event;
use App\Models\Users\User;

class PersonalEvent extends Event
{
	/**
	 * The table associated with the model.
	 *
	 * @var string
	 */
	protected $table = 'personal_events';

	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array
	 */
	protected $fillable = [
		'user_uid',
		'user',
	];

	/**
	 * The attributes that should be visible in serialization.
	 *
	 * @var array
	 */
	protected $visible = [
		'event_type', 
		'user',
		'event_date'
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
		$user = User::getIndex($this->user_uid);

		// return a subset of user fields
		//
		if ($user) {
			return [
				'first_name' => $user->first_name,
				'last_name' => $user->last_name,
				'email' => $user->email
			];
		}
	}
}
