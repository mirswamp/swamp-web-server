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
|        Copyright (C) 2012-2020 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\Utilities;

use App\Models\BaseModel;
use App\Models\TimeStamps\CreateStamped;

class Usage extends CreateStamped
{
	/**
	 * The database connection to use.
	 *
	 * @var string
	 */
	protected $connection = 'assessment';

	/**
	 * The table associated with the model.
	 *
	 * @var string
	 */
	protected $table = 'usage_stats';

	/**
	 * The primary key associated with the table.
	 *
	 * @var string
	 */
	protected $primaryKey = 'usage_stats_id';

	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array
	 */
	protected $fillable = [
		'enabled_users', 
		'package_uploads', 
		'assessments', 
		'loc'
	];

	/**
	 * The attributes that should be visible in serialization.
	 *
	 * @var array
	 */
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
