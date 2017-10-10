<?php
/******************************************************************************\
|                                                                              |
|                                UserStamped.php                               |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a model of a base class with create and update           |
|        time stamps and also the create and update users.                     |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2017 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\TimeStamps;

use App\Models\TimeStamps\TimeStamped;

class UserStamped extends TimeStamped {
	
	/**
	 * mass assignment policy
	 */
	protected $fillable = array(

		// user stamping
		//
		'create_user',
		'update_user'
	);

	/**
	 * array / json conversion whitelist
	 */
	protected $visible = array(
		'create_user',
		'update_user'
	);
}
