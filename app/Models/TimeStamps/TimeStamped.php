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
|        Copyright (C) 2012-2020 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\TimeStamps;

use App\Models\TimeStamps\CreateStamped;

class TimeStamped extends CreateStamped
{
	// attributes
	//
	public $timestamps = true;

	// use non-standard timestamp field names
	//
	const CREATED_AT = 'create_date';
	const UPDATED_AT = 'update_date';
	const DELETED_AT = 'delete_date';

	/**
	 * The attributes that should be visible in serialization.
	 *
	 * @var array
	 */
	protected $visible = [

		// timestamp attributes
		//
		'create_date',
		'update_date',
		'delete_date'
	];

	/**
	 * The attributes that should be cast to native types.
	 *
	 * @var array
	 */
	protected $casts = [
		'create_date' => 'datetime',
		'update_date' => 'datetime',
		'delete_date' => 'datetime'
	];
}
