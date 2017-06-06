<?php
/******************************************************************************\
|                                                                              |
|                        AssessmentResultsController.php                       |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a controller for assessment results.                     |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2017 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Http\Controllers\Assessments;

use PDO;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use App\Models\Assessments\AssessmentResult;
use App\Models\Assessments\AssessmentRun;
use App\Models\Executions\ExecutionRecord;
use App\Models\Tools\Tool;
use App\Models\Viewers\Viewer;
use App\Models\Viewers\ViewerInstance;
use App\Models\Users\User;
use App\Models\Users\Permission;
use App\Models\Users\UserPolicy;
use App\Models\Users\UserPermission;
use App\Models\Users\UserPermissionProject;
use App\Models\Projects\Project;
use App\Models\Tools\ToolVersion;
use App\Http\Controllers\BaseController;
use App\Utilities\Filters\DateFilter;
use App\Utilities\Filters\TripletFilter;
use App\Utilities\Filters\LimitFilter;
use App\Utilities\Strings\StringUtils;
use ErrorException;
use App\Services\HTCondorCollector;


class AssessmentResultsController extends BaseController {

	// get by index
	//
	public function getIndex($assessmentResultsUuid) {
		return AssessmentResult::where('assessment_result_uuid', '=', $assessmentResultsUuid)->first();
	}

	// get by project
	//
	public function getByProject($projectUuid) {

		// check for inactive or non-existant project
		//
		$project = Project::where('project_uid', '=', $projectUuid)->first();
		if (!$project || !$project->isActive()) {
			return array();
		}

		$assessmentResultsQuery = AssessmentResult::where('project_uuid', '=', $projectUuid);

		// add filters
		//
		$assessmentResultsQuery = DateFilter::apply($assessmentResultsQuery);
		$assessmentResultsQuery = TripletFilter::apply($assessmentResultsQuery, $projectUuid);

		// order results before applying filter
		//
		$assessmentResultsQuery = $assessmentResultsQuery->orderBy('create_date', 'DESC');

		// add limit filter
		//
		$assessmentResultsQuery = LimitFilter::apply($assessmentResultsQuery);

		// perform query
		//
		return $assessmentResultsQuery->get();
	}

	// get results for viewer
	//
	public function getResults($assessmentResultsUuid, $viewerUuid, $projectUuid) {

		// get latest version of viewer
		//
		$viewer = Viewer::where('viewer_uuid', '=', $viewerUuid)->first();
		$viewerVersion = $viewer->getLatestVersion();
		$viewerVersionUuid = $viewerVersion->viewer_version_uuid;

		if ($assessmentResultsUuid != "none") {
			foreach( explode( ',',$assessmentResultsUuid ) as $resultUuid ){
				$assessmentResult = AssessmentResult::where('assessment_result_uuid','=',$resultUuid)->first();

				// check permissions on result
				//
				$result = $this->checkPermissions($assessmentResult);
				if ($result !== true) {
					return $result;
				}
			}
		}

		// create stored procedure call
		//
		$connection = DB::connection('assessment');
		$pdo = $connection->getPdo();
		$stmt = $pdo->prepare("CALL launch_viewer(:assessmentResultsUuid, :userUuidIn, :viewerVersionUuid, :projectUuid, :destinationBasePath, @returnPath, @returnString, @viewerInstanceUuid);");
		$resultsDestination = Config::get('app.outgoing');

		// bind params
		//
		$stmt->bindParam(":assessmentResultsUuid", $assessmentResultsUuid, PDO::PARAM_STR, 5000);
		$stmt->bindParam(":userUuidIn", $userUuidIn, PDO::PARAM_STR, 45);
		$stmt->bindParam(":viewerVersionUuid", $viewerVersionUuid, PDO::PARAM_STR, 45);
		$stmt->bindParam(":projectUuid", $projectUuid, PDO::PARAM_STR, 45);
		$stmt->bindParam(":destinationBasePath", $resultsDestination, PDO::PARAM_STR, 45);

		// set param values
		//
		if ($assessmentResultsUuid == 'none') {
			$assessmentResultsUuid = '';
		}
		$userUuidIn = Session::get('user_uid');
		$returnString = null;
		$viewerInstanceUuid = null;

		// call stored procedure
		//
		$results = $stmt->execute();

		// fetch return parameters
		//
		$select = $pdo->query('SELECT @returnPath, @returnString, @viewerInstanceUuid');
		$results = $select->fetchAll();
		$returnPath = $results[0]["@returnPath"];
		$returnString = $results[0]["@returnString"];
		$viewerInstanceUuid = $results[0]["@viewerInstanceUuid"];

		// log viewer launching
		//
		Log::info("Launching viewer.",
			array('viewer_instance_uuid' => $viewerInstanceUuid)
		);

		if (StringUtils::endsWith($returnPath, '.html')) {
			$options=array(
				"ssl"=>array(
					"verify_peer"=>false,
					"verify_peer_name"=>false
				)
			);
			$results = @file_get_contents($returnPath, false, stream_context_create($options));

			// remove results file
			//
			//unlink($returnPath);

			// remove all files in results directory
			//
			//array_map('unlink', glob(dirname($returnPath).'/*.*'));

			// remove results directory
			//
			//rmdir(dirname($returnPath));

			// recursively remove results folder
			//
			if (StringUtils::endsWith($returnPath, 'nativereport.html')) {
				self::rrmdir(dirname($returnPath));
			}

			if ($results) {

				// return results
				//
				return array(
					"assessment_results_uuid" => $assessmentResultsUuid,
					"results" => $results,
					"results_status" => $returnString
				);
			} else {
				return response('Could not return results from '.$returnPath, 500);
			}
			
			// return results
			//
			return array(
				"assessment_results_uuid" => $assessmentResultsUuid,
				"results" => file_get_contents($returnUrl),
				"results_status" => $returnString
			);
		} else {

			// get url/status from viewer instance if present
			// otherwise just use what database gave us.
			// FIXME viewer is always present when url has no .html?
			if ($viewerInstanceUuid) {
				$instance = HTCondorCollector::getViewerInstance($viewerInstanceUuid);
				// TODO what is return value of status when returns immediately

				// if proxy url, return it
				//
				if ($instance->state && ($instance->state == 2) && $instance->proxy_url) {
					$pdo->query("CALL select_system_setting ('CODEDX_BASE_URL',@rtn);");
					$base_url  = $pdo->query("SELECT @rtn")->fetchAll()[0]["@rtn"];
					if ($base_url) {
						$returnUrl = $base_url.$instance->proxy_url;

						// log success
						//
						Log::info("Successfully launched viewer.",
							array('viewer_instance_uuid' => $viewerInstanceUuid)
						);

						return array(
							"assessment_results_uuid" => $assessmentResultsUuid,
							"results_url" => $returnUrl,
							"results_status" => $returnString
						);
					}
				}

				// otherwise return viewer status
				//
				else {
					return array(
						"results_viewer_status" => $instance->status,
						"results_status" => "LOADING",
						"viewer_instance" => $viewerInstanceUuid
					);
				}
			}

			// return results url
			//
			return array(
				"assessment_results_uuid" => $assessmentResultsUuid,
				"results_url" => Config::get('app.url').'/'.$returnPath,
				"results_status" => $returnString
			);
		}
	}

	public static function rrmdir($dir) {
		if (is_dir($dir)) {
			$objects = scandir($dir);
			foreach ($objects as $object) {
				if ($object != '.' && $object != '..') {
					if (filetype($dir.'/'.$object) == 'dir') {
						self::rrmdir($dir.'/'.$object); 
					} else {
						unlink($dir.'/'.$object);
					}
				}
			}
		}
		reset($objects);
		rmdir($dir);
	}

	// Get SCARF results as XML file
	//
	public function getScarf($assessmentResultsUuid) {

		if ($assessmentResultsUuid != "none") {
			foreach( explode( ',',$assessmentResultsUuid ) as $resultUuid ){
				try {
					$assessmentResult = AssessmentResult::where('assessment_result_uuid', '=', $resultUuid)->first();
					$executionRecord = ExecutionRecord::where('execution_record_uuid', '=', $assessmentResult->execution_record_uuid)->first();
					$assessmentRun = AssessmentRun::where('assessment_run_uuid', '=', $executionRecord->assessment_run_uuid)->first();
				} catch (\ErrorException $e) {
					return response()->json(array(
						'error' => 'not_found',
						'error_description' => 'No results found for the provided assessment result UUID.'
						),404);
				}
				
				if ($assessmentRun) {
					$result = $this->checkPermissions($assessmentResult);

					if ($result !== true) {

						// Check reponse contents. If JSON, return it, otherwise make new JSON response.
						//
						$content = @$result->getContent();
						json_decode($content);
						if ((json_last_error() == JSON_ERROR_NONE) && (strlen($content) > 0)) {
							return $result;
						} else {
							return response()->json(array(
								'error' => 'internal_error',
								'error_description' => 'The SWAMP server encountered an internal error when processing the request.'
								),500);
						}
					}
				}
			}
		}

		// create stored procedure call
		//
		$connection = DB::connection('assessment');
		$pdo = $connection->getPdo();
		$stmt = $pdo->prepare("CALL download_results(:assessmentResultsUuid, :userUuidIn, :returnType, @returnUrl, @returnFile, @returnString);");

		// set param values
		//
		if( $assessmentResultsUuid == 'none' ){
			$assessmentResultsUuid = '';
		}
		$userUuidIn = Session::get('user_uid');
		$returnType = 'scarf';
		$returnUrl = null;
		$returnFile = null;
		$returnString = null;

		// bind params
		//
		$stmt->bindParam(":assessmentResultsUuid", $assessmentResultsUuid, PDO::PARAM_STR, 5000);
		$stmt->bindParam(":userUuidIn", $userUuidIn, PDO::PARAM_STR, 45);
		$stmt->bindParam(":returnType", $returnType, PDO::PARAM_STR, 10);

		// call stored procedure
		//
		$results = $stmt->execute();

		// fetch return parameters
		//
		$select = $pdo->query('SELECT @returnUrl, @returnFile, @returnString');
		$results = $select->fetchAll();
		$returnUrl = $results[0]["@returnUrl"];
		$returnFile = $results[0]["@returnFile"];
		$returnString = $results[0]["@returnString"];

		// log returned download url
		//
		Log::info("In downloadResults.",
			array(
				'returnUrl' => $returnUrl, 
				'returnFile' => $returnFile,
				'returnString' => $returnString
			)
		);

		if (strcmp($returnString,'SUCCESS') == 0) {
			if (strlen($returnFile) > 0) {
				if (substr_compare($returnFile,'.xml',-4,4,true) == 0) {
					// ALL GOOD! Download the XML file as 'scarf.xml'
					$headers = array(
						'Content-Type: text/xml',
						'charset=UTF-8',
						);
					if (strcasecmp(Request::header('Accept-Encoding'),'gzip') == 0) {
						$headers[] = 'Content-Encoding: gzip';
					}
					return response()->download($returnFile,'scarf.xml',$headers);
				} else { // File type is not XML
					return response()->json(array(
						'error' => 'not_found',
						'error_description' => 'The results were not SCARF-formatted XML.'
						),404);
				}
			} else { // $returnFile is blank
				return response()->json(array(
					'error' => 'not_found',
					'error_description' => 'No results found for the provided assessment result UUID.'
					),404);
			}
		} else { // $returnString is not SUCCESS - return the error message if set
			if (strcmp($returnString,'ERROR: RESULT NOT FOUND') == 0) {
				return response()->json(array(
					'error' => 'not_found',
					'error_description' => 'No results found for the provided assessment result UUID.'
					),404);
			} elseif (strcmp($returnString,'ERROR: PROJECT NOT FOUND') == 0) {
				return response()->json(array(
					'error' => 'not_found',
					'error_description' => 'No project found for the provided assessment result UUID.'
					),404);
			} elseif (strcmp($returnString,'ERROR: USER ACCOUNT NOT VALID') == 0) {
				return response()->json(array(
					'error' => 'permission_denied',
					'error_description' => 'The client does not have permission to access the assessment result.'
					),403);
			} elseif (strcmp($returnString,'ERROR: USER PROJECT PERMISSION NOT VALID') == 0) {
				return response()->json(array(
					'error' => 'permission_denied',
					'error_description' => 'User project permissions for the assessment are invalid.'
					),403);
			} elseif (strcmp($returnString,'ERROR: DOWNLOADING OF COMMERCIAL TOOL RESULTS NOT ALLOWED') == 0) {
				return response()->json(array(
					'error' => 'permission_denied',
					'error_description' => 'Downloading of results generated by commercial tools is not permitted.'
					),403);
			} else { // 'ERROR: UNSPECIFIED ERROR' or some other error response
				return response()->json(array(
					'error' => 'internal_error',
					'error_description' => 'The SWAMP server encountered an internal error when processing the request.'
					),500);
			}
		}
	}

	public function getNoResultsPermission($viewerUuid, $projectUuid) {
		return response('approved', 200);
	}

	public function getResultsPermission($assessmentResultsUuid, $viewerUuid, $projectUuid) {
		
		// check for no results
		//
		if ($assessmentResultsUuid == 'none') {
			return response('approved', 200);
		}

		// check permission on each result
		//
		foreach (explode( ',',$assessmentResultsUuid ) as $resultUuid) {
			$assessmentResult = AssessmentResult::where('assessment_result_uuid','=',$resultUuid)->first();
			$result = $this->checkPermissions($assessmentResult);

			// if not true, return permissions error
			//
			if ($result !== true) {
				return $result;
			}
		}
	}

	private function checkPermissions($assessmentResult) {

		// return if no tool
		//
		$executionRecord = ExecutionRecord::where('execution_record_uuid', '=', $assessmentResult->execution_record_uuid)->first();
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
			$user = User::getIndex(Session::get('user_uid'));

			// check for no tool permission
			//
			$permission = Permission::where('policy_code', '=', $tool->policy_code)->first();
			/*
			if (!$permission) {
				return response()->json(array(
					'status' => 'tool_no_permission',
					'tool_name' => $tool->name
				), 404);
			}
			*/

			// check for no project
			//
			$project = Project::where('project_uid', '=', $assessmentResult->project_uuid)->first();
			/*
			if (!$project) {
				return response()->json(array(
					'status' => 'no_project'
				), 404);
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

			// if the project hasn't been designated
			//
			if ($tool->isRestrictedByProject()) {
				$userPermissionProject = UserPermissionProject::where('user_permission_uid', '=', $userPermission->user_permission_uid)->where('project_uid', '=', $assessmentRun->project_uuid)->first();
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
			/*
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
			*/

			// if the policy hasn't been accepted, return error
			//
			$userPolicy	= UserPolicy::where('policy_code', '=', $tool->policy_code)->where('user_uid', '=', $user->user_uid)->first();
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

	// get status of launching viewer, and then return results
	//
	public function getInstanceStatus($viewerInstanceUuid) {

		$connection = DB::connection('assessment');
		$pdo = $connection->getPdo();

		$instance = HTCondorCollector::getViewerInstance($viewerInstanceUuid);
		// TODO what is return value of status when returns immediately

		// if proxy url, return it
		//
		if($instance->state && ($instance->state == 2) && $instance->proxy_url) {
			$pdo->query("CALL select_system_setting ('CODEDX_BASE_URL',@rtn);");
			$base_url  = $pdo->query("SELECT @rtn")->fetchAll()[0]["@rtn"];
			if($base_url) {
				$returnUrl = $base_url.$instance->proxy_url;

				// log success
				//
				Log::info("Successfully launched viewer.",
					array('viewer_instance_uuid' => $viewerInstanceUuid)
				);

				return array(
					"results_url" => $returnUrl,
					"results_status" => "SUCCESS"
				);
			}
		}

		// otherwise return viewer status
		//
		else {
			return array(
				"results_viewer_status" => $instance->status,
				"results_status" => "LOADING",
				"viewer_instance" => $viewerInstanceUuid
			);
		}
	}
}
