<?php
/******************************************************************************\
|                                                                              |
|                          UserPermissionPackage.php                           |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a model of a permission requested by a patciular         |
|        user and associated with a particular package.                        |
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

use App\Models\TimeStamps\CreateStamped;
use App\Models\Users\User;

class UserPermissionPackage extends CreateStamped
{
	// database attributes
	//
	protected $table = 'user_permission_package';
	protected $primaryKey = 'user_permission_package_uid';
	public $incrementing = false;
		
	// mass assignment policy
	//
	protected $fillable = [
		'user_permission_package_uid', 
		'user_permission_uid',
		'package_uuid'
	];

	// array / json conversion whitelist
	//
	protected $visible = [
		'user_permission_package_uid',
		'user_permission_uid',
		'package_uuid'
	];
}
