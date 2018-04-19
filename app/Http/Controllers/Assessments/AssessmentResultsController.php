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
|        Copyright (C) 2012-2018 Software Assurance Marketplace (SWAMP)        |
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
use App\Models\Users\User;
use App\Models\Users\UserPolicy;
use App\Models\Users\Permission;
use App\Models\Users\UserPermission;
use App\Models\Users\UserPermissionProject;
use App\Models\Projects\Project;
use App\Models\Tools\Tool;
use App\Models\Tools\ToolVersion;
use App\Models\Assessments\AssessmentResult;
use App\Models\Assessments\AssessmentRun;
use App\Models\Executions\ExecutionRecord;
use App\Models\Viewers\Viewer;
use App\Models\Viewers\ViewerInstance;
use App\Http\Controllers\BaseController;
use App\Utilities\Filters\DateFilter;
use App\Utilities\Filters\TripletFilter;
use App\Utilities\Filters\LimitFilter;
use App\Utilities\Strings\StringUtils;
use ErrorException;
use App\Services\HTCondorCollector;


class AssessmentResultsController extends BaseController {

	// return sample JSON results data
	//
	const SAMPLERESULTS = null;
	const SAMPLERRORS = null;
	// const SAMPLERESULTS = 'nativereport.json';
	// const SAMPLERRORS = 'errorreport.json';

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
			return [];
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

	static public function launchViewer($assessmentResultsUuid, $viewerUuid, $projectUuid) {

		// get latest version of viewer
		//
		$viewer = Viewer::where('viewer_uuid', '=', $viewerUuid)->first();
		$viewerVersion = $viewer->getLatestVersion();
		$viewerVersionUuid = $viewerVersion->viewer_version_uuid;

		// create stored procedure call
		//
		$connection = DB::connection('assessment');
		$pdo = $connection->getPdo();
		$stmt = $pdo->prepare("CALL launch_viewer(:assessmentResultsUuid, :userUuidIn, :viewerVersionUuid, :projectUuid, :destinationBasePath, @returnPath, @returnString, @viewerInstanceUuid);");
		$resultsDestination = config('app.outgoing');

		// bind params
		//
		$stmt->bindParam(":assessmentResultsUuid", $assessmentResultsUuid, PDO::PARAM_STR, 5000);
		$stmt->bindParam(":userUuidIn", $userUuidIn, PDO::PARAM_STR, 45);
		$stmt->bindParam(":viewerVersionUuid", $viewerVersionUuid, PDO::PARAM_STR, 45);
		$stmt->bindParam(":projectUuid", $projectUuid, PDO::PARAM_STR, 45);
		$stmt->bindParam(":destinationBasePath", $resultsDestination, PDO::PARAM_STR, 45);

		$userUuidIn = session('user_uid');
		$returnString = null;
		$viewerInstanceUuid = null;

		// call stored procedure
		//
		$results = $stmt->execute();

		// fetch return parameters
		//
		$select = $pdo->query('SELECT @returnPath, @returnString, @viewerInstanceUuid');
		$results = $select->fetchAll();

		return $results;
	}

