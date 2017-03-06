<?php
/******************************************************************************\
|                                                                              |
|                              AssessmentResult.php                            |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a model of an assessment result.                         |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2017 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\Assessments;

use App\Models\TimeStamps\UserStamped;

class AssessmentResult extends UserStamped {

	/**
	 * database attributes
	 */
	protected $connection = 'assessment';
	protected $table = 'assessment_result';
	protected $primaryKey = 'assessment_results_uuid';

	/**
	 * mass assignment policy
	 */
	protected $fillable = array(
		'assessment_result_uuid',
		'execution_record_uuid',
		'project_uuid',
		'weakness_cnt',
		'file_host',
		'file_path',
		'checksum',
		'platform_name',
		'platform_version',
		'tool_name',
		'tool_version',
		'package_name',
		'package_version'
	);

	/**
	 * array / json conversion whitelist
	 */
	protected $visible = array(
		'assessment_result_uuid',
		'project_uuid',
		'weakness_cnt',
		'platform_name',
		'platform_version',
		'tool_name',
		'tool_version',
		'package_name',
		'package_version'
	);
}
