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
|        Copyright (C) 2012-2019 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\Users;

use Illuminate\Support\Facades\Config;
use App\Models\TimeStamps\CreateStamped;
use App\Models\Users\Policy;

class Permission extends CreateStamped {

	// database attributes
	//
	protected $table = 'permission';
	protected $primaryKey = 'permission_code';
	public $incrementing = false;

	// mass assignment policy
	//
	protected $fillable = [
		'permission_code',
		'title',
		'description',
		'admin_only_flag',
		'auto_approve_flag',
		'policy_code',
		'description',
		'user_info',
		'user_info_policy_text'
	];

	// array / json conversion whitelist
	//
	protected $visible = [
		'permission_code',
		'title',
		'description',
		'admin_only_flag',
		'auto_approve_flag',
		'policy_code',
		'description',
		'user_info',
		'user_info_policy_text',

		// timestamp attributes
		//
		'create_date'
	];

	// array / json appended model attributes
	//
	protected $appends = [
		'policy'
	];

	// attribute types
	//
	protected $casts = [
		'admin_only_flag' => 'boolean',
		'auto_approve_flag' => 'boolean'
	];

	//
	// accessor methods
	//

	public function getPolicyAttribute() {
		$policy = Policy::where('policy_code','=',$this->policy_code)->first();
		return $policy ? $policy->policy : '';
	}

	//
	// querying methods
	//

	public function isAdminOnly() {
		return $this->admin_only_flag;
	}

	public function isAutoApprove() {
		return $this->auto_approve_flag;
	}
}
