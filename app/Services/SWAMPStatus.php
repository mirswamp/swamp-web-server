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
|        Copyright (C) 2012-2017 Software Assurance Marketplace (SWAMP)        |
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
					$crecord[$fieldname] = str_replace(array('"', ','), '',  trim($temp[$n]));
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
		$summary = [];
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
		exec($command, $output, $returnVar);
		// echo "Output: "; print_r($output); echo "\n";
		// echo "returnVar: "; print_r($returnVar); echo "\n";
		if (($returnVar == 0) && (! empty($output))) {
			// $fieldnames = ['EXECRUNUID', 'JOBID', 'CMD', 'SUBMITTED', 'RUN TIME', 'ST', 'PRI', 'IMAGE', 'DISK'];
			$fieldnames = ['EXECRUNUID', 'CMD', 'SUBMITTED', 'RUN TIME', 'ST', 'PRI', 'IMAGE', 'DISK'];
			if ($localhost === false) {
				$fieldnames[] = 'HOST';
			}
			$fieldnames[] = 'VM';
			$summary = array(
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
					// $job['JOBID'] = $clusterid . '.' . $procid;
					// echo "JOBID: ", $job['JOBID'], "\n";
					if (! empty($owner) && ! empty($uid_domain)) {
						$job['VM'] = $owner . '_' . $uid_domain . '_' . $clusterid . '_' . $procid;
						// echo "VM: ", $job['VM'], "\n";
					}
					// order job by fieldnames
					$orderedjob = [];
					for ($n = 0; $n < sizeof($fieldnames); $n++) {
						$orderedjob[] = isset($job[$fieldnames[$n]]) ? $job[$fieldnames[$n]] : '';
					}
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
						$job['EXECRUNUID'] = isset($parts[1]) ? $parts[1] : $value;
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
						$summary[strtolower($status)] += 1;
						$summary['jobs'] += 1;
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
			$summary = [];
		}
		else {
			usort($jobs, function($a, $b) {return strcmp($a[2], $b[2]);});
		}
		$ra = array('fieldnames' => $fieldnames, 'data' => $jobs, 'summary' => $summary);
		return $ra;
	}

	private static function _sort_on_clusterid($commandfield) {
		return function($a, $b) use ($commandfield) {
			$acommand = $a[$commandfield];
			$bcommand = $b[$commandfield];
			$aparts = preg_split("/[\s]+/", $acommand, -1, PREG_SPLIT_NO_EMPTY);
			$bparts = preg_split("/[\s]+/", $bcommand, -1, PREG_SPLIT_NO_EMPTY);
			if (sizeof($aparts) >= 3) {
				// either clusterid procid or clusterid procid debug
				// sort on clusterid
				list($acid, $apid, $aname) = array_slice($aparts, -3);
				if (is_numeric($aname)) {
					$acid = $apid;
				}   
			}   
			else {
				// sort on last element
				$temp = array_slice($aparts, -1);
				$acid = array_pop($temp);
			}   
			if (sizeof($bparts) >= 3) {
				list($bcid, $bpid, $bname) = array_slice($bparts, -3);
				if (is_numeric($bname)) {
					$bcid = $bpid;
				}   
			}   
			else {
				$temp = array_slice($bparts, -1);
				$bcid = array_pop($temp);
			}                                                                                                             
			if (is_numeric($acid) && is_numeric($bcid)) {
				if ($acid < $bcid) {
					return -1; 
				}   
				elseif ($acid > $bcid) {
					return 1;
				}   
				return 0;
			}   
			else {
				return strcmp($acid, $bcid); 
			}   
		};
	}

	public static function get_swamp_processes($hostname, $host) {
		$fieldnames = [];
		$vmuA = [];
		$vmuV = [];
		$vmu = [];
		$other = [];
		$command = "ps aux | egrep 'PID|vmu_' | grep -v grep";
		if ($host != $hostname) {
			// multi-host currently not implemented
			$processes = array_merge($vmuA, $vmuV, $vmu, $other);
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
					for ($n = 0; $n < sizeof($fieldnames); $n++) {
						$fieldname = $fieldnames[$n];
						$process[$fieldname] = $temp[$n];
						$commandfield = $fieldname;
					}
					if (preg_match("/vmu_.*Assessment/", $process[$commandfield])) {
						$vmuA[] = $process;
					}
					elseif (preg_match("/vmu_.*Viewer/", $process[$commandfield])) {
						$vmuV[] = $process;
					}
					elseif (preg_match("/vmu_.*.pl/", $process[$commandfield])) {
						$vmu[] = $process;
					}
					else {
						$other[] = $process;
					}
				}
			}
			// sort vmuA and vmuV on clusterid at end of COMMAND field
			usort($vmuA, SWAMPStatus::_sort_on_clusterid($commandfield));
			usort($vmuV, SWAMPStatus::_sort_on_clusterid($commandfield));
			$processes = array_merge($vmuA, $vmuV, $vmu, $other);
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
					$temp = preg_split("/[\s]+/", $output[$i], -1, PREG_SPLIT_NO_EMPTY);
					for ($n = 0; $n < sizeof($fieldnames); $n++) {
						$fieldname = $fieldnames[$n];
						$machine[$fieldname] = $temp[$n];
					}
					$machines[] = $machine;
				}
			}
			// this is a bit of a hack to assume there is a field called Name to sort on
			usort($machines, function($a, $b) { return strcmp($a['Name'], $b['Name']); });
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
            	if (($dh = opendir('/opt/swamp/run/' . $jobdir['dir'])) !== false) {
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

	private static function html_table($ra, $brcount) {
		if (empty($ra)) {
			return '';
		}
		$fieldnames = $ra['fieldnames'];
		$thead = '<tr><th>' . implode('</th><th>', array_values($fieldnames)) . '</th></tr>';

		$data = $ra['data'];
		$tbody = array_reduce($data, function($a, $b){return $a.='<tr><td>'.implode('</td><td>',$b).'</td></tr>';});
		$rstring = "<table>\n$thead\n$tbody\n</table>";
		for ($i = 0; $i < $brcount; $i++) {
			$rstring .= '<br/>';
		}
		return $rstring;
	}

	private static function html_results($ra) {
		$rstring = "";
		foreach ($ra as $title => $table) {
			if (($title == 'SWAMP Processes') || ($title == 'Virtual Machines')) {
				foreach ($table as $machine => $value) {
					$rstring .= $title . ': ' . $machine . '<br/>';
					$rstring .= SWAMPStatus::html_table($table[$machine], 2);
				}
			}
			else {
				reset($table);
				$machine = key($table);
				$rstring .= $title . ': ' . $machine . '<br/>';
				$brcount = 2;
				if ($title == 'Condor Queue') {
					$brcount = 1;
					$fieldnames = &$table[$machine]['fieldnames'];
					$data = &$table[$machine]['data'];
					// Log::info("*****");
					// Log::info("queue fieldnames: " . serialize($fieldnames));
					// Log::info("queue data: " . serialize($data));
					// Log::info("*****");
					for ($n = 0; $n < sizeof($data); $n++) {
						// EXECRUNUID is the 0th element
						$execrunuid = $data[$n][0];
						// execrunuid is an mrun - do not annotate
						if (preg_match("/^M-/", $execrunuid)) {
							continue;
						}
						// execrunuid is a vrun - annotate execrunuid with link to Project page
						if (preg_match("/^vrun_/", $execrunuid)) {
							$execrunuid = preg_replace('/^vrun_/', '', $execrunuid);
							$execrunuid = preg_replace('/_.*$/', '', $execrunuid);
							$link = '/#projects/' . $execrunuid;
						}
						// execrunuid is an arun - annotate execrunuid with link to Run Status page
						else {
							$link = '/#runs/' . $execrunuid . '/status';
						}
						$data[$n][0] = "<a href=\"$link\" target=\"_blank\">$execrunuid</a>";
					}
				}
				elseif ($title == 'Collector Assessment Records') {
					$fieldnames = &$table[$machine]['fieldnames'];
					$data = &$table[$machine]['data'];
					// Log::info("=====");
					// Log::info("collector fieldnames: " . serialize($fieldnames));
					// Log::info("collector data: " . serialize($data));
					// Log::info("=====");
					for ($n = 0; $n < sizeof($data); $n++) {
						$execrunuid = $data[$n]['execrunuid'];
						// execrunuid is an mrun - do not annotate
						if (preg_match("/^M-/", $execrunuid)) {
							continue;
						}
						// annotate execrunuid with link to Run Status page
						$link = '/#runs/' . $execrunuid . '/status';
						$data[$n]['execrunuid'] = "<a href=\"$link\" target=\"_blank\">$execrunuid</a>";
						// annotate projectid with link to Project page
						$projectid = $data[$n]['projectid'];
						$link = '/#projects/' . $projectid;
						$data[$n]['projectid'] = "<a href=\"$link\" target=\"_blank\">$projectid</a>";
					}
				}
				// annotate projectid with link to Project page
				// /#projects/<projectid>
				elseif ($title == 'Collector Viewer Records') {
					$data = &$table[$machine]['data'];
					for ($n = 0; $n < sizeof($data); $n++) {
						$projectid = $data[$n]['projectid'];
						$link = '/#projects/' . $projectid;
						$data[$n]['projectid'] = "<a href=\"$link\" target=\"_blank\">$projectid</a>";
					}
				}
				$rstring .= SWAMPStatus::html_table($table[$machine], $brcount);
				if ($title == 'Condor Queue') {
					$summary = $table[$machine]['summary'];
					if (empty($summary)) {
						$rstring .= '<br/>';
						continue;
					}
					$rstring .= '<pre>';
					foreach ($summary as $key => $value) {
						$rstring .= "$key $value    ";
					}
					$rstring .= '</pre>';
					$rstring .= '<br/><br/>';
				}
			}
		}
		return $rstring;
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

	public static function getCurrentHtml() {

		// Return an html decorated string
		//
		return self::html_results(self::getCurrent());
	}
}
