<?php
/******************************************************************************\
|                                                                              |
|                                 UserClass.php                                |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a model of a class that a user may belong to.            |
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

use App\Models\TimeStamps\TimeStamped;

class UserClass extends TimeStamped {

	// database attributes
	//
	protected $table = 'class';
	protected $primaryKey = 'class_code';
	public $incrementing = false;

	// mass assignment policy
	//
	protected $fillable = [
		'class_code',
		'description', 
		'start_date', 
		'end_date', 
		'commercial_tool_access', 

		// timestamp attributes
		//
		'create_date',
		'update_date'
	];

	// array / json conversion whitelist
	//
	protected $visible = [
		'class_code',
		'description', 
		'start_date', 
		'end_date', 
		'commercial_tool_access', 

		// timestamp attributes
		//
		'create_date',
		'update_date'
	];
}
