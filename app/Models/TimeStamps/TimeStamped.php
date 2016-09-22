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
|        Copyright (C) 2012-2016 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\TimeStamps;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Input;
use App\Models\BaseModel;

class TimeStamped extends BaseModel {

	// attributes
	//
	public $timestamps = true;

	// use non-standard timestamp field names
	//
	const CREATED_AT = 'create_date';
	const UPDATED_AT = 'update_date';

	protected $visible = array(

		// timestamp attributes
		//
		'create_date',
		'update_date'
	);
}
