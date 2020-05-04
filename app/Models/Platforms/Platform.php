<?php
/******************************************************************************\
|                                                                              |
|                                 Platform.php                                 |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a model of platform.                                     |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2020 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\Platforms;

use App\Models\TimeStamps\TimeStamped;
use App\Models\Users\User;
use App\Models\Projects\Project;
use App\Models\Platforms\PlatformVersion;
use App\Models\Platforms\PlatformSharing;

class Platform extends TimeStamped
{
	/**
	 * The database connection to use.
	 *
	 * @var string
	 */
	protected $connection = 'platform_store';

	/**
	 * The table associated with the model.
	 *
	 * @var string
	 */
	protected $table = 'platform';

	/**
	 * The primary key associated with the table.
	 *
	 * @var string
	 */
	protected $primaryKey = 'platform_uuid';

	/**
	 * Indicates if the IDs are auto-incrementing.
	 *
	 * @var bool
	 */
	public $incrementing = false;

	/**
	 * The "type" of the auto-incrementing ID.
	 *
	 * @var string
	 */
	protected $keyType = 'string';

	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array
	 */
	protected $fillable = [
		'platform_uuid',
		'platform_owner_uuid',
		'name',
		'description',
		'version_strings',
		'platform_sharing_status'
	];

	/**
	 * The attributes that should be visible in serialization.
	 *
	 * @var array
	 */
	protected $visible = [
		'platform_uuid',
		'name',
		'description',
		'version_strings',
		'platform_sharing_status'
	];

	/**
	 * The accessors to append to the model's array form.
	 *
	 * @var array
	 */
	protected $appends = [
		'version_strings'
	];

	//
	// accessor methods
	//

	public function getVersionStringsAttribute() {
		$versionStrings = [];
		$platformVersions = PlatformVersion::where('platform_uuid', '=', $this->platform_uuid)->get();
		for ($i = 0; $i < sizeOf($platformVersions); $i++) {
			$versionString = $platformVersions[$i]->version_string;
			if (!in_array($versionString, $versionStrings)) {
				array_push($versionStrings, $versionString);
			}
		}
		rsort($versionStrings);
		return $versionStrings;
	}

	//
	// querying methods
	//

	public function getVersions() {
		return PlatformVersion::where('platform_uuid', '=', $this->platform_uuid)->get();
	}

	public function getLatestVersion() {
		return PlatformVersion::where('platform_uuid', '=', $this->platform_uuid)->
			orderBy('version_no', 'DESC')->first();
	}

	public function isOwnedBy(User $user) {
		return ($this->platform_owner_uuid == $user->user_uid);
	}

	//
	// sharing querying methods
	//

	public function isSharedWith(Project $project): bool {
		return PlatformSharing::where('platform_uuid', '=', $this->platform_uuid)
			->where('project_uuid', '=', $project->project_uid)->count() > 0;
	}

	public function getSharedWith(Project $project): PlatformSharing {
		return PlatformSharing::where('platform_uuid', '=', $this->package_version_uuid)
			->where('project_uuid', '=', $project->project_uid)->first();
	}

	//
	// sharing methods
	//

	public function unshare() {
		PlatformSharing::where('platform_uuid', '=', $this->platform_uuid)->delete();
	}

	public function shareWith($project): PlatformSharing {
		if (!$this->isSharedWith($project)) {
			$sharing = new PlatformSharing([
				'platform_uuid' => $this->platform_uuid,
				'project_uuid' => $project->project_uid
			]);
			$sharing->save();
			return $sharing;
		} else {
			return $this->getSharedWith($project);
		}
	}
}