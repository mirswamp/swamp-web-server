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
|        Copyright (C) 2012-2016 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\Assessments;

use App\Models\TimeStamps\UserStamped;

class AssessmentRunRequest extends UserStamped {

	/**
	 * database attributes
	 */
	protected $connection = 'assessment';
	protected $table = 'assessment_run_request';
	protected $primaryKey = 'assessment_run_request_id';

	/**
	 * mass assignment policy
	 */
	protected $fillable = array(
		'assessment_run_id',
		'run_request_id',
		'user_uuid',
		'notify_when_complete_flag'
	);

	/**
	 * array / json conversion whitelist
	 */
	protected $visible = array(
		'assessment_run_id',
		'run_request_id',
		'user_uuid',
		'notify_when_complete_flag'
	);
}
