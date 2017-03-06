<?php
/******************************************************************************\
|                                                                              |
|                          RunRequestsController.php                           |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a controller for assessment run requests.                |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2017 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Http\Controllers\RunRequests;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Response;
use App\Utilities\Uuids\Guid;
use App\Utilities\Filters\LimitFilter;
use App\Models\Users\Permission;
use App\Models\Users\UserPermission;
use App\Models\Users\UserPermissionProject;
use App\Models\Users\User;
use App\Models\Users\UserPolicy;
use App\Models\Users\Policy;
use App\Models\Projects\Project;
use App\Models\Tools\Tool;
use App\Models\Assessments\AssessmentRun;
use App\Models\Assessments\AssessmentRunRequest;
use App\Models\RunRequests\RunRequest;
use App\Http\Controllers\BaseController;

class RunRequestsController extends BaseController {

	// create
	//
	public function postCreate() {
		$runRequest = new RunRequest(array(
			'run_request_uuid' => Guid::create(),
			'project_uuid' => Input::get('project_uuid'),
			'name' => Input::get('name'),
			'description' => Input::get('description')
		));
		$runRequest->save();
		return $runRequest;
	}

	public function postOneTimeAssessmentRunRequests() {

		// get parameters
		//
		$assessmentRunUuids = Input::get('assessment-run-uuids');
		$notifyWhenComplete = Input::get('notify-when-complete');

		$assessmentRunRequests = new Collection;
		$runRequest = RunRequest::where('name', '=', 'One-time')->first();
		if ($runRequest != NULL) {
			
			// check permissions on each assessment run
			//
			foreach( $assessmentRunUuids as $assessmentRunUuid ) {
				$assessmentRun = AssessmentRun::where('assessment_run_uuid', '=', $assessmentRunUuid)->first();
				if ($assessmentRun != NULL) {
					$result = $this->checkPermissions($assessmentRun);
					if ($result !== true) {
						return $result;
					}
				}
			}

			// create assessment run requests
			//
			for ($i = 0; $i < sizeOf($assessmentRunUuids); $i++) {
				$assessmentRun = AssessmentRun::where('assessment_run_uuid', '=', $assessmentRunUuids[$i])->first();
				if ($assessmentRun != NULL) {
					$assessmentRunRequest = new AssessmentRunRequest(array(
						'assessment_run_id' => $assessmentRun->assessment_run_id,
						'run_request_id' => $runRequest->run_request_id,
						'user_uuid' => Session::get('user_uid'),
						'notify_when_complete_flag' => $notifyWhenComplete == 'true'? 1 : 0
					));
					$assessmentRunRequest->save();
					$assessmentRunRequests->push($assessmentRunRequest);
				}
			}
		}
		return $assessmentRunRequests;
	}

	private function checkPermissions($assessmentRun) {
		$tool = Tool::where('tool_uuid','=',$assessmentRun->tool_uuid)->first();

		// return if no tool
		//
		$tool = Tool::where('tool_uuid','=',$assessmentRun->tool_uuid)->first();
		if (!$tool) {
			return response('approved', 200);
		}

		// check restricted tools
		//
		if ($tool->isRestricted()) {
			$user = User::getIndex( Session::get('user_uid') );
			$permission = Permission::where('policy_code','=', $tool->policy_code)->first();
			$project = Project::where('project_uid', '=', $assessmentRun->project_uuid)->first();
			$projectOwner = $project->owner;

			// check for no permission, project, or owner
			//
			if (!$permission || !$project || !$projectOwner) {
				return response()->json(array(
					'status' => 'error'
				), 404);
			}

			// if the permission doesn't exist or isn't valid, return error
			//
			/*
			if ($tool->isRestrictedByProjectOwner()) {
				$userPermission = UserPermission::where('permission_code', '=', $permission->permission_code)->where('user_uid', '=', $projectOwner['user_uid'])->first();
				if (!$userPermission) {
					return response()->json(array(
						'status' => 'owner_no_permission',
						'project_name' => $project->full_name,
						'tool_name' => $tool->name
					), 404);
				}
				if ($userPermission->status !== 'granted') {
					return response()->json(array(
						'status' => 'owner_no_permission',
						'project_name' => $project->full_name,
						'tool_name' => $tool->name
					), 401);
				}
			}
			*/

			// if the project hasn't been designated, return error
			//
			/*
			if ($tool->isRestrictedByProject()) {
				$userPermissionProject = UserPermissionProject::where('user_permission_uid','=',$userPermission->user_permission_uid)->where('project_uid','=',$project->project_uid)->first();
				if (!$userPermissionProject) {
					return response()->json(array(
						'status' => 'no_project',
						'project_name' => $project->full_name,
						'tool_name' => $tool->name
					), 404);
				}
			}
			*/

			// check user permission
			//
			$userPermission = UserPermission::where('permission_code', '=', $permission->permission_code)->where('user_uid', '=', $user['user_uid'])->first();
			if (!$userPermission) {
				return response()->json(array(
					'status' => 'tool_no_permission',
					'project_name' => $project->full_name,
					'tool_name' => $tool->name
				), 404);
			}
			if ($userPermission->status !== 'granted') {
				return response()->json(array(
					'status' => 'tool_no_permission',
					'project_name' => $project->full_name,
					'tool_name' => $tool->name
				), 401);
			}

			// if the policy hasn't been accepted, return error
			//
			$userPolicy	= UserPolicy::where('policy_code','=',$tool->policy_code)->where('user_uid','=',$user->user_uid)->first();
			if (!$userPolicy || $userPolicy->accept_flag != '1') {
				return response()->json(array(
					'status' => 'no_policy',
					'policy' => $tool->policy,
					'policy_code' => $tool->policy_code,
					'tool' => $tool
				), 404);
			}
		}

		return true;
	}

	public function postAssessmentRunRequests($runRequestUuid) {
		$assessmentRunRequests = new Collection;
		$runRequest = $this->getIndex($runRequestUuid);
		if ($runRequest != NULL) {
			$assessmentRunUuids = Input::get('assessment-run-uuids');
			$notifyWhenComplete = Input::get('notify-when-complete');

			// check permissions on each assessment run
			//
			foreach( $assessmentRunUuids as $aru ) {
				$assessmentRun = AssessmentRun::where('assessment_run_uuid', '=', $aru)->first();
				if ($assessmentRun != NULL) {
					$result = $this->checkPermissions($assessmentRun);
					if ($result !== true) {
						return $result;
					}
				}
			}

			// create assessment run requests
			//
			for ($i = 0; $i < sizeOf($assessmentRunUuids); $i++) {
				$assessmentRun = AssessmentRun::where('assessment_run_uuid', '=', $assessmentRunUuids[$i])->first();
				if ($assessmentRun != NULL) {
					$assessmentRunRequest = new AssessmentRunRequest(array(
						'assessment_run_id' => $assessmentRun->assessment_run_id,
						'run_request_id' => $runRequest->run_request_id,
						'user_uuid' => Session::get('user_uid'),
						'notify_when_complete_flag' => $notifyWhenComplete == 'true'? 1 : 0
					));
					$assessmentRunRequest->save();
					$assessmentRunRequests->push($assessmentRunRequest);
				}
			}
		}
		return $assessmentRunRequests;
	}

	// get by index
	//
	public function getIndex($runRequestUuid) {
		$runRequest = RunRequest::where('run_request_uuid', '=', $runRequestUuid)->first();
		return $runRequest;
	}

	// get by project
	//
	public function getByProject($projectUuid) {
		if (!strpos($projectUuid, '+')) {

			// check for inactive or non-existant project
			//
			$project = Project::where('project_uid', '=', $projectUuid)->first();
			if (!$project || !$project->isActive()) {
				return array();
			}

			// get by a single project
			//
			$runRequestsQuery = RunRequest::where('project_uuid', '=', $projectUuid);
		} else {

			// get by multiple projects
			//
			$projectUuids = explode('+', $projectUuid);
			foreach ($projectUuids as $projectUuid) {

				// check for inactive or non-existant project
				//
				$project = Project::where('project_uid', '=', $projectUuid)->first();
				if (!$project || !$project->isActive()) {
					continue;
				}

				if (!isset($runRequestsQuery)) {
					$runRequestsQuery = RunRequest::where('project_uuid', '=', $projectUuid);
				} else {
					$runRequestsQuery = $runRequestsQuery->orWhere('project_uuid', '=', $projectUuid);
				}
			}
		}

		// add limit filter
		//
		$runRequestsQuery = LimitFilter::apply($runRequestsQuery);

		return $runRequestsQuery->get();
	}

	// get number by project
	//
	public function getNumByProject($projectUuid) {
		if (!strpos($projectUuid, '+')) {

			// get by a single project
			//
			$runRequestsQuery = RunRequest::where('project_uuid', '=', $projectUuid);
		} else {

			// get by multiple projects
			//
			$projectUuids = explode('+', $projectUuid);
			$runRequestsQuery = RunRequest::where('project_uuid', '=', $projectUuids[0]);
			for ($i = 1; $i < sizeof($projectUuids); $i++) {
				$runRequestsQuery = $runRequestsQuery->orWhere('project_uuid', '=', $projectUuids[$i]);
			}
		}

		return $runRequestsQuery->count();
	}

	// get by project
	//
	public function getAll() {
		$runRequests = RunRequest::all();
		return $runRequests;
	}

	// update by index
	//
	public function updateIndex($runRequestUuid) {

		// get model
		//
		$runRequest = $this->getIndex($runRequestUuid);

		// update attributes
		//
		$runRequest->project_uuid = Input::get('project_uuid');
		$runRequest->name = Input::get('name');
		$runRequest->description = Input::get('description');

		// save and return changes
		//
		$changes = $runRequest->getDirty();
		$runRequest->save();
		return $changes;
	}

	// delete by index
	//
	public function deleteIndex($runRequestUuid) {
		$runRequest = RunRequest::where('run_request_uuid', '=', $runRequestUuid)->first();
		$runRequest->delete();
		return $runRequest;
	}

	// delete assessment run request
	//
	public function deleteAssessmentRunRequest($runRequestUuid, $assessmentRunUuid) {
		$runRequest = $this->getIndex($runRequestUuid);
		$assessmentRun = AssessmentRun::where('assessment_run_uuid', '=', $assessmentRunUuid)->first();
		$assessmentRunRequest = AssessmentRunRequest::where('run_request_id', '=', $runRequest->run_request_id)->
			where('assessment_run_id', '=', $assessmentRun->assessment_run_id)->first();
		$assessmentRunRequest->delete();
		return $assessmentRunRequest;
	}
}
