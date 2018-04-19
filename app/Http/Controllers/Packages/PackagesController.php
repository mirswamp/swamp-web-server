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
|        Copyright (C) 2012-2018 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Http\Controllers\Packages;

use PDO;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use App\Utilities\Uuids\Guid;
use App\Utilities\Filters\DateFilter;
use App\Utilities\Filters\LimitFilter;
use App\Utilities\Filters\PackageTypeFilter;
use App\Models\Projects\Project;
use App\Models\Packages\Package;
use App\Models\Packages\PackageType;
use App\Models\Packages\PackageSharing;
use App\Models\Packages\PackageVersion;
use App\Models\Packages\PackageVersionSharing;
use App\Models\Packages\PackagePlatform;
use App\Models\Users\User;
use App\Http\Controllers\BaseController;

class PackagesController extends BaseController {
	public static $requireUniquePackageNames = false;

	// create
	//
	public function postCreate() {

		// parse parameters
		//
		$name = Input::get('name');
		$description = Input::get('description');
		$externalUrl = Input::get('external_url');
		$packageTypeId = Input::get('package_type_id');
		$packageLanguage = Input::get('package_language');
		$packageSharingStatus = Input::get('package_sharing_status');

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
			'name' => $name,
			'description' => $description,
			'external_url' => $externalUrl,
			'package_type_id' => $packageTypeId,
			'package_language' => $packageLanguage,
			'package_owner_uuid' => session('user_uid'),
			'package_sharing_status' => $packageSharingStatus
		]);
		$package->save();

		return $package;
	}

	// get all for admin user
	//
	public function getAll() {
		$user = User::getIndex(session('user_uid'));
		if ($user && $user->isAdmin()) {

			// create SQL query
			//
			$packagesQuery = Package::orderBy('create_date', 'DESC');

			// add filters
			//
			$packagesQuery = PackageTypeFilter::apply($packagesQuery);
			$packagesQuery = DateFilter::apply($packagesQuery);
			$packagesQuery = LimitFilter::apply($packagesQuery);

			// perform query
			//
			return $packagesQuery->get();
		}
		return '';
	}

	// get types for filtering
	//
	public function getTypes() {
		return PackageType::all();
	}

	// get by index
	//
	public function getIndex($packageUuid) {
		return Package::where('package_uuid', '=', $packageUuid)->first();
	}

	// get by user
	//
	public function getByOwner($userUuid) {
		if (config('database.use_stored_procedures')) {

			// execute stored procedure
			//
			return self::PDOListPackagesByOwner($userUuid);
		} else {

			// create SQL query
			//
			$packagesQuery = Package::where('package_owner_uuid', '=', $userUuid)->orderBy('create_date', 'DESC');

			// add filters
			//
			$packagesQuery = PackageTypeFilter::apply($packagesQuery);
			$packagesQuery = DateFilter::apply($packagesQuery);
			$packagesQuery = LimitFilter::apply($packagesQuery);

			// perform query
			//
			return $packagesQuery->get();
		}
	}

	public function getByUser($userUuid) {
		if (config('database.use_stored_procedures')) {

			// execute stored procedure
			//
			return self::PDOListPackagesByUser($userUuid);
		} else {
			
			// get user's projects
			//
			$user = User::getIndex($userUuid);
			$projects = $user->getProjects();

			// add packages of each project
			//
			$packages = new Collection;
			foreach ($projects as $project) {
				$projectPackages = $this->getProtected($project->project_uid);
				foreach ($projectPackages as $package) {
					if (!$packages->contains($package)) {
						$packages->push($package);

						// add to packages query
						//
						if (!isset($packagesQuery)) {
							$packagesQuery = Package::where('package_uuid', '=', $package->package_uuid);
						} else {
							$packagesQuery = $packagesQuery->orWhere('package_uuid', '=', $package->package_uuid);
						}

						// add filters
						//
						$packagesQuery = PackageTypeFilter::apply($packagesQuery);
						$packagesQuery = DateFilter::apply($packagesQuery);
					}
				}
			}

			// perform query
			//
			if (isset($packagesQuery)) {
				return $packagesQuery->get();
			} else {
				return [];
			}
		}
	}

	// get number by user
	//
	public function getNumByUser($userUuidIn) {

		// create SQL query
		//
		$packagesQuery = Package::where('package_owner_uuid', '=', $userUuidIn);

		// add filters
		//
		$packagesQuery = PackageTypeFilter::apply($packagesQuery);
		$packagesQuery = DateFilter::apply($packagesQuery);

		// perform query
		//
		return $packagesQuery->count();
	}

	// get by current user
	//
	public function getAvailable() {
		return $this->getByUser(session('user_uid'));
	}

	// get by public scoping
	//
	public function getPublic() {

		// create SQL query
		//
		$packagesQuery = Package::where('package_sharing_status', '=', 'public')->orderBy('name', 'ASC');

		// add filters
		//
		$packagesQuery = PackageTypeFilter::apply($packagesQuery);
		$packagesQuery = DateFilter::apply($packagesQuery);
		$packagesQuery = LimitFilter::apply($packagesQuery);

		// perform query
		//
		$packages = $packagesQuery->get();

		return $packages;
	}

	// get by protected scoping
	//
	public function getProtected($projectUuid) {
		$user = User::getIndex(session('user_uid'));
		$projects = $user->getProjects();

		if (config('database.use_stored_procedures')) {

			// execute stored procedure
			// 
			self::PDOListProtectedPkgsByProjectUser($projectUuid);
		} else {

			// execute SQL query
			//
			$packages = new Collection;

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
					$packageVersion = PackageVersion::where('package_version_uuid', '=', $packageVersionSharings[$i]->package_version_uuid)->first();
					$package = Package::where('package_uuid', '=', $packageVersion->package_uuid)->first();
					if ($package && !$packages->contains($package)) {
						$packages->push($package);

						// add to packages query
						//
						if (!isset($packagesQuery)) {
							$packagesQuery = Package::where('package_uuid', '=', $package->package_uuid);
						} else {
							$packagesQuery = $packagesQuery->orWhere('package_uuid', '=', $package->package_uuid);
						}

						// add filters
						//
						$packagesQuery = PackageTypeFilter::apply($packagesQuery);
						$packagesQuery = DateFilter::apply($packagesQuery);
						$packagesQuery = LimitFilter::apply($packagesQuery);
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
						$packageVersion = PackageVersion::where('package_version_uuid', '=', $packageVersionSharings[$i]->package_version_uuid)->first();
						$package = Package::where('package_uuid', '=', $packageVersion->package_uuid)->first();
						if ($package && !$packages->contains($package)) {
							$packages->push($package);

							// add to packages query
							//
							if (!isset($packagesQuery)) {
								$packagesQuery = Package::where('package_uuid', '=', $package->package_uuid);
							} else {
								$packagesQuery = $packagesQuery->orWhere('package_uuid', '=', $package->package_uuid);
							}

							// add filters
							//
							$packagesQuery = PackageTypeFilter::apply($packagesQuery);
							$packagesQuery = DateFilter::apply($packagesQuery);
							$packagesQuery = LimitFilter::apply($packagesQuery);
						}
					}
				}			
			}

			// perform query
			//
			if (isset($packagesQuery)) {
				return $packagesQuery->get();
			} else {
				return [];
			}
		}
	}

	public function getNumProtected($projectUuid) {
		$packages = new Collection;
		
		if (!strpos($projectUuid, '+')) {

			// collect packages shared with a single project
			//
			$packageVersionSharings = PackageVersionSharing::where('project_uuid', '=', $projectUuid)->get();
			for ($i = 0; $i < sizeof($packageVersionSharings); $i++) {
				$packageVersion = PackageVersion::where('package_version_uuid', '=', $packageVersionSharings[$i]->package_version_uuid)->first();
				$package = Package::where('package_uuid', '=', $packageVersion->package_uuid)->first();
				if ($package && !$packages->contains($package)) {
					$packages->push($package);

					// add to packages query
					//
					if (!isset($packagesQuery)) {
						$packagesQuery = Package::where('package_uuid', '=', $package->package_uuid);
					} else {
						$packagesQuery = $packagesQuery->orWhere('package_uuid', '=', $package->package_uuid);
					}

					// add filters
					//
					$packagesQuery = PackageTypeFilter::apply($packagesQuery);
					$packagesQuery = DateFilter::apply($packagesQuery);
					$packagesQuery = LimitFilter::apply($packagesQuery);
				}
			}
		} else {

			// collect packages shared with multiple projects
			//
			$projectUuids = explode('+', $projectUuid);
			foreach ($projectUuids as $projectUuid) {
				$packageVersionSharings = PackageVersionSharing::where('project_uuid', '=', $projectUuid)->get();
				for ($i = 0; $i < sizeof($packageVersionSharings); $i++) {
					$packageVersion = PackageVersion::where('package_version_uuid', '=', $packageVersionSharings[$i]->package_version_uuid)->first();
					$package = Package::where('package_uuid', '=', $packageVersion->package_uuid)->first();
					if ($package && !$packages->contains($package)) {
						$packages->push($package);

						// add to packages query
						//
						if (!isset($packagesQuery)) {
							$packagesQuery = Package::where('package_uuid', '=', $package->package_uuid);
						} else {
							$packagesQuery = $packagesQuery->orWhere('package_uuid', '=', $package->package_uuid);
						}

						// add filters
						//
						$packagesQuery = PackageTypeFilter::apply($packagesQuery);
						$packagesQuery = DateFilter::apply($packagesQuery);
						$packagesQuery = LimitFilter::apply($packagesQuery);
					}
				}
			}			
		}

		// perform query
		//
		if (isset($packagesQuery)) {
			return $packagesQuery->count();
		} else {
			return 0;
		}
	}

	// get by project
	//
	public function getByProject($projectUuid) {
		if (config('database.use_stored_procedures')) {

			// execute stored procedure
			//
			return self::PDOListPackagesByProjectUser($projectUuid);
		} else {

			// use SQL
			//
			$packages = $this->getPublic();
			$packages = $packages->merge($this->getProtected());
			return $packages;
		}
	}

	// get versions
	//
	public function getVersions($packageUuid) {
		return PackageVersion::where('package_uuid', '=', $packageUuid)->get();
	}

	// get versions a user can access
	//
	public function getAvailableVersions($packageUuid) {
		$user = User::getIndex(session('user_uid'));
		$packageVersions = PackageVersion::where('package_uuid', '=', $packageUuid)->get();

		// get available versions
		//
		foreach ($packageVersions as $packageVersion) {
			if ($user->packageVersionSharedWith($packageVersion->package_version_uuid)) {

				// add to package versions query
				//
				if (!isset($packageVersionsQuery)) {
					$packageVersionsQuery = PackageVersion::where('package_version_uuid', '=', $packageVersion->package_version_uuid);
				} else {
					$packageVersionsQuery = $packageVersionsQuery->orWhere('package_version_uuid', '=', $packageVersion->package_version_uuid);
				}

				// add filters
				//
				$packageVersionsQuery = DateFilter::apply($packageVersionsQuery);
				$packageVersionsQuery = LimitFilter::apply($packageVersionsQuery);
			}
		}

		// perform query
		//
		if (isset($packageVersionsQuery)) {
			return $packageVersionsQuery->get();
		} else {
			return [];
		}
	}

	public function getSharedVersions($packageUuid, $projectUuid) {
		$packageVersions = PackageVersion::where('package_uuid', '=', $packageUuid)->get();

		if (!strpos($projectUuid, '+')) {

			// get by a single project
			//
			foreach ($packageVersions as $packageVersion) {
				if ($packageVersion->isPublic()) {

					// add to package versions query
					//
					if (!isset($packageVersionsQuery)) {
						$packageVersionsQuery = PackageVersion::where('package_version_uuid', '=', $packageVersion->package_version_uuid);
					} else {
						$packageVersionsQuery = $packageVersionsQuery->orWhere('package_version_uuid', '=', $packageVersion->package_version_uuid);
					}

					// add filters
					//
					$packageVersionsQuery = DateFilter::apply($packageVersionsQuery);
					$packageVersionsQuery = LimitFilter::apply($packageVersionsQuery);
				} elseif ($packageVersion->isProtected()) {
					foreach (PackageVersionSharing::where('package_version_uuid', '=', $packageVersion->package_version_uuid)->get() as $packageVersionSharing) {
						if ($packageVersionSharing->project_uuid == $projectUuid) {

							// add to package versions query
							//
							if (!isset($packageVersionsQuery)) {
								$packageVersionsQuery = PackageVersion::where('package_version_uuid', '=', $packageVersion->package_version_uuid);
							} else {
								$packageVersionsQuery = $packageVersionsQuery->orWhere('package_version_uuid', '=', $packageVersion->package_version_uuid);
							}

							// add filters
							//
							$packageVersionsQuery = DateFilter::apply($packageVersionsQuery);
							$packageVersionsQuery = LimitFilter::apply($packageVersionsQuery);
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
					if (!isset($packageVersionsQuery)) {
						$packageVersionsQuery = PackageVersion::where('package_version_uuid', '=', $packageVersion->package_version_uuid);
					} else {
						$packageVersionsQuery = $packageVersionsQuery->orWhere('package_version_uuid', '=', $packageVersion->package_version_uuid);
					}

					// add filters
					//
					$packageVersionsQuery = DateFilter::apply($packageVersionsQuery);
					$packageVersionsQuery = LimitFilter::apply($packageVersionsQuery);
				} elseif ($packageVersion->isProtected()) {
					foreach (PackageVersionSharing::where('package_version_uuid', '=', $packageVersion->package_version_uuid)->get() as $packageVersionSharing) {
						foreach ($projectUuids as $projectUuid) {
							if ($packageVersionSharing->project_uuid == $projectUuid) {

								// add to package versions query
								//
								if (!isset($packageVersionsQuery)) {
									$packageVersionsQuery = PackageVersion::where('package_version_uuid', '=', $packageVersion->package_version_uuid);
								} else {
									$packageVersionsQuery = $packageVersionsQuery->orWhere('package_version_uuid', '=', $packageVersion->package_version_uuid);
								}

								// add filters
								//
								$packageVersionsQuery = DateFilter::apply($packageVersionsQuery);
								$packageVersionsQuery = LimitFilter::apply($packageVersionsQuery);
								break 2;
							}
						}
					}
				}
			}
		}

		// perform query
		//
		if (isset($packageVersionsQuery)) {
			return $packageVersionsQuery->get();
		} else {
			return [];
		}
	}


	// get sharing
	//
	public function getSharing($packageUuid) {
		$packageSharing = PackageSharing::where('package_uuid', '=', $packageUuid)->get();
		$projectUuids = [];
		for ($i = 0; $i < sizeof($packageSharing); $i++) {
			array_push($projectUuids, $packageSharing[$i]->project_uuid);
		}
		return $projectUuids;
	}

	// get platforms / platlform versions
	//
	public function getPackagePlatforms($packageUuid) {

		// parse parameters
		//
		$packageVersionUuid = Input::get('package_version_uuid');

		// get platforms
		//
		return PackagePlatform::where('package_uuid', '=', $packageUuid)->
			where('package_version_uuid', '=', $packageVersionUuid)->get();
	}

	// update by index
	//
	public function updateIndex($packageUuid) {

		// parse parameters
		//
		$name = Input::get('name');
		$description = Input::get('description');
		$externalUrl = Input::get('external_url');
		$packageTypeId = Input::get('package_type_id');
		$packageOwnerUuid = Input::get('package_owner_uuid', null);
		$packageSharingStatus = Input::get('package_sharing_status');

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
		$package->package_type_id = $packageTypeId;
		$package->package_owner_uuid = $packageOwnerUuid ? $packageOwnerUuid : $package->package_owner_uuid;
		$package->package_sharing_status = $packageSharingStatus;

		// save and return changes
		//
		$changes = $package->getDirty();
		$package->save();
		return $changes;
	}

	// update sharing by index
	//
	public function updateSharing($packageUuid) {

		// parse parameters
		//
		$projectUuids = Input::get('project_uuids');

		// remove previous sharing
		//
		$packageSharings = PackageSharing::where('package_uuid', '=', $packageUuid)->get();
		for ($i = 0; $i < sizeof($packageSharings); $i++) {
			$packageSharing = $packageSharings[$i];
			$packageSharing->delete();
		}

		// create new sharing
		//
		$packageSharings = new Collection;
		foreach ($projectUuids as $projectUuid) {
			$packageSharing = new PackageSharing([
				'package_uuid' => $packageUuid,
				'project_uuid' => $projectUuid
			]);
			$packageSharing->save();
			$packageSharings[] = $packageSharing;
		}

		return $packageSharings;
	}

	public function applyToAll($packageUuid){

		// get package
		//
		$package = $this->getIndex($packageUuid);

		// get default project sharings for package
		//
		$packageSharings = PackageSharing::where('package_uuid', '=', $packageUuid)->get();

		// get all package versions
		//
		$packageVersions = PackageVersion::where('package_uuid', '=', $packageUuid)->get();
		foreach( $packageVersions as $packageVersion ){

			// reset all package version sharings for current package version
			//
			$packageVersionSharings = PackageVersionSharing::where('package_version_uuid', '=', $packageVersion->package_version_uuid)->get();
			foreach( $packageVersionSharings as $pvs ){
				$pvs->delete();
			}

			// set all package version sharings for current package version
			//
			foreach( $packageSharings as $ps ){
				$packageVersionSharing = new PackageVersionSharing([
					'project_uuid' => $ps->project_uuid,
					'package_version_uuid' => $packageVersion->package_version_uuid
				]);
				$packageVersionSharing->save();
			}

			// update sharing status
			//
			$packageVersion->version_sharing_status = $package->package_sharing_status;
			$packageVersion->save();
		}
	}

	// delete by index
	//
	public function deleteIndex($packageUuid) {
		$package = Package::where('package_uuid', '=', $packageUuid)->first();
		$package->delete();
		return $package;
	}

	// delete versions
	//
	public function deleteVersions($packageUuid) {
		$packageVersions = $this->getVersions($packageUuid);
		for ($i = 0; $i < sizeof($packageVersions); $i++) {
			$packageVersions[$i]->delete();
		}
		return $packageVersions;
	}

	//
	// PDO methods
	//

	private static function PDOListPackagesByOwner($userUuid) {
		$connection = DB::connection('package_store');
		$pdo = $connection->getPdo();
		$stmt = $pdo->prepare("CALL list_pkgs_by_owner(:userUuidIn, @returnString);");
		$stmt->bindParam(':userUuidIn', $userUuid, PDO::PARAM_STR, 45);
		$stmt->execute();
		$results = [];

		// get results
		//
		do {
			foreach( $stmt->fetchAll( PDO::FETCH_ASSOC ) as $row )
			$results[] = $row;
		} while ($stmt->nextRowset());

		$select = $pdo->query('SELECT @returnString;');
		$returnString = $select->fetchAll( PDO::FETCH_ASSOC )[0]['@returnString'];
		$select->nextRowset();

		if ($returnString == 'SUCCESS') {
			return $results;
		} else {
			return response( $returnString, 500 );
		}
	}

	private static function PDOListPackagesByUser($userUuid) {
		$connection = DB::connection('package_store');
		$pdo = $connection->getPdo();
		$stmt = $pdo->prepare("CALL list_pkgs_by_user(:userUuidIn, @returnString);");
		$stmt->bindParam(':userUuidIn', $userUuid, PDO::PARAM_STR, 45);
		$stmt->execute();
		$results = [];

		// get results
		//
		do {
			foreach( $stmt->fetchAll( PDO::FETCH_ASSOC ) as $row )
			$results[] = $row;
		} while ($stmt->nextRowset());

		$select = $pdo->query('SELECT @returnString;');
		$returnString = $select->fetchAll( PDO::FETCH_ASSOC )[0]['@returnString'];
		$select->nextRowset();

		if ($returnString == 'SUCCESS') {
			return $results;
		} else {
			return response( $returnString, 500 );
		}
	}

	private static function PDOListPackagesByProjectUser($projectUuid) {
		$userUid = session('user_uid');
		$connection = DB::connection('package_store');
		$pdo = $connection->getPdo();
		$stmt = $pdo->prepare("CALL list_pkgs_by_project_user(:userUuidIn, :projectUuidIn, @returnString);");
		$stmt->bindParam(':userUuidIn', $userUid, PDO::PARAM_STR, 45);
		$stmt->bindParam(':projectUuidIn', $projectUuid, PDO::PARAM_STR, 45);
		$stmt->execute();
		$results = [];

		do {
			foreach( $stmt->fetchAll( PDO::FETCH_ASSOC ) as $row )
			$results[] =  $row;
		} while ($stmt->nextRowset());

		$select = $pdo->query('SELECT @returnString;');
		$returnString = $select->fetchAll( PDO::FETCH_ASSOC )[0]['@returnString'];
		$select->nextRowset();

		if ($returnString == 'SUCCESS') {
			return $results;
		} else {
			return response( $returnString, 500 );
		}
	}

	private static function PDOListProtectedPkgsByProjectUser($projectUuid) {
		$userUid = session('user_uid');
		$connection = DB::connection('package_store');
		$pdo = $connection->getPdo();
		$stmt = $pdo->prepare("CALL list_protected_pkgs_by_project_user(:userUuidIn, :projectUuidIn, @returnString);");
		$stmt->bindParam(':userUuidIn', $userUid, PDO::PARAM_STR, 45);
		$stmt->bindParam(':projectUuidIn', $projectUuid, PDO::PARAM_STR, 45);
		$stmt->execute();
		$results = [];

		// get results
		//
		do {
			foreach( $stmt->fetchAll( PDO::FETCH_ASSOC ) as $row )
			$results[] =  $row;
		} while ($stmt->nextRowset());

		$select = $pdo->query('SELECT @returnString;');
		$returnString = $select->fetchAll( PDO::FETCH_ASSOC )[0]['@returnString'];
		$select->nextRowset();

		if ($returnString == 'SUCCESS') {
			return $results;
		} else {
			return response( $returnString, 500 );
		}
	}
}
