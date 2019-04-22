<?php
/******************************************************************************\
|                                                                              |
|                      RunRequestSchedulesController.php                       |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a controller for run request schedules.                  |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2019 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Http\Controllers\RunRequests;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Input;
use App\Utilities\Uuids\Guid;
use App\Models\RunRequests\RunRequestSchedule;
use App\Http\Controllers\BaseController;

class RunRequestSchedulesController extends BaseController
{
	// create
	//
	public function postCreate() {

		// parse parameters
		//
		$runRequestUuid = Input::get('run_request_uuid', null);
		$recurranceType = Input::get('recurrence_type', null);

		// create new schedule
		//
		if ($runRequestUuid) {

			// create a single model
			//
			$runRequestSchedule = new RunRequestSchedule([
				'run_request_schedule_uuid' => Guid::create(),
				'run_request_uuid' => $runRquestUuid,
				'recurrence_type' => $recurranceType
			]);

			// set optional attributes
			//
			if (array_key_exists('recurrence_day', $input)) {
				$runRequestSchedule->recurrence_day = $input['recurrence_day'];
			}
			if (array_key_exists('time_of_day', $input)) {
				$runRequestSchedule->time_of_day = $input['time_of_day'];
			}

			$runRequestSchedule->save();
			return $runRequestSchedule;
		} else {

			// create an array of models
			//
			$inputs = Input::all();
			$runRequestSchedules = new Collection;
			for ($i = 0; $i < sizeOf($inputs); $i++) {
				$input = $inputs[$i];
				$runRequestSchedule = new RunRequestSchedule([
					'run_request_schedule_uuid' => Guid::create(),
					'run_request_uuid' => $input['run_request_uuid'],
					'recurrence_type' => $input['recurrence_type']
				]);

				// set optional attributes
				//
				if (array_key_exists('recurrence_day', $input)) {
					$runRequestSchedule->recurrence_day = $input['recurrence_day'];
				}
				if (array_key_exists('time_of_day', $input)) {
					$runRequestSchedule->time_of_day = $input['time_of_day'];
				}

				$runRequestSchedules->push($runRequestSchedule);
				$runRequestSchedule->save();
			}
			return $runRequestSchedules;
		}
	}
	
	// get by index
	//
	public function getIndex($runRequestScheduleUuid) {
		$runRequest = RunRequestSchedule::where('run_request_schedule_uuid', '=', $runRequestScheduleUuid)->first();
		return $runRequest;
	}

	// get by run request
	//
	public function getByRunRequest($runRequestUuid) {
		$runRequestSchedules = RunRequestSchedule::where('run_request_uuid', '=', $runRequestUuid)->get();
		return $runRequestSchedules;
	}

	// update by index
	//
	public function updateIndex($runRequestScheduleUuid) {

		// parse parameters
		//
		$runRequestUuid = Input::get('run_request_uuid');

		// get model
		//
		$runRequestSchedule = $this->getIndex($runRequestScheduleUuid);

		// update attributes
		//
		$runRequestSchedule->run_request_uuid = $runRequestUuid;

		// set optional attributes
		//
		if (array_key_exists('recurrence_day', $input)) {
			$runRequestSchedule->recurrence_day = $input['recurrence_day'];
		}
		if (array_key_exists('time_of_day', $input)) {
			$runRequestSchedule->time_of_day = $input['time_of_day'];
		}

		// save and return changes
		//
		$changes = $runRequestSchedule->getDirty();
		$runRequestSchedule->save();
		return $changes;
	}

	// update multiple
	//
	public function updateMultiple() {

		// parse parameters
		//
		$inputs = Input::all();

		// update schedules
		//
		$collection = new Collection;
		for ($i = 0; $i < sizeOf($inputs); $i++) {

			// get input item
			//
			$input = $inputs[$i];
			if (array_key_exists('run_request_schedule_uuid', $input)) {
				
				// find existing model
				//
				$runRequestSchedule = RunRequestSchedule::where('run_request_schedule_uuid', '=', $input['run_request_schedule_uuid'])->first();
				$collection->push($runRequestSchedule);
			} else {
				
				// create new model
				//
				$runRequestSchedule = new RunRequestSchedule([
					'run_request_schedule_uuid' => Guid::create()
				]);
			}
			
			// update model
			//
			$runRequestSchedule->run_request_uuid = $input['run_request_uuid'];
			$runRequestSchedule->recurrence_type = $input['recurrence_type'];

			// set optional attributes
			//
			if (array_key_exists('recurrence_day', $input)) {
				$runRequestSchedule->recurrence_day = $input['recurrence_day'];
			}
			if (array_key_exists('time_of_day', $input)) {
				$runRequestSchedule->time_of_day = $input['time_of_day'];
			}
			
			// save model
			//
			$runRequestSchedule->save();
		}
		return $collection;
	}

	// delete by index
	//
	public function deleteIndex($runRequestScheduleUuid) {
		$runRequestSchedule = RunRequestSchedule::where('run_request_schedule_uuid', '=', $runRequestScheduleUuid)->first();
		$runRequestSchedule->delete();
		return $runRequestSchedule;
	}
}