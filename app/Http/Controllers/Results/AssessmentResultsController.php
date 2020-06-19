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
|        Copyright (C) 2012-2020 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Http\Controllers\Results;

use PDO;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Log;
use App\Models\Users\User;
use App\Models\Users\UserPolicy;
use App\Models\Users\Permission;
use App\Models\Users\UserPermission;
use App\Models\Users\UserPermissionProject;
use App\Models\Projects\Project;
use App\Models\Tools\Tool;
use App\Models\Tools\ToolVersion;
use App\Models\Assessments\AssessmentRun;
use App\Models\Results\ExecutionRecord;
use App\Models\Results\AssessmentResult;
use App\Models\Viewers\Viewer;
use App\Models\Viewers\ViewerInstance;
use App\Http\Controllers\BaseController;
use App\Utilities\Filters\DateFilter;
use App\Utilities\Filters\TripletFilter;
use App\Utilities\Filters\LimitFilter;
use App\Utilities\Strings\StringUtils;
use ErrorException;
use App\Services\HTCondorCollector;

class AssessmentResultsController extends BaseController
{
	// get by index
	//
	public function getIndex(string $assessmentResultsUuid): ?AssessmentResult {
		return AssessmentResult::where('assessment_result_uuid', '=', $assessmentResultsUuid)->first();
	}

