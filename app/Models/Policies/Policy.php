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
|        Copyright (C) 2012-2020 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\Policies;

use App\Models\BaseModel;

class Policy extends BaseModel
{
	/**
	 * The database connection to use.
	 *
	 * @var string
	 */
	protected $connection = 'project';

	/**
	 * The table associated with the model.
	 *
	 * @var string
	 */
	protected $table = 'policy';

	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array
	 */
	protected $visible = [
		'policy_code', 
		'description', 
		'policy'
	];
}