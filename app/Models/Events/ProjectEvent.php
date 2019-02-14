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
|        Copyright (C) 2012-2019 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\Events;

use App\Models\BaseModel;
use App\Models\Events\Event;

class ProjectEvent extends Event {

	// database attributes
	//
	protected $table = 'project_events';

	// mass assignment policy
	//
	protected $fillable = [
		'full_name', 
		'project_uid'
	];

	// array / json conversion whitelist
	//
	protected $visible = [
		'full_name', 
		'project_uid'
	];
}
