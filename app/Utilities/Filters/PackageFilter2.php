<?php
/******************************************************************************\
|                                                                              |
|                              PackageFilter2.php                              |
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
|        Copyright (C) 2012-2019 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/
namespace App\Utilities\Filters;

use Illuminate\Support\Facades\Input;
use App\Models\Packages\Package;
use App\Models\Packages\PackageVersion;

class PackageFilter2
{
	static function apply($query, $projectUuid) {

		// parse parameters
		//
		$packageName = Input::get('package_name', null);
		$packageUuid = Input::get('package_uuid', null);
		$packageVersion = Input::get('package_version', null);
		$packageVersionUuid = Input::get('package_version_uuid', null);

		// add package to query
		//
		if ($packageName) {
			$query = $query->where('package_name', '=', $packageName);
		}
		if ($packageUuid) {
			$packageVersions = PackageVersion::where('package_uuid', '=', $packageUuid)->get();
			$query = $query->where(function($query) use($packageVersions) {
				for ($i = 0; $i < sizeof($packageVersions); $i++) {
					if ($i == 0) {
						$query->where('package_version_uuid', '=', $packageVersions[$i]->package_version_uuid);
					} else {
						$query->orWhere('package_version_uuid', '=', $packageVersions[$i]->package_version_uuid);
					}
				}
			});
		}

		// add package version to query
		//
		if ($packageVersion == 'latest') {
			$package = Package::where('package_uuid', '=', $packageUuid)->first();
			if ($package) {
				$latestVersion = $package->getLatestVersion($projectUuid);
				if ($latestVersion) {
					$query = $query->where('package_version_uuid', '=', $latestVersion->package_version_uuid);
				}
			}
		} else if ($packageVersion) {
			$query = $query->where('package_version_uuid', '=', $packageVersion);
		}	
		if ($packageVersionUuid == 'latest') {
			$package = Package::where('package_uuid', '=', $packageUuid)->first();
			if ($package) {
				$latestVersion = $package->getLatestVersion($projectUuid);
				if ($latestVersion) {
					$query = $query->where('package_version_uuid', '=', $latestVersion->package_version_uuid);
				}
			}
		} else if ($packageVersionUuid) {
			$query = $query->where('package_version_uuid', '=', $packageVersionUuid);
		}

		return $query;
	}
}
