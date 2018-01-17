<?php
/******************************************************************************\
|                                                                              |
|                                UserEvent.php                                 |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a model of an event associated with a user.              |
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
use App\Models\Users\User;

class UserEvent extends CreateStamped {

	// database attributes
	//
	protected $table = 'user_event';
	protected $primaryKey = 'user_event_id';

	// mass assignment policy
	//
	protected $fillable = [
		'user_uid',
		'event_type', 
		'value',

		// timestamp attributes
		//
		'create_date',
		'update_date',

		// userstamp attributes
		//
		'create_user',
		'update_user'
	];

	// array / json conversion whitelist
	//
	protected $visible = [
		'user_event_id',
		'user_uid',
		'value',

		// timestamp attributes
		//
		'create_date'
	];
}
