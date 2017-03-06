<?php
/******************************************************************************\
|                                                                              |
|                           PersonalEventsController.php                       |
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

namespace App\Http\Controllers\Events;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Input;
use App\Models\Events\PersonalEvent;
use App\Http\Controllers\BaseController;
use App\Http\Controllers\Events\ProjectEventsController;
use App\Utilities\Filters\EventDateFilter;

class PersonalEventsController extends BaseController {

	// get number of all events by user id
	//
	public static function getNumByUser($userUid) {

		// get number of user events
		//
		$num = self::getNumPersonalByUser($userUid);

		// add number of project events by user
		//
		$num += ProjectEventsController::getNumByUser($userUid);

		// add number of user project events by user
		//
		$num += ProjectEventsController::getNumUserProjectEvents($userUid);

		return $num;
	}

	// get personal events by user id
	//
	public static function getPersonalByUser($userUid) {
		$personalEventsQuery = PersonalEvent::where('user_uid', '=', $userUid);

		// add filters
		//
		$personalEventsQuery = EventDateFilter::apply($personalEventsQuery);

		return $personalEventsQuery->get();
	}

	// get number of personal events by user id
	//
	public static function getNumPersonalByUser($userUid) {
		$personalEventsQuery = PersonalEvent::where('user_uid', '=', $userUid);

		// add filters
		//
		$personalEventsQuery = EventDateFilter::apply($personalEventsQuery);

		return $personalEventsQuery->count();
	}
}