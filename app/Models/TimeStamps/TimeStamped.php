<?php
/******************************************************************************\
|                                                                              |
|                                TimeStamped.php                               |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a model of a base class with create and update           |
|        time stamps.                                                          |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2018 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\TimeStamps;

use App\Models\TimeStamps\CreateStamped;

class TimeStamped extends CreateStamped {

	// use non-standard timestamp field names
	//
	const CREATED_AT = 'create_date';
	const UPDATED_AT = 'update_date';
	const DELETED_AT = 'delete_date';

	protected $visible = [

		// timestamp attributes
		//
		'create_date',
		'update_date',
		'delete_date'
	];
}
