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
|        Copyright (C) 2012-2019 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\Platforms;

use App\Models\TimeStamps\UserStamped;
use App\Models\Platforms\PlatformVersion;

class Platform extends UserStamped
{
	// database attributes
	//
	protected $connection = 'platform_store';
	protected $table = 'platform';
	protected $primaryKey = 'platform_uuid';
	public $incrementing = false;

	// mass assignment policy
	//
	protected $fillable = [
		'platform_uuid',
		'platform_owner_uuid',
		'name',
		'description',
		'version_strings',
		'platform_sharing_status'
	];

	// array / json conversion whitelist
	//
	protected $visible = [
		'platform_uuid',
		'name',
		'description',
		'version_strings',
		'platform_sharing_status'
	];

	// array / json appended model attributes
	//
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

	public function isOwnedBy($user) {
		return ($this->platform_owner_uuid == $user->user_uid);
	}
}