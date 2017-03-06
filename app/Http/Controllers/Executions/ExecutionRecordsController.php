<?php
/******************************************************************************\
|                                                                              |
|                          ExecutionRecordsController.php                      |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a controller for personal events.                        |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2017 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Http\Controllers\Executions;

use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Config;
use App\Models\Projects\Project;
use App\Models\Executions\ExecutionRecord;
use App\Models\Packages\PackageVersion;
use App\Models\Tools\ToolVersion;
use App\Models\Platforms\PlatformVersion;
use App\Models\Users\User;
use App\Models\Users\UserPermission;
use App\Http\Controllers\BaseController;
use App\Utilities\Filters\DateFilter;
use App\Utilities\Filters\TripletFilter2;
use App\Utilities\Filters\LimitFilter;
use App\Services\HTCondorCollector;


class ExecutionRecordsController extends BaseController {

	// get by index
	//
	public function getIndex($executionRecordUuid) {
		// Use HTCondor Collector
		$result = ExecutionRecord::where('execution_record_uuid', '=', $executionRecordUuid)->first();
		return HTCondorCollector::insertStatus($result, $executionRecordUuid);
	}

	// get ssh access
	//
	public function getSshAccess($executionRecordUuid){
 		$permission = UserPermission::where('user_uid','=',Session::get('user_uid'))->where('permission_code','=','ssh-access')->first();
		if (!$permission) {
			return response('You do not have permission to access SSH information.', 401);
		}

		$attempts = 30;

		// look up vm ip
		//
		do {
			if( $attempts < 30 ) sleep( 1 );
			$record = ExecutionRecord::where('execution_record_uuid','=',$executionRecordUuid)->first();
			$vm_ip = $record->vm_ip_address;
			$attempts--;
		} while( ! $vm_ip && $attempts > 0 );

		if( ! $vm_ip ) return response('Request timed out.',500);

		// floodlight rules
		//
		$address = Config::get('app.floodlight') . '/wm/core/controller/switches/json';
		$result = `curl -X GET $address`;
		$switches = json_decode( $result );
		$results = array();		
		$id = 1;
		foreach( $switches as $switch ){
			$results[] = $switch->switchDPID;
			$address = Config::get('app.floodlight') . '/wm/staticflowpusher/json';
			$data = json_encode(array(
				'ip_proto'		=> 6,
				'tcp_dst'		=> 22, # ssh port
				'switch'		=> $switch->switchDPID,
				'name'			=> $record->vm_hostname . '-' . $_SERVER['REMOTE_ADDR'] . "-$id",
				'priority'		=> '65',
				'ipv4_src'		=> $_SERVER['REMOTE_ADDR'] . '/32',
				'ipv4_dst'		=> $vm_ip . '/32',
				'eth_type'	=> '2048',
				'active'		=> 'true',
				'actions'		=> 'output=normal'
			));
			$results[] = `curl -X POST -d '$data' $address`;
			$id++;
			$data = json_encode(array(
				'ip_proto'		=> 6,
				'tcp_src'		=> 22, # ssh port
				'switch'		=> $switch->switchDPID,
				'name'			=> $record->vm_hostname . '-' . $_SERVER['REMOTE_ADDR'] . "-$id",
				'priority'		=> '65',
				'ipv4_src'		=> $vm_ip . '/32',
				'ipv4_dst'		=> $_SERVER['REMOTE_ADDR'] . '/32',
				'eth_type'		=> '2048',
				'active'		=> 'true',
				'actions'		=> 'output=normal'
			));
			$results[] = `curl -X POST -d '$data' $address`;
			$id++;
		}

		// make floodlight request
		//
		return array(
			'src_ip'		=> $_SERVER['REMOTE_ADDR'],
			'vm_hostname'	=> $record->vm_hostname,
			'vm_ip'			=> $vm_ip,
			'vm_username'	=> $record->vm_username,
			'vm_password'	=> $record->vm_password
		);
	}

	// get by project
	//
	public function getByProject($projectUuid) {
		if (!strpos($projectUuid, '+')) {

			// check for inactive or non-existant project
			//
			$project = Project::where('project_uid', '=', $projectUuid)->first();
			if (!$project || !$project->isActive()) {
				return array();
			}

			// get by a single project
			//
			$executionRecordsQuery = ExecutionRecord::where('project_uuid', '=', $projectUuid);
		
			// add filters
			//
			$executionRecordsQuery = DateFilter::apply($executionRecordsQuery);
			$executionRecordsQuery = TripletFilter2::apply($executionRecordsQuery, $projectUuid);
		} else {

			// get by multiple projects
			//
			$projectUuids = explode('+', $projectUuid);
			foreach ($projectUuids as $projectUuid) {

				// check for inactive or non-existant project
				//
				$project = Project::where('project_uid', '=', $projectUuid)->first();
				if (!$project || !$project->isActive()) {
					continue;
				}

				if (!isset($executionRecordsQuery)) {
					$executionRecordsQuery = ExecutionRecord::where('project_uuid', '=', $projectUuid);
				} else {
					$executionRecordsQuery = $executionRecordsQuery->orWhere('project_uuid', '=', $projectUuid);
				}
			
				// add filters
				//
				$executionRecordsQuery = DateFilter::apply($executionRecordsQuery);
				$executionRecordsQuery = TripletFilter2::apply($executionRecordsQuery, $projectUuid);
			}
		}

		// order results before applying filter
		//
		$executionRecordsQuery = $executionRecordsQuery->orderBy('create_date', 'DESC');

		// add limit filter
		//
		$executionRecordsQuery = LimitFilter::apply($executionRecordsQuery);

		// allow soft delete
		//
		$executionRecordsQuery = $executionRecordsQuery->whereNull('delete_date');

		// execute query
		//
		// Use HTCondor Collector
		$result = $executionRecordsQuery->get();
		return HTCondorCollector::insertStatuses($result);
	}

	// get number by project
	//
	public function getNumByProject($projectUuid) {
		if (!strpos($projectUuid, '+')) {

			// get by a single project
			//
			$executionRecordsQuery = ExecutionRecord::where('project_uuid', '=', $projectUuid);
		
			// add filters
			//
			$executionRecordsQuery = DateFilter::apply($executionRecordsQuery);
			$executionRecordsQuery = TripletFilter2::apply($executionRecordsQuery, $projectUuid);
		} else {

			// get by multiple projects
			//
			$projectUuids = explode('+', $projectUuid);
			$executionRecordsQuery = ExecutionRecord::where('project_uuid', '=', $projectUuids[0]);
			
			// add filters
			//
			$executionRecordsQuery = DateFilter::apply($executionRecordsQuery);
			$executionRecordsQuery = TripletFilter2::apply($executionRecordsQuery, $projectUuid);

			// add queries for each successive project in list
			//
			for ($i = 1; $i < sizeof($projectUuids); $i++) {
				$executionRecordsQuery = $executionRecordsQuery->orWhere('project_uuid', '=', $projectUuids[$i]);
			
				// add filters
				//
				$executionRecordsQuery = DateFilter::apply($executionRecordsQuery);
				$executionRecordsQuery = TripletFilter2::apply($executionRecordsQuery, $projectUuid);
			}
		}

		// allow soft delete
		//
		$executionRecordsQuery = $executionRecordsQuery->whereNull('delete_date');

		// execute query
		//
		return $executionRecordsQuery->count();
	}

	// get all
	//
	public function getAll() {
		$user = User::getIndex(Session::get('user_uid'));
		if ($user && $user->isAdmin()) {
			$executionRecordsQuery = ExecutionRecord::orderBy('create_date', 'DESC');

			// add filters
			//
			$executionRecordsQuery = DateFilter::apply($executionRecordsQuery);
			$executionRecordsQuery = TripletFilter2::apply($executionRecordsQuery, null);
			$executionRecordsQuery = LimitFilter::apply($executionRecordsQuery);

			// allow soft delete
			//
			$executionRecordsQuery = $executionRecordsQuery->whereNull('delete_date');
			
			// Use HTCondor Collector
			$result = $executionRecordsQuery->get();
			return HTCondorCollector::insertStatuses($result);
		}
	}

	// delete by index
	//
	public function deleteIndex($executionRecordUuid) {
		$executionRecord = ExecutionRecord::where('execution_record_uuid', '=', $executionRecordUuid)->first();
		$executionRecord->delete();
		return $executionRecord;
	}
}
