<?php
/******************************************************************************\
|                                                                              |
|                              SystemSetting.php                               |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a model of a system setting.                             |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2016 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\Assessments;

use App\Models\TimeStamps\UserStamped;

class SystemSetting extends UserStamped {

	/**
	 * database attributes
	 */
	protected $connection = 'assessment';
	protected $table = 'system_setting';
	public $primaryKey = 'system_setting_id';

	/**
	 * mass assignment policy
	 */
	protected $fillable = array(
		'system_setting_code',
		'system_setting_value',
		'system_setting_value2'
	);

	/**
	 * array / json conversion whitelist
	 */
	protected $visible = array(
		'system_setting_code',
		'system_setting_value',
		'system_setting_value2'
	);
}
