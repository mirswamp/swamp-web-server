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
|        Copyright (C) 2012-2020 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Http\Controllers\Events;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use App\Models\Events\PersonalEvent;
use App\Http\Controllers\BaseController;
use App\Http\Controllers\Events\ProjectEventsController;
use App\Utilities\Filters\EventDateFilter;

class PersonalEventsController extends BaseController
{
	// get number of all events by user id
	//
	public static function getNumByUser(Request $request, string $userUid): int {

		// get number of user events
		//
		$num = self::getNumPersonalByUser($request, $userUid);

		// add number of project events by user
		//
		$num += ProjectEventsController::getNumByUser($request, $userUid);

		// add number of user project events by user
		//
		$num += ProjectEventsController::getNumUserProjectEvents($request, $userUid);

		return $num;
	}

	// get personal events by user id
	//
	public static function getPersonalByUser(Request $request, string $userUid): Collection {

		// create query
		//
		$query = PersonalEvent::where('user_uid', '=', $userUid);

		// add filters
		//
		$query = EventDateFilter::apply($request, $query);

		// perform query
		//
		return $query->get();
	}

	// get number of personal events by user id
	//
	public static function getNumPersonalByUser(Request $request, string $userUid): int {

		// create query
		//
		$query = PersonalEvent::where('user_uid', '=', $userUid);

		// add filters
		//
		$query = EventDateFilter::apply($request, $query);

		// perform query
		//
		return $query->count();
	}
}