	// get by project
	//
	public function getByProject(Request $request, string $projectUuid): Collection {

		// check for inactive or non-existant project
		//
		$project = Project::where('project_uid', '=', $projectUuid)->first();
		if (!$project || !$project->isActive()) {
			return [];
		}

		// create query
		//
		$query = AssessmentResult::where('project_uuid', '=', $projectUuid);

		// add filters
		//
		$query = DateFilter::apply($request, $query);
		$query = TripletFilter::apply($request, $query, $projectUuid);

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

	public static function launchViewer(string $assessmentResultsUuid, string $viewerUuid, string $projectUuid, string $resultsType = 'results') {

		// get latest version of viewer
		//
		$viewer = Viewer::where('viewer_uuid', '=', $viewerUuid)->first();
		$viewerVersion = $viewer->getLatestVersion();
		$viewerVersionUuid = $viewerVersion->viewer_version_uuid;

		// create stored procedure call
		//
		$connection = DB::connection('assessment');
		$pdo = $connection->getPdo();
		$stmt = $pdo->prepare("CALL launch_viewer(:assessmentResultsUuid, :userUuidIn, :viewerVersionUuid, :projectUuid, :destinationBasePath, :resultsType, @returnPath, @returnString, @viewerInstanceUuid);");
		$resultsDestination = config('app.outgoing');

		// bind params
		//
		$stmt->bindParam(":assessmentResultsUuid", $assessmentResultsUuid, PDO::PARAM_STR, 5000);
		$stmt->bindParam(":userUuidIn", $userUuidIn, PDO::PARAM_STR, 45);
		$stmt->bindParam(":viewerVersionUuid", $viewerVersionUuid, PDO::PARAM_STR, 45);
		$stmt->bindParam(":projectUuid", $projectUuid, PDO::PARAM_STR, 45);
		$stmt->bindParam(":destinationBasePath", $resultsDestination, PDO::PARAM_STR, 45);
		$stmt->bindParam(":resultsType", $resultsType, PDO::PARAM_STR, 45);

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
	public function getResults(Request $request, string $assessmentResultsUuid, string $viewerUuid, string $projectUuid) {

		// parse optional parameters
		//
		$resultsType = $request->input('type', 'results');

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
				$user = User::current();
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
		switch ($resultsType) {

			// return sample warnings
			//
			case 'warnings':
				if (config('app.sample_warnings')) {
					$info = $this->getAssessmentResultsInfo($assessmentResultsUuid);
					$contents = file_get_contents(config('app.sample_warnings'));
					$results = json_decode($contents, true);
					return [
						"assessment_results_uuid" => $assessmentResultsUuid,
						"results" => array_merge($info, $results),
						"results_status" => 'SUCCESS'
					];
				}
				break;

			// return sample errors
			//
			case 'errors':
				if (config('app.sample_errors')) {
					$info = $this->getAssessmentResultsInfo($assessmentResultsUuid);
					$contents = file_get_contents(config('app.sample_errors'));
					$results = $this->getJSONErrors($contents);
					return [
						"assessment_results_uuid" => $assessmentResultsUuid,
						"results" => array_merge($info, $results),
						"results_status" => 'SUCCESS'
					];
				}
				break;

			// return sample results
			//
			case 'results':
			default:
				if (config('app.sample_results')) {
					$info = $this->getAssessmentResultsInfo($assessmentResultsUuid);
					$contents = file_get_contents(config('app.sample_results'));
					$results = json_decode($contents, true);
					$results = $this->selectResults($request, $results);
					$results = $this->filterResults($request, $results);
					$results = $this->limitResults($request, $results);
					return [
						"assessment_results_uuid" => $assessmentResultsUuid,
						"results" => array_merge($info, $results),
						"results_status" => 'SUCCESS'
					];
				}
				break;
		}

		// fetch results
		//
		if ($viewerUuid != 'errors' && $viewerUuid != 'warnings') {
			$viewer = Viewer::where('viewer_uuid', '=', $viewerUuid)->first();
		} else {
			$viewer = null;
		}

		// check for cached results in storage directory
		//
		if (!$viewer || strtolower($viewer->name) == 'native') {
			$resultsPath = rtrim(config('app.outgoing'), '/') . '/' . $assessmentResultsUuid . '/nativereport.json';
	
			if (0) {
			// if (file_exists($resultsPath)) {
				$info = $this->getAssessmentResultsInfo($assessmentResultsUuid);
				$contents = file_get_contents($resultsPath, $assessmentResultsUuid);
				$results = $this->getJSONResults($request, $results);

				// return results
				//
				return [
					"assessment_results_uuid" => $assessmentResultsUuid,
					"results" => array_merge($info, $results),
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
				} else if ($instance->isLaunching()) {
					Log::info("getResults - state: " . $instance->stateToName() . " isLaunching");
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
				Log::info("getResults - state: " . $instance->stateToName() . " not isBlocked and not isLaunching and not (not isOKToLaunch and not isReady) - calling launchViewer");

			}	
		}

		// call stored procedure
		//
		$results = self::launchViewer($assessmentResultsUuid, $viewerUuid, $projectUuid, $resultsType);

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

			// get app url
			//
			$appUrl = config('app.url');
			if (!$appUrl) {
				return response("App URL is not defined.", 400);
			}

			// return a link to the results web page
			//
			$resultsUrl = str_replace('/swamp/outgoing', $appUrl . '/results', $returnPath);

			return [
				"assessment_results_uuid" => $assessmentResultsUuid,
				"results_url" => $resultsUrl,
				"results_status" => $returnString
			];
		} else if (StringUtils::endsWith($returnPath, '.html') || StringUtils::endsWith($returnPath, '.json')) {

			// return native results
			//
			return $this->getNativeResults($request, $assessmentResultsUuid, $returnPath, $returnString);
		} else {

			// return viewer results
			//
			return $this->getViewerResults($assessmentResultsUuid, $viewerInstanceUuid, $returnPath, $returnString);
		}
	}


	public function getCatalog(Request $request, string $assessmentResultsUuid, string $viewerUuid, string $projectUuid): array {
		$catalog = [];

		// get results
		//
		$results = $this->getResults($request, $assessmentResultsUuid, $viewerUuid, $projectUuid);
		if ($results) {
			$results = $results['results'];
		}

		// check if report exists
		//
		if ($results && array_key_exists('AnalyzerReport', $results)) {

			// check if bug instances exist
			//
			if (array_key_exists('BugInstances', $results['AnalyzerReport'])) {
				$bugInstances = $results['AnalyzerReport']['BugInstances'];
			
				for ($i = 0; $i < count($bugInstances); $i++) {
					$bugInstance = $bugInstances[$i];
					$bugCode = $bugInstance['BugCode'];

					if (!array_key_exists($bugCode, $catalog)) {
						$catalog[$bugCode] = array(
							'code' => $bugCode,
							'count' => 1
						);
					} else {
						$catalog[$bugCode]['count']++;
					}
				}

				// alphabetically sort list of weakness types
				//
				sort($catalog);
			}
		}

		return $catalog;
	}

	//
	// utility results methods
	//

	public function getNativeResults(Request $request, string $assessmentResultsUuid, string $returnPath, string $returnString): array {

		// return native results data
		//
		$results = @file_get_contents($returnPath, false, stream_context_create([
			"ssl" => [
				"verify_peer" => false,
				"verify_peer_name" => false
			]
		]));

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
		//
		if (StringUtils::endsWith($returnPath, 'nativereport.json')) {
			$results = $this->getJSONResults($request, $results, $assessmentResultsUuid);
		}

		$info = $this->getAssessmentResultsInfo($assessmentResultsUuid);

		if ($results) {

			// return results
			//
			return [
				"assessment_results_uuid" => $assessmentResultsUuid,
				"results" => array_merge($info, $results),
				"results_status" => $returnString
			];
		} else {
			return response('Could not return results from '.$returnPath, 500);
		}
		
		$results = file_get_contents($returnUrl);

		return [
			"assessment_results_uuid" => $assessmentResultsUuid,
			"results" => array_merge($info, $results),
			"results_status" => $returnString
		];
	}

	public function getViewerResults(string $assessmentResultsUuid, ?string $viewerInstanceUuid, ?string $returnPath, ?string $returnString) {

		// get url/status from viewer instance if present
		// otherwise just use what database gave us.
		// FIXME viewer is always present when url has no .html?
		//
		if ($viewerInstanceUuid) {
			$instance = HTCondorCollector::getViewerInstance($viewerInstanceUuid);
			Log::info("getViewerResults - state: " . $instance->stateToName());

			// TODO what is return value of status when returns immediately

			if ($instance->isLaunching() || !$instance->isReady()) {
				Log::info("getViewerResults - state: " . $instance->stateToName() . " isLaunching or not isReady");
				return [
					"results_viewer_status" => $instance->status,
					"results_status" => 'LOADING',
					"viewer_instance" => $viewerInstanceUuid
				];
			} else {

				// check instance proxy url
				//
				if (!$instance->proxy_url || $instance->proxy_url == 'undefined') {
					return response("Instance proxy URL is not defined.", 400);
				}

				// get CodeDX base url
				//
				$connection = DB::connection('assessment');
				$pdo = $connection->getPdo();
				$pdo->query("CALL select_system_setting ('CODEDX_BASE_URL',@rtn);");
				$base_url  = $pdo->query("SELECT @rtn")->fetchAll()[0]["@rtn"];
				if (!$base_url) {
					return response("CodeDX base url is not defined.", 400);
				}

				// compose results url
				//
				$returnUrl = $base_url . $instance->proxy_url;

				// log success
				//
				Log::info("Successfully launched viewer.", [
					'viewer_instance_uuid' => $viewerInstanceUuid,
					'assessment_results_uuid' => $assessmentResultsUuid,
					'results_url' => $returnUrl,
					'results_status' => $returnString
				]);

				return [
					"assessment_results_uuid" => $assessmentResultsUuid,
					"results_url" => $returnUrl,
					"results_status" => $returnString
				];
			}
		}

		// get app url
		//
		$appUrl = config('app.url');
		if (!$appUrl) {
			return response("App url is not defined.", 400);
		}

		return [
			"assessment_results_uuid" => $assessmentResultsUuid,
			"results_url" =>  $appUrl . '/' . $returnPath,
			"results_status" => $returnString
		];
	}

	public static function rrmdir(string $dir) {
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
	public function getJSONErrors(string $results, string $assessmentResultUuid) {

		// parse results
		//
		$results = json_decode($results, true);
		
		// append error link
		//
		$assessmentResult = AssessmentResult::where('assessment_result_uuid','=',$assessmentResultUuid)->first();
		$resultsDir = '/swamp/SCAProjects/'.$assessmentResult->project_uuid.'/A-Results/';
		$results['url'] = config('app.url').str_replace($resultsDir, '/results/', $assessmentResult->file_path);

		return $results;
	}

	// select results by file
	//
	public function selectResults(Request $request, array $results) {

		// parse parameters
		//
		$filename = $request->input('file');	
		
		if ($filename) {
			
			// check if bug instances exist
			//
			if (array_key_exists('BugInstances', $results['AnalyzerReport'])) {
				$bugInstances = $results['AnalyzerReport']['BugInstances'];
				$filtered = [];
				for ($i = 0; $i < count($bugInstances); $i++) {
					$bugInstance = $bugInstances[$i];
					foreach ($bugInstance['BugLocations'] as $bugLocation) {
						if ($bugLocation['primary']) {
							if (StringUtils::startsWith($bugLocation['SourceFile'], 'pkg1/' . $filename)) {
								array_push($filtered, $bugInstance);
							}
						}
					}
				}

				$results['AnalyzerReport']['BugInstances'] = $filtered;
			}
		}

		return $results;
	}

	// parse JSON results
	//
	public function limitResults(Request $request, array $results): array {

		// parse parameters
		//
		$from = filter_var($request->input('from'), FILTER_VALIDATE_INT);
		$to = filter_var($request->input('to'), FILTER_VALIDATE_INT);

		if (!$from) {
			$from = 0;
		}

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

		return $results;
	}

	public function includeBugInstances(array $bugInstances, array $filter = null): array {
		$filtered = [];
		for ($i = 0; $i < count($bugInstances); $i++) {
			$bugInstance = $bugInstances[$i];
			if (in_array($bugInstance['BugCode'], $filter)) {
				array_push($filtered, $bugInstance);
			}
		}
		return $filtered;
	}

	public function excludeBugInstances(array $bugInstances, array $filter =  null): array {
		$filtered = [];
		for ($i = 0; $i < count($bugInstances); $i++) {
			$bugInstance = $bugInstances[$i];
			if (!in_array($bugInstance['BugCode'], $filter)) {
				array_push($filtered, $bugInstance);
			}
		}
		return $filtered;
	}

	public function filterResults(Request $request, array $results = null): array {

		// check for no bug instances
		//
		if (!$results || !array_key_exists('BugInstances', $results['AnalyzerReport'])) {
			return [];
		}

		// get bug instances from results
		//
		$bugInstances = $results['AnalyzerReport']['BugInstances'];

		// get parameters
		//
		$include = $request->input('include');
		$exclude = $request->input('exclude');	

		// convert filters to arrays, if necessary
		//
		if ($include && is_string($include)) {
			if (StringUtils::contains($include, ',')) {
				$include = explode(',', $include);
			} else {
				$include = [$include];
			}
		}
		if ($exclude && is_string($exclude)) {
			if (StringUtils::contains($exclude, ',')) {
				$exclude = explode(',', $exclude);
			} else {
				$exclude = [$exclude];
			}
		}

		// filter bug instances
		//
		if ($exclude) {
			$bugInstances = $this->excludeBugInstances($bugInstances, $exclude);
		}	
		if ($include) {
			$bugInstances = $this->includeBugInstances($bugInstances, $include);
		}

		$results['AnalyzerReport']['BugInstances'] = $bugInstances;
		return $results;
	}

	public function getAssessmentResultsInfo(string $assessmentResultUuid): array {

		// find execution record
		//
		$assessmentResult = AssessmentResult::where('assessment_result_uuid','=',$assessmentResultUuid)->first();
		$executionRecord = ExecutionRecord::where('execution_record_uuid', '=', $assessmentResult->execution_record_uuid)->first();
	
		return [
			'package' => $executionRecord->package,
			'tool' => $executionRecord->tool,
			'platform' => $executionRecord->platform,
			'create_date' => $assessmentResult->create_date->format('Y/m/d H:i:s')
		];	
	}

	public function getJSONResults(Request $request, string $results) {

		// parse results
		//
		$results = json_decode($results, true);

		// filter results
		//
		$results = $this->filterResults($request, $results);
		$results = $this->limitResults($request, $results);

		return $results;
	}

	// Get SCARF results as XML file
	//
	public function getScarf(Request $request, string $assessmentResultsUuid) {
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
					$user = User::current();
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
					if (strcasecmp($request->header('Accept-Encoding'),'gzip') == 0) {
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

	public function getNoResultsPermission(string $viewerUuid, string $projectUuid) {
		return response('approved', 200);
	}

	public function getResultsPermission(string $assessmentResultsUuid, string $viewerUuid, string $projectUuid) {
		
		// check for no results
		//
		if ($assessmentResultsUuid == 'none') {
			return response('approved', 200);
		}

		// check permission on each result
		//
		foreach (explode( ',',$assessmentResultsUuid ) as $resultUuid) {
			$assessmentResult = AssessmentResult::where('assessment_result_uuid','=',$resultUuid)->first();
			$user = User::current();
			$result = $assessmentResult->checkPermissions($user);

			// if not true, return permissions error
			//
			if ($result !== true) {
				return $result;
			}
		}
	}

	private function checkProject(string $projectUuid) {
		$currentUser = User::current();
		$project = Project::where('project_uid', '=', $projectUuid)->first();
		if ($project && !$project->isReadableBy($currentUser)) {
			return response('Insufficient priveleges to access project.', 403);
		} else {
			return true;
		}
	}

	// get status of launching viewer, and then return results
	//
	public function getInstanceStatus(string $viewerInstanceUuid): array {
		$connection = DB::connection('assessment');
		$pdo = $connection->getPdo();

		$instance = HTCondorCollector::getViewerInstance($viewerInstanceUuid);

		// if proxy url, return it
		//
		if ($instance->isReady()) {

			// get CodeDX base url
			//
			$pdo->query("CALL select_system_setting ('CODEDX_BASE_URL',@rtn);");
			$base_url  = $pdo->query("SELECT @rtn")->fetchAll()[0]["@rtn"];
			if (!$base_url) {
				return response("CodeDX base URL is not defined.", 400);
			}

			// compose results url
			//
			$returnUrl = $base_url . $instance->proxy_url;

			// log success
			//
			Log::info("Successfully launched viewer.", [
				'viewer_instance_uuid' => $viewerInstanceUuid,
				'results_url' => $returnUrl,
				'results_status' => "SUCCESS"
			]);

			return [
				"results_url" => $returnUrl,
				"results_status" => "SUCCESS"
			];
		}

		// otherwise return viewer status
		//
		else if ($instance->isLaunching()) {
			Log::info("getInstanceStatus - state: " . $instance->stateToName() . " isLaunching");
			return [
				"results_viewer_status" => $instance->status,
				"results_status" => "LOADING",
				"viewer_instance" => $viewerInstanceUuid
			];
		}
		else if ($instance->isBeingTerminated()) {
			Log::info("getInstanceStatus - state: " . $instance->stateToName() . " isBeingTerminated");
			return [
				"results_viewer_status" => 'Terminated',
				"results_status" => "CLOSED",
				"viewer_instance" => $viewerInstanceUuid
			];
		}
		else if ($instance->hasTimedOut()) {
			Log::info("getInstanceStatus - state: " . $instance->stateToName() . " hasTimedOut");
			return [
				"results_viewer_status" => 'Timed Out',
				"results_status" => "TIMEOUT",
				"viewer_instance" => $viewerInstanceUuid
			];
		}
		else if ($instance->hasError()) {
			Log::info("getInstanceStatus - state: " . $instance->stateToName() . " hasError");
			return [
				"results_viewer_status" => $instance->status,
				"results_status" => "DEFAULT",
				"viewer_instance" => $viewerInstanceUuid
			];
		}
		else {
			Log::info("getInstanceStatus - state: " . $instance->stateToName() . " default");
			return [
				"results_viewer_status" => $instance->stateToName(),
				"results_status" => "DEFAULT",
				"viewer_instance" => $viewerInstanceUuid
			];
		}
	}
}
