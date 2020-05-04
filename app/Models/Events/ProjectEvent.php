<?php
/******************************************************************************\
|                                                                              |
|                               ProjectEvent.php                               |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a model of a project event.                              |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2020 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\Events;

use App\Models\BaseModel;
use App\Models\Events\Event;

class ProjectEvent extends Event
{
	/**
	 * The table associated with the model.
	 *
	 * @var string
	 */
	protected $table = 'project_events';

	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array
	 */
	protected $fillable = [
		'full_name', 
		'project_uid'
	];

	/**
	 * The attributes that should be visible in serialization.
	 *
	 * @var array
	 */
	protected $visible = [
		'full_name', 
		'project_uid',
		'event_date'
	];
}
