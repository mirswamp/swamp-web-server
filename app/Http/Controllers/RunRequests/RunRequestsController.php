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
|        Copyright (C) 2012-2018 Software Assurance Marketplace (SWAMP)        |
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

		// parse parameters
		//
		$projectUuid = Input::get('project_uuid');
		$name = Input::get('name');
		$description = Input::get('description');

		// create new run request
		//
		$runRequest = new RunRequest([
			'run_request_uuid' => Guid::create(),
			'project_uuid' => $projectUuid,
			'name' => $name,
			'description' => $description
		]);
		$runRequest->save();

		return $runRequest;
	}

	public function postOneTimeAssessmentRunRequests() {

		// parse parameters
		//
		$assessmentRunUuids = Input::get('assessment-run-uuids');
		$notifyWhenComplete = filter_var(Input::get('notify-when-complete'), FILTER_VALIDATE_BOOLEAN);

		// create new run requests
		//
		$assessmentRunRequests = new Collection;
		$runRequest = RunRequest::where('name', '=', 'One-time')->first();
		if ($runRequest != NULL) {
			
			// check permissions on each assessment run
			//
			foreach( $assessmentRunUuids as $assessmentRunUuid ) {
				$assessmentRun = AssessmentRun::where('assessment_run_uuid', '=', $assessmentRunUuid)->first();
				if ($assessmentRun != NULL) {
					$user = User::getIndex(session('user_uid'));
					$result = $assessmentRun->checkPermissions($user);

					// if not true, return permissions error
					//
					if ($result !== true) {
						return response()->json($result, 403);
					}
				}
			}

			// create assessment run requests
			//
			for ($i = 0; $i < sizeOf($assessmentRunUuids); $i++) {
				$assessmentRun = AssessmentRun::where('assessment_run_uuid', '=', $assessmentRunUuids[$i])->first();
				if ($assessmentRun != NULL) {
					$assessmentRunRequest = new AssessmentRunRequest([
						'assessment_run_id' => $assessmentRun->assessment_run_id,
						'run_request_id' => $runRequest->run_request_id,
						'user_uuid' => session('user_uid'),
						'notify_when_complete_flag' => $notifyWhenComplete
					]);
					$assessmentRunRequest->save();
					$assessmentRunRequests->push($assessmentRunRequest);
				}
			}
		}
		
		return $assessmentRunRequests;
	}

	public function postAssessmentRunRequests($runRequestUuid) {

		// parse parameters
		//
		$assessmentRunUuids = Input::get('assessment-run-uuids');
		$notifyWhenComplete = filter_var(Input::get('notify-when-complete'), FILTER_VALIDATE_BOOLEAN);

		// create new run requests
		//
		$assessmentRunRequests = new Collection;
		$runRequest = $this->getIndex($runRequestUuid);
		if ($runRequest != NULL) {

			// check permissions on each assessment run
			//
			foreach( $assessmentRunUuids as $aru ) {
				$assessmentRun = AssessmentRun::where('assessment_run_uuid', '=', $aru)->first();
				if ($assessmentRun != NULL) {
					$user = User::getIndex(session('user_uid'));
					$result = $assessmentRun->checkPermissions($user);

					// if not true, return permissions error
					//
					if ($result !== true) {
						return response()->json($result, 403);
					}
				}
			}

			// create assessment run requests
			//
			for ($i = 0; $i < sizeOf($assessmentRunUuids); $i++) {
				$assessmentRun = AssessmentRun::where('assessment_run_uuid', '=', $assessmentRunUuids[$i])->first();
				if ($assessmentRun != NULL) {
					$assessmentRunRequest = new AssessmentRunRequest([
						'assessment_run_id' => $assessmentRun->assessment_run_id,
						'run_request_id' => $runRequest->run_request_id,
						'user_uuid' => session('user_uid'),
						'notify_when_complete_flag' => $notifyWhenComplete
					]);
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
				return [];
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

		// parse parameters
		//
		$projectUuid = Input::get('project_uuid');
		$name = Input::get('name');
		$description = Input::get('description');

		// get model
		//
		$runRequest = $this->getIndex($runRequestUuid);

		// update attributes
		//
		$runRequest->project_uuid = $projectUuid;
		$runRequest->name = $name;
		$runRequest->description = $description;

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
