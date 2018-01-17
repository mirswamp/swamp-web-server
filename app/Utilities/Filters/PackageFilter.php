<?php
/******************************************************************************\
|                                                                              |
|                              PackageFilter.php                               |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a utility for filtering packages.                        |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        Copyright (C) 2012-2016 SWAMP - Software Assurance Marketplace        |
|        Morgridge Institute for Research                                      |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2018 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Utilities\Filters;

use Illuminate\Support\Facades\Input;
use App\Models\Packages\Package;
use App\Models\Packages\PackageVersion;

class PackageFilter {
	static function apply($query, $projectUuid) {

		// check for package name
		//
		$packageName = Input::get('package_name');
		if ($packageName != '') {
			$query = $query->where('package_name', '=', $packageName);
		}

		// check for package uuid
		//
		$packageUuid = Input::get('package_uuid');
		if ($packageUuid != '') {
			$query = $query->where('package_uuid', '=', $packageUuid);
		}

		// check for package version
		//
		$packageVersion = Input::get('package_version');
		if ($packageVersion == 'latest') {
			$query = $query->whereNull('package_version_uuid');
		} else if ($packageVersion != '') {
			$query = $query->where('package_version_uuid', '=', $packageVersion);
		}

		// check for package version uuid
		//
		$packageVersionUuid = Input::get('package_version_uuid');
		if ($packageVersionUuid == 'latest') {
			$query = $query->whereNull('package_version_uuid');
		} else if ($packageVersionUuid != '') {
			$query = $query->where('package_version_uuid', '=', $packageVersionUuid);
		}

		return $query;
	}
}
