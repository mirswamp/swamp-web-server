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
|        Copyright (C) 2012-2019 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\Platforms;

use App\Models\BaseModel;

class PlatformSharing extends BaseModel {

	// database attributes
	//
	protected $connection = 'platform_store';
	protected $table = 'platform_sharing';
	protected $primaryKey = 'platform_sharing_id';

	// mass assignment policy
	//
	protected $fillable = [
		'platform_uuid',
		'platform_uuid'
	];

	//
	// relation methods
	//
	
	public function platform() {
		return $this->belongsTo('Models\Platforms\Platform', 'platform_uuid');
	}
}