<?php
/******************************************************************************\
|                                                                              |
|                               ToolLanguage.php                               |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a model of a language supported by a tool.               |
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
use App\Models\Packages\PackageType;

class ToolLanguage extends BaseModel
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
	protected $table = 'tool_language';

	/**
	 * The primary key associated with the table.
	 *
	 * @var string
	 */
	protected $primaryKey = 'tool_language_id';

	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array
	 */
	protected $fillable = [
		'tool_uuid',
		'tool_version_uuid',
		'package_type_id'
	];

	/**
	 * The attributes that should be visible in serialization.
	 *
	 * @var array
	 */
	protected $visible = [
		'tool_uuid',
		'tool_version_uuid',
		'package_type_name'
	];

	/**
	 * The accessors to append to the model's array form.
	 *
	 * @var array
	 */
	protected $appends = [
		'package_type_name'
	];

	//
	// accessor methods
	//

	public function getPackageTypeNameAttribute() {
		$packageType = PackageType::where('package_type_id', '=', $this->package_type_id)->first();
		if ($packageType) {
			return $packageType->name;
		}
	}
}
