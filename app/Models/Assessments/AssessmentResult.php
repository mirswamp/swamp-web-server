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
|        Copyright (C) 2012-2018 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\Assessments;

use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\TimeStamps\UserStamped;
use App\Models\Users\UserPolicy;
use App\Models\Users\Permission;
use App\Models\Projects\Project;
use App\Models\Executions\ExecutionRecord;
use App\Models\Tools\Tool;
use App\Models\Tools\ToolVersion;

class AssessmentResult extends UserStamped {

	// enable soft delete
	//
	use SoftDeletes;

	// database attributes
	//
	protected $connection = 'assessment';
	protected $table = 'assessment_result';
	protected $primaryKey = 'assessment_results_uuid';
	public $incrementing = false;

	// mass assignment policy
	//
	protected $fillable = [
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
	];

	// array / json conversion whitelist
	//
	protected $visible = [
		'assessment_result_uuid',
		'project_uuid',
		'weakness_cnt',
		'platform_name',
		'platform_version',
		'tool_name',
		'tool_version',
		'package_name',
		'package_version'
	];

	// attribute types
	//
	protected $casts = [
		'weakness_cnt' => 'integer'
	];


	public function checkPermissions($user) {

		// check for no project
		//
		$project = Project::where('project_uid', '=', $this->project_uuid)->first();
		if (!$project) {
			return [
				'status' => 'no_project'
			];

		// check for project membership
		//
		} else if (!$user->isMemberOf($project)) {
			return [
				'status' => 'no_project_permission'
			];
		}
		
		// return if no tool
		//
		$executionRecord = ExecutionRecord::where('execution_record_uuid', '=', $this->execution_record_uuid)->first();
		$toolVersion = ToolVersion::where('tool_version_uuid', '=', $executionRecord->tool_version_uuid)->first();
		if ($toolVersion) {
			$tool = Tool::where('tool_uuid', '=', $toolVersion->tool_uuid)->first();
			if (!$tool) {
				return true;
			}
		} else {
			return true;	
		}

		// check restricted tools
		//
		if ($tool->isRestricted()) {

			// check for no tool permission
			//
			$permission = Permission::where('policy_code', '=', $tool->policy_code)->first();
			/*
			if (!$permission) {
				return [
					'status' => 'tool_no_permission',
					'tool_name' => $tool->name
				];
			}
			*/

			// check for owner permission
			//
			/*
			$owner = User::getIndex($project->project_owner_uid);
			$userPermission = UserPermission::where('permission_code', '=', $permission->permission_code)->where('user_uid', '=', $owner->user_uid)->first();

			// if the permission doesn't exist or isn't valid, return error
			//
			if ($tool->isRestrictedByProjectOwner()) {
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

			// if the project hasn't been designated
			//
			if ($tool->isRestrictedByProject()) {
				$userPermissionProject = UserPermissionProject::where('user_permission_uid', '=', $userPermission->user_permission_uid)->where('project_uid', '=', $assessmentRun->project_uuid)->first();
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
			/*
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
			*/

			// if the policy hasn't been accepted, return error
			//
			$userPolicy	= UserPolicy::where('policy_code', '=', $tool->policy_code)->where('user_uid', '=', $user->user_uid)->first();
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
}
