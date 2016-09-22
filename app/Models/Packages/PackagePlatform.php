<?php
/******************************************************************************\
|                                                                              |
|                              PackagePlatform.php                             |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a model of platform that a package supports.             |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2016 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\Packages;

use App\Models\BaseModel;

class PackagePlatform extends BaseModel {

	/**
	 * database attributes
	 */
	protected $connection = 'package_store';
	protected $table = 'package_platform';
	public $primaryKey = 'package_platform_id';
}
