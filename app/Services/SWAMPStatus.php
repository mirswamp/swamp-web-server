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
|        Copyright (C) 2012-2016 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Services;
use Illuminate\Support\Facades\Config;

class SWAMPStatus {
	private static function get_condor_submit_node() {
		$command = "condor_status -schedd -af Name";
		exec($command, $output, $returnVar);
		$submit_node = "";
		if (($returnVar == 0) && (! empty($output))) {
			$submit_node = str_replace('"', '', $output[0]);
		}
		return $submit_node;
	}

	private static function get_condor_exec_nodes() {
		$command = "condor_status -af Machine -constraint 'SlotType == \"Partitionable\"'";
		exec($command, $output, $returnVar);
		$exec_nodes = [];
		if (($returnVar == 0) && (! empty($output))) {
			for ($i = 0; $i < sizeof($output); $i++) {
				$exec_nodes[$i] = str_replace('"', '', $output[$i]);
			}
		}
		return $exec_nodes;
	}

	private static function get_swamp_data_nodes() {
		$data_nodes = [];
		$hostname = gethostname();
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

	private static function get_condor_collector_host() {
		$collector_host = Config::get('app.htcondorcollectorhost');
		return $collector_host;
	}


	public static function get_collector_records($collector_host, $title) {
		$global_fields['assessment'] = [
			'Name', 
			'SWAMP_vmu_assessment_vmhostname', 
			'SWAMP_vmu_assessment_status'
		];
		$global_constraint['assessment'] = "-constraint \"isString(SWAMP_vmu_assessment_status)\"";
		$global_fields['viewer'] = [
			'Name',
			'SWAMP_vmu_viewer_vmhostname',
			'SWAMP_vmu_viewer_name',
			'SWAMP_vmu_viewer_state',
			'SWAMP_vmu_viewer_status',
			'SWAMP_vmu_viewer_vmip',
			'SWAMP_vmu_viewer_project',
			'SWAMP_vmu_viewer_instance_uuid',
			'SWAMP_vmu_viewer_apikey',
			'SWAMP_vmu_viewer_url_uuid'
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
			$fieldnames[0] = 'execrunuid';
			for ($i = 1; $i < sizeof($fields); $i++) {
				$fieldnames[$i] = str_replace($prefix, '', $fields[$i]);
			}
			for ($i = 0; $i < sizeof($output); $i++) {
				$crecord = [];
				$temp = preg_split("/,/", $output[$i], sizeof($fieldnames), PREG_SPLIT_NO_EMPTY);
				for ($n = 0; $n < sizeof($fieldnames); $n++) {
					$fieldname = $fieldnames[$n];
					// $crecord[$fieldname] = str_replace(array('"', ','), '',  $temp[$n]);
					$crecord[$fieldname] = str_replace(array('"', ','), '',  $temp[$n]);
				}
				$crecords[] = $crecord;
			}
		}
		$ra =  array('fieldnames' => $fieldnames, 'data' => $crecords);
		return $ra;
	}

	public static function get_condor_queue($hostname, $submit_node) {
		$fieldnames = [];
		$jobs = [];
		$summary = [];
		$command = "condor_q";
		if ($submit_node != $hostname) {
			// multi-host currently not implemented
			$ra = array('fieldnames' => $fieldnames, 'data' => $jobs, 'summary' => $summary);
			return $ra;
			// modify command to use ssh or other remote access method
		}
		exec($command, $output, $returnVar);
		if (($returnVar == 0) && (! empty($output))) {
			$start = 0;
			for ($i = 0; $i < sizeof($output); $i++) {
				// skip empty lines
				if (empty($output[$i])) {
					continue;
				}
				// collect fieldnames
				if (preg_match('/ID/', $output[$i])) {
					$start = 1;
					$fieldnames = preg_split("/[\s]+/", $output[$i], -1, PREG_SPLIT_NO_EMPTY);
				}
				// collect job summary
				elseif (preg_match('/jobs;/', $output[$i])) {
					$start = 0;
					$temp = preg_split("/[\s]+/", $output[$i], -1, PREG_SPLIT_NO_EMPTY);
					for ($n = 0; $n < sizeof($temp); $n += 2) {
						$name = str_replace('"', '', $temp[$n+1]);
						$name = str_replace(';', '', $name);
						$name = str_replace(',', '', $name);
						$summary[$name] = $temp[$n];
					}
				}
				// collect job records
				elseif ($start == 1) {
					$job = [];
					$temp = preg_split("/[\s]+/", $output[$i], -1, PREG_SPLIT_NO_EMPTY);
					$j = 0;
					for ($n = 0; $n < sizeof($fieldnames); $n++) {
						$fieldname = $fieldnames[$n];
						if ($fieldname == "SUBMITTED") {
							$job[$fieldname] = $temp[$j] . " " . $temp[$j+1];
							$j += 1;
						}
						else {
							$job[$fieldname] = $temp[$j];
						}
						$j += 1;
					}
					$jobs[] = $job; 
				}
			}
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
				// either clusterid procid or clusterid procid [a|v|m]swamp-clusterid-procid
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
		$java = [];
		$other = [];
		$command = "ps aux | egrep 'PID|vmu_|/bin/java' | grep -v grep";
		if ($host != $hostname) {
			// multi-host currently not implemented
			$processes = array_merge($vmuA, $vmuV, $vmu, $java, $other);
			$ra = array('fieldnames' => $fieldnames, 'data' => $processes);
			return $ra;
			// modify command to use ssh or other remote access method
		}
		exec($command, $output, $returnVar);
		if (($returnVar == 0) && (! empty($output))) {
			$commandfield = "";
			for ($i = 0; $i < sizeof($output); $i++) {
				// skip empty lines
				if (empty($output[$i])) {
					continue;
				}
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
					elseif (preg_match("/java/", $process[$commandfield])) {
						$java[] = $process;
					}
					else {
						$other[] = $process;
					}
				}
			}
			// sort vmuA and vmuV on clusterid at end of COMMAND field
			usort($vmuA, SWAMPStatus::_sort_on_clusterid($commandfield));
			usort($vmuV, SWAMPStatus::_sort_on_clusterid($commandfield));
			$processes = array_merge($vmuA, $vmuV, $vmu, $java, $other);
		}
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
				if (empty($output[$i])) {
					continue;
				}
				// skip --- lines
				if (preg_match('/--/', $output[$i])) {
					continue;
				}
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
		$ra =  array('fieldnames' => $fieldnames, 'data' => $machines);
		return $ra;
	}

	public static function get_submit_job_dirs($hostname, $submit_node) {
		// this is a hack because unix/linux ls command does not display field names
		// ls -lrt is presumably guaranteed to produce exactly the following columns
		// log field is added after the ls command by finding the *swamp-<clusterid>-<procid>.log file
		$fieldnames = ['permissions', 'links', 'owner', 'group', 'size', 'modtime', 'dir', 'log'];
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
			for ($i = 0; $i < sizeof($output); $i++) {
				if (empty($output[$i])) {
					continue;
				}
				if (preg_match('/total/', $output[$i])) {
					continue;
				}
				if (preg_match('/swamp_monitor/', $output[$i])) {
					continue;
				}
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
            	// look in dir for *swamp-<clusterid>-<procid>.log
            	$jobdir['log'] = 'n/a'; 
            	if (($dh = opendir('/opt/swamp/run/' . $jobdir['dir'])) !== false) {
                	while (false !== ($file = readdir($dh))) {
                    	if (preg_match('/^[a|m|v]swamp\-\d+\-\d+\.log$/', $file)) {
                        	$log = str_replace('.log', '', $file);
                        	if (! $log) {
                            	$log = 'n/m'; 
                        	}
                        	$jobdir['log'] = $log;
							break;
                    	}
                	}
                	closedir($dh);  
            	}
            	else {
                	$jobdir['log'] = 'opendir failed';
            	}
				$jobdirs[] = $jobdir;
			}
		}
		$ra = array('fieldnames' => $fieldnames, 'data' => $jobdirs);
		return $ra;
	}

