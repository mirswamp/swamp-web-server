<?php
/******************************************************************************\
|                                                                              |
|                                 Policy.php                                   |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a model of policy (usually applied to tools).            |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2018 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\Users;

use Illuminate\Support\Facades\Config;
use App\Models\TimeStamps\CreateStamped;

class Policy extends CreateStamped {

	// database attributes
	//
	protected $table = 'policy';
	protected $primaryKey = 'policy_code';
	public $incrementing = false;

	// mass assignment policy
	//
	protected $fillable = [
		'policy_code',
		'policy',
		'description'
	];

	// array / json conversion whitelist
	//
	protected $visible = [
		'policy_code',
		'policy',
		'description',

		// timestamp attributes
		//
		'create_date'
	];
}
