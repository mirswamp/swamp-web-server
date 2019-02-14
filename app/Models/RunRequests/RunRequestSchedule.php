<?php
/******************************************************************************\
|                                                                              |
|                            RunRequestSchedule.php                            |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a model of schedule that determines when to perform      |
|        an assessment run.                                                    |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2019 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\RunRequests;

use App\Models\TimeStamps\UserStamped;

class RunRequestSchedule extends UserStamped {

	// database attributes
	//
	protected $connection = 'assessment';
	protected $table = 'run_request_schedule';
	protected $primaryKey = 'run_request_schedule_id';

	// mass assignment policy
	//
	protected $fillable = [
		'run_request_schedule_uuid',
		'run_request_uuid',
		'recurrence_type',
		'recurrence_day',
		'time_of_day'
	];

	// array / json conversion whitelist
	//
	protected $visible = [
		'run_request_schedule_uuid',
		'run_request_uuid',
		'recurrence_type',
		'recurrence_day',
		'time_of_day'
	];
}