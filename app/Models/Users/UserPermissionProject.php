<?php
/******************************************************************************\
|                                                                              |
|                          UserPermissionProject.php                           |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a model of a permission requested by a patciular         |
|        user and associated with a particular project.                        |
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

use App\Models\TimeStamps\CreateStamped;
use App\Models\Users\User;

class UserPermissionProject extends CreateStamped
{
	/**
	 * The table associated with the model.
	 *
	 * @var string
	 */
	protected $table = 'user_permission_project';

	/**
	 * The primary key associated with the table.
	 *
	 * @var string
	 */
	protected $primaryKey = 'user_permission_project_uid';

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
		'user_permission_project_uid', 
		'user_permission_uid',
		'project_uid'
	];

	/**
	 * The attributes that should be visible in serialization.
	 *
	 * @var array
	 */
	protected $visible = [
		'user_permission_project_uid', 
		'user_permission_uid',
		'project_uid'
	];
}
