<?php
/******************************************************************************\
|                                                                              |
|                              SWAMPStatus.php                           	   |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a class that represents an SWAMP Status node.            |
|                                                                              |
|        Author(s): Abe Megahed, Thomas Jay Anthony Bricker                    |
|                                                                              |
|        Copyright (C) 2012-2016 SWAMP - Software Assurance Marketplace        |
|        Morgridge Institute for Research                                      |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2019 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Services;

use \DateTime;
use \DateTimeZone;
use Illuminate\Support\Facades\Log;
use App\Models\Results\ExecutionRecord;
use App\Models\Viewers\ViewerInstance;
use App\Services\HTCondor;

class SWAMPStatus
{
	// turn trace logging on or off
	// 
	private static $global_trace_logging = false;

	private static $global_is_condor_available = true;
	private static $global_is_localhost = true;
	private static $global_condor_env_prefix = '';
	private static $global_usr_sbin_condor_master_pid = '';
	private static $global_opt_swamp_htcondor_sbin_condor_master_pid = '';

	private static function get_condor_collector_host() {
		$collector_host = config('app.htcondorcollectorhost');
		return $collector_host;
	}

	private static function get_condor_submit_nodes($condor_manager) {
		$command = self::$global_condor_env_prefix . "condor_status ";
		if (! self::$global_is_localhost) {
			$command .= "-pool $condor_manager ";
		}
		$command .= "-schedd -constraint \"CollectorHost==\\\"$condor_manager\\\"\" -af Name";
		if (self::$global_trace_logging) {
			Log::info("get_condor_submit_nodes command: $command");
		}
		exec("$command 2>&1", $output, $returnVar);
		$submit_nodes = [];
		if (($returnVar == 0) && (! empty($output))) {
			for ($i = 0; $i < sizeof($output); $i++) {
				$submit_nodes[$i] = str_replace('"', '', $output[$i]);
			}
		}
		elseif ($returnVar == 1) {
			Log::error("Error - command: $command output: " . print_r($output, 1));
		}
		return $submit_nodes;
	}

	private static function get_condor_exec_nodes($condor_manager) {
		$command = self::$global_condor_env_prefix . "condor_status ";
		if (! self::$global_is_localhost) {
			$command .= "-pool $condor_manager ";
		}
		$command .= "-af Machine | sort -u";
		if (self::$global_trace_logging) {
			Log::info("get_condor_exec_nodes command: $command");
		}
		exec("$command 2>&1", $output, $returnVar);
		$exec_nodes = [];
		if (($returnVar == 0) && (! empty($output))) {
			for ($i = 0; $i < sizeof($output); $i++) {
				$exec_nodes[$i] = str_replace('"', '', $output[$i]);
			}
		}
		elseif ($returnVar == 1) {
			Log::error("Error - command: $command output: " . print_r($output, 1));
		}
		return $exec_nodes;
	}

	private static function get_swamp_data_nodes($hostname) {
		$data_nodes = [];
		$first_data_node = env('PACKAGE_DB_HOST', $hostname);
		$data_nodes[] = $first_data_node;
		$data_node = env('TOOL_DB_HOST', $hostname);
		if ($data_node != $first_data_node) {
			$data_nodes[] = $data_node;
		}
		$data_node = env('PLATFORM_DB_HOST', $hostname);
		if ($data_node != $first_data_node) {
			$data_nodes[] = $data_node;
		}
		$data_node = env('ASSESSMENT_DB_HOST', $hostname);
		if ($data_node != $first_data_node) {
			$data_nodes[] = $data_node;
		}
		$data_node = env('VIEWER_DB_HOST', $hostname);
		if ($data_node != $first_data_node) {
			$data_nodes[] = $data_node;
		}
		return $data_nodes;
	}

	public static function get_collector_records($collector_host, $title) {
		$global_fields['assessment'] = [
			'Name', 
			'SWAMP_vmu_assessment_vmhostname', 
			'SWAMP_vmu_assessment_projectid',
			'SWAMP_vmu_assessment_status',
		];
		$global_constraint['assessment'] = "-constraint \"isString(SWAMP_vmu_assessment_status)\"";
		$global_fields['viewer'] = [
			'SWAMP_vmu_viewer_vmhostname',
			'SWAMP_vmu_viewer_name',
			'SWAMP_vmu_viewer_state',
			'SWAMP_vmu_viewer_status',
			'SWAMP_vmu_viewer_vmip',
			'SWAMP_vmu_viewer_projectid',
			'SWAMP_vmu_viewer_instance_uuid',
			'SWAMP_vmu_viewer_apikey',
			'SWAMP_vmu_viewer_url_uuid',
		];
		$global_constraint['viewer'] = "-constraint \"isString(SWAMP_vmu_viewer_status)\"";
		$fieldnames = [];
		$crecords = [];
		$fields = $global_fields[$title];
		$constraint = $global_constraint[$title];
		$sortfield = "SWAMP_vmu_" . $title . "_vmhostname";
		$command = self::$global_condor_env_prefix . "condor_status ";
		if (! self::$global_is_localhost) {
			$command .= "-pool $collector_host ";
		}
		$command .= "-sort $sortfield -generic -af:V, ";
		foreach ($fields as $field) {
			$command .= ' ' . $field;
		}
		if (! empty($constraint)) {
			$command .= ' ' . $constraint;
		}
		if (self::$global_trace_logging) {
			Log::info("get_collector_records command: $command");
		}
		$returnVar = 1;
		if (self::$global_is_condor_available) {
			exec("$command 2>&1", $output, $returnVar);
		}
		if (($returnVar == 0) && (! empty($output))) {
			$prefix = "SWAMP_vmu_" . $title . "_";

			// special case to convert Name to execrunuid
			//
			if ($fields[0] == 'Name') {
				$fieldnames[0] = 'execrunuid';
			}
			else {
				$fieldnames[0] = str_replace($prefix, '', $fields[0]);
			}
			for ($i = 1; $i < sizeof($fields); $i++) {
				$fieldnames[$i] = str_replace($prefix, '', $fields[$i]);
			}
			for ($i = 0; $i < sizeof($output); $i++) {
				$crecord = [];
				$temp = preg_split("/,/", $output[$i], sizeof($fieldnames), PREG_SPLIT_NO_EMPTY);
				for ($n = 0; $n < sizeof($fieldnames); $n++) {
					$fieldname = $fieldnames[$n];
					$fieldvalue = str_replace(['"', ','], '',  trim($temp[$n]));

					if ($title == 'assessment') {

						// check execrunuid column for execrunuuids
						//
						if ($fieldname == 'execrunuid') {
							if (preg_match("/^M-/", $fieldvalue)) {
								$fieldvalue = preg_replace('/^M-/', '', $fieldvalue);
							}
							else {
								$fieldvalue = '{execrunuid}' . $fieldvalue;
							}
						}
						elseif ($fieldname == 'projectid') {
							if ($fieldvalue != 'METRIC') {
								$fieldvalue = '{projectuid}' . $fieldvalue;
							}
						}
					}
					elseif ($title == 'viewer') {
						if ($fieldname == 'projectid') {
							$fieldvalue = '{projectuid}' . $fieldvalue;
						}
					}

					$crecord[$fieldname] = $fieldvalue;
					if ($fieldname === 'state') {
						$crecord[$fieldname] = ViewerInstance::state_to_name($crecord[$fieldname]);
					}
				}
				$crecords[] = $crecord;
			}

			// if there are no crecords in final list then clear fieldnames for consistency
			//
			if (empty($crecords)) {
				$fieldnames = [];
			}
		}
		else if ($returnVar == 1) {
			if (self::$global_is_condor_available) {
				Log::error("Error - command: $command output: " . print_r($output, 1));
			}
			self::$global_is_condor_available = false;
		}
		$ra =  ['fieldnames' => $fieldnames, 'data' => $crecords];
		return $ra;
	}

	public static function get_database_queue($interval) {
		$dbfieldnames = ['execution_record_uuid', 'create_date', 'run_date', 'status', 'launch_flag', 'launch_counter', 'submitted_to_condor_flag', 'complete_flag'];
		$fieldnames = ['execrunuid', 'create', 'run', 'status', 'launch', 'count', 'submitted', 'complete'];
		$executionRecords = ExecutionRecord::where('complete_flag', '=', '0')->orWhere('launch_flag', '=', '1')->orWhereRaw("create_date >= subdate(now(), interval $interval minute)")->get();

		// pluck out requested fields from execution records
		//
		$records = [];
		for ($i = 0; $i < sizeof($executionRecords); $i++) {
			$values = [];
			for ($fni = 0; $fni < sizeof($fieldnames); $fni++) {
				$fieldname = $fieldnames[$fni];
				$dbfieldname = $dbfieldnames[$fni];
				$values[$fieldname] = $executionRecords[$i][$dbfieldname];
				if ($dbfieldname == 'execution_record_uuid') {
					$values[$fieldname] = '{execrunuid}' . $values[$fieldname];
				}
				else if (preg_match('/date$/', $dbfieldname)) {

					// database timestamp is already in UTC
					//
					$values[$fieldname] = '{timestamp}' . $values[$fieldname];
				}
			}
			$records[] = $values; 
		}
		if (empty($records)) {
			$fieldnames = [];
		}
		$ra =  ['fieldnames' => $fieldnames, 'data' => $records];
		return $ra;
	}

	public static function get_condor_status($condor_manager) {
		$fieldnames = [];
		$slots = [];
		$command = self::$global_condor_env_prefix . "condor_status -vm";
		if (! self::$global_is_localhost) {

			// modify command to use pool
			//
			$command .= " -pool $condor_manager";
		}
		if (self::$global_trace_logging) {
			Log::info("get_condor_status command: $command");
		}
		$returnVar = 1;
		if (self::$global_is_condor_available) {
			exec("$command 2>&1", $output, $returnVar);
		}
		if (($returnVar == 0) && (! empty($output))) {
			$have_slots = false;
			for ($i = 0; $i < sizeof($output); $i++) {

				// skip empty lines
				//
				if (empty($output[$i])) {

					// quit after last slot
					//
					if ($have_slots) {
						break;
					}
					continue;
				}

				// collect field names
				//
				if (preg_match('/Name/', $output[$i])) {
					$fieldnames = preg_split("/[\s]+/", $output[$i], -1, PREG_SPLIT_NO_EMPTY);
				}
				else {
					$slot = [];
					$temp = preg_split("/[\s]+/", $output[$i], -1, PREG_SPLIT_NO_EMPTY);

					// condor_status does not separate VMMem and ActivityTime string values
					// if ActivityTime > 99 days so split string based on length
					// VMMem is the 6th field and has length <= 7
					// ActivityTime has length 12 on overflow of > 99 days
					//
					for ($n = 0; $n < sizeof($temp); $n++) {
						$fieldname = $fieldnames[$n];
						$len = strlen($temp[$n]);

						// test VMMem and ActivityTime blended into single string
						//
						if (($n == 6) && ($len > 7)) {

							// ActivityTime has 12 characters instead of 11
							//
							$slot[$fieldname] = substr($temp[$n], 0, $len - 12);

							// push ActivityTime remainder of string onto temp
							//
							$temp[] = substr($temp[$n], $len - 12);
						}
						else {
							$slot[$fieldname] = $temp[$n];
						}
					}
					$slots[] = $slot;
					$have_slots = true;
				}
			}

			// if there are no slots in final list then clear fieldnames and summary for consistency
			if (empty($slots)) {
				$fieldnames = [];
			}
		}
		else if ($returnVar == 1) {
			if (self::$global_is_condor_available) {
				Log::error("Error - command: $command output: " . print_r($output, 1));
			}
			self::$global_is_condor_available = false;
		}
		$ra = ['fieldnames' => $fieldnames, 'data' => $slots];
		return $ra;
	}

	public static function get_condor_queue($submit_node, $condor_manager) {
		$JobStatus = [
			1	=> 'Idle',
			2	=> 'Running',
			3	=> 'Removed',
			4	=> 'Completed',
			5	=> 'Held',
			6	=> 'Transferring Output',
			7	=> 'Suspended'
		];
		$fieldnames = [];
		$jobs['all'] = [];
		$jobs['aruns'] = [];
		$jobs['mruns'] = [];
		$jobs['vruns'] = [];
		$summary['aruns'] = [];
		$summary['mruns'] = [];
		$summary['vruns'] = [];
		$summary['total'] = [];
		$command = self::$global_condor_env_prefix . "condor_q -allusers";
		if (! self::$global_is_localhost) {

			// modify command to use pool and name
			//
			$command .= " -pool $condor_manager -name $submit_node";
		}
		$time_now = time();
		$command .= " -long -attributes Owner,Match_UidDomain,ClusterId,ProcId,Cmd";
		if (! self::$global_is_localhost) {
			$command .= ",RemoteHost";
		}
		$command .= ",QDate,JobStartDate,RemoteWallClockTime,JobStatus,JobPrio,ImageSize,DiskUsage,SWAMP_arun_execrunuid,SWAMP_mrun_execrunuid,SWAMP_vrun_execrunuid";
		if (self::$global_trace_logging) {
			Log::info("get_condor_queue command: $command");
		}
		$returnVar = 1;
		if (self::$global_is_condor_available) {
			exec("$command 2>&1", $output, $returnVar);
		}
		if (($returnVar == 0) && (! empty($output))) {
			$fieldnames = ['EXECRUNUID', 'CMD', 'SUBMITTED', 'RUN TIME', 'ST', 'PRI', 'IMAGE', 'DISK'];
			if (! self::$global_is_localhost) {
				$fieldnames[] = 'HOST';
			}
			$fieldnames[] = 'VM';
			$summary['aruns'] = [
				'jobs'		=> 0,
				'completed'	=> 0,
				'removed'	=> 0,
				'idle'		=> 0,
				'running'	=> 0,
				'held'		=> 0,
				'suspended'	=> 0,
			];
			$summary['mruns'] = [
				'jobs'		=> 0,
				'completed'	=> 0,
				'removed'	=> 0,
				'idle'		=> 0,
				'running'	=> 0,
				'held'		=> 0,
				'suspended'	=> 0,
			];
			$summary['vruns'] = [
				'jobs'		=> 0,
				'completed'	=> 0,
				'removed'	=> 0,
				'idle'		=> 0,
				'running'	=> 0,
				'held'		=> 0,
				'suspended'	=> 0,
			];
			$summary['total'] = [
				'jobs'		=> 0,
				'completed'	=> 0,
				'removed'	=> 0,
				'idle'		=> 0,
				'running'	=> 0,
				'held'		=> 0,
				'suspended'	=> 0,
			];
			$jobs['all'] = [];
			$jobs['aruns'] = [];
			$jobs['mruns'] = [];
			$jobs['vruns'] = [];
			$job = [];
			$clusterid = "";
			$procid = "";
			$owner = "";
			$uid_domain = "";
			for ($i = 0; $i < sizeof($output); $i++) {

				// start_new_job on empty line
				//
				if (empty($output[$i])) {
					if ($runtype != 'unknown') {
						$summary[$runtype][strtolower($status)] += 1;
						$summary[$runtype]['jobs'] += 1;
						$summary['total'][strtolower($status)] += 1;
						$summary['total']['jobs'] += 1;
					}
					if (! empty($owner) && ! empty($uid_domain)) {
						$job['VM'] = $owner . '_' . $uid_domain . '_' . $clusterid . '_' . $procid;
					}

					// order job by fieldnames
					//
					$orderedjob = [];
					for ($n = 0; $n < sizeof($fieldnames); $n++) {
						$orderedjob[$fieldnames[$n]] = isset($job[$fieldnames[$n]]) ? $job[$fieldnames[$n]] : '';
					}

					// add hidden fields here
					//
					$orderedjob['type'] = isset($job['type']) ? $job['type'] : '';
					$jobs['all'][] = $orderedjob;
					$jobs[$runtype][] = $orderedjob; 
					$job = [];
					$clusterid = "";
					$procid = "";
					$owner = "";
					$uid_domain = "";
					continue;
				}
				else {
					list($name, $value) = explode(" = ", $output[$i]);
					$value = trim($value, "\"");

					// execrunuid
					//
					if (preg_match("/^SWAMP_.run_execrunuid$/", $name)) {
						$parts = explode(".", $value);
						$execrunuid = isset($parts[1]) ? $parts[1] : $value;
						if (preg_match("/^M-/", $execrunuid)) {
							$execrunuid = preg_replace('/^M-/', '', $execrunuid);
							$job['type'] = 'mrun';
						}
						elseif (preg_match("/^vrun_/", $execrunuid)) {
							$execrunuid = preg_replace('/^vrun_/', '', $execrunuid);
							$execrunuid = preg_replace('/_.*$/', '', $execrunuid);
							$execrunuid = '{projectuid}' . $execrunuid;
							$job['type'] = 'vrun';
						}
						else {
							$execrunuid = '{execrunuid}' . $execrunuid;
							$job['type'] = 'arun';
						}
						$job['EXECRUNUID'] = $execrunuid;
					}

					// jobid - to be determined
					//
					elseif ($name == 'ClusterId') {
						$clusterid = $value;
					}
					elseif ($name == 'ProcId') {
						$procid = $value;
					}

					// cmd
					//
					elseif ($name == 'Cmd') {
						$job['CMD'] = $value;
						if (preg_match("/^aswamp/", $value)) {
							$runtype = 'aruns';
						}
						elseif (preg_match("/^mswamp/", $value)) {
							$runtype = 'mruns';
						}
						elseif (preg_match("/^vswamp/", $value)) {
							$runtype = 'vruns';
						}
						else {
							$runtype = 'unknown';
						}
					}

					// submitted
					//
					elseif ($name == 'QDate') {
						$date = new DateTime("@$value");

						// convert timestamp to UTC
						//
						$job['SUBMITTED'] = '{timestamp}' . $date->format('Y-m-d H:i:s');
					}

					// run time - assumes JobStartDate always preceeds RemoteWallClockTime in output
					// compute run time from time_now - JobStartDate
					//
					elseif ($name == 'JobStartDate') {
						$run_time = $time_now - $value;
						$days = intval($run_time / 86400);
						$run_time = $run_time % 86400;
						$hours = intval($run_time / 3600);
						$run_time = $run_time % 3600;
						$minutes = intval($run_time / 60);
						$seconds = $run_time % 60;
						$job['RUN TIME'] = sprintf("%d+%02d:%02d:%02d", $days, $hours, $minutes, $seconds);
					}

					// replace run time with RemoteWallClockTime if it is > 0
					//
					elseif ($name == 'RemoteWallClockTime') {
						$run_time = $value;
						if ($value > 0) {
							$days = intval($run_time / 86400);
							$run_time = $run_time % 86400;
							$hours = intval($run_time / 3600);
							$run_time = $run_time % 3600;
							$minutes = intval($run_time / 60);
							$seconds = $run_time % 60;
							$job['RUN TIME'] = sprintf("%d+%02d:%02d:%02d", $days, $hours, $minutes, $seconds);
						}
					}

					// st
					//
					elseif ($name == 'JobStatus') {
						$status = $JobStatus[$value];
						$job['ST'] = $status;
					}

					// pri
					//
					elseif ($name == 'JobPrio') {
						$job['PRI'] = $value;
					}

					// image
					//
					elseif ($name == 'ImageSize') {
						$job['IMAGE'] = sprintf("%.1f", $value / 1024.0);
					}

					// disk
					//
					elseif ($name == 'DiskUsage') {
						$job['DISK'] = sprintf("%.1f", $value / 1024.0);
					}

					// host
					//
					elseif ($name == 'RemoteHost') {
						$value = preg_replace('/^slot.*\@/', '', $value);
						$value = preg_replace('/\..*$/', '', $value);
						$job['HOST'] = $value;
					}

					// vm - to be determined
					//
					elseif ($name == 'Owner') {
						$owner = $value;
					}
					elseif ($name == 'Match_UidDomain') {
						$uid_domain = $value;
					}
				}
				if (empty($job['RUN TIME'])) {
					$job['RUN TIME'] = '0+00:00:00';
				}
			}

			// if there are no jobs in any of the final lists 
			// then clear fieldnames and summary for consistency
			//
			if (empty($jobs['all'])) {
				$fieldnames = [];
				$summary['aruns'] = [];
				$summary['mruns'] = [];
				$summary['vruns'] = [];
				$summary['total'] = [];
			}
		}
		else if ($returnVar == 1) {
			if (self::$global_is_condor_available) {
				Log::error("Error - command: $command output: " . print_r($output, 1));
			}
			self::$global_is_condor_available = false;
		}
		$ra = ['fieldnames' => $fieldnames, 'data' => $jobs, 'summary' => $summary];
		return $ra;
	}

	public static function get_command_type($ppid, $pid, $command) {
		if (preg_match("/vmu_.*Assessment/", $command)) {
    		return 'swamp arun';
		} elseif (preg_match("/vmu_.*Viewer/", $command)) {
       		return 'swamp vrun';
		} elseif (preg_match("/vmu_.*/", $command)) {
			return 'swamp daemon';
		} elseif (preg_match("/mysql/", $command)) {
			return 'mysql';
		} elseif (preg_match("/condor/", $command)) {

			// if there is no condor_env_prefix then this is rpm condor
			//
			if (empty(self::$global_condor_env_prefix)) {
				return 'condor';
			}

			// if /opt/swamp/htcondor is in command then this is swamp-condor
			// if we have found condor_master then record pid
			//
			if (preg_match("/opt\/swamp\/htcondor/", $command)) {
				if (preg_match("/condor_master/", $command)) {
                    self::$global_opt_swamp_htcondor_sbin_condor_master_pid = $pid; 
				}
				return 'swamp condor';
			}

			// if /opt/swamp/htcondor is not in command
			// if we have found condor_master then record pid
			//
            if (preg_match("/condor_master/", $command)) {
				self::$global_usr_sbin_condor_master_pid = $pid; 
				return 'condor';
            }

			// if match on /usr/sbin/condor_master for ppid then rpm condor
			//
            if ($ppid == self::$global_usr_sbin_condor_master_pid) {
				return 'condor';
			}

			// if match on /opt/swamp/htcondor/sbin/condor_master for ppid then swamp-condor
			//
			if ($ppid == self::$global_opt_swamp_htcondor_sbin_condor_master_pid) {
				return 'swamp condor';
            }
            return 'unknown condor';
		} else {
			return 'other';
		}
	}

	public static function get_swamp_processes($hostname, $host) {
		$fieldnames = [];
		$processes = [];
		$command = "ps ax -o \"user ppid pid pcpu pmem tty stat etime time command\" | egrep 'PID|vmu_|mysql|condor' | fgrep -v grep | fgrep -vw -e vim -e vi";
		if ($host != $hostname) {

			// multi-host currently not implemented
			//
			$ra = ['fieldnames' => $fieldnames, 'data' => $processes];
			return $ra;

			// modify command to use ssh or other remote access method
			//
		}

		// capture time of command execution in UTC for adjustment of elapsed time
		//
		$command_date = new DateTime();

		if (self::$global_trace_logging) {
			Log::info("get_swamp_processes command: $command");
		}
		exec("$command 2>&1", $output, $returnVar);
		if (($returnVar == 0) && (! empty($output))) {
			$commandfield = "";
			for ($i = 0; $i < sizeof($output); $i++) {

				// skip empty lines
				//
				if (empty($output[$i])) {
					continue;
				}

				// collect field names
				//
				if (preg_match('/PPID\s+PID\s+%CPU\s+%MEM/', $output[$i])) {
					$fieldnames = preg_split("/[\s]+/", $output[$i], -1, PREG_SPLIT_NO_EMPTY);
				}
				else {
					$process = [];

					// collect all of the COMMAND field in the last element of the split
					//
					$temp = preg_split("/[\s]+/", $output[$i], sizeof($fieldnames), PREG_SPLIT_NO_EMPTY);
					$process['TYPE'] = self::get_command_type($temp[1], $temp[2], $temp[sizeof($fieldnames) - 1]);
					for ($n = 0; $n < sizeof($fieldnames); $n++) {
						$fieldname = $fieldnames[$n];

						// adjust etime to date time (in UTC) offset from command execution time
						//
						if ($fieldname == 'ELAPSED') {
							
							// obtain elapsed time components
							//
							$parts1 = explode('-', $temp[$n]);
							$days = 0;
							$time = $parts1[0];
							if (sizeof($parts1) == 2) {
								$days = $parts1[0];
								$time = $parts1[1];
							}
							$parts2 = explode(':', $time);
							$hours = 0;
							$minutes = $parts2[0];
							$seconds = $parts2[1];
							if (sizeof($parts2) == 3) {
								$hours = $parts2[0];
								$minutes = $parts2[1];
								$seconds = $parts2[2];
							}

							// deep copy command execution time
							//
							$date = clone $command_date;

							// adjust command execution time backward by etime
							//
							$date->modify('-'.$days.' day');
							$date->modify('-'.$hours.' hour');
							$date->modify('-'.$minutes.' minute');
							$date->modify('-'.$seconds.' second');

							// timestamp is already in UTC
							//
							$process['STARTED'] = '{timestamp}' . $date->format('Y-m-d H:i:s');
						}
						else {
							$process[$fieldname] = $temp[$n];
						}
					}
				    $processes[] = $process;
				}
			}
			$fieldnames = array_merge(['TYPE'], $fieldnames);
			$index = array_search('ELAPSED', $fieldnames);
			if ($index != false) {
				$fieldnames[$index] = 'STARTED';
			}
			// if there are no processes in final list then clear fieldnames for consistency
			//
			if (empty($processes)) {
				$fieldnames = [];
			}
		}
		elseif ($returnVar == 1) {
			Log::error("Error - command: $command output: " . print_r($output, 1));
		}
		$ra = ['fieldnames' => $fieldnames, 'data' => $processes];
		return $ra;
	}

	public static function get_virtual_machines($hostname, $exec_node) {
		$fieldnames = [];
		$machines = [];
		$command = "sudo virsh list --all";
		if ($exec_node != $hostname) {

			// multi-host currently not implemented
			//
			$ra = ['fieldnames' => $fieldnames, 'data' => $machines];
			return $ra;

			// modify command to use ssh or other remote access method
			//
		}
		if (self::$global_trace_logging) {
			Log::info("get_virtual_machines command: $command");
		}
		exec("$command 2>&1", $output, $returnVar);
		if (($returnVar == 0) && (! empty($output))) {
			for ($i = 0; $i < sizeof($output); $i++) {

				// skip empty lines
				//
				if (empty($output[$i])) {
					continue;
				}

				// skip --- lines
				//
				if (preg_match('/--/', $output[$i])) {
					continue;
				}

				// collect field names
				//
				if (preg_match('/Id/', $output[$i])) {
					$fieldnames = preg_split("/[\s]+/", $output[$i], -1, PREG_SPLIT_NO_EMPTY);
				}

				// collect machines
				//
				else {
					$machine = [];
					$temp = preg_split("/[\s]+/", $output[$i], sizeof($fieldnames), PREG_SPLIT_NO_EMPTY);
					for ($n = 0; $n < sizeof($fieldnames); $n++) {
						$fieldname = $fieldnames[$n];
						$machine[$fieldname] = $temp[$n];
					}
					$machines[] = $machine;
				}
			}

			// if there are no machines in final list then clear fieldnames for consistency
			//
			if (empty($machines)) {
				$fieldnames = [];
			}
		}
		elseif ($returnVar == 1) {
			Log::error("Error - command: $command output: " . print_r($output, 1));
		}
		$ra =  ['fieldnames' => $fieldnames, 'data' => $machines];
		return $ra;
	}

	public static function get_submit_job_dirs($hostname, $submit_node) {
		$fieldnames = [];
		$jobdirs = [];
		$command = "ls -lrt --time-style=+'%Y-%m-%d %H:%M:%S' /opt/swamp/run";
		if ($submit_node != $hostname) {

			// multi-host currently not implemented
			//
			$ra = ['fieldnames' => $fieldnames, 'data' => $jobdirs];
			return $ra;

			// modify command to use ssh or other remote access method
			//
		}
		if (self::$global_trace_logging) {
			Log::info("get_submit_job_dirs command: $command");
		}
		exec("$command 2>&1", $output, $returnVar);
		if (($returnVar == 0) && (! empty($output))) {

			// this is a hack because unix/linux ls command does not display field names
			// ls -lrt is presumably guaranteed to produce exactly the following columns
			// clusterid field is added after the ls command by finding the ClusterId_<clusterid> file
			//
			$fieldnames = ['permissions', 'links', 'owner', 'group', 'size', 'modtime', 'dir', 'clusterid'];
			for ($i = 0; $i < sizeof($output); $i++) {
				if (empty($output[$i])) {
					continue;
				}

				// Log::info("output: <" . $output[$i] . ">");
				//
				if (preg_match('/total/', $output[$i])) {
					continue;
				}
				if (preg_match('/swamp_monitor/', $output[$i])) {
					continue;
				}
				if (preg_match('/.bog$/', $output[$i])) {
					continue;
				}
				$jobdir = [];

				// modtime parts and dir are in part1[5]
				//
				$part1 = preg_split("/[\s]+/", $output[$i], 6, PREG_SPLIT_NO_EMPTY);

				// modtime = part2[0,1]
				// dir = part2[2]
				//
				$part2 = preg_split("/[\s]+/", $part1[5], -1, PREG_SPLIT_NO_EMPTY);
				for ($n = 0; $n < sizeof($fieldnames); $n++) {
					$fieldname = $fieldnames[$n];
					if ($n <= 4) {
						$jobdir[$fieldname] = $part1[$n];
					}
					elseif ($n == 5) {
						
						// convert timestamp to UTC
						//
						$systemTimezone = `date +'%Z'`;
						$date = new DateTime($part2[0] . ' ' . $part2[1] . ' ' . $systemTimezone);
						$date->setTimezone(new DateTimeZone("UTC"));
						$jobdir[$fieldname] = '{timestamp}' . $date->format('Y-m-d H:i:s');
					}
					else {
						$jobdir[$fieldname] = $part2[2];
					}
				}

            	// look in dir for ClusterId_<clusterid>
            	//
            	$jobdir['clusterid'] = 'n/a'; 
            	if (($dh = @opendir('/opt/swamp/run/' . $jobdir['dir'])) !== false) {
                	while (false !== ($file = readdir($dh))) {
                    	if (preg_match('/ClusterId/', $file)) {
                        	$clusterid = str_replace('ClusterId_', '', $file);
                        	if (! $clusterid) {
                            	$clusterid = 'n/m'; 
                        	}
                        	$jobdir['clusterid'] = $clusterid;
							break;
                    	}
                	}
                	closedir($dh);  
            	}
            	else {
                	$jobdir['clusterid'] = 'opendir failed';
            	}
				$jobdirs[] = $jobdir;
			}

			// if there are no jobs in final list then clear fieldnames for consistency
			//
			if (empty($jobdirs)) {
				$fieldnames = [];
			}
		}
		elseif ($returnVar == 1) {
			Log::error("Error - command: $command output: " . print_r($output, 1));
		}
		$ra = ['fieldnames' => $fieldnames, 'data' => $jobdirs];
		return $ra;
	}

	public static function getCurrent($options) {
		if (self::$global_trace_logging) {
			Log::info("getCurrent options: " . print_r($options, 1));
		}
		self::$global_condor_env_prefix = HTCondor::get_condor_env_command();
		$hostname = gethostname();
		$collector_host = self::get_condor_collector_host();

		// Log::info(".env collector_host: <$collector_host>");
		//
		self::$global_is_localhost = false;
		$parts = explode('.', $collector_host);
		if (preg_match('/localhost/', $collector_host)) {
			$parts = explode('.', $hostname);
			self::$global_is_localhost = true;
		}

		// change csacol to csacon and domain to mirsam
		//
		$parts[0] = str_replace('csacol', 'csacon', $parts[0]);
		$parts[1] = 'mirsam';
		$condor_manager = implode('.', $parts);

		if (self::$global_is_localhost) {
			$submit_nodes[] = $hostname;
			$exec_nodes[] = $hostname;
			$data_nodes[] = $hostname;
		}
		else {
			$submit_nodes = self::get_condor_submit_nodes($condor_manager);
			$exec_nodes = self::get_condor_exec_nodes($condor_manager);
			$data_nodes = self::get_swamp_data_nodes($hostname);
		}

		if (self::$global_trace_logging) {
        	Log::info("collector_host: <$collector_host>");
			Log::info("condor_manager: <$condor_manager>");
			Log::info("submit_nodes: <" . implode(" ", $submit_nodes) . ">");
			Log::info("exec_nodes: <" . implode(" ", $exec_nodes) . ">"); 
			Log::info("data_nodes: <" . implode(" ", $data_nodes) . ">"); 
		}

		// condor available
		//
		self::$global_is_condor_available = true;

		// database queue
		//
		$interval = 0;
		if (! empty($options['database-record-interval'])) {
			$interval = $options['database-record-interval'];
		}

		// Log::info("getCurrent interval: $interval");
		//
		$dbq = self::get_database_queue($interval);
		$all_dbq["database"] = $dbq;

		// condor status
		//
		$cs = self::get_condor_status($condor_manager);
		$all_cs[$condor_manager] = $cs;

		// condor queue
		//
		foreach ($submit_nodes as $submit_node) {
			$cq = self::get_condor_queue($submit_node, $condor_manager);
			$fieldnames = $cq['fieldnames'];
			$summary = $cq['summary'];
			$all_data = $cq['data']['all'];
			$arun_data = $cq['data']['aruns'];
			$mrun_data = $cq['data']['mruns'];
			$vrun_data = $cq['data']['vruns'];
			$all_cq[$submit_node] = ['fieldnames' => $fieldnames, 'data' => $all_data, 'summary' => $summary];
			$all_caq[$submit_node] = ['fieldnames' => $fieldnames, 'data' => $arun_data];
			$all_cmq[$submit_node] = ['fieldnames' => $fieldnames, 'data' => $mrun_data];
			$all_cvq[$submit_node] = ['fieldnames' => $fieldnames, 'data' => $vrun_data];
		}

		// assessment collector
		//
		$acr = self::get_collector_records($collector_host, 'assessment');
		$all_acr[$collector_host] = $acr;

		// viewer collector
		//
		$vcr = self::get_collector_records($collector_host, 'viewer');
		$all_vcr[$collector_host] = $vcr;

		// submit job dirs
		//
		foreach ($submit_nodes as $submit_node) {
			$sjd = self::get_submit_job_dirs($hostname, $submit_node);
			if (! empty($sjd['fieldnames'])) {
				$all_sjd[$submit_node] = $sjd;
			}
		}

		// swamp processes
		// first get submit_node
		//
		foreach ($submit_nodes as $submit_node) {
			$sp = self::get_swamp_processes($hostname, $submit_node);
			if (! empty($sp['fieldnames'])) {
				$all_sp[$submit_node] = $sp;
			}
		}

		// then iterate over data_nodes if they are different from the submit_nodes
		//
		foreach ($data_nodes as $data_node) {
			if (! in_array($data_node, $submit_nodes)) {
				$sp = self::get_swamp_processes($hostname, $data_node);
				if (! empty($sp['fieldnames'])) {
					$all_sp[$data_node] = $sp;
				}
			}
		}

		// then iterate over exec_nodes if they are different from the submit_nodes
		//
		foreach ($exec_nodes as $exec_node) {
			if (! in_array($exec_node, $submit_nodes)) {
				$sp = self::get_swamp_processes($hostname, $exec_node);
				if (! empty($sp['fieldnames'])) {
					$all_sp[$exec_node] = $sp;
				}
			}
		}

		// virsh list
		//
		foreach ($exec_nodes as $exec_node) {
			$vm = self::get_virtual_machines($hostname, $exec_node);
			if (! empty($vm['fieldnames'])) {
				$all_vm[$exec_node] = $vm;
			}
		}

		// the order in which tables appear in the output is specified by array order
		//
		$ra = [
				'Condor Queue' => $all_cq, 
				'Assessment Queue' => $all_caq, 
				'Metric Queue' => $all_cmq, 
				'Viewer Queue' => $all_cvq, 
				'Assessment Records' => $all_acr,
				'Viewer Records' => $all_vcr,
				'Database Queue' => $all_dbq, 
				'Condor Status' => $all_cs, 
		];
		if (isset($all_sjd)) {
			$ra['Submit Job Directories'] = $all_sjd;
		}
		if (isset($all_sp)) {
			$ra['SWAMP Processes'] = $all_sp;
		}
		if (isset($all_vm)) {
			$ra['Virtual Machines'] = $all_vm;
		}

		// Return the data table object
		//
		// Log::info("getCurrent returns");
		return $ra;
	}
}