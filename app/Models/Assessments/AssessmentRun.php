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
		if ($this->package_uuid != null) {
			$package = Package::where('package_uuid', '=', $this->package_uuid)->first();
			if ($package) {
				return $package->name;
			} else {
				return '?';
			}
		} else {
			return '';
		}
	}

	public function getPackageVersionStringAttribute() {
		if ($this->package_version_uuid != null) {
			$packageVersion = PackageVersion::where('package_version_uuid', '=', $this->package_version_uuid)->first();
			if ($packageVersion) {
				return $packageVersion->version_string;
			} else {
				return '?';
			}
		} else {
			return 'latest';
		}
	}

	public function getToolNameAttribute() {
		if ($this->tool_uuid != null) {
			$tool = Tool::where('tool_uuid', '=', $this->tool_uuid)->first();
			if ($tool) {
				return $tool->name;
			} else {
				return '?';
			}
		} else {
			return 'all';
		}
	}

	public function getToolVersionStringAttribute() {
		if ($this->tool_version_uuid != null) {
			$toolVersion = ToolVersion::where('tool_version_uuid', '=', $this->tool_version_uuid)->first();
			if ($toolVersion) {
				return $toolVersion->version_string;
			} else {
				return '?';
			}
		} else {
			return 'latest';
		}
	}

	public function getPlatformNameAttribute() {
		if ($this->platform_uuid != null) {
			$platform = Platform::where('platform_uuid', '=', $this->platform_uuid)->first();
			if ($platform) {
				return $platform->name;
			} else {
				return '?';
			}
		} else {
			return 'default';
		}
	}

	public function getPlatformVersionStringAttribute() {
		if ($this->platform_version_uuid != null) {
			$platformVersion = PlatformVersion::where('platform_version_uuid', '=', $this->platform_version_uuid)->first();
			if ($platformVersion) {
				return $platformVersion->version_string;
			} else {
				return '?';
			}
		} else {
			return 'latest';
		}
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

	/*
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
	*/

	public function getRunRequests() {
		$oneTimeRunRequest = $runRequest = RunRequest::where('name', '=', 'One-time')->first();
		return $assessmentRunRequests = AssessmentRunRequest::where('assessment_run_id', '=', $this->assessment_run_id)->where('run_request_id', '!=', $oneTimeRunRequest->run_request_id)->get();
	}

	public function getNumRunRequests() {
		$oneTimeRunRequest = $runRequest = RunRequest::where('name', '=', 'One-time')->first();
		return $assessmentRunRequests = AssessmentRunRequest::where('assessment_run_id', '=', $this->assessment_run_id)->where('run_request_id', '!=', $oneTimeRunRequest->run_request_id)->count();
	}

	//
	// overridden Laravel methods
	//

	public function toArray() {
		$array = parent::toArray();

		// add checks for uuid integrity
		//
		if ($array['package_uuid'] && !Package::where('package_uuid', '=', $array['package_uuid'])->exists()) {
			$array['package_uuid'] = 'undefined';
		}
		if ($array['package_version_uuid'] && !PackageVersion::where('package_version_uuid', '=', $array['package_version_uuid'])->exists()) {
			$array['package_version_uuid'] = 'undefined';
		}

		if ($array['tool_uuid'] && !Tool::where('tool_uuid', '=', $array['tool_uuid'])->exists()) {
			$array['tool_uuid'] = 'undefined';
		}
		if ($array['tool_version_uuid'] && !ToolVersion::where('tool_version_uuid', '=', $array['tool_version_uuid'])->exists()) {
			$array['tool_version_uuid'] = 'undefined';
		}

		if ($array['platform_uuid'] && !Platform::where('platform_uuid', '=', $array['platform_uuid'])->exists()) {
			$array['platform_uuid'] = 'undefined';
		}
		if ($array['platform_version_uuid'] && !PlatformVersion::where('platform_version_uuid', '=', $array['platform_version_uuid'])->exists()) {
			$array['platform_version_uuid'] = 'undefined';
		}

		return $array;
	}
}