	private static function show_result($title, $ra, $brcount) {
		if (empty($ra)) {
			return "";
		}
		$fieldnames = $ra['fieldnames'];
		$thead = "<tr><th>" . implode("</th><th>", array_values($fieldnames)) . "</th></tr>";

		$data = $ra['data'];
		$tbody = array_reduce($data, function($a, $b){return $a.="<tr><td>".implode("</td><td>",$b)."</td></tr>";});
		$rstring = "<table>\n$thead\n$tbody\n</table>";
		for ($i = 0; $i < $brcount; $i++) {
			$rstring .= "<br/>";
		}
		return $rstring;
	}

	private static function show_results($ra) {
		reset($ra['condorqueue']);
		$machine = key($ra['condorqueue']);
		$rstring = "Condor queue: $machine:<br/>";
		$rstring .= SWAMPStatus::show_result("condor queue", $ra['condorqueue'][$machine], 1);
		// condor queue summary
		$summary = $ra['condorqueue'][$machine]['summary'];
		foreach ($summary as $key => $value) {
			$rstring .= "$key $value ";
		}
		$rstring .= "<br/><br/><br/>";

		foreach ($ra['swampprocesses'] as $machine => $value) {
			$rstring .= "Swamp processes: $machine:<br/>";
			$rstring .= SWAMPStatus::show_result("swamp processes", $ra['swampprocesses'][$machine], 2);
		}

		foreach ($ra['virtualmachines'] as $machine => $value) {
			$rstring .= "Virtual machines: $machine:<br/>";
			$rstring .= SWAMPStatus::show_result("virsh list", $ra['virtualmachines'][$machine], 2);
		}

		reset($ra['submitjobdirs']);
		$machine = key($ra['submitjobdirs']);
		$rstring .= "Submit job directories: $machine:<br/>";
		$rstring .= SWAMPStatus::show_result("submit job directories", $ra['submitjobdirs'][$machine], 2);

		reset($ra['assessments']);
		$machine = key($ra['assessments']);
		$rstring .= "Collector assessment records: $machine:<br/>";
		$rstring .= SWAMPStatus::show_result("assessment collector", $ra['assessments'][$machine], 2);

		reset($ra['viewers']);
		$machine = key($ra['viewers']);
		$rstring .= "Collector viewer records: $machine:<br/>";
		$rstring .= SWAMPStatus::show_result("viewer collector", $ra['viewers'][$machine], 2);
		return $rstring;
	}

