<?php
/******************************************************************************\
|                                                                              |
|                           AssessmentRunsController.php                       |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a controller for assessment runs.                        |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2018 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Http\Controllers\Assessments;

use DB;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Response;
use App\Utilities\Uuids\Guid;
use App\Utilities\Filters\TripletFilter;
use App\Utilities\Filters\LimitFilter;
use App\Models\Projects\Project;
use App\Models\Assessments\AssessmentRun;
use App\Models\Assessments\AssessmentRunRequest;
use App\Models\Assessments\Group;
use App\Models\Packages\Package;
use App\Models\Packages\PackageVersion;
use App\Models\Packages\PackagePlatform;
use App\Models\Tools\Tool;
use App\Models\Tools\ToolVersion;
use App\Models\Tools\ToolSharing;
use App\Models\Platforms\Platform;
use App\Models\Platforms\PlatformVersion;
use App\Models\Users\User;
use App\Models\RunRequests\RunRequest;
use App\Http\Controllers\BaseController;
use App\Http\Controllers\Executions\ExecutionRecordsController;

class AssessmentRunsController extends BaseController {

	// checkCompatibility
	//
	public function checkCompatibility() {

		// get parameters
		//
		$projectUuid = Input::get('project_uuid');
		$packageUuid = Input::get('package_uuid');
		$packageVersionUuid = Input::get('package_version_uuid');
		$platformUuid = Input::get('platform_uuid');
		$platformVersionUuid = Input::get('platform_version_uuid');

		// get specified models
		//
		$project = $projectUuid? Project::where('project_uid', '=', $projectUuid)->first() : NULL;
		$package = $packageUuid? Package::where('package_uuid', '=', $packageUuid)->first() : NULL;
		$packageVersion = $packageVersionUuid? PackageVersion::where('package_version_uuid', '=', $packageVersionUuid)->first() : NULL;
		$platform = $platformUuid? Platform::where('platform_uuid', '=', $platformUuid)->first() : NULL;
		$platformVersion = $platformVersionUuid? PlatformVersion::where('platform_version_uuid', '=', $platformVersionUuid)->first() : NULL;

		// get latest package version
		//
		if ($package && !$packageVersion) {
			$packageVersion = $package->getLatestVersion($projectUuid);
		}

		// get default platform / platform version
		//
		if (!$platform && $package) {
			$platform = $package->getDefaultPlatform($platformVersion);
		}

		// get latest platform version
		//
		if ($platform && !$platformVersion) {
			$platformVersion = $platform->getLatestVersion();
		}

		// get platform from version
		//
		if (!$platform && $platformVersion) {
			$platform = $platformVersion->getPlatform();
		}

		// check compatibility in order of most specific to least specific
		//
		$compatibility = null;
		$message = "Platform is compatible.";

		if ($compatibility === null && $packageVersion && $platformVersion) {
			$compatibility = $packageVersion->getPlatformVersionCompatibility($platformVersion);
			if ($compatibility === 0) {
				$message = "Package version is incompatible with platform version.";
			}
		}

		if ($compatibility === null && $packageVersion && $platform) {
			$compatibility = $packageVersion->getPlatformCompatibility($platform);
			if ($compatibility === 0) {
				$message = "Package version is incompatible with platform.";
			}
		}

		if ($compatibility === null && $package && $platformVersion) {
			$compatibility = $package->getPlatformVersionCompatibility($platformVersion);
			if ($compatibility === 0) {
				$message = "Package is incompatible with platform version.";
			}
		}

		if ($compatibility === null && $package && $platform) {
			$compatibility = $package->getPlatformCompatibility($platform);
			if ($compatibility === 0) {
				$message = "Package is incompatible with platform.";
			}
		}

		// return response
		//
		if ($compatibility === 0) {
			return response($message, 400);
		} else {
			return response($message, 200);
		}
	}

	// create
	//
	public function postCreate() {

		// get parameters
		//
		$projectUuid = Input::get('project_uuid');
		$packageUuid = Input::get('package_uuid');
		$packageVersionUuid = Input::get('package_version_uuid');
		$toolUuid = Input::get('tool_uuid');
		$toolVersionUuid = Input::get('tool_version_uuid');
		$platformUuid = Input::get('platform_uuid');
		$platformVersionUuid = Input::get('platform_version_uuid');

		// get specified models
		//
		$project = $projectUuid? Project::where('project_uid', '=', $projectUuid)->first() : NULL;
		$package = $packageUuid? Package::where('package_uuid', '=', $packageUuid)->first() : NULL;
		$packageVersion = $packageVersionUuid? PackageVersion::where('package_version_uuid', '=', $packageVersionUuid) : NULL;
		$tool = $toolUuid && $toolUuid != '*'? Tool::where('tool_uuid', '=', $toolUuid)->first() : NULL;
		$toolVersion = $toolVersionUuid? ToolVersion::where('tool_version_uuid', '=', $toolVersionUuid) : NULL;
		$platform = $platformUuid? Platform::where('platform_uuid', '=', $platformUuid)->first() : NULL;
		$platformVersion = $platformVersionUuid? PlatformVersion::where('platform_version_uuid', '=', $platformVersionUuid)->first() : NULL;

		// error checking
		//
		if (!$project) {
			return response('Can not save assessment because a valid project has not been specified.', 400);	
		}
		if (!$package) {
			return response('Can not save assessment because a valid package has not been specified.', 400);	
		}
		if ($toolUuid != '*' && !$tool) {
			return response('Can not save assessment because a valid tool has not been specified.', 400);	
		}

		// find platform
		//
		if ($platformVersion) {

			// get platform from version
			//
			$platform = $platformVersion->getPlatform();
		
			// get platform uuid
			//
			if ($platform) {
				$platformUuid = $platform->platform_uuid;
			}
		} else if (!$platform && $package) {

			// get default platform / platform version
			//
			$platform = $package->getDefaultPlatform($platformVersion);

			// get platform / version uuids
			//
			if ($platform) {
				$platformUuid = $platform->platform_uuid;
			}
			if ($platformVersion) {
				$platformVersionUuid = $platformVersion->platform_version_uuid;
			}
		}

		if ($tool) {

			// check public tool permission
			//
			if ($project->exclude_public_tools_flag) {
				if ($tool->isPublic()) {
					return response('Public tools may not be used with this project.', 400);	
				}
			}

			// check tool permission
			//
			if ($tool->isRestricted()) {
				$user = User::getIndex(session('user_uid'));
				$permission = $tool->getPermission($package, $project, $user);
				if ($permission != 'granted') {
					return response($permission, 401);
				}
			}

			// check if assessment run already exists
			//
			$assessmentRun = AssessmentRun::where('project_uuid', '=', $projectUuid)->
				where('package_uuid', '=', $packageUuid)->
				where('package_version_uuid', '=', $packageVersionUuid)->
				where('tool_uuid', '=', $toolUuid)->
				where('tool_version_uuid', '=', $toolVersionUuid)->
				where('platform_uuid', '=', $platformUuid)->
				where('platform_version_uuid', '=', $platformVersionUuid)->first();

			// if assessment run does not already exist, create it
			//
			if (!$assessmentRun) {
				$assessmentRun = new AssessmentRun([
					'assessment_run_uuid' => Guid::create(),
					'project_uuid' => $projectUuid,
					'package_uuid' => $packageUuid,
					'package_version_uuid' => $packageVersionUuid,
					'tool_uuid' => $toolUuid,
					'tool_version_uuid' => $toolVersionUuid,
					'platform_uuid' => $platformUuid,
					'platform_version_uuid' => $platformVersionUuid
				]);
				$assessmentRun->save();
			}

			return $assessmentRun;
		} else {
			//$assessmentRunGroupUuid = Guid::create();
			$assessmentRunUuids = [];
			$assessmentRuns = [];
			$toolUuids = [];

			// use all available tools
			//
			if (Tool::where('name', '=', 'Spotbugs')->exists()) {
				$publicTools = Tool::where('tool_sharing_status', '=', 'public')
						->where('name', '!=', 'Findbugs')
						->orderBy('name', 'ASC')->get();
			} else {
				$publicTools = Tool::where('tool_sharing_status', '=', 'public')
						->orderBy('name', 'ASC')->get();
			}
			$protectedTools = ToolSharing::getToolsByProject($projectUuid);
			$tools = $publicTools->merge($protectedTools);

			foreach ($tools as $tool) {

				// check tool permission
				//
				if ($tool->isRestricted()) {
					$user = User::getIndex(session('user_uid'));
					$permission = $tool->getPermission($package, $project, $user);
					if ($permission != 'granted') {
						continue;
					}
				}

				// check package type
				//
				if (!$tool->supports($package->package_type)) {
					continue;
				}

				// get tool uuid
				//
				$toolUuid = $tool->tool_uuid;

				// check if assessment run already exists
				//
				$assessmentRun = AssessmentRun::where('project_uuid', '=', $projectUuid)->
					where('package_uuid', '=', $packageUuid)->
					where('package_version_uuid', '=', $packageVersionUuid)->
					where('tool_uuid', '=', $toolUuid)->
					where('tool_version_uuid', '=', null)->
					where('platform_uuid', '=', $platformUuid)->
					where('platform_version_uuid', '=', $platformVersionUuid)->first();

				// if assessment run does not already exist, create it
				//
				if (!$assessmentRun) {
					$assessmentRun = new AssessmentRun([
						'assessment_run_uuid' => Guid::create(),
						//'assessment_run_group_uuid' => $assessmentRunGroupUuid,
						'project_uuid' => $projectUuid,
						'package_uuid' => $packageUuid,
						'package_version_uuid' => $packageVersionUuid,
						'tool_uuid' => $toolUuid,
						'tool_version_uuid' => null,
						'platform_uuid' => $platformUuid,
						'platform_version_uuid' => $platformVersionUuid
					]);

					// append assessment run to list of runs to save
					//
					array_push($assessmentRuns, $assessmentRun);
				}

				// append assessment run and tool uuids to list
				//
				array_push($assessmentRunUuids, $assessmentRun->assessment_run_uuid);
				array_push($toolUuids, $assessmentRun->tool_uuid);
			}

			// convert tool uuids array to string
			//
			$toolUuidsString = '';
			for ($i = 0; $i < sizeof($toolUuids); $i++) {
				if ($i > 0) {
					$toolUuidsString .= ', ';
				}
				$toolUuidsString .= $toolUuids[$i];
			}

			// create explicit group
			//
			/*
			$group = new Group([
				'group_uuid' => Guid::create(),
				'group_type' => 'assessment_run',
				'uuid_list' => $toolUuidsString
			]);
			*/

			// make changes to database in a single transaction
			//
			DB::beginTransaction();
			try {

				// save all newly created assessment runs
				//
				for ($i = 0; $i < sizeof($assessmentRuns); $i++) {
					$assessmentRuns[$i]->save();
				}

				// save group
				//
				//$group->save();

				DB::commit();
			} catch (Exception $e) {
				DB::rollback();
			}

			// create response
			//
			return new AssessmentRun([
				'assessment_run_uuid' => $assessmentRunUuids,
				'project_uuid' => $projectUuid,
				'package_uuid' => $packageUuid,
				'package_version_uuid' => $packageVersionUuid,
				'tool_uuid' => $toolUuids,
				'tool_version_uuid' => NULL,
				'platform_uuid' => $platformUuid,
				'platform_version_uuid' => $platformVersionUuid
			]);
		}
	}

	// get by index
	//
	public function getIndex($assessmentRunUuid) {
		$user = User::getIndex(session('user_uid'));
		$assessmentRun = AssessmentRun::where('assessment_run_uuid', '=', $assessmentRunUuid)->first();
		$project = Project::where('project_uid', '=', $assessmentRun->project_uuid)->first();
		
		if (($user && $user->isAdmin()) || ($assessmentRun && $user->isMemberOf($project))) {
			return $assessmentRun;
		} else {
			return response('Access denied.', 401);
		}

		return $assessmentRun;
	}

	// get by project
	//
	public function getQueryByProject($projectUuid) {
		if (!strpos($projectUuid, '+')) {

			// check for inactive or non-existant project
			//
			$project = Project::where('project_uid', '=', $projectUuid)->first();
			if (!$project || !$project->isActive()) {
				return AssessmentRun::getQuery();
			}

			// get by a single project
			//
			$assessmentRunsQuery = AssessmentRun::where('project_uuid', '=', $projectUuid);

			// add filters
			//
			$assessmentRunsQuery = TripletFilter::apply($assessmentRunsQuery, $projectUuid);
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

				if (!isset($assessmentRunsQuery)) {
					$assessmentRunsQuery = AssessmentRun::where('project_uuid', '=', $projectUuid);
				} else {
					$assessmentRunsQuery = $assessmentRunsQuery->orWhere('project_uuid', '=', $projectUuid);
				}

				// add filters
				//
				$assessmentRunsQuery = TripletFilter::apply($assessmentRunsQuery, $projectUuid);
			}
		}

		return $assessmentRunsQuery;
	}

	public function getAllByProject($projectUuid) {
		$assessmentRunsQuery = $this->getQueryByProject($projectUuid);

		// perform query
		//
		return $assessmentRunsQuery->get();
	}

	public function getByProject($projectUuid) {
		$assessmentRunsQuery = $this->getQueryByProject($projectUuid);

		// order results before applying filter
		//
		$assessmentRunsQuery = $assessmentRunsQuery->orderBy('create_date', 'DESC');

		// add limit filter
		//
		$assessmentRunsQuery = LimitFilter::apply($assessmentRunsQuery);

		// perform query
		//
		return $assessmentRunsQuery->get();
	}

	// get number by project
	//
	public function getNumByProject($projectUuid) {
		$assessmentRunsQuery = $this->getQueryByProject($projectUuid);

		// perform query
		//
		return $assessmentRunsQuery->count();
	}

	// get run requests
	//
	public function getRunRequests($assessmentRunUuid) {
		$assessmentRun = AssessmentRun::where('assessment_run_uuid', '=', $assessmentRunUuid)->first();
		return $assessmentRun->getRunRequests();
	}

	// get scheduled assessment runs by project
	//
	public function getScheduledByProject($projectUuid) {
		$assessmentRuns = $this->getAllByProject($projectUuid);

		// get one time run request
		//
		$oneTimeRunRequest = RunRequest::where('name', '=', 'One-time')->first();

		// compile list of non-one time assessment run requests
		//
		$assessmentRunRequests = new Collection;
		if ($oneTimeRunRequest) {
			foreach ($assessmentRuns as $assessmentRun) {
				$assessmentRunRequests = $assessmentRunRequests->merge(
					AssessmentRunRequest::where('assessment_run_id', '=', $assessmentRun->assessment_run_id)
					->where('run_request_id', '!=', $oneTimeRunRequest->run_request_id)->get());
			}
		} else {
			foreach ($assessmentRuns as $assessmentRun) {
				$assessmentRunRequests = $assessmentRunRequests->merge(
					AssessmentRunRequest::where('assessment_run_id', '=', $assessmentRun->assessment_run_id)->get());
			}
		}

		// get limit filter
		//
		$limit = Input::get('limit');

		// create scheduled assessment runs containing the run request
		//
		$scheduledAssessmentRuns = new Collection;
		foreach ($assessmentRunRequests as $assessmentRunRequest) {
			$scheduledAssessmentRun = AssessmentRun::where('assessment_run_id', '=', $assessmentRunRequest->assessment_run_id)->first()->toArray();
			$runRequest = RunRequest::where('run_request_id', '=', $assessmentRunRequest->run_request_id)->first();
			
			// return run requests up to limit
			//
			if (!$limit || sizeof($scheduledAssessmentRuns) < $limit) {
				$scheduledAssessmentRun['run_request'] = $runRequest->toArray();
				$scheduledAssessmentRuns->push($scheduledAssessmentRun);
			} else {
				break;
			}
		}

		return $scheduledAssessmentRuns;
	}

	// get number of scheduled assessment runs by project
	//
	public function getNumScheduledByProject($projectUuid) {
		$num = 0;
		$assessmentRuns = $this->getByProject($projectUuid);
		for ($i = 0; $i < sizeof($assessmentRuns); $i++) {
			$num += $assessmentRuns[$i]->getNumRunRequests();
		}
		return $num;
	}

	// update by index
	//
	public function updateIndex($assessmentRunUuid) {

		// get model
		//
		$assessmentRun = $this->getIndex($assessmentRunUuid);

		// update attributes
		//
		$assessmentRun->project_uuid = Input::get('project_uuid');
		$assessmentRun->package_uuid = Input::get('package_uuid');
		$assessmentRun->package_version_uuid = Input::get('package_version_uuid');
		$assessmentRun->tool_uuid = Input::get('tool_uuid');
		$assessmentRun->tool_version_uuid = Input::get('tool_version_uuid');
		$assessmentRun->platform_uuid = Input::get('platform_uuid');
		$assessmentRun->platform_version_uuid = Input::get('platform_version_uuid');

		// save and return changes
		//
		$changes = $assessmentRun->getDirty();
		$assessmentRun->save();
		return $changes;
	}

	// delete by index
	//
	public function deleteIndex($assessmentRunUuid) {
		$assessmentRun = AssessmentRun::where('assessment_run_uuid', '=', $assessmentRunUuid)->first();
		$assessmentRun->delete();
		return $assessmentRun;
	}
}