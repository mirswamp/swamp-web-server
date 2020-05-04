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
|        Copyright (C) 2012-2020 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\Tools;

use App\Models\BaseModel;
use App\Models\Platforms\Platform;

class ToolPlatform extends BaseModel
{
	/**
	 * The database connection to use.
	 *
	 * @var string
	 */
	protected $connection = 'tool_shed';

	/**
	 * The table associated with the model.
	 *
	 * @var string
	 */
	protected $table = 'tool_platform';

	/**
	 * The primary key associated with the table.
	 *
	 * @var string
	 */
	protected $primaryKey = 'tool_platform_id';

	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array
	 */
	protected $fillable = [
		'tool_uuid',
		'platform_uuid',
		'platform'
	];

	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array
	 */
	protected $visible = [
		'tool_uuid',
		'platform_uuid',
		'platform'
	];

	/**
	 * The accessors to append to the model's array form.
	 *
	 * @var array
	 */
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
