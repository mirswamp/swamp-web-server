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
|        Copyright (C) 2012-2020 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\RunRequests;

use App\Models\TimeStamps\TimeStamped;
use App\Models\Projects\Project;

class RunRequest extends TimeStamped
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
	protected $table = 'run_request';

	/**
	 * The primary key associated with the table.
	 *
	 * @var string
	 */
	protected $primaryKey = 'run_request_id';

	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array
	 */
	protected $fillable = [
		'run_request_uuid',
		'project_uuid',
		'name',
		'description'
	];

	/**
	 * The attributes that should be visible in serialization.
	 *
	 * @var array
	 */
	protected $visible = [
		'run_request_uuid',
		'project_name',
		'project_uuid',
		'name',
		'description'
	];

	/**
	 * The accessors to append to the model's array form.
	 *
	 * @var array
	 */
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