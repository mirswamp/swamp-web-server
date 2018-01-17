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
|        Copyright (C) 2012-2018 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\Executions;

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
use App\Models\Assessments\AssessmentResult;
use App\Models\Users\User;
use PDO;

class ExecutionRecord extends TimeStamped {

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
		'package',
		'tool',
		'platform',
		'assessment_result_uuid',
		'assessment_result',
		'weakness_cnt',
		'vm_ready_flag'
	];

	//
	// accessor methods
	//

	public function getPackageAttribute() {
		$packageVersion = PackageVersion::where('package_version_uuid', '=', $this->package_version_uuid)->first();
		if ($packageVersion != null) {
			$package = Package::where('package_uuid', '=', $packageVersion->package_uuid)->first();
		} else {
			$package = null;
		}

		// get package info from results
		//
		if (!$package || !$packageVersion) {
			$assessmentResult = AssessmentResult::where('execution_record_uuid', '=', $this->execution_record_uuid)->first();
		}

		return [
			'name' => $package? $package->name : 
				($assessmentResult? $assessmentResult->package_name : ''),
			'version_string' => $packageVersion? $packageVersion->version_string : 
				($assessmentResult? $assessmentResult->package_version : ''),
			'package_uuid' => $package? $package->package_uuid : '',
			'package_version_uuid' => $packageVersion? $packageVersion->package_version_uuid : ''
		];
	}

	public function getToolAttribute() {
		$tool = null;
		$toolVersion = ToolVersion::where('tool_version_uuid', '=', $this->tool_version_uuid)->first();

		// get tool version from assessment results
		//
		if (!$toolVersion) {
			$assessmentResult = AssessmentResult::where('assessment_result_uuid', '=', $this->assessment_result_uuid)->first();
			if ($assessmentResult) {
				$tool = Tool::where('tool_uuid', '=', $assessmentResult->tool_uuid)->first();
			}
		}

		// get tool from version or results
		//
		if ($toolVersion != null) {
			$tool = Tool::where('tool_uuid', '=', $toolVersion->tool_uuid)->first();
		} else if ($assessmentResult) {
			$toolVersion = ToolVersion::where('tool_version_uuid', '=', $assessmentResult->tool_version_uuid)->first();
			if ($toolVersion) {
				$tool = Tool::where('tool_uuid', '=', $toolVersion->tool_uuid)->first();
			}
		}

		// get tool info from results
		//
		if (!$tool || !$toolVersion) {
			$assessmentResult = AssessmentResult::where('execution_record_uuid', '=', $this->execution_record_uuid)->first();
		}

		return [
			'name' => $tool? $tool->name : 
				($assessmentResult? $assessmentResult->tool_name : ''),
			'version_string' => $toolVersion? $toolVersion->version_string : 
				($assessmentResult? $assessmentResult->tool_version : ''),
			'tool_uuid' => $tool? $tool->tool_uuid : '',
			'tool_version_uuid' => $toolVersion? $toolVersion->tool_version_uuid : '',
			'viewer_names' => $tool? $tool->viewer_names : ($assessmentResult? (new Tool([
				'tool_uuid' => $assessmentResult->tool_uuid
			]))->viewer_names : ''),
			'is_restricted' => $tool? $tool->is_restricted : ''
		];
	}

	public function getPlatformAttribute() {
		$platformVersion = PlatformVersion::where('platform_version_uuid', '=', $this->platform_version_uuid)->first();
		if ($platformVersion != null) {
			$platform = Platform::where('platform_uuid', '=', $platformVersion->platform_uuid)->first();
		} else {
			$platform = null;
		}

		// get platform info from results
		//
		if (!$platform || !$platformVersion) {
			$assessmentResult = AssessmentResult::where('execution_record_uuid', '=', $this->execution_record_uuid)->first();
		}

		return [
			'name' => $platform? $platform->name : 
				($assessmentResult? $assessmentResult->platform_name : ''),
			'version_string' => $platformVersion? $platformVersion->version_string : 
				($assessmentResult? $assessmentResult->platform_version : ''),
			'platform_uuid' => $platform? $platform->platform_uuid : '',
			'platform_version_uuid' => $platformVersion? $platformVersion->platform_version_uuid : ''
		];
	}

	public function getAssessmentResultAttribute() {
		return AssessmentResult::where('execution_record_uuid', '=', $this->execution_record_uuid)->first();
	}

	public function getAssessmentResultUuidAttribute() {

		// get assessment result uiid from associated result
		//
		$assessmentResult = AssessmentResult::where('execution_record_uuid', '=', $this->execution_record_uuid)->first();
		if ($assessmentResult) {
			return $assessmentResult->assessment_result_uuid;
		}
	}

	public function getWeaknessCntAttribute() {

		// get weakness count from associated result
		//
		$assessmentResult = AssessmentResult::where('execution_record_uuid', '=', $this->execution_record_uuid)->first();
		if ($assessmentResult) {
			return $assessmentResult->weakness_cnt;
		}
	}
	
	public function getVmReadyFlagAttribute() {
		return 
			($this->vm_hostname != '') && 
			($this->vm_username != '') && 
			($this->vm_password != '') &&
			($this->vm_ip_address != '')
			? 1 : 0;
	}

	//
	// querying methods
	//

	public function getProject() {
		return Project::where('project_uid', '=', $this->project_uuid)->first();
	}

	public function getUser() {
		return User::getIndex($this->user_uuid);
	}

	//
	// methods
	//

	public function kill() {

		// create stored procedure call
		//
		$connection = DB::connection('assessment');
		$pdo = $connection->getPdo();
		$stmt = $pdo->prepare("CALL kill_assessment_run(:execution_record_uuid, @return_string);");

		// bind params
		//
		$execution_record_uuid = $this->execution_record_uuid;
		$returnString = null;
		$stmt->bindParam(":execution_record_uuid", $execution_record_uuid, PDO::PARAM_STR, 45);

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
