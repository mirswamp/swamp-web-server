<?php
/******************************************************************************\
|                                                                              |
|                              PlatformFilter.php                              |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a utility for filtering platforms.                       |
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
use App\Models\Platforms\Platform;
use App\Models\Platforms\PlatformVersion;

class PlatformFilter2
{
	static function apply($query) {

		// parse parameters
		//
		$platformName = Input::get('platform_name', null);
		$platformUuid = Input::get('platform_uuid', null);
		$platformVersion = Input::get('platform_version', null);
		$platformVersionUuid = Input::get('platform_version_uuid', null);

		// add platform to query
		//
		if ($platformName) {
			$query = $query->where('platform_name', '=', $platformName);
		}	
		if ($platformUuid) {
			$platformVersions = PlatformVersion::where('platform_uuid', '=', $platformUuid)->get();
			$query = $query->where(function($query) use ($platformVersions) {
				for ($i = 0; $i < sizeof($platformVersions); $i++) {
					if ($i == 0) {
						$query->where('platform_version_uuid', '=', $platformVersions[$i]->platform_version_uuid);
					} else {
						$query->orWhere('platform_version_uuid', '=', $platformVersions[$i]->platform_version_uuid);
					}
				}
			});
		}

		// add platform version to query
		//
		if ($platformVersion == 'latest') {
			$platform = Platform::where('platform_uuid', '=', $platformUuid)->first();
			if ($platform) {
				$latestVersion = $platform->getLatestVersion();
				$query = $query->where('platform_version_uuid', '=', $latestVersion->platform_version_uuid);
			}
		} else if ($platformVersion) {
			$query = $query->where('platform_version_uuid', '=', $platformVersion);
		}
		if ($platformVersionUuid == 'latest') {
			$platform = Platform::where('platform_uuid', '=', $platformUuid)->first();
			if ($platform) {
				$latestVersion = $platform->getLatestVersion();
				$query = $query->where('platform_version_uuid', '=', $latestVersion->platform_version_uuid);
			}
		} else if ($platformVersionUuid) {
			$query = $query->where('platform_version_uuid', '=', $platformVersionUuid);
		}

		return $query;
	}
}
