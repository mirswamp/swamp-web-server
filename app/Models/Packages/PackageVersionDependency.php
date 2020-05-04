<?php
/******************************************************************************\
|                                                                              |
|                         PackageVersionDependency.php                         |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a model of package version dependency.                   |
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

use App\Models\BaseModel;

class PackageVersionDependency extends BaseModel
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
	protected $table = 'package_version_dependency';

	/**
	 * The primary key associated with the table.
	 *
	 * @var string
	 */
	protected $primaryKey = 'package_version_dependency_id';

	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array
	 */
	protected $fillable = [
		'package_version_uuid',
		'platform_version_uuid',
		'dependency_list',

		// timestamp attributes
		//
		'update_date'
	];
	
	/**
	 * The attributes that should be visible in serialization.
	 *
	 * @var array
	 */
	protected $visible = [
		'package_version_dependency_id',
		'package_version_uuid',
		'platform_version_uuid',
		'dependency_list',

		// timestamp attributes
		//
		'create_date',
		'update_date'
	];

	/**
	 * The attributes that should be cast to native types.
	 *
	 * @var array
	 */
	protected $casts = [
		'create_date' => 'datetime',
		'update_date' => 'datetime'
	];
}
