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
|        Copyright (C) 2012-2020 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Http\Controllers\Results;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Mail;
use App\Models\Projects\Project;
use App\Models\Results\ExecutionRecord;
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

class ExecutionRecordsController extends BaseController
{
	// get by index
	//
	public function getIndex(string $executionRecordUuid): ?ExecutionRecord {

		// Use HTCondor Collector
		//
		$result = ExecutionRecord::where('execution_record_uuid', '=', $executionRecordUuid)->first();
		return HTCondorCollector::insertStatus($result, $executionRecordUuid);
	}

	// get ssh access
	//
	public function getSshAccess(string $executionRecordUuid) {
		$permission = UserPermission::where('user_uid','=',session('user_uid'))->where('permission_code','=','ssh-access')->first();
		if (!$permission) {
			return response('You do not have permission to access SSH information.', 401);
		}

		$attempts = 30;

		// look up vm ip
		//
		do {
			if ($attempts < 30) {
				sleep(1);
			}
			$record = ExecutionRecord::where('execution_record_uuid','=',$executionRecordUuid)->first();
			$vm_ip = $record->vm_ip_address;
			$attempts--;
		} while (!$vm_ip && $attempts > 0);

		if (!$vm_ip) {
			return response('Request timed out.', 500);
		}

		$address = config('app.floodlight') . '/wm/core/controller/switches/json';
		$result = `curl -X GET $address`;
		$switches = json_decode( $result );

		// floodlight controller switch result is not available
		// this could be a non floodlight controlled environment
		// return vm_* data and warn user that they must be in ip space
		// that allows access to vm via ssh for it to succeed
		//
		if (! $switches || ! is_array($switches)) {
			return [
				'src_ip'		=> $_SERVER['REMOTE_ADDR'],
				'vm_hostname'	=> $record->vm_hostname,
				'vm_ip'			=> $vm_ip,
				'vm_username'	=> $record->vm_username,
				'vm_password'	=> $record->vm_password
			];
		}
		
		// setup floodlight rules for vm_ip
		//
		$results = [];		
		$id = 1;
		foreach( $switches as $switch ){
			$results[] = $switch->switchDPID;
			$address = config('app.floodlight') . '/wm/staticflowpusher/json';
			$data = json_encode([
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
			]);
			$results[] = `curl -X POST -d '$data' $address`;
			$id++;
			$data = json_encode([
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
			]);
			$results[] = `curl -X POST -d '$data' $address`;
			$id++;
		}

		// make floodlight request
		//
		return [
			'src_ip'		=> $_SERVER['REMOTE_ADDR'],
			'vm_hostname'	=> $record->vm_hostname,
			'vm_ip'			=> $vm_ip,
			'vm_username'	=> $record->vm_username,
			'vm_password'	=> $record->vm_password
		];
	}

	// get by project
	//
	public function getByProject(Request $request, string $projectUuid): Collection {
		if (!strpos($projectUuid, '+')) {

			// check for inactive or non-existant project
			//
			$project = Project::where('project_uid', '=', $projectUuid)->first();
			if (!$project || !$project->isActive()) {
				return [];
			}

			// get by a single project
			//
			$query = ExecutionRecord::where('project_uuid', '=', $projectUuid);
		
			// add triplet filter
			//
			$query = TripletFilter2::apply($request, $query, $projectUuid);
		} else {

			// get by multiple projects
			//
			$projectUuids = explode('+', $projectUuid);
			$query = ExecutionRecord::where(function($query) use ($request, $projectUuids) {
				foreach ($projectUuids as $projectUuid) {

					// check for inactive or non-existant project
					//
					$project = Project::where('project_uid', '=', $projectUuid)->first();
					if (!$project || !$project->isActive()) {
						continue;
					}

					if (!isset($query)) {
						$query = ExecutionRecord::where('project_uuid', '=', $projectUuid);
					} else {
						$query = $query->orWhere('project_uuid', '=', $projectUuid);
					}

					// add triplet filter
					//
					$query = TripletFilter2::apply($request, $query, $projectUuid);
				}
			});
		}

		// add date filter
		//
		$query = DateFilter::apply($request, $query);

		// order results before applying filter
		//
		$query = $query->orderBy('create_date', 'DESC');

		// add limit filter
		//
		$query = LimitFilter::apply($request, $query);

		// perform query
		//
		$result = $query->get();

		// append statuses to results
		//
		return HTCondorCollector::insertStatuses($result);
	}

