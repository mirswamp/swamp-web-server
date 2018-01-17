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
|        Copyright (C) 2012-2018 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\Assessments;

use App\Models\TimeStamps\CreateStamped;

class Group extends CreateStamped {

	// database attributes
	//
	protected $connection = 'assessment';
	protected $table = 'group_list';
	protected $primaryKey = 'group_uuid';

	// mass assignment policy
	//
	protected $fillable = [
		'group_uuid',
		'name',
		'group_type',
		'uuid_list'
	];

	// array / json conversion whitelist
	//
	protected $visible = [
		'group_uuid',
		'name',
		'group_type',
		'uuid_list'
	];
}
