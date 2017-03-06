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
|        Copyright (C) 2012-2017 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\Tools;

use App\Models\BaseModel;
use App\Models\Packages\PackageType;

class ToolLanguage extends BaseModel {

	/**
	 * database attributes
	 */
	protected $connection = 'tool_shed';
	protected $table = 'tool_language';
	protected $primaryKey = 'tool_language_id';

	/**
	 * mass assignment policy
	 */
	protected $fillable = array(
		'tool_uuid',
		'tool_version_uuid',
		'package_type_id'
	);

	/**
	 * array / json conversion whitelist
	 */
	protected $visible = array(
		'tool_uuid',
		'tool_version_uuid',
		'package_type_name'
	);

	/**
	 * array / json appended model attributes
	 */
	protected $appends = array(
		'package_type_name'
	);

	/**
	 * accessor methods
	 */

	public function getPackageTypeNameAttribute() {
		$packageType = PackageType::where('package_type_id', '=', $this->package_type_id)->first();
		if ($packageType) {
			return $packageType->name;
		}
	}
}
