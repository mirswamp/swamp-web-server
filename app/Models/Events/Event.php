<?php
/******************************************************************************\
|                                                                              |
|                                  Event.php                                   |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines an abstract base class of events.                        |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2016 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\Events;

use App\Models\BaseModel;

abstract class Event extends BaseModel {

	/**
	 * mass assignment policy
	 */
	protected $fillable = array(
		'event_type', 
		'event_date'
	);

	/**
	 * array / json conversion whitelist
	 */
	protected $visible = array(
		'event_type', 
		'event_date'
	);
}
