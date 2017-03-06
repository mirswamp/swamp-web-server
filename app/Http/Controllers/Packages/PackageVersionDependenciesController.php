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
|        Copyright (C) 2012-2017 Software Assurance Marketplace (SWAMP)        |
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

		// fetch parameters
		//
		$packageVersionUuid = Input::get('package_version_uuid');
		$platformVersionUuid = Input::get('platform_version_uuid');
		$dependencyList = Input::get('dependency_list');

		// create new model
		//
		$packageVersionDependency = new PackageVersionDependency(array(
			'package_version_uuid' => $packageVersionUuid,
			'platform_version_uuid' => $platformVersionUuid,
			'dependency_list' => $dependencyList
		));

		// save new model
		//
		$packageVersionDependency->save();

		return $packageVersionDependency;
	}

	//
	// get
	//

	public function getByPackageVersion($packageVersionUuid) {
		return PackageVersionDependency::where('package_version_uuid','=', $packageVersionUuid)->get() ?: array();
	}

	public function getMostRecent($packageUuid) {
		$packageVersion = PackageVersion::where('package_uuid','=',$packageUuid)->orderBy('create_date','desc')->first();
		return $packageVersion? PackageVersionDependency::where('package_version_uuid','=', $packageVersion->package_version_uuid)->get() : array();
	}

	// update
	//
	public function update($packageVersionDependencyId) {

		// fetch parameters
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
		$dependencies = Input::get('data') ?: array();
		$results = new Collection();
		foreach( $dependencies as $pvd ){
			$p = null;
			if( array_key_exists('package_version_dependency_id', $pvd) ){
				$p = PackageVersionDependency::where('package_version_dependency_id','=',$pvd['package_version_dependency_id'])->first();
				$p->dependency_list = $pvd['dependency_list'];
			}
			else
				$p = new PackageVersionDependency( $pvd );
			$p->save();
			$results->push( $p );
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
