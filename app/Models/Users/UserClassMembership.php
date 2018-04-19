<?php
/******************************************************************************\
|                                                                              |
|                            UserClassMembership.php                           |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a model of a user's membership in a class.               |
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

use App\Models\TimeStamps\TimeStamped;

class UserClassMembership extends TimeStamped {

	// database attributes
	//
	protected $table = 'class_user';
	protected $primaryKey = 'class_user_uuid';
	public $incrementing = false;

	// mass assignment policy
	//
	protected $fillable = [
		'class_user_uuid',
		'user_uid', 
		'class_code',

		// timestamp attributes
		//
		'create_date',
		'update_date'
	];

	// array / json conversion whitelist
	//
	protected $visible = [
		'user_uid',	
		'class_code',

		// timestamp attributes
		//
		'create_date',
		'update_date'
	];
}
