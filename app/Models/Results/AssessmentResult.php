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
|        Copyright (C) 2012-2019 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\Results;

use PDO;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\TimeStamps\UserStamped;
use App\Models\Users\UserPolicy;
use App\Models\Users\Permission;
use App\Models\Projects\Project;
use App\Models\Tools\Tool;
use App\Models\Tools\ToolVersion;
use App\Models\Results\ExecutionRecord;
use App\Models\Viewers\Viewer;

class AssessmentResult extends UserStamped
{
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
		'policy_code',
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
		'policy_code',
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

		// check restricted tools
		//
		if ($this->policy_code) {

			// if the policy hasn't been accepted, return error
			//
			$userPolicy	= UserPolicy::where('policy_code', '=', $this->policy_code)->where('user_uid', '=', $user->user_uid)->first();
			if (!$userPolicy || $userPolicy->accept_flag != '1') {

				// find tool associated with policy
				//
				$toolVersion = ToolVersion::find($this->tool_version_uuid);
				$tool = $toolVersion? $toolVersion->getTool() : null;

				return [
					'status' => 'no_policy',
					'policy' => $tool? $tool->policy : null,
					'policy_code' => $this->policy_code,
					'tool' => $tool
				];
			}
		}

		return true;
	}

	public function launchViewer($viewer) {

		// get latest version of viewer
		//
		$viewerVersion = $viewer->getLatestVersion();

		// create stored procedure call
		//
		$connection = DB::connection('assessment');
		$pdo = $connection->getPdo();
		$stmt = $pdo->prepare("CALL launch_viewer(:assessmentResultsUuid, :userUuidIn, :viewerVersionUuid, :projectUuid, :destinationBasePath, @returnPath, @returnString, @viewerInstanceUuid);");

		// set parameters to pass to stored procedure
		//
		$assessmentResultsUuid = $this->assessment_result_uuid;
		$userUuidIn = session('user_uid');
		$viewerVersionUuid = $viewerVersion->viewer_version_uuid;
		$projectUuid = $this->project_uuid;
		$returnString = null;
		$resultsDestination = config('app.outgoing');
		$returnPath = null;
		$returnString = null;
		$viewerInstanceUuid = null;	

		// bind params
		//
		$stmt->bindParam(":assessmentResultsUuid", $assessmentResultsUuid, PDO::PARAM_STR, 5000);
		$stmt->bindParam(":userUuidIn", $userUuidIn, PDO::PARAM_STR, 45);
		$stmt->bindParam(":viewerVersionUuid", $viewerVersionUuid, PDO::PARAM_STR, 45);
		$stmt->bindParam(":projectUuid", $projectUuid, PDO::PARAM_STR, 45);
		$stmt->bindParam(":destinationBasePath", $resultsDestination, PDO::PARAM_STR, 45);

		// call stored procedure
		//
		$results = $stmt->execute();

		// fetch return parameters
		//
		$select = $pdo->query('SELECT @returnPath, @returnString, @viewerInstanceUuid');
		return $select->fetchAll();
	}

	public function getNativeResultsData() {

		// find path for cached results
		//
		if (config('app.sample_results')) {
			$resultsPath = config('app.sample_results');
		} else {
			$resultsPath = config('app.outgoing').'/'.$this->assessment_result_uuid.'/nativereport.json';	
		}

		// check for cached results in storage directory
		//
		if (file_exists($resultsPath)) {
			return file_get_contents($resultsPath, $this->assessment_result_uuid);
		}

		// call stored procedure
		//
		$nativeViewer = Viewer::where('name', '=', 'Native')->first();
		$results = $this::launchViewer($nativeViewer);

		// read and return native results data
		//
		return @file_get_contents($results[0]["@returnPath"], false, stream_context_create([
			"ssl" => [
				"verify_peer" => false,
				"verify_peer_name" => false
			]
		]));
	}
}
