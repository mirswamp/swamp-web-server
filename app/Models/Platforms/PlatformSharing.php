<?php
/******************************************************************************\
|                                                                              |
|                              PlatformSharing.php                             |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a model that associates a platform with a project.       |
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

use App\Models\BaseModel;

class PlatformSharing extends BaseModel
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
	protected $table = 'platform_sharing';

	/**
	 * The primary key associated with the table.
	 *
	 * @var string
	 */
	protected $primaryKey = 'platform_sharing_id';

	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array
	 */
	protected $fillable = [
		'platform_uuid',
		'project_uuid'
	];

	//
	// relation methods
	//
	
	public function platform() {
		return $this->belongsTo('Models\Platforms\Platform', 'platform_uuid');
	}
}