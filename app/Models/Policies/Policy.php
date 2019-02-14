<?php
/******************************************************************************\
|                                                                              |
|                                  Policy.php                                  |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a model of a policy.                                     |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2019 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\Policies;

use App\Models\BaseModel;

class Policy extends BaseModel {

	// database attributes
	//
	protected $connection = 'project';
	protected $table = 'policy';

	// array / json conversion whitelist
	//
	protected $visible = [
		'policy_code', 
		'description', 
		'policy'
	];
}
