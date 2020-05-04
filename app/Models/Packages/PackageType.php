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
|        Copyright (C) 2012-2020 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\Packages;

use App\Models\TimeStamps\TimeStamped;

class PackageType extends TimeStamped
{
	/**
	 * The database connection to use.
	 *
	 * @var string
	 */
	protected $connection = 'package_store';

	/**
	 * The table associated with the model.
	 *
	 * @var string
	 */
	protected $table = 'package_type';

	/**
	 * The primary key associated with the table.
	 *
	 * @var string
	 */
	protected $primaryKey = 'package_type_id';

	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array
	 */
	protected $fillable = [
		'name',
		'package_type_enabled',
		'platform_user_selectable',
		'default_platform_uuid',
		'default_platform_version_uuid'
	];

	/**
	 * The attributes that should be visible in serialization.
	 *
	 * @var array
	 */
	protected $visible = [
		'package_type_id',
		'name',
		'package_type_enabled',
		'platform_user_selectable',
		'default_platform_uuid',
		'default_platform_version_uuid'
	];

	/**
	 * The attributes that should be cast to native types.
	 *
	 * @var array
	 */
	protected $casts = [
		'package_type_enabled' => 'boolean',
		'platform_user_selectable' => 'boolean'
	];
}
