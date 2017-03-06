<?php
/******************************************************************************\
|                                                                              |
|                              PackagePlatform.php                             |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a model of platform that a package supports.             |
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
use App\Models\Packages\Package;
use App\Models\Platforms\Platform;
use App\Models\Platforms\PlatformVersion;

class PackagePlatform extends BaseModel {

	/**
	 * database attributes
	 */
	protected $connection = 'package_store';
	protected $table = 'package_platform';
	public $primaryKey = 'package_platform_id';

	/**
	 * array / json appended model attributes
	 */
	protected $appends = array(
		'package',
		'platform',
		'platform_version'
	);

	/**
	 * accessor methods
	 */

	public function getPackageAttribute() {
		if ($this->package_uuid) {
			return Package::where('package_uuid', '=', $this->package_uuid)->first();
		}
	}

	public function getPlatformAttribute() {
		if ($this->platform_uuid) {
			return Platform::where('platform_uuid', '=', $this->platform_uuid)->first();
		} else if ($this->platform_version_uuid) {
			return Platform::where('platform_uuid', '=', $this->platform_version->platform_uuid)->first();
		}
	}

	public function getPlatformVersionAttribute() {
		if ($this->platform_version_uuid) {
			return PlatformVersion::where('platform_version_uuid', '=', $this->platform_version_uuid)->first();
		}
	}
}
