<?php
/******************************************************************************\
|                                                                              |
|                           AssessmentRunRequest.php                           |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a model of an assessment run request.                    |
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

use App\Models\TimeStamps\TimeStamped;

class AssessmentRunRequest extends TimeStamped
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
	protected $table = 'assessment_run_request';

	/**
	 * The primary key associated with the table.
	 *
	 * @var string
	 */
	protected $primaryKey = 'assessment_run_request_id';

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
		'assessment_run_id',
		'run_request_id',
		'user_uuid',
		'notify_when_complete_flag'
	];

	/**
	 * The attributes that should be visible in serialization.
	 *
	 * @var array
	 */
	protected $visible = [
		'assessment_run_id',
		'run_request_id',
		'user_uuid',
		'notify_when_complete_flag'
	];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
	protected $casts = [
		'notify_when_complete_flag' => 'boolean'
	];
}
