<?php
/******************************************************************************\
|                                                                              |
|                                 UserInfo.php                                 |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a model of user's personal information plus              |
|        additional information used for reviewing user data.                  |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2020 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\Users;

use App\Models\BaseModel;
use App\Models\Projects\Project;
use App\Models\Projects\ProjectMembership;
use App\Models\Packages\Package;
use App\Models\Results\ExecutionRecord;

class UserInfo extends BaseModel
{
	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array
	 */
	protected $fillable = [
		'user_uid',

		// count attributes
		//
		'num_packages',
		'num_projects',
		'num_executions',
		'num_results'
	];

	/**
	 * The attributes that should be visible in serialization.
	 *
	 * @var array
	 */
	protected $visible = [
		'user_uid',

		// count attributes
		//
		'num_packages',
		'num_projects',
		'num_executions',
		'num_results'
	];

	/**
	 * The accessors to append to the model's array form.
	 *
	 * @var array
	 */
	protected $appends = [

		// count attributes
		//
		'num_packages',
		'num_projects',
		'num_executions',
		'num_results'
	];

	/**
	 * The attributes that should be cast to native types.
	 *
	 * @var array
	 */
	protected $casts = [

		// count attributes
		//
		'num_packages' => 'int',
		'num_projects' => 'int',
		'num_executions' => 'int',
		'num_results' => 'int'
	];

	//
	// accessor methods
	//

	public function getNumPackagesAttribute() {
		return Package::where('package_owner_uuid', '=', $this->user_uid)->count();
	}

	public function getNumProjectsAttribute() {
		return $this->numProjects();
	}

	public function getNumExecutionsAttribute() {
		return ExecutionRecord::where('user_uuid', '=', $this->user_uid)->count();
	}

	public function getNumResultsAttribute() {
		return ExecutionRecord::where('user_uuid', '=', $this->user_uid)
			->where('status', '=', 'Finished')->count();
	}

	//
	// querying methods
	//

	public function numProjects(): int {
		$count = 0;

		// execute SQL query
		//
		$projectMemberships = ProjectMembership::where('user_uid', '=', $this->user_uid)->
			whereNull('delete_date')->get();
		
		// add projects of which user is a member
		//
		for ($i = 0; $i < sizeOf($projectMemberships); $i++) {
			$projectMembership = $projectMemberships[$i];
			$projectUid = $projectMembership['project_uid'];
			$project = Project::where('project_uid', '=', $projectUid)->first();
			if ($project != null && !$project->isTrialProject() && $project->isActive()) {
				$count++;
			}
		}

		return $count;
	}
}
