<?php
/******************************************************************************\
|                                                                              |
|                           ProjectEventsController.php                        |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a controller for project events.                         |
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
use App\Models\Users\User;
use App\Models\Projects\Project;
use App\Http\Controllers\BaseController;
use App\Utilities\Filters\EventDateFilter;
use App\Utilities\Filters\LimitFilter;

class ProjectEventsController extends BaseController
{
	// get by user id
	//
	public static function getByUser(Request $request, string $userUid): Collection {
		$projectEvents = collect();

		// parse parameters
		//
		$projectUid = $request->input('project_uuid');

		// get user
		//
		$user = User::getIndex($userUid);
		if ($user) {
			if ($projectUid != '') {
				$project = Project::where('project_uid', '=', $projectUid)->first();
				if ($project && $project->isReadableBy($user) && !$project->isTrialProject()) {

					// create query
					//
					$query = $project->getEventsQuery();

					// add filters
					//
					$query = EventDateFilter::apply($request, $query);
					$query = LimitFilter::apply($request, $query);

					// perform query
					//
					$query = $query->get();
				} 
			} else {
				$projectEvents = collect();

				// collect events of user's projects
				//
				$user = User::getIndex($userUid);
				if ($user) {
					$projects = $user->getProjects();
					for ($i = 0; $i < sizeOf($projects); $i++) {
						$project = $projects[$i];
						if ($project && $project->isReadableBy($user) && !$project->isTrialProject()) {

							// create query
							//
							$query = $project->getEventsQuery();

							// apply filters
							//
							$query = EventDateFilter::apply($request, $query);
							$query = LimitFilter::apply($request, $query);

							// perform query
							//
							$events = $query->get();

							// add events to project events
							//
							if ($events) {
								$projectEvents = $projectEvents->merge($events);
							}
						}
					}
				}
			}
		}

		return $projectEvents;
	}

	// get number by user id
	//
	public static function getNumByUser(Request $request, string $userUid): int {
		$num = 0;

		// parse parameters
		//
		$projectUid = $request->input('project_uuid');

		// get user
		//
		$user = User::getIndex($userUid);
		if ($user) {
			if ($projectUid != '') {
				$project = Project::where('project_uid', '=', $projectUid)->first();
				if ($project->isReadableBy($user)) {

					// create query
					//
					$query = $project->getEventsQuery();

					// add filters
					//
					$query = EventDateFilter::apply($request, $query);
					$query = LimitFilter::apply($request, $query);

					// perform query
					//
					$num = $query->count();
				}
			} else {
				$projectEvents = collect();

				// collect events of user's projects
				//
				$user = User::getIndex($userUid);
				if ($user) {
					$projects = $user->getProjects();
					for ($i = 0; $i < sizeOf($projects); $i++) {
						if ($projects[$i] != null && $projects[$i]->isReadableBy($user)) {

							// create query
							//
							$query = $projects[$i]->getEventsQuery();

							// apply filters
							//
							$query = EventDateFilter::apply($request, $query);
							$query = LimitFilter::apply($request, $query);

							// peform query
							//
							$num += $query->count();
						}
					}
				}
			}
		}

		return $num;
	}

	// get user project events by id
	//
	public static function getUserProjectEvents(Request $request, string $userUid): Collection {
		$userProjectEvents = collect();

		// parse parameters
		//
		$projectUid = $request->input('project_uuid');

		// get user
		//
		$user = User::getIndex($userUid);
		if ($user) {
			if ($projectUid != '') {
				$project = Project::where('project_uid', '=', $projectUid)->first();
				if ($project && $project->isReadableBy($user) && !$project->isTrialProject()) {

					// create query
					//
					$query = $project->getUserEventsQuery();

					// apply filters
					//
					$query = EventDateFilter::apply($request, $query);
					$query = LimitFilter::apply($request, $query);

					// perform query
					//
					$userProjectEvents = $query->get();
				}
			} else {
				$userProjectEvents = collect();

				// collect events of user's projects
				//
				$user = User::getIndex($userUid);
				if ($user) {
					$projects = $user->getProjects();
					for ($i = 0; $i < sizeOf($projects); $i++) {
						$project = $projects[$i];
						if ($projects && $project->isReadableBy($user) && !$project->isTrialProject()) {

							// create query
							//
							$query = $project->getUserEventsQuery();

							// apply filters
							//
							$query = EventDateFilter::apply($request, $query);
							$query = LimitFilter::apply($request, $query);

							// perform query
							//
							$events = $query->get();

							// add events to user project events
							//			
							if ($events) {
								$userProjectEvents = $userProjectEvents->merge($events);
							}
						}
					}
				}
			}
		}

		return $userProjectEvents;
	}

	// get number of user project events by id
	//
	public static function getNumUserProjectEvents(Request $request, string $userUid): int {
		$num = 0;

		// parse parameters
		//
		$projectUid = $request->input('project_uuid');

		// get user
		//
		$user = User::getIndex($userUid);
		if ($user) {
			if ($projectUid != '') {
				$project = Project::where('project_uid', '=', $projectUid)->first();
				if ($project && $project->isReadableBy($user) && !$project->isTrialProject()) {

					// create query
					//
					$query = $project->getUserEventsQuery();

					// apply filters
					//
					$query = EventDateFilter::apply($request, $query);
					$query = LimitFilter::apply($request, $query);

					// perform query
					//
					$num = $query->count();
				}
			} else {
				$userProjectEvents = collect();

				// collect events of user's projects
				//
				$user = User::getIndex($userUid);
				if ($user) {
					$projects = $user->getProjects();
					for ($i = 0; $i < sizeOf($projects); $i++) {
						$project = $projects[$i];
						if ($project && $project->isReadableBy($user) && !$project->isTrialProject()) {

							// create query
							//
							$query = $project->getUserEventsQuery();

							// apply filters
							//
							$query = EventDateFilter::apply($request, $query);
							$query = LimitFilter::apply($request, $query);

							// perform query
							//
							$num += $query->count();
						}
					}
				}
			}
		}

		return $num;
	}
}