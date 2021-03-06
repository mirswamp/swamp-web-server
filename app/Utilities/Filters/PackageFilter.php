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
|        Copyright (C) 2012-2020 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Utilities\Filters;

use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Packages\Package;
use App\Models\Packages\PackageVersion;

class PackageFilter
{
	static function apply(Request $request, Builder $query, ?string $projectUuid) {

		// parse parameters
		//
		$packageName = $request->input('package_name', null);
		$packageUuid = $request->input('package_uuid', null);
		$packageVersion = $request->input('package_version', null);
		$packageVersionUuid = $request->input('package_version_uuid', null);

		// add package to query
		//
		if ($packageName) {
			$query = $query->where('package_name', '=', $packageName);
		}
		if ($packageUuid) {
			$query = $query->where('package_uuid', '=', $packageUuid);
		}

		// add package version to query
		//
		if ($packageVersion == 'latest') {
			$query = $query->whereNull('package_version_uuid');
		} else if ($packageVersion) {
			$query = $query->where('package_version_uuid', '=', $packageVersion);
		}
		if ($packageVersionUuid == 'latest') {
			$query = $query->whereNull('package_version_uuid');
		} else if ($packageVersionUuid) {
			$query = $query->where('package_version_uuid', '=', $packageVersionUuid);
		}

		return $query;
	}
}
