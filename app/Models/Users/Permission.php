<?php
/******************************************************************************\
|                                                                              |
|                               Permission.php                                 |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a model of permission request record.                    |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2017 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\Users;

use Illuminate\Support\Facades\Config;
use App\Models\TimeStamps\CreateStamped;
use App\Models\Users\Policy;

class Permission extends CreateStamped {

	/**
	 * database attributes
	 */
	protected $table = 'permission';
	protected $primaryKey = 'permission_code';

	/**
	 * mass assignment policy
	 */
	protected $fillable = array(
		'permission_code',
		'policy_code',
		'description'
	);

	/**
	 * array / json conversion whitelist
	 */
	protected $visible = array(
		'permission_code',
		'policy_code',
		'description',
		'create_date'
	);

	/**
	 * array / json appended model attributes
	 */
	protected $appends = array(
		'policy'
	);

	public function getPolicyAttribute(){
		$policy = Policy::where('policy_code','=',$this->policy_code)->first();
		return $policy ? $policy->policy : '';
	}
}
