<?php
/******************************************************************************\
|                                                                              |
|                                  Usage.php                                   |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a model of application usage over a time interval.       |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2019 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\Utilities;

use App\Models\BaseModel;
use App\Models\TimeStamps\CreateStamped;

class Usage extends CreateStamped {

	// database attributes
	//
	protected $connection = 'assessment';
	protected $table = 'usage_stats';
	protected $primaryKey = 'usage_stats_id';

	// mass assignment policy
	//
	protected $fillable = [
		'enabled_users', 
		'package_uploads', 
		'assessments', 
		'loc'
	];

	// array / json conversion whitelist
	//
	protected $visible = [
		'usage_banner_id',
		'enabled_users', 
		'package_uploads', 
		'assessments', 
		'loc',

		// timestamp attributes
		//
		'create_date'
	];
}
