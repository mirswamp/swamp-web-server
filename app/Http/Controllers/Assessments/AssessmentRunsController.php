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
|        Copyright (C) 2012-2020 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Http\Controllers\Assessments;

use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Session;
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
use App\Http\Controllers\Results\ExecutionRecordsController;

class AssessmentRunsController extends BaseController
{
	// checkCompatibility
	//
	public function checkCompatibility(Request $request) {

		// parse parameters
		//
		$projectUuid = $request->input('project_uuid');
		$packageUuid = $request->input('package_uuid');
		$packageVersionUuid = $request->input('package_version_uuid');
		$platformUuid = $request->input('platform_uuid');
		$platformVersionUuid = $request->input('platform_version_uuid');

		// get specified models
		//
		$project = $projectUuid? Project::where('project_uid', '=', $projectUuid)->first() : null;
		$package = $packageUuid? Package::where('package_uuid', '=', $packageUuid)->first() : null;
		$packageVersion = $packageVersionUuid? PackageVersion::where('package_version_uuid', '=', $packageVersionUuid)->first() : null;
		$platform = $platformUuid? Platform::where('platform_uuid', '=', $platformUuid)->first() : null;
		$platformVersion = $platformVersionUuid? PlatformVersion::where('platform_version_uuid', '=', $platformVersionUuid)->first() : null;

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
	public function postCreate(Request $request) {

		// parse parameters
		//
		$projectUuid = $request->input('project_uuid');
		$packageUuid = $request->input('package_uuid');
		$packageVersionUuid = $request->input('package_version_uuid');
		$toolUuid = $request->input('tool_uuid');
		$toolVersionUuid = $request->input('tool_version_uuid');
		$platformUuid = $request->input('platform_uuid');
		$platformVersionUuid = $request->input('platform_version_uuid');

		// get specified models
		//
		$project = $projectUuid? Project::where('project_uid', '=', $projectUuid)->first() : null;
		$package = $packageUuid? Package::where('package_uuid', '=', $packageUuid)->first() : null;
		$packageVersion = $packageVersionUuid? PackageVersion::where('package_version_uuid', '=', $packageVersionUuid) : null;
		$tool = $toolUuid && $toolUuid != '*'? Tool::where('tool_uuid', '=', $toolUuid)->first() : null;
		$toolVersion = $toolVersionUuid? ToolVersion::where('tool_version_uuid', '=', $toolVersionUuid) : null;
		$platform = $platformUuid? Platform::where('platform_uuid', '=', $platformUuid)->first() : null;
		$platformVersion = $platformVersionUuid? PlatformVersion::where('platform_version_uuid', '=', $platformVersionUuid)->first() : null;

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
				$user = User::current();
				$permission = $tool->getPermission($project, $user);
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
					$user = User::current();
					$permission = $tool->getPermission($project, $user);
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
				'tool_version_uuid' => null,
				'platform_uuid' => $platformUuid,
				'platform_version_uuid' => $platformVersionUuid
			]);
		}
	}

	// get by index
	//
	public function getIndex(string $assessmentRunUuid): ?AssessmentRun {
		$user = User::current();
		$assessmentRun = AssessmentRun::where('assessment_run_uuid', '=', $assessmentRunUuid)->first();

		// check permissions
		//
		if ($assessmentRun) {
			$project = Project::where('project_uid', '=', $assessmentRun->project_uuid)->first();
		
			if (($user && $user->isAdmin()) || ($assessmentRun && $user->isMemberOf($project))) {
				return $assessmentRun;
			} else {
				return null;
			}
		}

		return $assessmentRun;
	}

	// get by project
	//
	public function getQueryByProject(Request $request, string $projectUuid) {
		if (!strpos($projectUuid, '+')) {

			// check for inactive or non-existant project
			//
			$project = Project::where('project_uid', '=', $projectUuid)->first();
			if (!$project || !$project->isActive()) {
				return AssessmentRun::getQuery();
			}

			// get by a single project
			//
			$query = AssessmentRun::where('project_uuid', '=', $projectUuid);

			// add filters
			//
			$query = TripletFilter::apply($request, $query, $projectUuid);
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

				if (!isset($query)) {
					$query = AssessmentRun::where('project_uuid', '=', $projectUuid);
				} else {
					$query = $query->orWhere('project_uuid', '=', $projectUuid);
				}

				// add filters
				//
				$query = TripletFilter::apply($request, $query, $projectUuid);
			}
		}

		return $query;
	}

	public function getAllByProject(Request $request, string $projectUuid): Collection {
		$query = $this->getQueryByProject($request, $projectUuid);

		// perform query
		//
		return $query->get();
	}

	public function getByProject(Request $request, string $projectUuid): Collection {
		$query = $this->getQueryByProject($request, $projectUuid);

		// order results before applying filter
		//
		$query = $query->orderBy('create_date', 'DESC');

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
		return $this->getQueryByProject($request, $projectUuid)->count();
	}

	// get run requests
	//
	public function getRunRequests(string $assessmentRunUuid): Collection {
		return AssessmentRun::where('assessment_run_uuid', '=', $assessmentRunUuid)->first()->getRunRequests();
	}

	// get scheduled assessment runs by project
	//
	public function getScheduledByProject(Request $request, string $projectUuid): Collection {
		$assessmentRuns = $this->getAllByProject($request, $projectUuid);

		// parse parameters
		//
		$limit = filter_var($request->input('limit'), FILTER_VALIDATE_INT);

		// get one time run request
		//
		$oneTimeRunRequest = RunRequest::where('name', '=', 'One-time')
			->where('project_uuid', '=', null)
			->first();

		// compile list of non-one time assessment run requests
		//
		$assessmentRunRequests = collect();
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

		// create scheduled assessment runs containing the run request
		//
		$scheduledAssessmentRuns = collect();
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
	public function getNumScheduledByProject(Request $request, string $projectUuid): int {
		$runs = $this->getScheduledByProject($request, $projectUuid);
		if ($runs) {
			return count($runs);
		} else {
			return 0;
		}
	}

	// update by index
	//
	public function updateIndex(Request $request, string $assessmentRunUuid) {

		// parse parameters
		//
		$projectUuid = $request->input('project_uuid');
		$packageUuid = $request->input('package_uuid');
		$packageVersionUuid = $request->input('package_version_uuid');
		$toolUuid = $request->input('tool_uuid');
		$toolVersionUuid = $request->input('tool_version_uuid');
		$platformUuid = $request->input('platform_uuid');
		$platformVersionUuid = $request->input('platform_version_uuid');

		// get model
		//
		$assessmentRun = $this->getIndex($assessmentRunUuid);
		if (!$assessmentRun) {
			return response("Assessment run not found.", 404);
		}

		// update attributes
		//
		$assessmentRun->project_uuid = $projectUuid;
		$assessmentRun->package_uuid = $packageUuid;
		$assessmentRun->package_version_uuid = $packageVersionUuid;
		$assessmentRun->tool_uuid = $toolUuid;
		$assessmentRun->tool_version_uuid = $toolVersionUuid;
		$assessmentRun->platform_uuid = $platformUuid;
		$assessmentRun->platform_version_uuid = $platformVersionUuid;

		// save and return changes
		//
		$changes = $assessmentRun->getDirty();
		$assessmentRun->save();
		return $changes;
	}

	// delete by index
	//
	public function deleteIndex(string $assessmentRunUuid) {
		$assessmentRun = AssessmentRun::where('assessment_run_uuid', '=', $assessmentRunUuid)->first();
		$assessmentRun->delete();
		return $assessmentRun;
	}
}