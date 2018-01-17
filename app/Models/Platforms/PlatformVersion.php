<?php
/******************************************************************************\
|                                                                              |
|                             PlatformVersion.php                              |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a model of a particular version of a platform.           |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2018 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\Platforms;

use App\Models\TimeStamps\UserStamped;
use App\Models\Platforms\Platform;


class PlatformVersion extends UserStamped {

	// database attributes
	//
	protected $connection = 'platform_store';
	protected $table = 'platform_version';
	protected $primaryKey = 'platform_version_uuid';
	public $incrementing = false;

	// mass assignment policy
	//
	protected $fillable = [
		'platform_version_uuid',
		'platform_uuid',
		'version_string',
		
		'release_date',
		'retire_date',
		'notes',

		'platform_path',
		'checksum',
		'invocation_cmd',
		'deployment_cmd'
	];

	// array / json conversion whitelist
	//
	protected $visible = [
		'platform_version_uuid',
		'platform_uuid',
		'version_string',
		
		'release_date',
		'retire_date',
		'notes',

		'platform_path',
		'checksum',
		'invocation_cmd',
		'deployment_cmd',

		'full_name'
	];
	
	protected $appends = [
		'full_name'
	];

	//
	// accessor methods
	//

	public function getFullNameAttribute(){
		return $this->getPlatform()->name . ' ' . $this->version_string;
	}

	//
	// querying methods
	//

	public function getPlatform() {
		return Platform::where('platform_uuid', '=', $this->platform_uuid)->first();
	}
}
