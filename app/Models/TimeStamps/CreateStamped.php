<?php
/******************************************************************************\
|                                                                              |
|                               CreateStamped.php                              |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a model of a base class with create time stamps.         |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2019 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\TimeStamps;

use App\Models\BaseModel;

class CreateStamped extends BaseModel {

	// use non-standard timestamp field names
	//
	const CREATED_AT = 'create_date';
	
	// array / json conversion whitelist
	//
	protected $visible = [

		// timestamp attributes
		//
		'create_date'
	];

	// attribute types
	//
	protected $casts = [
		'create_date' => 'datetime'
	];
}
