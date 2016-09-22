<?php
/******************************************************************************\
|                                                                              |
|                             UserProjectEvent.php                             |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a model of a user project event (a project event         |
|        that pertains to a user).                                             |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2016 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\Events;

use App\Models\Events\PersonalEvent;
use App\Models\Users\User;

class UserProjectEvent extends PersonalEvent {

	/**
	 * database attributes
	 */
	protected $table = 'user_project_events';

	/**
	 * mass assignment policy
	 */
	protected $fillable = array(
		'user_uid',
		'project_uid'
	);

	/**
	 * array / json conversion whitelist
	 */
	protected $visible = array(
		'project_uid',
		'user'
	);


	/**
	 * array / json appended model attributes
	 */
	protected $appends = array(
		'user'
	);

	/**
	 * accessor methods
	 */

	public function getUserAttribute() {
		$user = User::getIndex($this->user_uid);
		if ($user) {
			return array(
				'first_name' => $user->first_name,
				'last_name' => $user->last_name,
				'preferred_name' => $user->preferred_name,
				'email' => $user->email
			);
		}
	}
}
