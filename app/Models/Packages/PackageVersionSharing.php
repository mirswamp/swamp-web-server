<?php
/******************************************************************************\
|                                                                              |
|                              PackageSharing.php                              |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a model of the sharing of a package version.             |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2016 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\Packages;

use App\Models\BaseModel;
use App\Models\Projects\Project;
use App\Models\Packages\PackageVersion;

class PackageVersionSharing extends BaseModel {

	/**
	 * database attributes
	 */
	protected $connection = 'package_store';
	protected $table = 'package_version_sharing';
	protected $primaryKey = 'package_version_sharing_id';

	/**
	 * mass assignment policy
	 */
	protected $fillable = array(
		'package_version_uuid',
		'project_uuid'
	);

	/**
	 * array / json conversion whitelist
	 */
	protected $visible = array(
		'package_version_uuid',
		'project_uuid'
	);

	/**
	 * querying methods
	 */

	public function packageVersion() {
		return PackageVersion::where('package_version_uuid', '=', $this->package_version_uuid)->first();
	}

	public function project() {
		return Project::where('project_uuid', '=', $this->project_uuid)->first();
	}
}
