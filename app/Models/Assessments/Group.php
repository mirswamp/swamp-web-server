<?php
/******************************************************************************\
|                                                                              |
|                                  Group.php                                   |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a model of a generic group.                              |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2020 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\Assessments;

use App\Models\TimeStamps\CreateStamped;

class Group extends CreateStamped
{
	/**
	 * The database connection to use.
	 *
	 * @var string
	 */
	protected $connection = 'assessment';

	/**
	 * The table associated with the model.
	 *
	 * @var string
	 */
	protected $table = 'group_list';

	/**
	 * The primary key associated with the table.
	 *
	 * @var string
	 */
	protected $primaryKey = 'group_uuid';

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
		'group_uuid',
		'name',
		'group_type',
		'uuid_list'
	];

	/**
	 * The attributes that should be visible in serialization.
	 *
	 * @var array
	 */
	protected $visible = [
		'group_uuid',
		'name',
		'group_type',
		'uuid_list'
	];
}
