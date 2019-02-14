<?php
/******************************************************************************\
|                                                                              |
|                                RunRequest.php                                |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a model of request to perform an assessment run.         |
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
use App\Models\Projects\Project;

class RunRequest extends UserStamped {

	// database attributes
	//
	protected $connection = 'assessment';
	protected $table = 'run_request';
	protected $primaryKey = 'run_request_id';

	// mass assignment policy
	//
	protected $fillable = [
		'run_request_uuid',
		'project_uuid',
		'name',
		'description'
	];

	// array / json conversion whitelist
	//
	protected $visible = [
		'run_request_uuid',
		'project_name',
		'project_uuid',
		'name',
		'description'
	];

	// array / json appended model attributes
	//
	protected $appends = [
		'project_name'
	];

	//
	// accessor methods
	//

	public function getProjectNameAttribute() {
		$project = Project::where('project_uid', '=', $this->project_uuid)->first();
		if ($project) {
			return $project->full_name;
		}
	}
}