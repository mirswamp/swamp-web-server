<?php
/******************************************************************************\
|                                                                              |
|                                AssessmentRun.php                             |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a model of an assessment run.                            |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2018 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\Assessments;

use Illuminate\Support\Collection;
use App\Models\TimeStamps\UserStamped;
use App\Models\Users\Permission;
use App\Models\Projects\Project;
use App\Models\Users\UserPolicy;
use App\Models\Users\UserPermission;
use App\Models\Packages\Package;
use App\Models\Packages\PackageVersion;
use App\Models\Tools\Tool;
use App\Models\Tools\ToolVersion;
use App\Models\Platforms\Platform;
use App\Models\Platforms\PlatformVersion;
use App\Models\Executions\ExecutionRecord;
use App\Models\RunRequests\RunRequest;
use App\Models\Assessments\AssessmentRunRequest;

class AssessmentRun extends UserStamped {

	// database attributes
	//
	protected $connection = 'assessment';
	protected $table = 'assessment_run';
	protected $primaryKey = 'assessment_run_id';

	// mass assignment policy
	//
	protected $fillable = [
		'assessment_run_uuid',
		'project_uuid',
		'package_uuid',
		'package_version_uuid',
		'tool_uuid',
		'tool_version_uuid',
		'platform_uuid',
		'platform_version_uuid'
	];

	// array / json conversion whitelist
	//
	protected $visible = [
		'assessment_run_uuid',
		'project_uuid',
		'package_uuid',
		'package_version_uuid',
		'tool_uuid',
		'tool_version_uuid',
		'platform_uuid',
		'platform_version_uuid',
		'package_name',
		'package_version_string',
		'tool_name',
		'tool_version_string',
		'platform_name',
		'platform_version_string',
		'num_execution_records'
	];

	// array / json appended model attributes
	//
	protected $appends = [
		'package_name',
		'package_version_string',
		'tool_name',
		'tool_version_string',
		'platform_name',
		'platform_version_string',
		'num_execution_records'
	];

	// attribute types
	//
	protected $casts = [
		'num_execution_records' => 'integer'
	];

	//
	// accessor methods
	//

	public function getPackageNameAttribute() {
		if ($this->package_uuid != null) {
			$package = Package::where('package_uuid', '=', $this->package_uuid)->first();
			if ($package) {
				return $package->name;
			} else {
				return '?';
			}
		} else {
			return '';
		}
	}

	public function getPackageVersionStringAttribute() {
		if ($this->package_version_uuid != null) {
			$packageVersion = PackageVersion::where('package_version_uuid', '=', $this->package_version_uuid)->first();
			if ($packageVersion) {
				return $packageVersion->version_string;
			} else {
				return '?';
			}
		} else {
			return 'latest';
		}
	}

	public function getToolNameAttribute() {
		if ($this->tool_uuid != null) {
			$tool = Tool::where('tool_uuid', '=', $this->tool_uuid)->first();
			if ($tool) {
				return $tool->name;
			} else {
				return '?';
			}
		} else {
			return 'all';
		}
	}

	public function getToolVersionStringAttribute() {
		if ($this->tool_version_uuid != null) {
			$toolVersion = ToolVersion::where('tool_version_uuid', '=', $this->tool_version_uuid)->first();
			if ($toolVersion) {
				return $toolVersion->version_string;
			} else {
				return '?';
			}
		} else {
			return 'latest';
		}
	}

	public function getPlatformNameAttribute() {
		if ($this->platform_uuid != null) {
			$platform = Platform::where('platform_uuid', '=', $this->platform_uuid)->first();
			if ($platform) {
				return $platform->name;
			} else {
				return '?';
			}
		} else {
			return 'default';
		}
	}

	public function getPlatformVersionStringAttribute() {
		if ($this->platform_version_uuid != null) {
			$platformVersion = PlatformVersion::where('platform_version_uuid', '=', $this->platform_version_uuid)->first();
			if ($platformVersion) {
				return $platformVersion->version_string;
			} else {
				return '?';
			}
		} else {
			return 'latest';
		}
	}

	public function getNumExecutionRecordsAttribute() {
		if (!$this->isMultiple()) {
			return ExecutionRecord::where('assessment_run_uuid', '=', $this->assessment_run_uuid)->count();
		} else {
			$count = 0;
			foreach ($this->assessment_run_uuid as $assessmentRunUuid) {
				$count += ExecutionRecord::where('assessment_run_uuid', '=', $assessmentRunUuid)->count();
			}
			return $count;
		}
	}

	//
	// querying methods
	//

	public function getVisible() {
		return $this->visible;
	}

	public function isMultiple() {
		return is_array($this->assessment_run_uuid);
	}

	/*
	public function getRunRequests() {
		$assessmentRunRequests = AssessmentRunRequest::where('assessment_run_id', '=', $this->assessment_run_id)->get();
		$collection = new Collection;
		foreach ($assessmentRunRequests as $assessmentRunRequest) {
			$runRequest = RunRequest::where('run_request_id', '=', $assessmentRunRequest->run_request_id)->first();
			
			// don't report one time requests
			//
			if ($runRequest->name != 'One-time') {
				$collection->push($runRequest);
			}
		}
		return $collection;
	}

	public function getNumRunRequests() {
		$num = 0;
		$assessmentRunRequests = AssessmentRunRequest::where('assessment_run_id', '=', $this->assessment_run_id)->get();
		foreach ($assessmentRunRequests as $assessmentRunRequest) {
			$runRequest = RunRequest::where('run_request_id', '=', $assessmentRunRequest->run_request_id)->first();
			
			// don't report one time requests
			//
			if ($runRequest->name != 'One-time') {
				$num++;
			}
		}
		return $num;
	}
	*/

	public function getRunRequests() {
		$oneTimeRunRequest = $runRequest = RunRequest::where('name', '=', 'One-time')->first();
		return $assessmentRunRequests = AssessmentRunRequest::where('assessment_run_id', '=', $this->assessment_run_id)->where('run_request_id', '!=', $oneTimeRunRequest->run_request_id)->get();
	}

	public function getNumRunRequests() {
		$oneTimeRunRequest = $runRequest = RunRequest::where('name', '=', 'One-time')->first();
		return $assessmentRunRequests = AssessmentRunRequest::where('assessment_run_id', '=', $this->assessment_run_id)->where('run_request_id', '!=', $oneTimeRunRequest->run_request_id)->count();
	}

	public function checkPermissions($user) {
		$tool = Tool::where('tool_uuid','=',$this->tool_uuid)->first();

		// return if no tool
		//
		$tool = Tool::where('tool_uuid','=',$this->tool_uuid)->first();
		if (!$tool) {
			return true;
		}

		// check restricted tools
		//
		if ($tool->isRestricted()) {
			$permission = Permission::where('policy_code','=', $tool->policy_code)->first();
			$project = Project::where('project_uid', '=', $this->project_uuid)->first();
			$projectOwner = $project->owner;

			// check for no permission, project, or owner
			//
			if (!$permission || !$project || !$projectOwner) {
				return [
					'status' => 'error'
				];
			}

			// if the permission doesn't exist or isn't valid, return error
			//
			/*
			if ($tool->isRestrictedByProjectOwner()) {
				$userPermission = UserPermission::where('permission_code', '=', $permission->permission_code)->where('user_uid', '=', $projectOwner['user_uid'])->first();
				if (!$userPermission) {
					return [
						'status' => 'owner_no_permission',
						'project_name' => $project->full_name,
						'tool_name' => $tool->name
					];
				}
				if ($userPermission->status !== 'granted') {
					return [
						'status' => 'owner_no_permission',
						'project_name' => $project->full_name,
						'tool_name' => $tool->name
					];
				}
			}
			*/

			// if the project hasn't been designated, return error
			//
			/*
			if ($tool->isRestrictedByProject()) {
				$userPermissionProject = UserPermissionProject::where('user_permission_uid','=',$userPermission->user_permission_uid)->where('project_uid','=',$project->project_uid)->first();
				if (!$userPermissionProject) {
					return [
						'status' => 'no_project',
						'project_name' => $project->full_name,
						'tool_name' => $tool->name
					];
				}
			}
			*/

			// check user permission
			//
			$userPermission = UserPermission::where('permission_code', '=', $permission->permission_code)->where('user_uid', '=', $user['user_uid'])->first();
			if (!$userPermission) {
				return [
					'status' => 'tool_no_permission',
					'project_name' => $project->full_name,
					'tool_name' => $tool->name
				];
			}
			if ($userPermission->status !== 'granted') {
				return [
					'status' => 'tool_no_permission',
					'project_name' => $project->full_name,
					'tool_name' => $tool->name
				];
			}

			// if the policy hasn't been accepted, return error
			//
			$userPolicy	= UserPolicy::where('policy_code','=',$tool->policy_code)->where('user_uid','=',$user->user_uid)->first();
			if (!$userPolicy || $userPolicy->accept_flag != '1') {
				return [
					'status' => 'no_policy',
					'policy' => $tool->policy,
					'policy_code' => $tool->policy_code,
					'tool' => $tool
				];
			}
		}

		return true;
	}

	//
	// overridden Laravel methods
	//

	public function toArray() {
		$array = parent::toArray();

		// add checks for uuid integrity
		//
		if ($array['package_uuid'] && !Package::where('package_uuid', '=', $array['package_uuid'])->exists()) {
			$array['package_uuid'] = 'undefined';
		}
		if ($array['package_version_uuid'] && !PackageVersion::where('package_version_uuid', '=', $array['package_version_uuid'])->exists()) {
			$array['package_version_uuid'] = 'undefined';
		}

		if ($array['tool_uuid'] && !Tool::where('tool_uuid', '=', $array['tool_uuid'])->exists()) {
			$array['tool_uuid'] = 'undefined';
		}
		if ($array['tool_version_uuid'] && !ToolVersion::where('tool_version_uuid', '=', $array['tool_version_uuid'])->exists()) {
			$array['tool_version_uuid'] = 'undefined';
		}

		if ($array['platform_uuid'] && !Platform::where('platform_uuid', '=', $array['platform_uuid'])->exists()) {
			$array['platform_uuid'] = 'undefined';
		}
		if ($array['platform_version_uuid'] && !PlatformVersion::where('platform_version_uuid', '=', $array['platform_version_uuid'])->exists()) {
			$array['platform_version_uuid'] = 'undefined';
		}

		return $array;
	}
}
