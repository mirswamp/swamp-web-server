<?php
/******************************************************************************\
|                                                                              |
|                               ToolPlatform.php                               |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a model of a platform supported by a tool.               |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2018 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\Tools;

use App\Models\BaseModel;
use App\Models\Platforms\Platform;

class ToolPlatform extends BaseModel {

	// database attributes
	//
	protected $connection = 'tool_shed';
	protected $table = 'tool_platform';
	protected $primaryKey = 'tool_platform_id';

	// mass assignment policy
	//
	protected $fillable = [
		'tool_uuid',
		'platform_uuid',
		'platform'
	];

	// array / json conversion whitelist
	//
	protected $visible = [
		'tool_uuid',
		'platform_uuid',
		'platform'
	];

	// array / json appended model attributes
	//
	protected $appends = [
		'platform'
	];

	//
	// accessor methods
	//

	public function getPlatformAttribute() {
		return Platform::where('platform_uuid', '=', $this->platform_uuid)->first();
	}
}
