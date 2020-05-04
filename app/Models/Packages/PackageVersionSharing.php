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
|        Copyright (C) 2012-2020 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\Packages;

use App\Models\BaseModel;
use App\Models\Projects\Project;
use App\Models\Packages\PackageVersion;

class PackageVersionSharing extends BaseModel
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
	protected $table = 'package_version_sharing';

	/**
	 * The primary key associated with the table.
	 *
	 * @var string
	 */
	protected $primaryKey = 'package_version_sharing_id';

	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array
	 */
	protected $fillable = [
		'package_version_uuid',
		'project_uuid'
	];

	/**
	 * The attributes that should be visible in serialization.
	 *
	 * @var array
	 */
	protected $visible = [
		'package_version_uuid',
		'project_uuid'
	];

	//
	// querying methods
	//

	public function packageVersion() {
		return PackageVersion::where('package_version_uuid', '=', $this->package_version_uuid)->first();
	}

	public function project() {
		return Project::where('project_uuid', '=', $this->project_uuid)->first();
	}
}