	// get results for viewer
	//
	public function getResults($assessmentResultsUuid, $viewerUuid, $projectUuid) {

		// check project permissions
		//
		$result = $this->checkProject($projectUuid);
		if ($result !== true) {
			return $result;
		}

		// check results permissions
		//
		if ($assessmentResultsUuid != "none") {
			foreach (explode( ',',$assessmentResultsUuid ) as $resultUuid) {
				$assessmentResult = AssessmentResult::where('assessment_result_uuid','=',$resultUuid)->first();

				// check permissions on result
				//
				$user = User::getIndex(session('user_uid'));
				$result = $assessmentResult->checkPermissions($user);

				// if not true, return permissions error
				//
				if ($result !== true) {
					return response()->json($result, 403);
				}
			}
		}

		// check for sample test results
		//
		if (self::SAMPLERESULTS) {

			// return results
			//
			return [
				"assessment_results_uuid" => $assessmentResultsUuid,
				"results" => json_decode(file_get_contents(__DIR__.'/'.self::SAMPLERESULTS), true),
				"results_status" => 'SUCCESS'
			];
		}

		// check for sample error results
		//
		if (self::SAMPLERRORS) {

			// return results
			//
			return [
				"assessment_results_uuid" => $assessmentResultsUuid,
				"results" => $this->getJSONErrors(file_get_contents(__DIR__.'/'.self::SAMPLERRORS), $assessmentResultsUuid),
				"results_status" => 'FAILED'
			];
		}

		// fetch results
		//
		$viewer = Viewer::where('viewer_uuid', '=', $viewerUuid)->first();

		// check for cached results in storage directory
		//
		if (strtolower($viewer->name) == 'native') {
			$resultsDir = config('app.outgoing').'/'.$assessmentResultsUuid.'/'.'nativereport.json';
			if (file_exists($resultsDir)) {
				$results = file_get_contents($resultsDir, $assessmentResultsUuid);

				// return results
				//
				return [
					"assessment_results_uuid" => $assessmentResultsUuid,
					"results" => $this->getJSONResults($results, $assessmentResultsUuid),
					"results_status" => 'SUCCESS'
				];
			}
		}

		// get viewer version from viewer
		//
		$viewerVersion = $viewer->getLatestVersion();	

		// look up existing viewer instance from database 
		//
		if ($viewerVersion) {	
			$viewerInstance = ViewerInstance::where('viewer_version_uuid', '=', $viewerVersion->viewer_version_uuid)->where('project_uuid', '=', $projectUuid)->first();
			if ($viewerInstance) {	
				$viewerInstanceUuid = $viewerInstance->viewer_instance_uuid;
				$instance = HTCondorCollector::getViewerInstance($viewerInstanceUuid); 
				Log::info("getResults - state: " . $instance->stateToName());

				if ($instance->isBlocked()) {
					Log::info("getResults - state: " . $instance->stateToName() . " isBlocked");
					return [
                		"assessment_results_uuid" => $assessmentResultsUuid,
                		"results" => null,
                		"results_status" => 'TRYAGAIN'
					];
				} else if ($instance->isLoading()) {
					Log::info("getResults - state: " . $instance->stateToName() . " isLoading");
					return [
						"results_viewer_status" => 'Loading',
						"results_status" => 'LOADING',
						"viewer_instance" => $viewerInstanceUuid
					];
				} else if (!$instance->isOKToLaunch() && !$instance->isReady()) {
					Log::info("getResults - state: " . $instance->stateToName() . " not isOKToLaunch and not isReady");
					return [
                		"assessment_results_uuid" => $assessmentResultsUuid,
                		"results" => null,
                		"results_status" => 'NOLAUNCH'
            		];
  
				}
			}	
		}

		// call stored procedure
		//
		$results = self::launchViewer($assessmentResultsUuid, $viewerUuid, $projectUuid);

		// set param values
		//
		if ($assessmentResultsUuid == 'none') {
			$assessmentResultsUuid = '';
		}

		$returnPath = $results[0]["@returnPath"];
		$returnString = $results[0]["@returnString"];
		$viewerInstanceUuid = $results[0]["@viewerInstanceUuid"];

		// log viewer launching
		//
		Log::info("Launching viewer.", [
			'viewer_instance_uuid' => $viewerInstanceUuid
		]);

		if (StringUtils::endsWith($returnPath, 'index.html')) {

			// return a link to the results web page
			//
			$resultsUrl = str_replace('/swamp/outgoing', config('app.url').'/results', $returnPath);
			return [
				"assessment_results_uuid" => $assessmentResultsUuid,
				"results_url" => $resultsUrl,
				"results_status" => $returnString
			];
		} else if (StringUtils::endsWith($returnPath, '.html') || StringUtils::endsWith($returnPath, '.json')) {

			// return native results
			//
			return $this->getNativeResults($assessmentResultsUuid, $returnPath, $returnString);
		} else {

			// return viewer results
			//
			return $this->getViewerResults($assessmentResultsUuid, $viewerInstanceUuid, $returnPath, $returnString);
		}
	}

