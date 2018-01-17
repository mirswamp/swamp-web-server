<?php
/******************************************************************************\
|                                                                              |
|                               PackageType.php                                |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a model of type (language, etc.) of package.             |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2018 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\Packages;

use App\Models\TimeStamps\TimeStamped;

class PackageType extends TimeStamped {

	// database attributes
	//
	protected $connection = 'package_store';
	protected $table = 'package_type';
	protected $primaryKey = 'package_type_id';

	// mass assignment policy
	//
	protected $fillable = [
		'name',
		'package_type_enabled',
		'platform_user_selectable',
		'default_platform_uuid',
		'default_platform_version_uuid'
	];

	// array / json conversion whitelist
	//
	protected $visible = [
		'package_type_id',
		'name',
		'package_type_enabled',
		'platform_user_selectable',
		'default_platform_uuid',
		'default_platform_version_uuid'
	];
}
