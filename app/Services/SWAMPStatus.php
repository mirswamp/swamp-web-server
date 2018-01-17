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
|        Copyright (C) 2012-2018 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Services;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class SWAMPStatus {

	private static function get_condor_collector_host() {
		$collector_host = Config::get('app.htcondorcollectorhost');
		return $collector_host;
	}

	private static function get_condor_submit_node($condor_manager) {
		$command = "condor_status -pool $condor_manager -schedd -af Name";
		exec($command, $output, $returnVar);
		$submit_node = "";
		if (($returnVar == 0) && (! empty($output))) {
			$submit_node = str_replace('"', '', $output[0]);
		}
		return $submit_node;
	}

	private static function get_condor_exec_nodes($condor_manager) {
		$command = "condor_status -pool $condor_manager -af Machine -constraint 'SlotType == \"Partitionable\"'";
		exec($command, $output, $returnVar);
		$exec_nodes = [];
		if (($returnVar == 0) && (! empty($output))) {
			for ($i = 0; $i < sizeof($output); $i++) {
				$exec_nodes[$i] = str_replace('"', '', $output[$i]);
			}
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

	// currently these translation values come from vmu_ViewerSupport.pm
	// and must reconcile with the VIEWER_STATE values therein
	private static function state_to_name($state) {
		if ($state == 0) {
			return "null";
		}
		if ($state == 1) {
			return "launching";
		}
		if ($state == 2) {
			return "ready";
		}
		if ($state == -1) {
			return "stopping";
		}
		if ($state == -2) {
			return "jobdir";
		}
		if ($state == -3) {
			return "shutdown";
		}
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
		$command = "condor_status -pool $collector_host -sort $sortfield -generic -af:V, ";
		foreach ($fields as $field) {
			$command .= ' ' . $field;
		}
		if (! empty($constraint)) {
			$command .= ' ' . $constraint;
		}
		exec($command, $output, $returnVar);
		if (($returnVar == 0) && (! empty($output))) {
			$prefix = "SWAMP_vmu_" . $title . "_";
			// special case to convert Name to execrunuid
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
					$fieldvalue = str_replace(array('"', ','), '',  trim($temp[$n]));

					if ($title == 'assessment') {
						// check execrunuid column for execrunuuids
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
						$crecord[$fieldname] = SWAMPStatus::state_to_name($crecord[$fieldname]);
					}
				}
				$crecords[] = $crecord;
			}
		}
		// if there are no crecords in final list then clear fieldnames for consistency
		if (empty($crecords)) $fieldnames = [];
		$ra =  array('fieldnames' => $fieldnames, 'data' => $crecords);
		return $ra;
	}

	public static function get_condor_status($hostname, $submit_node, $condor_manager) {
		$fieldnames = [];
		$slots = [];
		$command = "condor_status -vm";
		if ($submit_node != $hostname) {
			// modify command to use pool and name
			$command .= " -pool $condor_manager";
		}
		// Log::info("get_condor_status command: $command");
		exec($command, $output, $returnVar);
		// echo "Output: "; print_r($output); echo "\n";
		// echo "returnVar: "; print_r($returnVar); echo "\n";
		if (($returnVar == 0) && (! empty($output))) {
			for ($i = 0; $i < sizeof($output); $i++) {
				// skip empty lines
				if (empty($output[$i])) continue;
				// quit after last slot
				if (preg_match('/Machines/', $output[$i])) break;
				// collect field names
				if (preg_match('/Name/', $output[$i])) {
					$fieldnames = preg_split("/[\s]+/", $output[$i], -1, PREG_SPLIT_NO_EMPTY);
				}
				else {
					$slot = [];
					$temp = preg_split("/[\s]+/", $output[$i], -1, PREG_SPLIT_NO_EMPTY);
					for ($n = 0; $n < sizeof($fieldnames); $n++) {
						$fieldname = $fieldnames[$n];
						$slot[$fieldname] = $temp[$n];
					}
					$slots[] = $slot;
				}
			}
		}
		// if there are no slots in final list then clear fieldnames and summary for consistency
		if (empty($slots)) {
			$fieldnames = [];
		}
		$ra = array('fieldnames' => $fieldnames, 'data' => $slots);
		return $ra;
	}

	public static function get_condor_queue($hostname, $submit_node, $condor_manager) {
		$JobStatus = array(
			1	=> 'Idle',
			2	=> 'Running',
			3	=> 'Removed',
			4	=> 'Completed',
			5	=> 'Held',
			6	=> 'Transferring Output',
			7	=> 'Suspended'
		);
		$fieldnames = [];
		$jobs = [];
		$summary['aruns'] = [];
		$summary['mruns'] = [];
		$summary['vruns'] = [];
		$summary['total'] = [];
		$command = "condor_q -allusers";
		$localhost = true;
		if ($submit_node != $hostname) {
			// modify command to use pool and name
			$command .= " -pool $condor_manager -name $submit_node";
			$localhost = false;
		}
		$time_now = time();
		$command .= " -long -attributes Owner,Match_UidDomain,ClusterId,ProcId,Cmd";
		if ($localhost === false) {
			$command .= ",RemoteHost";
		}
		$command .= ",QDate,JobStartDate,JobStatus,JobPrio,ImageSize,DiskUsage,SWAMP_arun_execrunuid,SWAMP_mrun_execrunuid,SWAMP_vrun_execrunuid";
		// Log::info("get_condor_queue command: $command");
		exec($command, $output, $returnVar);
		// echo "Output: "; print_r($output); echo "\n";
		// echo "returnVar: "; print_r($returnVar); echo "\n";
		if (($returnVar == 0) && (! empty($output))) {
			$fieldnames = ['EXECRUNUID', 'CMD', 'SUBMITTED', 'RUN TIME', 'ST', 'PRI', 'IMAGE', 'DISK'];
			if ($localhost === false) {
				$fieldnames[] = 'HOST';
			}
			$fieldnames[] = 'VM';
			$summary['aruns'] = array(
				'jobs'		=> 0,
				'completed'	=> 0,
				'removed'	=> 0,
				'idle'		=> 0,
				'running'	=> 0,
				'held'		=> 0,
				'suspended'	=> 0,
			);
			$summary['mruns'] = array(
				'jobs'		=> 0,
				'completed'	=> 0,
				'removed'	=> 0,
				'idle'		=> 0,
				'running'	=> 0,
				'held'		=> 0,
				'suspended'	=> 0,
			);
			$summary['vruns'] = array(
				'jobs'		=> 0,
				'completed'	=> 0,
				'removed'	=> 0,
				'idle'		=> 0,
				'running'	=> 0,
				'held'		=> 0,
				'suspended'	=> 0,
			);
			$summary['total'] = array(
				'jobs'		=> 0,
				'completed'	=> 0,
				'removed'	=> 0,
				'idle'		=> 0,
				'running'	=> 0,
				'held'		=> 0,
				'suspended'	=> 0,
			);
			$jobs = [];
			$job = [];
			$clusterid = "";
			$procid = "";
			$owner = "";
			$uid_domain = "";
			for ($i = 0; $i < sizeof($output); $i++) {
				// start_new_job on empty line
				if (empty($output[$i])) {
					if ($runtype != 'unknown') {
						$summary[$runtype][strtolower($status)] += 1;
						$summary[$runtype]['jobs'] += 1;
						$summary['total'][strtolower($status)] += 1;
						$summary['total']['jobs'] += 1;
					}
					if (! empty($owner) && ! empty($uid_domain)) {
						$job['VM'] = $owner . '_' . $uid_domain . '_' . $clusterid . '_' . $procid;
						// echo "VM: ", $job['VM'], "\n";
					}
					// order job by fieldnames
					$orderedjob = [];
					for ($n = 0; $n < sizeof($fieldnames); $n++) {
						$orderedjob[$fieldnames[$n]] = isset($job[$fieldnames[$n]]) ? $job[$fieldnames[$n]] : '';
					}
					// add hidden fields here
					$orderedjob['type'] = $job['type'];
					$jobs[] = $orderedjob; 
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
						// echo "EXECRUNUID: ", $job['EXECRUNUID'], "\n";
					}
					// jobid - to be determined
					elseif ($name == 'ClusterId') {
						$clusterid = $value;
					}
					elseif ($name == 'ProcId') {
						$procid = $value;
					}
					// cmd
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
						// echo "CMD: ", $job['CMD'], "\n";
					}
					// submitted, run_time
					elseif ($name == 'QDate') {
						$job['SUBMITTED'] = strftime("%m/%d %H:%M", $value);
						// echo "SUBMITTED: ", $job['SUBMITTED'], "\n";
					}
					elseif ($name == 'JobStartDate') {
						$run_time = $time_now - $value;
						$days = intval($run_time / 86400);
						$run_time = $run_time % 86400;
						$hours = intval($run_time / 3600);
						$run_time = $run_time % 3600;
						$minutes = intval($run_time / 60);
						$seconds = $run_time % 60;
						$job['RUN TIME'] = sprintf("%d+%02d:%02d:%02d", $days, $hours, $minutes, $seconds);
						// echo "RUN TIME: ", $job['RUN TIME'], "\n";
					}
					// st
					elseif ($name == 'JobStatus') {
						$status = $JobStatus[$value];
						$job['ST'] = $status;
						// echo "ST: ", $value, " ", $job['ST'], "\n";
					}
					// pri
					elseif ($name == 'JobPrio') {
						$job['PRI'] = $value;
						// echo "PRI: ", $job['PRI'], "\n";
					}
					// image
					elseif ($name == 'ImageSize') {
						$job['IMAGE'] = sprintf("%.1f", $value / 1024.0);
						// echo "IMAGE: ", $job['IMAGE'], "\n";
					}
					// disk
					elseif ($name == 'DiskUsage') {
						$job['DISK'] = sprintf("%.1f", $value / 1024.0);
						// echo "DISK: ", $job['DISK'], "\n";
					}
					// host
					elseif ($name == 'RemoteHost') {
						$value = preg_replace('/^slot.*\@/', '', $value);
						$value = preg_replace('/\..*$/', '', $value);
						$job['HOST'] = $value;
						// echo "HOST: ", $job['HOST'], "\n";
					}
					// vm - to be determined
					elseif ($name == 'Owner') {
						$owner = $value;
					}
					elseif ($name == 'Match_UidDomain') {
						$uid_domain = $value;
					}
					// echo $name, ' => ', $value, "\n";
				}
				if (empty($job['RUN TIME'])) {
					$job['RUN TIME'] = '0+00:00:00';
					// echo "RUN TIME: ", $job['RUN TIME'], "\n";
				}
			}
		}
		// if there are no jobs in final list then clear fieldnames and summary for consistency
		if (empty($jobs)) {
			$fieldnames = [];
			$summary['aruns'] = [];
			$summary['mruns'] = [];
			$summary['vruns'] = [];
			$summary['total'] = [];
		}
		$ra = array('fieldnames' => $fieldnames, 'data' => $jobs, 'summary' => $summary);
		return $ra;
	}

	public static function get_command_type($command) {
		if (preg_match("/vmu_.*Assessment/", $command)) {
    		return 'swamp arun';
		} elseif (preg_match("/vmu_.*Viewer/", $command)) {
       		return 'swamp vrun';
		} elseif (preg_match("/vmu_.*/", $command)) {
			return 'swamp daemon';
		} elseif (preg_match("/mysql/", $command)) {
			return 'mysql';
		} elseif (preg_match("/condor/", $command)) {
			return 'condor';
		} else {
			return 'other';
		}
	}

	public static function get_swamp_processes($hostname, $host) {
		$fieldnames = [];
		$processes = [];
		// $command = "ps aux | egrep 'PID|vmu_|mysql|condor' | grep -v grep";
		$command = "ps ax -o \"user ppid pid pcpu pmem tty stat start time command\" | egrep 'PID|vmu_|mysql|condor' | grep -v grep";
		if ($host != $hostname) {
			// multi-host currently not implemented
			$ra = array('fieldnames' => $fieldnames, 'data' => $processes);
			return $ra;
			// modify command to use ssh or other remote access method
		}
		exec($command, $output, $returnVar);
		if (($returnVar == 0) && (! empty($output))) {
			$commandfield = "";
			for ($i = 0; $i < sizeof($output); $i++) {
				// skip empty lines
				if (empty($output[$i])) continue;
				// collect field names
				if (preg_match('/PID/', $output[$i])) {
					$fieldnames = preg_split("/[\s]+/", $output[$i], -1, PREG_SPLIT_NO_EMPTY);
				}
				else {
					$process = [];
					// collect all of the COMMAND field in the last element of the split
					$temp = preg_split("/[\s]+/", $output[$i], sizeof($fieldnames), PREG_SPLIT_NO_EMPTY);
					$process['TYPE'] = self::get_command_type($temp[sizeof($fieldnames) - 1]);
					for ($n = 0; $n < sizeof($fieldnames); $n++) {
						$fieldname = $fieldnames[$n];
						$process[$fieldname] = $temp[$n];
					}
				    $processes[] = $process;
				}
			}
			$fieldnames = array_merge(['TYPE'], $fieldnames);
		}
		// if there are no processes in final list then clear fieldnames for consistency
		if (empty($processes)) $fieldnames = [];
		$ra = array('fieldnames' => $fieldnames, 'data' => $processes);
		return $ra;
	}

	public static function get_virtual_machines($hostname, $exec_node) {
		$fieldnames = [];
		$machines = [];
		$command = "sudo virsh list --all";
		if ($exec_node != $hostname) {
			// multi-host currently not implemented
			$ra = array('fieldnames' => $fieldnames, 'data' => $machines);
			return $ra;
			// modify command to use ssh or other remote access method
		}
		exec($command, $output, $returnVar);
		if (($returnVar == 0) && (! empty($output))) {
			for ($i = 0; $i < sizeof($output); $i++) {
				// skipt empty lines
				if (empty($output[$i])) continue;
				// skip --- lines
				if (preg_match('/--/', $output[$i])) continue;
				// collect field names
				if (preg_match('/Id/', $output[$i])) {
					$fieldnames = preg_split("/[\s]+/", $output[$i], -1, PREG_SPLIT_NO_EMPTY);
				}
				// collect machines
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
		}
		// if there are no machines in final list then clear fieldnames for consistency
		if (empty($machines)) $fieldnames = [];
		$ra =  array('fieldnames' => $fieldnames, 'data' => $machines);
		return $ra;
	}

	public static function get_submit_job_dirs($hostname, $submit_node) {
		$fieldnames = [];
		$jobdirs = [];
		$command = "ls -lrt /opt/swamp/run";
		if ($submit_node != $hostname) {
			// multi-host currently not implemented
			$ra = array('fieldnames' => $fieldnames, 'data' => $jobdirs);
			return $ra;
			// modify command to use ssh or other remote access method
		}
		exec($command, $output, $returnVar);
		if (($returnVar == 0) && (! empty($output))) {
			// this is a hack because unix/linux ls command does not display field names
			// ls -lrt is presumably guaranteed to produce exactly the following columns
			// clusterid field is added after the ls command by finding the ClusterId_<clusterid> file
			$fieldnames = ['permissions', 'links', 'owner', 'group', 'size', 'modtime', 'dir', 'clusterid'];
			for ($i = 0; $i < sizeof($output); $i++) {
				if (empty($output[$i])) continue;
				// Log::info("output: <" . $output[$i] . ">");
				if (preg_match('/total/', $output[$i])) continue;
				if (preg_match('/swamp_monitor/', $output[$i])) continue;
				if (preg_match('/.bog$/', $output[$i])) continue;
				$jobdir = [];
				// modtime parts and dir are in part1[5]
				$part1 = preg_split("/[\s]+/", $output[$i], 6, PREG_SPLIT_NO_EMPTY);
				// modtime = part2[0,1,2]
				// dir = part2[3]
				$part2 = preg_split("/[\s]+/", $part1[5], -1, PREG_SPLIT_NO_EMPTY);
				for ($n = 0; $n < sizeof($fieldnames); $n++) {
					$fieldname = $fieldnames[$n];
					if ($n <= 4) {
						$jobdir[$fieldname] = $part1[$n];
					}
					elseif ($n == 5) {
						$jobdir[$fieldname] = $part2[0] . ' ' . $part2[1] . ' ' . $part2[2];
					}
					else {
						$jobdir[$fieldname] = $part2[3];
					}
				}
            	// look in dir for ClusterId_<clusterid> 
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
		}
		// if there are no jobs in final list then clear fieldnames for consistency
		if (empty($jobdirs)) $fieldnames = [];
		$ra = array('fieldnames' => $fieldnames, 'data' => $jobdirs);
		return $ra;
	}

	public static function getCurrent() {
		$hostname = gethostname();
		$collector_host = SWAMPStatus::get_condor_collector_host();
		// Log::info(".env collector_host: <$collector_host>");
		$localhost = false;
		$parts = explode('.', $collector_host);
		if (preg_match('/localhost/', $collector_host)) {
			$parts = explode('.', $hostname);
			$localhost = true;
		}
		// change csacol to csacon and domain to mirsam
		$parts[0] = str_replace('csacol', 'csacon', $parts[0]);
		$parts[1] = 'mirsam';
		$condor_manager = implode('.', $parts);

		if ($localhost === true) {
			$submit_node = $hostname;
			$exec_nodes[] = $hostname;
			$data_nodes[] = $hostname;
		}
		else {
			$submit_node = SWAMPStatus::get_condor_submit_node($condor_manager);
			$exec_nodes = SWAMPStatus::get_condor_exec_nodes($condor_manager);
			$data_nodes = SWAMPStatus::get_swamp_data_nodes($hostname);
		}

        // Log::info("collector_host: <$collector_host>");
		// Log::info("condor_manager: <$condor_manager>");
		// Log::info("submit_node: <$submit_node>");
		// Log::info("exec_nodes: <" . implode(" ", $exec_nodes) . ">"); 
		// Log::info("data_nodes: <" . implode(" ", $data_nodes) . ">"); 

		// condor status
		$cs = SWAMPStatus::get_condor_status($hostname, $submit_node, $condor_manager);
		$all_cs[$submit_node] = $cs;

		// condor queue
		$cq = SWAMPStatus::get_condor_queue($hostname, $submit_node, $condor_manager);
		$all_cq[$submit_node] = $cq;

		// assessment collector	
		$acr = SWAMPStatus::get_collector_records($collector_host, 'assessment');
		$all_acr[$collector_host] = $acr;

		// viewer collector	
		$vcr = SWAMPStatus::get_collector_records($collector_host, 'viewer');
		$all_vcr[$collector_host] = $vcr;

		// submit job dirs
		$sjd = SWAMPStatus::get_submit_job_dirs($hostname, $submit_node);
		if (! empty($sjd['fieldnames'])) {
			$all_sjd[$submit_node] = $sjd;
		}

		// swamp processes
		// first get submit_node
		$sp = SWAMPStatus::get_swamp_processes($hostname, $submit_node);
		if (! empty($sp['fieldnames'])) {
			$all_sp[$submit_node] = $sp;
		}
		// then iterate over data_nodes if they are different from the submit_node
		foreach ($data_nodes as $data_node) {
			if ($data_node != $submit_node) {
				$sp = SWAMPStatus::get_swamp_processes($hostname, $data_node);
				if (! empty($sp['fieldnames'])) {
					$all_sp[$data_node] = $sp;
				}
			}
		}
		// then iterate over exec_nodes if they are different from the submit_node
		foreach ($exec_nodes as $exec_node) {
			if ($exec_node != $submit_node) {
				$sp = SWAMPStatus::get_swamp_processes($hostname, $exec_node);
				if (! empty($sp['fieldnames'])) {
					$all_sp[$exec_node] = $sp;
				}
			}
		}

		// virsh list
		foreach ($exec_nodes as $exec_node) {
			$vm = SWAMPStatus::get_virtual_machines($hostname, $exec_node);
			if (! empty($vm['fieldnames'])) {
				$all_vm[$exec_node] = $vm;
			}
		}

		// the order in which tables appear in the output is specified by array order
		$ra = array(
				'Condor Queue' => $all_cq, 
				'Condor Status' => $all_cs, 
				'Collector Assessment Records' => $all_acr,
				'Collector Viewer Records' => $all_vcr
				);
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
		return $ra;
	}

}
