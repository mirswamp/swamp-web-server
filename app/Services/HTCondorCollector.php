<?php 
/******************************************************************************\
|                                                                              |
|                              HTCondorCollector.php                           |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a class that represents an HTCondor collector            |
|        node.                                                                 |
|                                                                              |
|        Author(s): Carrie Steinen, Tom Bricker                                |
|                                                                              |
|        Copyright (C) 2012-2016 SWAMP - Software Assurance Marketplace        |
|        Morgridge Institute for Research                                      |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2020 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Services;

use App\Models\Viewers\ViewerInstance;
use Illuminate\Support\Facades\Log;
use App\Services\HTCondor;

class HTCondorCollector
{
	// turn trace logging on or off
	// 
	private static $global_trace_logging = false;

	public static function getViewerData($proxyUrl) {
		$retval = "";
		$command = HTCondor::get_condor_env_command() . "condor_status ";
		$HTCONDOR_COLLECTOR_HOST = config('app.htcondorcollectorhost');
		if (! preg_match('/localhost/', $HTCONDOR_COLLECTOR_HOST)) {
			$command .= "-pool $HTCONDOR_COLLECTOR_HOST ";
		}
		$command .= "-any -af:V, SWAMP_vmu_viewer_vmip SWAMP_vmu_viewer_projectid -constraint SWAMP_vmu_viewer_url_uuid==\\\"$proxyUrl\\\"";
		if (self::$global_trace_logging) {
			Log::info("getViewerData command: $command");
		}
		exec($command, $output, $returnVar);
		if (($returnVar == 0) && (! empty($output))) {
			$retval = preg_split("/,/", $output[0]);
			foreach ($retval as &$value) {
				$value = trim($value);
				$value = str_replace('"', '', $value);
			}
		}
		return $retval;
	}

	public static function getViewerInstance($viewerInstanceUuid) {
	    $instance = new ViewerInstance();
        $HTCONDOR_COLLECTOR_HOST = config('app.htcondorcollectorhost');
        $command = HTCondor::get_condor_env_command() . "condor_status ";
		if (! preg_match('/localhost/', $HTCONDOR_COLLECTOR_HOST)) {
			$command .= "-pool $HTCONDOR_COLLECTOR_HOST ";
		}
		$command .= "-any -af:V, SWAMP_vmu_viewer_state SWAMP_vmu_viewer_status SWAMP_vmu_viewer_url_uuid -constraint SWAMP_vmu_viewer_instance_uuid==\\\"$viewerInstanceUuid\\\"";
		if (self::$global_trace_logging) {
			Log::info("getViewerInstance command: $command");
		}
        exec($command, $output, $returnVar);
        if (($returnVar == 0) && (! empty($output))) {
			list($state, $status, $proxy_url) = explode(', ', $output[0]);
			$instance->state = str_replace('"', '', $state);
			$instance->status = str_replace('"', '', $status);
			$instance->proxy_url = str_replace('"', '', $proxy_url);
        }
        return $instance;
	}

	public static function insertStatuses($result) {
		$HTCONDOR_COLLECTOR_HOST = config('app.htcondorcollectorhost');  
		$command = HTCondor::get_condor_env_command() . "condor_status ";
		if (! preg_match('/localhost/', $HTCONDOR_COLLECTOR_HOST)) {
			$command .= "-pool $HTCONDOR_COLLECTOR_HOST ";
		}
		$command .= "-any -af:V, Name SWAMP_vmu_assessment_status -constraint \"isString(SWAMP_vmu_assessment_status)\"";
		if (self::$global_trace_logging) {
			Log::info("insertStatuses command: $command");
		}
		exec($command, $output, $returnVar);
		if (($returnVar == 0) && (! empty($output))) {
			$condorResult = [];
			for ($i = 0; $i < sizeof($output); $i++) {
				list($executionRecordUuid, $status) = explode(',', $output[$i]);
				$executionRecordUuid = str_replace('"', '', $executionRecordUuid);
				$status = str_replace('"', '', trim($status));
				$condorResult[$executionRecordUuid] = $status;
			}
			for ($i = 0; $i < sizeof($result); $i++) {
				$executionRecordUuid = $result[$i]['execution_record_uuid'];
				if (array_key_exists($executionRecordUuid, $condorResult)) {
					$result[$i]['status'] = $condorResult[$executionRecordUuid];
				}
			}
		}
		return $result;
	}

	public static function insertStatus($result, $executionRecordUuid) {
		if (! $executionRecordUuid) {
			return $result;
		}
		$condorResult = null;
		$HTCONDOR_COLLECTOR_HOST = config('app.htcondorcollectorhost');  
		$command = HTCondor::get_condor_env_command() . "condor_status ";
		if (! preg_match('/localhost/', $HTCONDOR_COLLECTOR_HOST)) {
			$command .= "-pool $HTCONDOR_COLLECTOR_HOST ";
		}
		$command .= "-any -af:V, SWAMP_vmu_assessment_status -constraint \"(strcmp(Name, \\\"$executionRecordUuid\\\") == 0)\"";
		if (self::$global_trace_logging) {
			Log::info("insertStatus command: $command");
		}
		exec($command, $output, $returnVar);
		if (($returnVar == 0) && (! empty($output))) {
			$outstr = '';
			for ($i = 0; $i < sizeof($output); $i++) {
				$outstr .= $output[$i];
			}
			$condorResult = str_replace('"', '', $outstr);
			$result['status'] = $condorResult;
		}
		return $result;
	}
}