	// get number by project
	//
	public function getNumByProject(Request $request, string $projectUuid): int {
		if (!strpos($projectUuid, '+')) {

			// get by a single project
			//
			$query = ExecutionRecord::where('project_uuid', '=', $projectUuid);
		
			// add triplet filter
			//
			$query = TripletFilter2::apply($request, $query, $projectUuid);
		} else {

			// get by multiple projects
			//
			$projectUuids = explode('+', $projectUuid);
			$query = ExecutionRecord::where(function($query) use ($projectUuids, $request) {
				foreach ($projectUuids as $projectUuid) {

					// check for inactive or non-existant project
					//
					$project = Project::where('project_uid', '=', $projectUuid)->first();
					if (!$project || !$project->isActive()) {
						continue;
					}

					if (!isset($query)) {
						$query = ExecutionRecord::where('project_uuid', '=', $projectUuid);
					} else {
						$query = $query->orWhere('project_uuid', '=', $projectUuid);
					}

					// add triplet filter
					//
					$query = TripletFilter2::apply($request, $query, $projectUuid);
				}
			});
		}

		// add date filter
		//
		$query = DateFilter::apply($request, $query);

		// perform query
		//
		return $query->count();
	}

	// get all
	//
	public function getAll(Request $request): Collection {
		$user = User::current();
		if ($user && $user->isAdmin()) {

			// create query
			//
			$query = ExecutionRecord::orderBy('create_date', 'DESC');

			// add filters
			//
			$query = DateFilter::apply($request, $query);
			$query = TripletFilter2::apply($request, $query, null);
			$query = LimitFilter::apply($request, $query);
			
			// perform query
			//
			$result = $query->get();

			// append statuses
			//
			return HTCondorCollector::insertStatuses($result);
		} else {
			return collect();
		}
	}

	// kill by index
	//
	public function killIndex(Request $request, string $executionRecordUuid) {
		
		// parse parameters
		//
		$type = $request->input('type'); 
		$hard = $request->input('hard') == true; 

		// check for current session
		//
		$userUid = session('user_uid');
		if (!$userUid) {
			return response("No current session.", 400);
		}

		// check for current user
		//
		$user = User::getIndex($userUid);
		if (!$user) {
			return response("No current user.", 400);
		}

		// check permissions
		//
		switch ($type) {

			case 'execrunuid':

				// assessment runs
				//
				$executionRecord = ExecutionRecord::where('execution_record_uuid', '=', $executionRecordUuid)->first();
				$project = $executionRecord->getProject();
				$hasPermission = ($user && ($project->isOwnedBy($user) || $project->hasMember($user)) || $user->isAdmin()); 
				break;
			case 'project_uuid':

				// viewer runs
				//
				$executionRecord = new ExecutionRecord([
					'execution_record_uuid' => $executionRecordUuid
				]);
				$project = Project::where('project_uid', '=', $executionRecordUuid)->first();
				$hasPermission = ($user && ($project->isOwnedBy($user) || $project->hasMember($user)) || $user->isAdmin());
				break;
			default:

				// metric runs
				// 
				$executionRecord = new ExecutionRecord([
					'execution_record_uuid' => $executionRecordUuid
				]);
				$hasPermission = true;
				break;
		}

		if ($hasPermission) {

			// kill execution record
			//
			$returnString = $executionRecord->kill($hard);
			$executionRecord->status = $returnString;
			return $executionRecord;
		} else {
			return response('You do not have permission to kill this execution record.', 400);
		}
	}

	// notify by index
	//
	public function notifyIndex(string $executionRecordUuid) {
		$executionRecord = ExecutionRecord::where('execution_record_uuid', '=', $executionRecordUuid)->first();

		// look up execution record user
		//
		$user = $executionRecord->getUser();
		if (!$user) {
			return response('Execution record user not found', 404);
		}

		Mail::send('emails.assessment-completed', [
			'user' => $user,
			'package' => $executionRecord->package,
			'tool' => $executionRecord->tool,
			'platform' => $executionRecord->platform,
			'completionDate' => $executionRecord->completion_date,
			'status' => $executionRecord->status
		], function($message) use ($user) {
			$message->to($user->email, $user->getFullName());
			$message->subject('SWAMP Assessment Completed');
		});
	}

	// delete by index
	//
	public function deleteIndex(string $executionRecordUuid) {
		$executionRecord = ExecutionRecord::where('execution_record_uuid', '=', $executionRecordUuid)->first();
		$executionRecord->delete();
		return $executionRecord;
	}
}