	public static function getCurrent() {
		$hostname = gethostname();
		$collector_host = SWAMPStatus::get_condor_collector_host();
		$submit_node = SWAMPStatus::get_condor_submit_node();
		$exec_nodes = SWAMPStatus::get_condor_exec_nodes();
		$data_nodes = SWAMPStatus::get_swamp_data_nodes();

		// condor queue
		$cq = SWAMPStatus::get_condor_queue($hostname, $submit_node);
		$all_cq[$submit_node] = $cq;

		// swamp processes
		// first get submit_node
		$sp = SWAMPStatus::get_swamp_processes($hostname, $submit_node);
		$all_sp = array($submit_node => $sp);
		// then iterate over exec_nodes if they are different from the submit_node
		foreach ($exec_nodes as $exec_node) {
			if ($exec_node != $submit_node) {
				$sp = SWAMPStatus::get_swamp_processes($hostname, $exec_node);
				$all_sp[$exec_node] = $sp;
			}
		}
		// then iterate over data_nodes if they are different from the submit_node
		foreach ($data_nodes as $data_node) {
			if ($data_node != $submit_node) {
				$sp = SWAMPStatus::get_swamp_processes($hostname, $data_node);
				$all_sp[$data_node] = $sp;
			}
		}

		// virsh list
		$all_vm = [];
		foreach ($exec_nodes as $exec_node) {
			$vm = SWAMPStatus::get_virtual_machines($hostname, $exec_node);
			$all_vm[$exec_node] = $vm;
		}

		// submit job dirs
		$sjd = SWAMPStatus::get_submit_job_dirs($hostname, $submit_node);
		$all_sjd[$submit_node] = $sjd;

		// assessment collector	
		$acr = SWAMPStatus::get_collector_records($collector_host, 'assessment');
		$all_acr[$collector_host] = $acr;

		// viewer collector	
		$vcr = SWAMPStatus::get_collector_records($collector_host, 'viewer');
		$all_vcr[$collector_host] = $vcr;

		$ra = array(
				'condorqueue' => $all_cq, 
				'swampprocesses' => $all_sp, 
				'virtualmachines' => $all_vm,
				'submitjobdirs' => $all_sjd,
				'assessments' => $all_acr,
				'viewers' => $all_vcr
				);
		// return $ra;
		// To see a string version of the structures
		return SWAMPStatus::show_results($ra);
	}
}
