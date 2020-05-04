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
|        Copyright (C) 2012-2020 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\Users;

use App\Models\TimeStamps\TimeStamped;

class UserClassMembership extends TimeStamped
{
	/**
	 * The table associated with the model.
	 *
	 * @var string
	 */
	protected $table = 'class_user';

	/**
	 * The primary key associated with the table.
	 *
	 * @var string
	 */
	protected $primaryKey = 'class_user_uuid';

	/**
	 * Indicates if the IDs are auto-incrementing.
	 *
	 * @var bool
	 */
	public $incrementing = false;

	/**
	 * The "type" of the auto-incrementing ID.
	 *
	 * @var string
	 */
	protected $keyType = 'string';

	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array
	 */
	protected $fillable = [
		'class_user_uuid',
		'user_uid', 
		'class_code',

		// timestamp attributes
		//
		'create_date',
		'update_date'
	];

	/**
	 * The attributes that should be visible in serialization.
	 *
	 * @var array
	 */
	protected $visible = [
		'user_uid',	
		'class_code',

		// timestamp attributes
		//
		'create_date',
		'update_date'
	];
}
