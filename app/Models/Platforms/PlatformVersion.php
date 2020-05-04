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
|        Copyright (C) 2012-2020 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\Platforms;

use App\Models\TimeStamps\TimeStamped;
use App\Models\Platforms\Platform;

class PlatformVersion extends TimeStamped
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
	protected $table = 'platform_version';

	/**
	 * The primary key associated with the table.
	 *
	 * @var string
	 */
	protected $primaryKey = 'platform_version_uuid';

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

	/**
	 * The attributes that should be visible in serialization.
	 *
	 * @var array
	 */
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
	
	/**
	 * The accessors to append to the model's array form.
	 *
	 * @var array
	 */
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
