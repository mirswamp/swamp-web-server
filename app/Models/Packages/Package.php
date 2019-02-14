<?php
/******************************************************************************\
|                                                                              |
|                                  Package.php                                 |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a model of type of package.                              |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2019 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\Packages;

use Illuminate\Support\Facades\Session;
use App\Models\TimeStamps\UserStamped;
use App\Models\Users\User;
use App\Models\Users\Owner;
use App\Models\Packages\PackageType;
use App\Models\Packages\PackageVersion;
use App\Models\Packages\PackagePlatform;
use App\Models\Platforms\Platform;
use App\Models\Platforms\PlatformVersion;
use App\Models\Assessments\SystemSetting;

class Package extends UserStamped {

	// attributes
	//
	private $maxVersions = 5;	// max size of version_strings

	// database attributes
	//
	protected $connection = 'package_store';
	protected $table = 'package';
	protected $primaryKey = 'package_uuid';
	public $incrementing = false;

	// mass assignment policy
	//
	protected $fillable = [
		'package_uuid',
		'name',
		'description',
		'external_url',
		'secret_token',
		'package_type_id',
		'package_language',
		'package_owner_uuid',
		'package_sharing_status'
	];

	// array / json conversion whitelist
	//
	protected $visible = [
		'package_uuid',
		'name',
		'description',
		'external_url',
		'secret_token',
		'package_type_id',
		'package_language',
		'package_sharing_status',
		'is_owned',
		'package_type',
		'num_versions',
		'version_strings',
		'platform_user_selectable'
	];

	// array / json appended model attributes
	//
	public $appends = [
		'is_owned',
		'external_url',
		'secret_token',
		'package_type',
		'num_versions',
		'version_strings',
		'platform_user_selectable'
	];

	// attribute types
	//
	protected $casts = [
		'is_owned' => 'boolean',
		'platform_user_selectable' => 'boolean'
	];

	//
	// accessor methods
	//

	public function getIsOwnedAttribute() {
		return session('user_uid') == $this->package_owner_uuid;
	}

	public function getExternalUrlAttribute() {

		// if external URL is empty string, then return null
		//
		return $this->getOriginal('external_url') != ''? $this->getOriginal('external_url') : null;
	}

	public function getSecretTokenAttribute() {
		$currentUser = User::getIndex(session('user_uid'));
		return $this->isOwnedBy($currentUser)? $this->getOriginal('secret_token') : null;
	}

	public function getPackageTypeAttribute() {
		return $this->getPackageType();
	}

	public function getNumVersionsAttribute() {
		return PackageVersion::where('package_uuid', '=', $this->package_uuid)->count();
	}

	public function getVersionStringsAttribute() {
		return $this->getVersionStrings($this->maxVersions);
	}

	public function getPlatformUserSelectableAttribute() {
		return $this->getPlatformUserSelectable();
	}

	//
	// querying methods
	//

	public function getPackageType() {

		// get package type name
		//
		if ($this->package_type_id != null) {
			$packageType = PackageType::where('package_type_id', '=', $this->package_type_id)->first();
			if ($packageType) {
				return $packageType->name;
			}
		}
	}

	public function getVersionStrings($limit = 5) {
		$versionStrings = [];
		$packageVersions = PackageVersion::where('package_uuid', '=', $this->package_uuid)->limit($limit)->get();
		for ($i = 0; $i < sizeOf($packageVersions); $i++) {
			$versionString = $packageVersions[$i]->version_string;
			if (!in_array($versionString, $versionStrings)) {
				array_push($versionStrings, $versionString);
			}
		}
		rsort($versionStrings);
		return $versionStrings;
	}

	public function getVersions() {
		return PackageVersion::where('package_uuid', '=', $this->package_uuid)->get();
	}

	public function getLatestVersion($projectUuid) {
		if (!$projectUuid) {

			// get package version associated with any project
			//
			$packageVersionQuery = PackageVersion::where('package_uuid', '=', $this->package_uuid);
		} else {
			if (!strpos($projectUuid, '+')) {

				// get by a single project
				//
				$packageVersionQuery = PackageVersion::where('package_uuid', '=', $this->package_uuid)
					->where(function($query0) use ($projectUuid) {
						$query0->whereRaw("upper(version_sharing_status)='PUBLIC'")
						->orWhere(function($query1) use ($projectUuid) {
							$query1->whereRaw("upper(version_sharing_status)='PROTECTED'")
							->whereExists(function($query2) use ($projectUuid) {
								$query2->select('package_version_uuid')->from('package_store.package_version_sharing')
								->whereRaw('package_store.package_version_sharing.package_version_uuid=package_version_uuid')
								->where('package_store.package_version_sharing.project_uuid', '=', $projectUuid);
							});
						});
					});
			} else {

				// get by multiple projects
				//
				$projectUuids = explode('+', $projectUuid);
				foreach ($projectUuids as $projectUuid) {
					if (!isset($assessmentRunsQuery)) {
						$packageVersionQuery = PackageVersion::where('package_uuid', '=', $this->package_uuid)
							->where(function($query0) use ($projectUuid) {
								$query0->whereRaw("upper(version_sharing_status)='PUBLIC'")
								->orWhere(function($query1) use ($projectUuid) {
									$query1->whereRaw("upper(version_sharing_status)='PROTECTED'")
									->whereExists(function($query2) use ($projectUuid) {
										$query2->select('package_version_uuid')->from('package_store.package_version_sharing')
										->whereRaw('package_store.package_version_sharing.package_version_uuid=package_version_uuid')
										->where('package_store.package_version_sharing.project_uuid', '=', $projectUuid);
									});
								});
							});
					} else {
						$packageVersionQuery = $packageVersionQuery->orWhere('package_uuid', '=', $this->package_uuid)
							->where(function($query0) use ($projectUuid) {
								$query0->whereRaw("upper(version_sharing_status)='PUBLIC'")
								->orWhere(function($query1) use ($projectUuid) {
									$query1->whereRaw("upper(version_sharing_status)='PROTECTED'")
									->whereExists(function($query2) use ($projectUuid) {
										$query2->select('package_version_uuid')->from('package_store.package_version_sharing')
										->whereRaw('package_store.package_version_sharing.package_version_uuid=package_version_uuid')
										->where('package_store.package_version_sharing.project_uuid', '=', $projectUuid);
									});
								});
							});
					}
				}
			}
		}

		// perform query
		//
		return $packageVersionQuery->orderBy('version_no', 'DESC')->first();
	}

	public function getPlatformUserSelectable() {
		
		// get platform user selectable from package type
		//
		if ($this->package_type_id != null) {
			$packageType = PackageType::where('package_type_id', '=', $this->package_type_id)->first();
			if ($packageType) {
				return $packageType->platform_user_selectable;
			}
		}
	}

	public function getDefaultPlatform(&$platformVersion) {
		$platform = NULL;
		$platformVersion = NULL;

		// select platform based upon package type
		//
		if ($this->package_type_id) {

			// look up package type
			//
			$packageType = PackageType::where('package_type_id', '=', $this->package_type_id)->first();

			if ($packageType && $packageType->platform_user_selectable) {

				// look up default platform for package type
				//
				$platform = Platform::where('platform_uuid', '=', $packageType->default_platform_uuid)->first();

				// look up default platform version for package type
				//
				$platformVersion = PlatformVersion::where('platform_uuid', '=', $packageType->default_platform_version_uuid)->first();
			}
		}

		return $platform;
	}

	public function getDefaultPlatformBySystemSetting(&$platformVersion) {
		$platform = NULL;
		$platformVersion = NULL;

		// select platform based upon package type
		//
		if ($this->package_type_id) { 

			// look up default platform system setting
			//
			$systemSetting = SystemSetting::where('system_setting_code', '=' ,'DEFAULT_PLATFORM_FOR_PKG_TYPE')->where('system_setting_value', '=', $this->package_type_id)->first();

			// find platform from system setting
			//
			if ($systemSetting) {
				$platform = Platform::where('platform_uuid', '=', $systemSetting->system_setting_value2)->first();
			}
		}

		return $platform;
	}

	public function getDefaultPlatformByName(&$platformVersion) {
		$platform = NULL;
		$platformName = NULL;
		$platformVersion = NULL;

		// select platform version based upon package type
		//
		if ($this->package_type) { 

			// Android packages
			//
			if (strpos($this->package_type, 'Android') !== false) {
				$platformName = 'Android';
				$versionString = NULL;

			// Java packages
			//
			} else if (strpos($this->package_type, 'Java') !== false) {
				$platformName = 'Red Hat Enterprise Linux 64-bit';
				$versionString = NULL;

			// Python packages
			//
			} else if (strpos($this->package_type, 'Python') !== false) {
				$platformName = 'Scientific Linux 64-bit';
				$versionString = NULL;

			// Ruby packages
			//
			} else if (strpos($this->package_type, 'Ruby') !== false) {
				$platformName = 'Scientific Linux 64-bit';
				$versionString = NULL;
			}
		}

		if ($platformName) {

			// find desired platform version
			//
			$platform = Platform::where('name', '=', $platformName)->first();
			if ($platform && $versionString) {

				// find desired platform version
				//
				$platformVersion = PlatformVersion::where('platform_uuid', '=', $platform->platform_uuid)->
					where('version_string', '=', $versionString)->first();
			}
		}

		return $platform;
	}

	//
	// sharing methods
	//

	public function isPublic() {
		return $this->getSharingStatus() == 'public';
	}

	public function isProtected() {
		return $this->getSharingStatus() == 'protected';
	}

	public function isPrivate() {
		return $this->getSharingStatus() == 'private';
	}

	public function getSharingStatus() {
		return strtolower($this->package_sharing_status);
	}

	public function isSharedBy($user) {
		$versions = $this->getVersions();
		foreach ($versions as $version) {
			if ($version->isSharedBy($user)) {
				return true;
			}
		}
		return false;
	}

	public function getProjectNames($user = null) {

		// assume current user if not specified
		//
		if (!$user) {
			$user = User::getIndex(session('user_uid'));
		}

		$versions = $this->getVersions();
		$names = [];
		foreach ($versions as $version) {
			$projects = $version->getProjects($user);
			foreach ($projects as $project) {
				$name = $project->full_name;
				if (!in_array($name, $names)) {
					array_push($names, $name);
				}
			}
		}
		return $names;
	}

	public function getProjectUuids($user = null) {

		// assume current user if not specified
		//
		if (!$user) {
			$user = User::getIndex(session('user_uid'));
		}

		$versions = $this->getVersions($user);
		$uuids = [];
		foreach ($versions as $version) {
			$projects = $version->getProjects($user);
			foreach ($projects as $project) {
				$uuid = $project->project_uid;
				if (!in_array($uuid, $uuids)) {
					array_push($uuids, $uuid);
				}
			}
		}
		return $uuids;
	}

	public function getProjects($user = null) {

		// assume current user if not specified
		//
		if (!$user) {
			$user = User::getIndex(session('user_uid'));
		}

		$versions = $this->getVersions();
		$uuids = [];
		$projects = [];
		foreach ($versions as $version) {
			$versionProjects = $version->getProjects($user);
			foreach ($versionProjects as $project) {
				if (!in_array($project->project_uid, $uuids)) {
					array_push($projects, $project);
					array_push($uuids, $project->project_uid);
				}
			}
		}
		return $projects;
	}

	//
	// compatibility methods
	//

	public function getPlatformCompatibility($platform) {
		$compatibility = PackagePlatform::where('package_uuid', '=', $this->package_uuid)->
			whereNull('package_version_uuid')->
			where('platform_uuid', '=', $platform->platform_uuid)->
			whereNull('platform_version_uuid')->first();
		if ($compatibility) {
			return $compatibility->compatible_flag;
		}
	}

	public function getPlatformVersionCompatibility($platformVersion) {
		$compatibility = PackagePlatform::where('package_uuid', '=', $this->package_uuid)->
			whereNull('package_version_uuid')->
			where('platform_version_uuid', '=', $platformVersion->platform_version_uuid)->first();
		if ($compatibility) {
			return $compatibility->compatible_flag;
		}
	}

	//
	// access control methods
	//

	public function isOwnedBy($user) {
		return ($this->package_owner_uuid == $user->user_uid);
	}

	public function isReadableBy($user) {
		if ($user->isAdmin()) {
			return true;
		} else if ($this->isPublic()) {
			return true;
		} else if ($this->isOwnedBy($user)) {
			return true;
		} else if ($this->isSharedBy($user)) {
			return true;
		} else {
			return false;
		}
	}

	public function isWriteableBy($user) {
		if ($user->isAdmin()) {
			return true;
		} else if ($this->isOwnedBy($user)) {
			return true;
		} else {
			return false;
		}
	}
}
