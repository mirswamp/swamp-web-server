<?php
/******************************************************************************\
|                                                                              |
|                   PackageVersionDependenciesController.php                   |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a controller for package version dependencies.           |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2020 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Http\Controllers\Packages;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use App\Models\Packages\Package;
use App\Models\Packages\PackageVersion;
use App\Models\Packages\PackageVersionDependency;
use App\Http\Controllers\BaseController;

class PackageVersionDependenciesController extends BaseController
{
	// create
	//
	public function postCreate(Request $request): PackageVersionDependency {

		// parse parameters
		//
		$packageVersionUuid = $request->input('package_version_uuid');
		$platformVersionUuid = $request->input('platform_version_uuid');
		$dependencyList = $request->input('dependency_list');

		// create new model
		//
		$packageVersionDependency = new PackageVersionDependency([
			'package_version_uuid' => $packageVersionUuid,
			'platform_version_uuid' => $platformVersionUuid,
			'dependency_list' => $dependencyList
		]);

		// save new model
		//
		$packageVersionDependency->save();

		return $packageVersionDependency;
	}

	//
	// get
	//

	public function getByPackageVersion(Request $request, string $packageVersionUuid) {
		return PackageVersionDependency::where('package_version_uuid','=', $packageVersionUuid)->get() ?: [];
	}

	public function getMostRecent(Request $request, string $packageUuid) {
		$packageVersion = PackageVersion::where('package_uuid','=',$packageUuid)->orderBy('create_date','desc')->first();
		return $packageVersion? PackageVersionDependency::where('package_version_uuid','=', $packageVersion->package_version_uuid)->get() : [];
	}

	// update
	//
	public function update(Request $request, string $packageVersionDependencyId) {

		// parse parameters
		//
		$packageVersionUuid = $request->input('package_version_uuid');
		$platformVersionUuid = $request->input('platform_version_uuid');
		$dependencyList = $request->input('dependency_list');

		// find model
		//
		$packageVersionDependency = PackageVersionDependency::where('package_version_dependency_id', '=', $packageVersionDependencyId)->first();

		// update attributes
		//
		$packageVersionDependency->package_version_uuid = $packageVersionUuid;
		$packageVersionDependency->platform_version_uuid = $platformVersionUuid;
		$packageVersionDependency->dependency_list = $dependencyList;

		// save and return changes
		//
		$changes = $packageVersionDependency->getDirty();
		$packageVersionDependency->save();
		return $changes;
	}

	// delete
	//
	public function delete(Request $request, string $packageVersionUuid, string $platformVersionUuid) {
		return $packageVersionDependencies = PackageVersionDependency::where('package_version_uuid', '=', $packageVersionUuid)
			->where('platform_version_uuid', '=', $platformVersionUuid)->delete();
	}
}
