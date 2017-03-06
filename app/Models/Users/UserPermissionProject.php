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
|        Copyright (C) 2012-2017 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\Users;

use Illuminate\Support\Facades\Config;
use App\Models\TimeStamps\CreateStamped;
use App\Models\Users\User;

class UserPermissionProject extends CreateStamped {

	/**
	 * database attributes
	 */
	protected $table = 'user_permission_project';
	protected $primaryKey = 'user_permission_project_uid';
		
	/**
	 * mass assignment policy
	 */
	protected $fillable = array(
		'user_permission_project_uid', 
		'user_permission_uid',
		'project_uid'
	);

	/**
	 * array / json conversion whitelist
	 */
	protected $visible = array(
		'user_permission_project_uid', 
		'user_permission_uid',
		'project_uid'
	);
}
