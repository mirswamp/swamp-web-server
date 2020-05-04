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
|        Copyright (C) 2012-2020 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\Events;

use App\Models\Events\PersonalEvent;
use App\Models\Users\User;
use App\Models\Projects\Project;

class UserProjectEvent extends PersonalEvent
{
	/**
	 * The table associated with the model.
	 *
	 * @var string
	 */
	protected $table = 'user_project_events';

	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array
	 */
	protected $fillable = [
		'user_uid',
		'project_uid'
	];

	/**
	 * The attributes that should be visible in serialization.
	 *
	 * @var array
	 */
	protected $visible = [
		'user',
		'project',
		'event_date'
	];

	/**
	 * The accessors to append to the model's array form.
	 *
	 * @var array
	 */
	protected $appends = [
		'user',
		'project'
	];

	//
	// accessor methods
	//

	public function getUserAttribute() {
		$user = User::getIndex($this->user_uid);
		if ($user) {
			return [
				'first_name' => $user->first_name,
				'last_name' => $user->last_name,
				'preferred_name' => $user->preferred_name,
				'email' => $user->email
			];
		}
	}

	public function getProjectAttribute() {
		return Project::where('project_uid', '=', $this->project_uid)->first();
	}
}
