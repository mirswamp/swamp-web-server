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
|        Copyright (C) 2012-2020 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\Users;

use App\Models\TimeStamps\TimeStamped;

class UserClass extends TimeStamped
{
	/**
	 * The table associated with the model.
	 *
	 * @var string
	 */
	protected $table = 'class';

	/**
	 * The primary key associated with the table.
	 *
	 * @var string
	 */
	protected $primaryKey = 'class_code';

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

	/**
	 * The attributes that should be visible in serialization.
	 *
	 * @var array
	 */
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
