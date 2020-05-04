<?php
/******************************************************************************\
|                                                                              |
|                              PackagesController.php                          |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a controller for packages.                               |
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
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\DB;
use App\Utilities\Uuids\Guid;
use App\Utilities\Filters\DateFilter;
use App\Utilities\Filters\LimitFilter;
use App\Utilities\Filters\PackageTypeFilter;
use App\Models\Projects\Project;
use App\Models\Packages\Package;
use App\Models\Packages\PackageType;
use App\Models\Packages\PackageVersion;
use App\Models\Packages\PackageVersionSharing;
use App\Models\Packages\PackagePlatform;
use App\Models\Users\User;
use App\Http\Controllers\BaseController;

class PackagesController extends BaseController
{
	public static $requireUniquePackageNames = false;

	// create
	//
	public function postCreate(Request $request): Package {

		// parse parameters
		//
		$name = $request->input('name');
		$description = $request->input('description');
		$externalUrl = $request->input('external_url');
		$externalUrlType = $request->input('external_url_type');
		$secretToken = $request->input('secret_token');
		$packageTypeId = $request->input('package_type_id');
		$packageLanguage = $request->input('package_language');
		$packageSharingStatus = $request->input('package_sharing_status');

		// check for existing package name
		//
		if (self::$requireUniquePackageNames) {
			$existingPackage = Package::where('name', '=', $name)->first();
			if ($existingPackage) {
				return response('A package named '.$name.' already exists.  Please rename your package to a unique name and try again.', 500);
			}
		}

		// convert package language to string
		//
		if (is_array($packageLanguage)) {
			$packageLanguage = implode(' ', $packageLanguage);
		}

		// create new package
		//
		$package = new Package([
			'package_uuid' => Guid::create(),

			// package attributes
			//
			'name' => $name,
			'description' => $description,
			'package_type_id' => $packageTypeId,
			'package_language' => $packageLanguage,
			'package_owner_uuid' => session('user_uid'),
			'package_sharing_status' => $packageSharingStatus,

			// external url attributes
			//
			'external_url' => $externalUrl,
			'external_url_type' => $externalUrlType,
			'secret_token' => $secretToken
		]);
		$package->save();

		return $package;
	}

	// get all for admin user
	//
	public function getAll(Request $request): Collection {
		$user = User::current();
		if ($user && $user->isAdmin()) {

			// create SQL query
			//
			$query = Package::orderBy('create_date', 'DESC');

			// add filters
			//
			$query = PackageTypeFilter::apply($request, $query);
			$query = DateFilter::apply($request, $query);
			$query = LimitFilter::apply($request, $query);

			// perform query
			//
			return $query->get();
		} else {
			return collect();
		}
	}

	// get types for filtering
	//
	public function getTypes(): Collection {
		return PackageType::all();
	}

	// get by index
	//
	public function getIndex(string $packageUuid): ?Package {
		return Package::find($packageUuid);
	}

	// get by user
	//
	public function getByOwner(Request $request, string $userUuid): Collection {

		// create SQL query
		//
		$query = Package::where('package_owner_uuid', '=', $userUuid)->orderBy('create_date', 'DESC');

		// add filters
		//
		$query = PackageTypeFilter::apply($request, $query);
		$query = DateFilter::apply($request, $query);
		$query = LimitFilter::apply($request, $query);

		// perform query
		//
		return $query->get();
	}

	public function getByUser(Request $request, string $userUuid): Collection {
			
		// get user's projects
		//
		$user = User::getIndex($userUuid);
		$projects = $user->getProjects();

		// add packages of each project
		//
		$packages = collect();
		foreach ($projects as $project) {
			$projectPackages = $this->getProtected($request, $project->project_uid);
			foreach ($projectPackages as $package) {
				if (!$packages->contains($package)) {
					$packages->push($package);

					// add to packages query
					//
					if (!isset($query)) {
						$query = Package::where('package_uuid', '=', $package->package_uuid);
					} else {
						$query = $query->orWhere('package_uuid', '=', $package->package_uuid);
					}

					// add filters
					//
					$query = PackageTypeFilter::apply($request, $query);
					$query = DateFilter::apply($request, $query);
				}
			}
		}

		// perform query
		//
		if (isset($query)) {
			return $query->get();
		} else {
			return collect();
		}
	}

	// get number by user
	//
	public function getNumByUser(Request $request, string $userUuidIn): int {

		// create SQL query
		//
		$query = Package::where('package_owner_uuid', '=', $userUuidIn);

		// add filters
		//
		$query = PackageTypeFilter::apply($request, $query);
		$query = DateFilter::apply($request, $query);

		// perform query
		//
		return $query->count();
	}

	// get by current user
	//
	public function getAvailable(): Collection {
		return $this->getByUser(session('user_uid'));
	}

	// get by public scoping
	//
	public function getPublic(Request $request): Collection {

		// create SQL query
		//
		$query = Package::where('package_sharing_status', '=', 'public')->orderBy('name', 'ASC');

		// add filters
		//
		$query = PackageTypeFilter::apply($request, $query);
		$query = DateFilter::apply($request, $query);
		$query = LimitFilter::apply($request, $query);

		// perform query
		//
		return $query->get();
	}

	// get by protected scoping
	//
	public function getProtected(Request $request, string $projectUuid): Collection {
		$user = User::current();
		$projects = $user->getProjects();

		// execute SQL query
		//
		$packages = collect();

		if (!strpos($projectUuid, '+')) {

			// check to see if project is in list of user's projects
			//
			if (!$user->isAdmin()) {
				$found = false;
		 		foreach ($projects as $project) {
		 			if ($project->project_uid == $projectUuid) {
		 				$found = true;
		 			}
		 		}
		 		if (!$found) {
					return response('User does not have permission to access given project.', 400);
		 		}
		 	}

			// collect packages shared with a single project
			//
			$packageVersionSharings = PackageVersionSharing::where('project_uuid', '=', $projectUuid)->get();
			for ($i = 0; $i < sizeof($packageVersionSharings); $i++) {
				$packageVersion = PackageVersion::find($packageVersionSharings[$i]->package_version_uuid);
				$package = Package::find($packageVersion->package_uuid);
				if ($package && !$packages->contains($package)) {
					$packages->push($package);

					// add to packages query
					//
					if (!isset($query)) {
						$query = Package::where('package_uuid', '=', $package->package_uuid);
					} else {
						$query = $query->orWhere('package_uuid', '=', $package->package_uuid);
					}

					// add filters
					//
					$query = PackageTypeFilter::apply($request, $query);
					$query = DateFilter::apply($request, $query);
					$query = LimitFilter::apply($request, $query);
				}
			}
		} else {

			// collect packages shared with multiple projects
			//
			$projectUuids = explode('+', $projectUuid);
			foreach ($projectUuids as $projectUuid) {

				// check to see if project is in list of user's projects
				//
				if (!$user->isAdmin()) {
					$found = false;
			 		foreach ($projects as $project) {
			 			if ($project->project_uid == $projectUuid) {
			 				$found = true;
			 			}
			 		}
			 		if (!$found) {
						return response('User does not have permission to access given project.', 400);
			 		}
			 	}

				$packageVersionSharings = PackageVersionSharing::where('project_uuid', '=', $projectUuid)->get();
				for ($i = 0; $i < sizeof($packageVersionSharings); $i++) {
					$packageVersion = PackageVersion::find($packageVersionSharings[$i]->package_version_uuid);
					$package = Package::find($packageVersion->package_uuid);
					if ($package && !$packages->contains($package)) {
						$packages->push($package);

						// add to packages query
						//
						if (!isset($query)) {
							$query = Package::where('package_uuid', '=', $package->package_uuid);
						} else {
							$query = $query->orWhere('package_uuid', '=', $package->package_uuid);
						}

						// add filters
						//
						$query = PackageTypeFilter::apply($request, $query);
						$query = DateFilter::apply($request, $query);
						$query = LimitFilter::apply($request, $query);
					}
				}
			}			
		}

		// perform query
		//
		if (isset($query)) {
			return $query->get();
		} else {
			return collect();
		}
	}

	public function getNumProtected(Request $request, string $projectUuid): int {
		$packages = collect();
		
		if (!strpos($projectUuid, '+')) {

			// collect packages shared with a single project
			//
			$packageVersionSharings = PackageVersionSharing::where('project_uuid', '=', $projectUuid)->get();
			for ($i = 0; $i < sizeof($packageVersionSharings); $i++) {
				$packageVersion = PackageVersion::find($packageVersionSharings[$i]->package_version_uuid);
				$package = $packageVersion? $packageVersion->getPackage() : null;
				if ($package && !$packages->contains($package)) {
					$packages->push($package);

					// add to packages query
					//
					if (!isset($query)) {
						$query = Package::where('package_uuid', '=', $package->package_uuid);
					} else {
						$query = $query->orWhere('package_uuid', '=', $package->package_uuid);
					}

					// add filters
					//
					$query = PackageTypeFilter::apply($request, $query);
					$query = DateFilter::apply($request, $query);
					$query = LimitFilter::apply($request, $query);
				}
			}
		} else {

			// collect packages shared with multiple projects
			//
			$projectUuids = explode('+', $projectUuid);
			foreach ($projectUuids as $projectUuid) {
				$packageVersionSharings = PackageVersionSharing::where('project_uuid', '=', $projectUuid)->get();
				for ($i = 0; $i < sizeof($packageVersionSharings); $i++) {
					$packageVersion = PackageVersion::find($packageVersionSharings[$i]->package_version_uuid);
					$package = $packageVersion? $packageVersion->getPackage() : null;
					if ($package && !$packages->contains($package)) {
						$packages->push($package);

						// add to packages query
						//
						if (!isset($query)) {
							$query = Package::where('package_uuid', '=', $package->package_uuid);
						} else {
							$query = $query->orWhere('package_uuid', '=', $package->package_uuid);
						}

						// add filters
						//
						$query = PackageTypeFilter::apply($request, $query);
						$query = DateFilter::apply($request, $query);
						$query = LimitFilter::apply($request, $query);
					}
				}
			}			
		}

		// perform query
		//
		if (isset($query)) {
			return $query->count();
		} else {
			return 0;
		}
	}

	// get by project
	//
	public function getByProject(Request $request, string $projectUuid): Collection {
		return $this->getPublic($request)->merge($this->getProtected($request, $projectUuid));
	}

	// get versions
	//
	public function getVersions(string $packageUuid): Collection {
		return PackageVersion::where('package_uuid', '=', $packageUuid)->get();
	}

	// get versions a user can access
	//
	public function getAvailableVersions(Request $request, string $packageUuid): Collection {
		$user = User::current();
		$packageVersions = PackageVersion::where('package_uuid', '=', $packageUuid)->get();

		// get available versions
		//
		foreach ($packageVersions as $packageVersion) {
			if ($user->packageVersionSharedWith($packageVersion->package_version_uuid)) {

				// add to package versions query
				//
				if (!isset($query)) {
					$query = PackageVersion::where('package_version_uuid', '=', $packageVersion->package_version_uuid);
				} else {
					$query = $query->orWhere('package_version_uuid', '=', $packageVersion->package_version_uuid);
				}

				// add filters
				//
				$query = DateFilter::apply($request, $query);
				$query = LimitFilter::apply($request, $query);
			}
		}

		// perform query
		//
		if (isset($query)) {
			return $query->get();
		} else {
			return collect();
		}
	}

	public function getSharedVersions(Request $request, string $packageUuid, string $projectUuid) {
		$packageVersions = PackageVersion::where('package_uuid', '=', $packageUuid)->get();

		if (!strpos($projectUuid, '+')) {

			// get by a single project
			//
			foreach ($packageVersions as $packageVersion) {
				if ($packageVersion->isPublic()) {

					// add to package versions query
					//
					if (!isset($query)) {
						$query = PackageVersion::where('package_version_uuid', '=', $packageVersion->package_version_uuid);
					} else {
						$query = $query->orWhere('package_version_uuid', '=', $packageVersion->package_version_uuid);
					}

					// add filters
					//
					$query = DateFilter::apply($request, $query);
					$query = LimitFilter::apply($request, $query);
				} elseif ($packageVersion->isProtected()) {
					foreach (PackageVersionSharing::where('package_version_uuid', '=', $packageVersion->package_version_uuid)->get() as $packageVersionSharing) {
						if ($packageVersionSharing->project_uuid == $projectUuid) {

							// add to package versions query
							//
							if (!isset($query)) {
								$query = PackageVersion::where('package_version_uuid', '=', $packageVersion->package_version_uuid);
							} else {
								$query = $query->orWhere('package_version_uuid', '=', $packageVersion->package_version_uuid);
							}

							// add filters
							//
							$query = DateFilter::apply($request, $query);
							$query = LimitFilter::apply($request, $query);
							break;
						}
					}
				}
			}
		} else {

			// get by multiple projects
			//
			$projectUuids = explode('+', $projectUuid);

			foreach ($packageVersions as $packageVersion) {
				if ($packageVersion->isPublic()) {

					// add to package versions query
					//
					if (!isset($query)) {
						$query = PackageVersion::where('package_version_uuid', '=', $packageVersion->package_version_uuid);
					} else {
						$query = $query->orWhere('package_version_uuid', '=', $packageVersion->package_version_uuid);
					}

					// add filters
					//
					$query = DateFilter::apply($request, $query);
					$query = LimitFilter::apply($request, $query);
				} elseif ($packageVersion->isProtected()) {
					foreach (PackageVersionSharing::where('package_version_uuid', '=', $packageVersion->package_version_uuid)->get() as $packageVersionSharing) {
						foreach ($projectUuids as $projectUuid) {
							if ($packageVersionSharing->project_uuid == $projectUuid) {

								// add to package versions query
								//
								if (!isset($query)) {
									$query = PackageVersion::where('package_version_uuid', '=', $packageVersion->package_version_uuid);
								} else {
									$query = $query->orWhere('package_version_uuid', '=', $packageVersion->package_version_uuid);
								}

								// add filters
								//
								$query = DateFilter::apply($request, $query);
								$query = LimitFilter::apply($request, $query);
								break 2;
							}
						}
					}
				}
			}
		}

		// perform query
		//
		if (isset($query)) {
			return $query->get();
		} else {
			return collect();
		}
	}

	// get platforms / platlform versions
	//
	public function getPackagePlatforms(Request $request, string $packageUuid): Collection {

		// parse parameters
		//
		$packageVersionUuid = $request->input('package_version_uuid');

		// get platforms
		//
		return PackagePlatform::where('package_uuid', '=', $packageUuid)->
			where('package_version_uuid', '=', $packageVersionUuid)->get();
	}

	// update by index
	//
	public function updateIndex(Request $request, string $packageUuid) {

		// parse parameters
		//
		$name = $request->input('name');
		$description = $request->input('description');
		$externalUrl = $request->input('external_url');
		$externalUrlType = $request->input('external_url_type');
		$secretToken = $request->input('secret_token');
		$packageTypeId = $request->input('package_type_id');
		$packageOwnerUuid = $request->input('package_owner_uuid', null);
		$packageSharingStatus = $request->input('package_sharing_status');

		// get model
		//
		$package = $this->getIndex($packageUuid);

		// check if name has changed
		//
		if ($name != $package->name) {
			if (self::$requireUniquePackageNames) {

				// check new name against existing package names
				//
				$existingPackage = Package::where('name', '=', $name)->first();
				if ($existingPackage && ($existingPackage->package_uuid != $packageUuid)) {
					return response('A package named '.$name.' already exists.  Please rename your package to a unique name and try again.', 500);
				}
			}
		}

		// update attributes
		//
		$package->name = $name;
		$package->description = $description;
		$package->external_url = $externalUrl;
		$package->external_url_type = $externalUrlType;
		$package->secret_token = $secretToken;
		$package->package_type_id = $packageTypeId;
		$package->package_owner_uuid = $packageOwnerUuid ? $packageOwnerUuid : $package->package_owner_uuid;
		$package->package_sharing_status = $packageSharingStatus;

		// save and return changes
		//
		$changes = $package->getDirty();
		$package->save();
		return $changes;
	}

	// delete by index
	//
	public function deleteIndex(string $packageUuid) {

		// find package
		//
		$package = Package::find($packageUuid);
		if (!$package) {
			return response("Package not found.", 404);
		}


		$package->delete();
		return $package;
	}

	// delete versions
	//
	public function deleteVersions(string $packageUuid) {
		$packageVersions = $this->getVersions($packageUuid);
		for ($i = 0; $i < sizeof($packageVersions); $i++) {
			$packageVersions[$i]->delete();
		}
		return $packageVersions;
	}
}
