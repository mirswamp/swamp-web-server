<?php
/******************************************************************************\
|                                                                              |
|                        PlatformVersionsController.php                        |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a controller for platform versions.                      |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2020 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Http\Controllers\Platforms;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use App\Utilities\Uuids\Guid;
use App\Models\Platforms\PlatformVersion;
use App\Http\Controllers\BaseController;

class PlatformVersionsController extends BaseController
{
	// create
	//
	public function postCreate(Request $request): PlatformVersion {

		// parse parameters
		//
		$platformUuid = $request->input('platform_uuid');
		$versionString = $request->input('version_string');
		$releaseDate = $request->input('release_date');
		$retireDate = $request->input('retire_date');

		// create new platform version
		//
		$platformVersion = new PlatformVersion([
			'platform_version_uuid' => Guid::create(),
			'platform_uuid' => $platformUuid,
			'version_string' => $versionString,
			'release_date' => $releaseDate,
			'retire_date' => $retireDate
		]);
		$platformVersion->save();
		
		return $platformVersion;
	}

	// get all
	//
	public function getAll(Request $request): Collection {
		return PlatformVersion::all();
	}

	// get by index
	//
	public function getIndex(string $platformVersionUuid): ?PlatformVersion {
		return PlatformVersion::find($platformVersionUuid);
	}

	// update by index
	//
	public function updateIndex(Request $request, string $platformVersionUuid) {

		// parse parameters
		//
		$platformUuid = $request->input('platform_uuid');
		$versionString = $request->input('version_string');
		$releaseDate = $request->input('release_date');
		$retireDate = $request->input('retire_date');

		// get model
		//
		$platformVersion = $this->getIndex($platformVersionUuid);
		if (!$platformVersion) {
			return response('Platform not found', 404);
		}

		// update attributes
		//
		$platformVersion->platform_uuid = $platformUuid;
		$platformVersion->version_string = $versionString;
		$platformVersion->release_date = $releaseDate;
		$platformVersion->retire_date = $retireDate;

		// save and return changes
		//
		$changes = $platformVersion->getDirty();
		$platformVersion->save();
		return $changes;
	}

	// delete by index
	//
	public function deleteIndex(string $platformVersionUuid) {
		$platformVersion = PlatformVersion::where('platform_version_uuid', '=', $platformVersionUuid)->first();
		$platformVersion->delete();
		return $platformVersion;
	}
}
