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
|        Copyright (C) 2012-2019 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Http\Controllers\Packages;

use Illuminate\Support\Facades\Input;
use Illuminate\Support\Collection;
use App\Models\Packages\Package;
use App\Models\Packages\PackageVersion;
use App\Models\Packages\PackageVersionDependency;
use App\Http\Controllers\BaseController;

class PackageVersionDependenciesController extends BaseController {

	// create
	//
	public function postCreate() {

		// parse parameters
		//
		$packageVersionUuid = Input::get('package_version_uuid');
		$platformVersionUuid = Input::get('platform_version_uuid');
		$dependencyList = Input::get('dependency_list');

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

	public function getByPackageVersion($packageVersionUuid) {
		return PackageVersionDependency::where('package_version_uuid','=', $packageVersionUuid)->get() ?: [];
	}

	public function getMostRecent($packageUuid) {
		$packageVersion = PackageVersion::where('package_uuid','=',$packageUuid)->orderBy('create_date','desc')->first();
		return $packageVersion? PackageVersionDependency::where('package_version_uuid','=', $packageVersion->package_version_uuid)->get() : [];
	}

	// update
	//
	public function update($packageVersionDependencyId) {

		// parse parameters
		//
		$packageVersionUuid = Input::get('package_version_uuid');
		$platformVersionUuid = Input::get('platform_version_uuid');
		$dependencyList = Input::get('dependency_list');

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

	public function updateAll() {

		// parse parameters
		//
		$dependencies = Input::get('data')? : [];

		// update dependencies
		//
		$results = new Collection();
		foreach ($dependencies as $dependency) {
			$packageVersionDependency = null;
			if (array_key_exists('package_version_dependency_id', $dependency)) {
				$packageVersionDependency = PackageVersionDependency::where('package_version_dependency_id', '=', $dependency['package_version_dependency_id'])->first();
				$packageVersionDependency->dependency_list = $dependency['dependency_list'];
			} else {
				$packageVersionDependency = new PackageVersionDependency($dependency);
			}
			$packageVersionDependency->save();
			$results->push($packageVersionDependency);
		}

		return $results;
	}

	// delete
	//
	public function delete($packageVersionUuid, $platformVersionUuid) {
		return $packageVersionDependencies = PackageVersionDependency::where('package_version_uuid', '=', $packageVersionUuid)
			->where('platform_version_uuid', '=', $platformVersionUuid)->delete();
	}
}
