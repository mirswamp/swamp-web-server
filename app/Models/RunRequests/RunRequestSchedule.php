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
|        Copyright (C) 2012-2020 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\RunRequests;

use App\Models\TimeStamps\TimeStamped;

class RunRequestSchedule extends TimeStamped
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
	protected $table = 'run_request_schedule';

	/**
	 * The primary key associated with the table.
	 *
	 * @var string
	 */
	protected $primaryKey = 'run_request_schedule_id';

	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array
	 */
	protected $fillable = [
		'run_request_schedule_uuid',
		'run_request_uuid',
		'recurrence_type',
		'recurrence_day',
		'time_of_day'
	];

	/**
	 * The attributes that should be visible in serialization.
	 *
	 * @var array
	 */
	protected $visible = [
		'run_request_schedule_uuid',
		'run_request_uuid',
		'recurrence_type',
		'recurrence_day',
		'time_of_day'
	];
}