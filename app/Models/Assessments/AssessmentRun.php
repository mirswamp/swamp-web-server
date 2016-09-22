<?php
/******************************************************************************\
|                                                                              |
|                                AssessmentRun.php                             |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a model of an assessment run.                            |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2016 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\Assessments;

use Illuminate\Support\Collection;
use App\Models\TimeStamps\UserStamped;
use App\Models\Packages\Package;
use App\Models\Packages\PackageVersion;
use App\Models\Tools\Tool;
use App\Models\Tools\ToolVersion;
use App\Models\Platforms\Platform;
use App\Models\Platforms\PlatformVersion;
use App\Models\Executions\ExecutionRecord;
use App\Models\RunRequests\RunRequest;
use App\Models\Assessments\AssessmentRunRequest;

class AssessmentRun extends UserStamped {

	/**
	 * database attributes
	 */
	protected $connection = 'assessment';
	protected $table = 'assessment_run';
	protected $primaryKey = 'assessment_run_id';

	/**
	 * mass assignment policy
	 */
	protected $fillable = array(
		'assessment_run_uuid',
		//'assessment_run_group_uuid',
		'project_uuid',
		'package_uuid',
		'package_version_uuid',
		'tool_uuid',
		'tool_version_uuid',
		'platform_uuid',
		'platform_version_uuid'
	);

	/**
	 * array / json conversion whitelist
	 */
	protected $visible = array(
		'assessment_run_uuid',
		//'assessment_run_group_uuid',
		'project_uuid',
		'package_uuid',
		'package_version_uuid',
		'tool_uuid',
		'tool_version_uuid',
		'platform_uuid',
		'platform_version_uuid',
		'package_name',
		'package_version_string',
		'tool_name',
		'tool_version_string',
		'platform_name',
		'platform_version_string',
		'num_execution_records'
	);

	/**
	 * array / json appended model attributes
	 */
	protected $appends = array(
		'package_name',
		'package_version_string',
		'tool_name',
		'tool_version_string',
		'platform_name',
		'platform_version_string',
		'num_execution_records'
	);

	/**
	 * query methods
	 */

	public function isMultiple() {
		return is_array($this->assessment_run_uuid);
	}

	/**
	 * accessor methods
	 */

	public function getVisible() {
		return $this->visible;
	}

	public function getPackageNameAttribute() {
		$package = Package::where('package_uuid', '=', $this->package_uuid)->first();
		return $package != null? $package->name : '?';
	}

	public function getPackageVersionStringAttribute() {
		$packageVersion = PackageVersion::where('package_version_uuid', '=', $this->package_version_uuid)->first();
		return $packageVersion != null? $packageVersion->version_string : 'latest';
	}

	public function getToolNameAttribute() {
		if ($this->tool_uuid) {
			if (!$this->isMultiple()) {
				$tool = tool::where('tool_uuid', '=', $this->tool_uuid)->first();
			} else {
				return "All";
			}
		} else {
			$tool = null;
		}
		return $tool != null? $tool->name : '?';
	}

	public function getToolVersionStringAttribute() {
		$toolVersion = ToolVersion::where('tool_version_uuid', '=', $this->tool_version_uuid)->first();
		return $toolVersion != null? $toolVersion->version_string : 'latest';
	}

	public function getPlatformNameAttribute() {
		$platform = Platform::where('platform_uuid', '=', $this->platform_uuid)->first();
		return $platform != null? $platform->name : '?';
	}

	public function getPlatformVersionStringAttribute() {
		$platformVersion = PlatformVersion::where('platform_version_uuid', '=', $this->platform_version_uuid)->first();
		return $platformVersion != null? $platformVersion->version_string : 'latest';
	}

	public function getNumExecutionRecordsAttribute() {
		if (!$this->isMultiple()) {
			return ExecutionRecord::where('assessment_run_uuid', '=', $this->assessment_run_uuid)->count();
		} else {
			$count = 0;
			foreach ($this->assessment_run_uuid as $assessmentRunUuid) {
				$count += ExecutionRecord::where('assessment_run_uuid', '=', $assessmentRunUuid)->count();
			}
			return $count;
		}
	}

	//
	// querying methods
	//

	public function getRunRequests() {
		$assessmentRunRequests = AssessmentRunRequest::where('assessment_run_id', '=', $this->assessment_run_id)->get();
		$collection = new Collection;
		foreach ($assessmentRunRequests as $assessmentRunRequest) {
			$runRequest = RunRequest::where('run_request_id', '=', $assessmentRunRequest->run_request_id)->first();
			
			// don't report one time requests
			//
			if ($runRequest->name != 'One-time') {
				$collection->push($runRequest);
			}
		}
		return $collection;
	}

	public function getNumRunRequests() {
		$num = 0;
		$assessmentRunRequests = AssessmentRunRequest::where('assessment_run_id', '=', $this->assessment_run_id)->get();
		foreach ($assessmentRunRequests as $assessmentRunRequest) {
			$runRequest = RunRequest::where('run_request_id', '=', $assessmentRunRequest->run_request_id)->first();
			
			// don't report one time requests
			//
			if ($runRequest->name != 'One-time') {
				$num++;
			}
		}
		return $num;
	}
}