	//
	// utility results methods
	//

	public function getNativeResults($assessmentResultsUuid, $returnPath, $returnString) {

		// return native results data
		//
		$options = [
			"ssl" => [
				"verify_peer" => false,
				"verify_peer_name" => false
			]
		];
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

		// parse the failed json report
		//
		if (StringUtils::endsWith($returnPath, 'failedreport.json')) {

			// for failedreport, mark returnString as Failed and parse the failed JSON
			//
			$returnString = 'FAILED';
			$results = $this->getJSONErrors($results, $assessmentResultsUuid);
		}

		// parse the success json report
		if (StringUtils::endsWith($returnPath, 'nativereport.json')) {
			$results = $this->getJSONResults($results, $assessmentResultsUuid);
		}

		if ($results) {

			// return results
			//
			return [
				"assessment_results_uuid" => $assessmentResultsUuid,
				"results" => $results,
				"results_status" => $returnString
			];
		} else {
			return response('Could not return results from '.$returnPath, 500);
		}
		
		return [
			"assessment_results_uuid" => $assessmentResultsUuid,
			"results" => file_get_contents($returnUrl),
			"results_status" => $returnString
		];
	}

	public function getViewerResults($assessmentResultsUuid, $viewerInstanceUuid, $returnPath, $returnString) {

		// get url/status from viewer instance if present
		// otherwise just use what database gave us.
		// FIXME viewer is always present when url has no .html?
		//
		if ($viewerInstanceUuid) {
			$instance = HTCondorCollector::getViewerInstance($viewerInstanceUuid);
			Log::info("getViewerResults - state: " . $instance->stateToName());

			// TODO what is return value of status when returns immediately

			if ($instance->isLoading() || !$instance->isReady()) {
				Log::info("getViewerResults - state: " . $instance->stateToName() . " isLoading or not isReady");
				return [
					"results_viewer_status" => $instance->status,
					"results_status" => 'LOADING',
					"viewer_instance" => $viewerInstanceUuid
				];
			} else {

				// if proxy url, return it
				//
				$connection = DB::connection('assessment');
				$pdo = $connection->getPdo();
				$pdo->query("CALL select_system_setting ('CODEDX_BASE_URL',@rtn);");
				$base_url  = $pdo->query("SELECT @rtn")->fetchAll()[0]["@rtn"];
				if ($base_url) {
					$returnUrl = $base_url.$instance->proxy_url;

					// log success
					//
					Log::info("Successfully launched viewer.", [
						'viewer_instance_uuid' => $viewerInstanceUuid
					]);

					return [
						"assessment_results_uuid" => $assessmentResultsUuid,
						"results_url" => $returnUrl,
						"results_status" => $returnString
					];
				}
			}
		}

		return [
			"assessment_results_uuid" => $assessmentResultsUuid,
			"results_url" => config('app.url').'/'.$returnPath,
			"results_status" => $returnString
		];
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

	// parse JSON errors
	//
	public function getJSONErrors($results, $assessmentResultUuid) {
		$results = json_decode($results, true);
		$assessmentResult = AssessmentResult::where('assessment_result_uuid','=',$assessmentResultUuid)->first();

		// append error link
		//
		$resultsDir = '/swamp/SCAProjects/'.$assessmentResult->project_uuid.'/A-Results/';
        $results['url'] = config('app.url').str_replace($resultsDir, '/results/', $assessmentResult->file_path);

		return $results;
	}

	// parse JSON results
	//
	public function getJSONResults($results, $assessmentResultUuid) {

		// parse parameters
		//
		$from = filter_var(Input::get('from'), FILTER_VALIDATE_INT);
		$to = filter_var(Input::get('to'), FILTER_VALIDATE_INT);

		// parse results
		//
		$results = json_decode($results, true);
		$assessmentResult = AssessmentResult::where('assessment_result_uuid','=',$assessmentResultUuid)->first();

		// find bug count
		//
		if (isset($results['AnalyzerReport']['BugInstances'])) {
			$bugCount = sizeof($results['AnalyzerReport']['BugInstances']);
		} else {
			$bugCount = 0;
		}

		// select results
		//
		if ($bugCount > 1) {
			if ($from != null && $to != null) {
				$results['AnalyzerReport']['BugInstances'] = array_slice($results['AnalyzerReport']['BugInstances'], $from - 1, $to - $from + 1);
			} else if ($from != null) {
				$results['AnalyzerReport']['BugInstances'] = array_slice($results['AnalyzerReport']['BugInstances'], $from - 1);
			} else if ($to != null) {
				$results['AnalyzerReport']['BugInstances'] = array_slice($results['AnalyzerReport']['BugInstances'], 0, $to);
			}
		}

		// append bug count
		//
		$results['AnalyzerReport']['BugCount'] = $bugCount;

		// append triplet info to results
		//
		$executionRecord = ExecutionRecord::where('execution_record_uuid', '=', $assessmentResult->execution_record_uuid)->first();
	
		$results['AnalyzerReport']['package'] = $executionRecord->package;
		$results['AnalyzerReport']['tool'] = $executionRecord->tool;
		$results['AnalyzerReport']['platform'] = $executionRecord->platform;

		// append create date
		//
		$results['AnalyzerReport']['create_date'] = $assessmentResult->create_date->format('Y/m/d H:i:s');

		return $results;
	}

	// Get SCARF results as XML file
	//
	public function getScarf($assessmentResultsUuid) {
		if ($assessmentResultsUuid != "none") {
			foreach (explode( ',', $assessmentResultsUuid) as $resultUuid) {
				try {
					$assessmentResult = AssessmentResult::where('assessment_result_uuid', '=', $resultUuid)->first();
					$executionRecord = ExecutionRecord::where('execution_record_uuid', '=', $assessmentResult->execution_record_uuid)->first();
					$assessmentRun = AssessmentRun::where('assessment_run_uuid', '=', $executionRecord->assessment_run_uuid)->first();
				} catch (\ErrorException $e) {
					return response()->json([
						'error' => 'not_found',
						'error_description' => 'No results found for the provided assessment result UUID.'
					], 404);
				}
				
				if ($assessmentRun) {
					$user = User::getIndex(session('user_uid'));
					$result = $assessmentResult->checkPermissions($user);

					// if not true, return permissions error
					//
					if ($result !== true) {
						return response()->json($result, 403);
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
		if ($assessmentResultsUuid == 'none') {
			$assessmentResultsUuid = '';
		}
		$userUuidIn = session('user_uid');
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
		Log::info("In downloadResults.", [
			'returnUrl' => $returnUrl, 
			'returnFile' => $returnFile,
			'returnString' => $returnString
		]);

		if (strcmp($returnString,'SUCCESS') == 0) {
			if (strlen($returnFile) > 0) {
				if (substr_compare($returnFile, '.xml', -4, 4, true) == 0) {

					// ALL GOOD! Download the XML file as 'scarf.xml'
					//
					$headers = [
						'Content-Type: text/xml',
						'charset=UTF-8',
					];
					if (strcasecmp(Request::header('Accept-Encoding'),'gzip') == 0) {
						$headers[] = 'Content-Encoding: gzip';
					}
					return response()->download($returnFile,'scarf.xml',$headers);
				} else { 

					// file type is not XML
					//
					return response()->json([
						'error' => 'not_found',
						'error_description' => 'The results were not SCARF-formatted XML.'
					], 404);
				}
			} else { 

				// return file is blank
				//
				return response()->json([
					'error' => 'not_found',
					'error_description' => 'No results found for the provided assessment result UUID.'
				], 404);
			}
		} else {

			// return string is not SUCCESS - return the error message if set
			//
			if (strcmp($returnString,'ERROR: RESULT NOT FOUND') == 0) {
				return response()->json([
					'error' => 'not_found',
					'error_description' => 'No results found for the provided assessment result UUID.'
				], 404);
			} elseif (strcmp($returnString,'ERROR: PROJECT NOT FOUND') == 0) {
				return response()->json([
					'error' => 'not_found',
					'error_description' => 'No project found for the provided assessment result UUID.'
				], 404);
			} elseif (strcmp($returnString,'ERROR: USER ACCOUNT NOT VALID') == 0) {
				return response()->json([
					'error' => 'permission_denied',
					'error_description' => 'The client does not have permission to access the assessment result.'
				], 403);
			} elseif (strcmp($returnString,'ERROR: USER PROJECT PERMISSION NOT VALID') == 0) {
				return response()->json([
					'error' => 'permission_denied',
					'error_description' => 'User project permissions for the assessment are invalid.'
				], 403);

			/*
			} elseif (strcmp($returnString,'ERROR: DOWNLOADING OF COMMERCIAL TOOL RESULTS NOT ALLOWED') == 0) {
				return response()->json([
					'error' => 'permission_denied',
					'error_description' => 'Downloading of results generated by commercial tools is not permitted.'
				], 403);
			*/
				
			} else {

				// 'ERROR: UNSPECIFIED ERROR' or some other error response
				//
				return response()->json([
					'error' => 'internal_error',
					'error_description' => 'The SWAMP server encountered an internal error when processing the request.'
				], 500);
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
			$user = User::getIndex(session('user_uid'));
			$result = $assessmentResult->checkPermissions($user);

			// if not true, return permissions error
			//
			if ($result !== true) {
				return $result;
			}
		}
	}

	private function checkProject($projectUuid) {
		$currentUser = User::getIndex(session('user_uid'));
		$project = Project::where('project_uid', '=', $projectUuid)->first();
		if ($project && !$project->isReadableBy($currentUser)) {
			return response('Insufficient priveleges to access project.', 403);
		} else {
			return true;
		}
	}

	// get status of launching viewer, and then return results
	//
	public function getInstanceStatus($viewerInstanceUuid) {

		$connection = DB::connection('assessment');
		$pdo = $connection->getPdo();

		$instance = HTCondorCollector::getViewerInstance($viewerInstanceUuid);
		Log::info("getInstanceStatus - state: " . $instance->stateToName());
		// TODO what is return value of status when returns immediately

		// if proxy url, return it
		//
		if ($instance->isReady()) {
			Log::info("getInstanceStatus - state: " . $instance->stateToName() . " isReady");
			$pdo->query("CALL select_system_setting ('CODEDX_BASE_URL',@rtn);");
			$base_url  = $pdo->query("SELECT @rtn")->fetchAll()[0]["@rtn"];
			if ($base_url) {
				$returnUrl = $base_url.$instance->proxy_url;

				// log success
				//
				Log::info("Successfully launched viewer.", [
					'viewer_instance_uuid' => $viewerInstanceUuid
				]);

				return [
					"results_url" => $returnUrl,
					"results_status" => "SUCCESS"
				];
			}
		}

		// otherwise return viewer status
		//
		else if ($instance->isLoading()) {
			Log::info("getInstanceStatus - state: " . $instance->stateToName() . " isLoading");
			return [
				"results_viewer_status" => $instance->status,
				"results_status" => "LOADING",
				"viewer_instance" => $viewerInstanceUuid
			];
		}
		else {
			return [
				"results_viewer_status" => 'Terminated',
				"results_status" => "CLOSED",
				"viewer_instance" => $viewerInstanceUuid
			];
		}
	}
}
