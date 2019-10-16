<?php
/******************************************************************************\
|                                                                              |
|                              ExecutionRecord.php                             |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a model of an execution record.                          |
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

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use App\Models\TimeStamps\TimeStamped;
use App\Models\Projects\Project;
use App\Models\Packages\Package;
use App\Models\Packages\PackageVersion;
use App\Models\Tools\Tool;
use App\Models\Tools\ToolVersion;
use App\Models\Platforms\Platform;
use App\Models\Platforms\PlatformVersion;
use App\Models\Results\AssessmentResult;
use App\Models\Users\User;
use PDO;

class ExecutionRecord extends TimeStamped
{
	// enable soft delete
	//
	use SoftDeletes;

	// database attributes
	//
	protected $connection = 'assessment';
	protected $table = 'execution_record';
	protected $primaryKey = 'execution_record_id';

	// mass assignment policy
	//
	protected $fillable = [
		'execution_record_uuid',
		'assessment_run_uuid',
		'run_request_uuid',
		'user_uuid',
		'launch_flag',
		'launch_counter',
		'complete_flag',
		'submitted_to_condor_flag',
		'project_uuid',
		'status',
		'run_date',
		'completion_date',
		'queued_duration',
		'execution_duration',
		'execute_node_architecture_id',
		'cpu_utilization',
		'vm_hostname'
	];

	// array / json conversion whitelist
	//
	protected $visible = [
		'execution_record_uuid',
		'assessment_run_uuid',
		'run_request_uuid',
		'user_uuid',
		'launch_flag',
		'launch_counter',
		'complete_flag',
		'submitted_to_condor_flag',
		'project_uuid',
		'status',
		'run_date',
		'completion_date',
		'queued_duration',
		'execution_duration',
		'execute_node_architecture_id',
		'cpu_utilization',
		'vm_hostname',

		// appended attributes
		//
		'project',
		'package',
		'tool',
		'platform',
		'assessment_result_uuid',
		'weakness_cnt',
		'vm_ready_flag'
	];

	// array / json appended model attributes
	//
	protected $appends = [
		'project',
		'package',
		'tool',
		'platform',
		'assessment_result_uuid',
		'assessment_result',
		'weakness_cnt',
		'vm_ready_flag'
	];

	// attribute types
	//
	protected $casts = [
		'launch_flag' => 'boolean',
		'launch_counter' => 'integer',
		'complete_flag' => 'boolean',
		'submitted_to_condor_flag' => 'boolean',
		'run_date' => 'datetime',
		'completion_date' => 'datetime',
		'weakness_cnt' => 'integer',
		'vm_ready_flag' => 'boolean'
	];

	//
	// accessor methods
	//

	public function getProjectAttribute() {
		$project = $this->getProject();
		return [
			'project_uuid' => $this->project_uuid,
			'name' => $project? $project->full_name : ''
		];
	}

	public function getPackageAttribute() {
		$packageVersion = $this->getPackageVersion();
		$package = $packageVersion? $packageVersion->getPackage() : null;

		// get package info from results
		//
		if (!$package || !$packageVersion) {
			$assessmentResult = AssessmentResult::where('execution_record_uuid', '=', $this->execution_record_uuid)->first();
		}

		return [
			'name' => $package? $package->name : 
				($assessmentResult? $assessmentResult->package_name : null),
			'version_string' => $packageVersion? $packageVersion->version_string : 
				($assessmentResult? $assessmentResult->package_version : null),
			'package_uuid' => $package? $package->package_uuid : null,
			'package_version_uuid' => $packageVersion? $packageVersion->package_version_uuid : null
		];
	}

	public function getToolAttribute() {
		$toolVersion = $this->getToolVersion();
		$tool = $toolVersion? $toolVersion->getTool() : null;
		$packageVersion = $this->getPackageVersion();
		$package = $packageVersion? $packageVersion->getPackage() : null;
		$project = $this->getProject();
		$user = User::getIndex(session('user_uid'));
		$policy = $tool? $tool->getUserPolicy($user) : null;

		// get tool info from results
		//
		if (!$tool || !$toolVersion) {
			$assessmentResult = AssessmentResult::where('execution_record_uuid', '=', $this->execution_record_uuid)->first();
		}

		return [
			'name' => $tool? $tool->name : 
				($assessmentResult? $assessmentResult->tool_name : null),
			'version_string' => $toolVersion? $toolVersion->version_string : 
				($assessmentResult? $assessmentResult->tool_version : null),
			'tool_uuid' => $tool? $tool->tool_uuid : null,
			'tool_version_uuid' => $toolVersion? $toolVersion->tool_version_uuid : null,
			'policy_code' => $tool? $tool->policy_code : null,
			'viewer_names' => $tool? $tool->viewer_names : ($assessmentResult? (new Tool([
				'tool_uuid' => $assessmentResult->tool_uuid
			]))->viewer_names : null),
			'is_restricted' => $tool? $tool->is_restricted : null,
			'permission' => $policy? 'granted' : 'no_policy'
		];
	}

	public function getPlatformAttribute() {	
		$platformVersion = $this->getPlatformVersion();
		$platform = $platformVersion? $platformVersion->getPlatform() : null;

		// get platform info from results
		//
		if (!$platform || !$platformVersion) {
			$assessmentResult = AssessmentResult::where('execution_record_uuid', '=', $this->execution_record_uuid)->first();
		}

		return [
			'name' => $platform? $platform->name : 
				($assessmentResult? $assessmentResult->platform_name : null),
			'version_string' => $platformVersion? $platformVersion->version_string : 
				($assessmentResult? $assessmentResult->platform_version : null),
			'platform_uuid' => $platform? $platform->platform_uuid : null,
			'platform_version_uuid' => $platformVersion? $platformVersion->platform_version_uuid : null
		];
	}

	public function getAssessmentResultAttribute() {

		// get most recent assessment result
		//
		return $this->getAssessmentResult();
	}

	public function getAssessmentResultUuidAttribute() {

		// get assessment result uiid from associated result
		//
		$assessmentResult = $this->getAssessmentResult();
		if ($assessmentResult) {
			return $assessmentResult->assessment_result_uuid;
		}
	}

	public function getWeaknessCntAttribute() {

		// get weakness count from associated result
		//
		$assessmentResult = $this->getAssessmentResult();
		if ($assessmentResult) {
			return $assessmentResult->weakness_cnt;
		}
	}
	
	public function getVmReadyFlagAttribute() {
		return $this->getVmReadyFlag();
	}

	//
	// querying methods
	//

	public function getPackageVersion() {
		return PackageVersion::where('package_version_uuid', '=', $this->package_version_uuid)->first();
	}

	public function getToolVersion() {
		$toolVersion = ToolVersion::where('tool_version_uuid', '=', $this->tool_version_uuid)->first();

		// get tool version from results
		//
		if (!$toolVersion) {
			$assessmentResult = AssessmentResult::where('assessment_result_uuid', '=', $this->assessment_result_uuid)->first();
			if ($assessmentResult) {
				$toolVersion = ToolVersion::where('tool_version_uuid', '=', $assessmentResult->tool_version_uuid)->first();
			}
		}

		return $toolVersion;
	}

	public function getPlatformVersion() {
		return PlatformVersion::where('platform_version_uuid', '=', $this->platform_version_uuid)->first();
	}

	public function getProject() {
		return Project::where('project_uid', '=', $this->project_uuid)->first();
	}

	public function getUser() {
		return User::getIndex($this->user_uuid);
	}

	public function getAssessmentResult() {
		return AssessmentResult::where('execution_record_uuid', '=', $this->execution_record_uuid)->orderBy('create_date', 'DESC')->first();
	}

	public function getVmReadyFlag() {
		return 
			($this->vm_hostname != '') && 
			($this->vm_username != '') && 
			($this->vm_password != '') &&
			($this->vm_ip_address != '')
			? 1 : 0;
	}

	//
	// methods
	//

	public function kill($hard) {

		// create stored procedure call
		//
		$connection = DB::connection('assessment');
		$pdo = $connection->getPdo();
		$stmt = $pdo->prepare("CALL kill_assessment_run(:execution_record_uuid, :hard, @return_string);");

		// bind params
		//
		$execution_record_uuid = $this->execution_record_uuid;
		$returnString = null;
		$stmt->bindParam(":execution_record_uuid", $execution_record_uuid, PDO::PARAM_STR, 45);
		$stmt->bindParam(":hard", $hard, PDO::PARAM_BOOL);

		// call stored procedure
		//
		$stmt->execute();

		// fetch return parameters
		//
		$select = $pdo->query('SELECT @return_string');
		$results = $select->fetchAll();
		$returnString = $results[0]["@return_string"];

		return $returnString;
	}
}