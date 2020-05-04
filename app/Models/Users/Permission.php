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
|        Copyright (C) 2012-2020 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\Users;

use App\Models\TimeStamps\CreateStamped;
use App\Models\Users\Policy;

class Permission extends CreateStamped
{
	/**
	 * The table associated with the model.
	 *
	 * @var string
	 */
	protected $table = 'permission';

	/**
	 * The primary key associated with the table.
	 *
	 * @var string
	 */
	protected $primaryKey = 'permission_code';
	
	/**
	 * Indicates if the IDs are auto-incrementing.
	 *
	 * @var bool
	 */
	public $incrementing = false;

	/**
	 * The "type" of the auto-incrementing ID.
	 *
	 * @var string
	 */
	protected $keyType = 'string';

	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array
	 */
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

	/**
	 * The attributes that should be visible in serialization.
	 *
	 * @var array
	 */
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

	/**
	 * The accessors to append to the model's array form.
	 *
	 * @var array
	 */
	protected $appends = [
		'policy'
	];

	/**
	 * The attributes that should be cast to native types.
	 *
	 * @var array
	 */
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

	public function isAdminOnly(): bool {
		return $this->admin_only_flag;
	}

	public function isAutoApprove(): bool {
		return $this->auto_approve_flag;
	}
}
