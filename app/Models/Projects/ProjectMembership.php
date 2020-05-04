<?php
/******************************************************************************\
|                                                                              |
|                             ProjectMembership.php                            |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a model of a user's membership within a project.         |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2020 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\Projects;

use App\Models\TimeStamps\CreateStamped;
use App\Models\Users\User;

class ProjectMembership extends CreateStamped
{
	/**
	 * The table associated with the model.
	 *
	 * @var string
	 */
	protected $table = 'project_user';

	/**
	 * The primary key associated with the table.
	 *
	 * @var string
	 */
	protected $primaryKey = 'membership_uid';

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
		'membership_uid',
		'project_uid', 
		'user_uid',
		'admin_flag'
	];

	/**
	 * The attributes that should be visible in serialization.
	 *
	 * @var array
	 */
	protected $visible = [
		'membership_uid',
		'project_uid', 
		'user_uid',
		'user',
		'admin_flag'
	];

	/**
	 * The accessors to append to the model's array form.
	 *
	 * @var array
	 */
	protected $appends = [
		'user'
	];

	/**
	 * The attributes that should be cast to native types.
	 *
	 * @var array
	 */
	protected $casts = [
		'admin_flag' => 'boolean'
	];

	//
	// accessor methods
	//

	public function getUserAttribute() {
		return User::getIndex($this->user_uid);
	}

	//
	// querying methods
	//

	public function isActive() {
		return (!$this->delete_date);
	}

	//
	// methods
	//

	public static function deleteByUser(User $user) {

		// execute SQL query
		//
		self::where('user_uid', '=', $user->user_uid)->delete();
	}
}
