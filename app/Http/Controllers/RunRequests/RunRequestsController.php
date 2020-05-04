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
|        Copyright (C) 2012-2020 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Http\Controllers\RunRequests;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Session;
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

class RunRequestsController extends BaseController
{
	// create
	//
	public function postCreate(Request $request): RunRequest {

		// parse parameters
		//
		$projectUuid = $request->input('project_uuid');
		$name = $request->input('name');
		$description = $request->input('description');

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

	public function postOneTimeAssessmentRunRequests(Request $request) {

		// parse parameters
		//
		$assessmentRunUuids = $request->input('assessment-run-uuids');
		$notifyWhenComplete = filter_var($request->input('notify-when-complete'), FILTER_VALIDATE_BOOLEAN);

		// create new run requests
		//
		$assessmentRunRequests = collect();
		$runRequest = RunRequest::where('name', '=', 'One-time')
			->where('project_uuid', '=', null)
			->first();
			
		if ($runRequest != null) {
			
			// check permissions on each assessment run
			//
			foreach( $assessmentRunUuids as $assessmentRunUuid ) {
				$assessmentRun = AssessmentRun::where('assessment_run_uuid', '=', $assessmentRunUuid)
					->first();
				if ($assessmentRun != null) {
					$user = User::current();
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
				$assessmentRun = AssessmentRun::where('assessment_run_uuid', '=', $assessmentRunUuids[$i])
					->first();
				if ($assessmentRun != null) {
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

	public function postAssessmentRunRequests(Request $request, string $runRequestUuid) {

		// parse parameters
		//
		$assessmentRunUuids = $request->input('assessment-run-uuids');
		$notifyWhenComplete = filter_var($request->input('notify-when-complete'), FILTER_VALIDATE_BOOLEAN);

		// create new run requests
		//
		$assessmentRunRequests = collect();
		$runRequest = $this->getIndex($runRequestUuid);

		if ($runRequest) {

			// check permissions on each assessment run
			//
			foreach ($assessmentRunUuids as $assessmentRunUuid) {
				$assessmentRun = AssessmentRun::where('assessment_run_uuid', '=', $assessmentRunUuid)->first();
				if ($assessmentRun != null) {
					$user = User::current();
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
			for ($i = 0; $i < count($assessmentRunUuids); $i++) {
				$assessmentRun = AssessmentRun::where('assessment_run_uuid', '=', $assessmentRunUuids[$i])->first();
				if ($assessmentRun) {
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
	public function getIndex(string $runRequestUuid): ?RunRequest {
		return RunRequest::where('run_request_uuid','=', $runRequestUuid)->first();
	}

	// get by project
	//
	public function getByProject(Request $request, string $projectUuid): Collection {
		if (!strpos($projectUuid, '+')) {

			// check for inactive or non-existant project
			//
			$project = Project::where('project_uid', '=', $projectUuid)
				->first();
			if (!$project || !$project->isActive()) {
				return [];
			}

			// get by a single project
			//
			$query = RunRequest::where('project_uuid', '=', $projectUuid);
		} else {

			// get by multiple projects
			//
			$projectUuids = explode('+', $projectUuid);
			foreach ($projectUuids as $projectUuid) {

				// check for inactive or non-existant project
				//
				$project = Project::where('project_uid', '=', $projectUuid)
					->first();
				if (!$project || !$project->isActive()) {
					continue;
				}

				if (!isset($query)) {
					$query = RunRequest::where('project_uuid', '=', $projectUuid);
				} else {
					$query = $query->orWhere('project_uuid', '=', $projectUuid);
				}
			}
		}

		$query = $query->orWhere(function($query) {
			return $query->where('project_uuid', '=', null)
				->where('hidden_flag', '!=', 1);
		});

		// add limit filter
		//
		$query = LimitFilter::apply($request, $query);

		// perform query
		//
		return $query->get();
	}

	// get number by project
	//
	public function getNumByProject(Request $request, string $projectUuid): int {
		if (!strpos($projectUuid, '+')) {

			// get by a single project
			//
			$query = RunRequest::where('project_uuid', '=', $projectUuid);
		} else {

			// get by multiple projects
			//
			$projectUuids = explode('+', $projectUuid);
			$query = RunRequest::where('project_uuid', '=', $projectUuids[0]);
			for ($i = 1; $i < sizeof($projectUuids); $i++) {
				$query = $query->orWhere('project_uuid', '=', $projectUuids[$i]);
			}
		}

		// perform query
		//
		return $query->count();
	}

	// get by project
	//
	public function getAll(): Collection {
		return RunRequest::all();
	}

	// update by index
	//
	public function updateIndex(Request $request, string $runRequestUuid) {

		// parse parameters
		//
		$projectUuid = $request->input('project_uuid');
		$name = $request->input('name');
		$description = $request->input('description');

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
	public function deleteIndex(string $runRequestUuid) {
		$runRequest = RunRequest::where('run_request_uuid', '=', $runRequestUuid)
			->first();
		$runRequest->delete();
		return $runRequest;
	}

	// delete assessment run request
	//
	public function deleteAssessmentRunRequest(string $runRequestUuid, string $assessmentRunUuid) {
		$runRequest = $this->getIndex($runRequestUuid);
		$assessmentRun = AssessmentRun::where('assessment_run_uuid', '=', $assessmentRunUuid)->first();
		$assessmentRunRequest = AssessmentRunRequest::where('run_request_id', '=', $runRequest->run_request_id)
			->where('assessment_run_id', '=', $assessmentRun->assessment_run_id)
			->first();
		$assessmentRunRequest->delete();
		return $assessmentRunRequest;
	}
}
