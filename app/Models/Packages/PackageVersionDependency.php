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
|        Copyright (C) 2012-2017 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\Packages;

use App\Models\BaseModel;

class PackageVersionDependency extends BaseModel {

	/**
	 * database attributes
	 */
	protected $connection = 'package_store';
	protected $table = 'package_version_dependency';
	protected $primaryKey = 'package_version_dependency_id';

	/**
	 * mass assignment policy
	 */
	protected $fillable = array(
		'package_version_uuid',
		'platform_version_uuid',
		'dependency_list',
		'update_date'
	);
	
	protected $visible = array(
		'package_version_dependency_id',
		'package_version_uuid',
		'platform_version_uuid',
		'dependency_list',
		'create_date',
		'update_date'
	);

}
