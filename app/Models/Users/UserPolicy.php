<?php
/******************************************************************************\
|                                                                              |
|                                UserPolicy.php                                |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a model of record that a particular user has             |
|        agreed to a particular policy.                                        |
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

class UserPolicy extends CreateStamped {

	/**
	 * database attributes
	 */
	protected $table = 'user_policy';
	protected $primaryKey = 'policy_code';

	/**
	 * mass assignment policy
	 */
	protected $fillable = array(
		'user_policy_uid',
		'user_uid',
		'policy_code'
	);

	/**
	 * array / json conversion whitelist
	 */
	protected $visible = array(
		'user_policy_uid',
		'user_uid',
		'policy_code'
	);
}
