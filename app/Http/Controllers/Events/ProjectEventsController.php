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
|        Copyright (C) 2012-2019 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Http\Controllers\Events;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Input;
use App\Models\Users\User;
use App\Models\Projects\Project;
use App\Http\Controllers\BaseController;
use App\Utilities\Filters\EventDateFilter;
use App\Utilities\Filters\LimitFilter;

class ProjectEventsController extends BaseController
{
	// get by user id
	//
	public static function getByUser($userUid) {
		$projectEvents = new Collection;

		// parse parameters
		//
		$projectUid = Input::get('project_uuid');

		// get user
		//
		$user = User::getIndex($userUid);
		if ($user) {
			if ($projectUid != '') {
				$project = Project::where('project_uid', '=', $projectUid)->first();
				if ($project && $project->isReadableBy($user) && !$project->isTrialProject()) {

					// get events for a specific project
					//
					$projectEventsQuery = $project->getEventsQuery();

					// add filters
					//
					$projectEventsQuery = EventDateFilter::apply($projectEventsQuery);
					$projectEventsQuery = LimitFilter::apply($projectEventsQuery);

					$projectEvents = $projectEventsQuery->get();
				} 
			} else {
				$projectEvents = new Collection;

				// collect events of user's projects
				//
				$user = User::getIndex($userUid);
				if ($user) {
					$projects = $user->getProjects();
					for ($i = 0; $i < sizeOf($projects); $i++) {
						$project = $projects[$i];
						if ($project && $project->isReadableBy($user) && !$project->isTrialProject()) {
							$projectEventsQuery = $project->getEventsQuery();

							// apply filters
							//
							$projectEventsQuery = EventDateFilter::apply($projectEventsQuery);
							$projectEventsQuery = LimitFilter::apply($projectEventsQuery);

							$events = $projectEventsQuery->get();
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
	public static function getNumByUser($userUid) {
		$num = 0;

		// parse parameters
		//
		$projectUid = Input::get('project_uuid');

		// get user
		//
		$user = User::getIndex($userUid);
		if ($user) {
			if ($projectUid != '') {
				$project = Project::where('project_uid', '=', $projectUid)->first();
				if ($project->isReadableBy($user)) {

					// get events for a specific project
					//
					$projectEventsQuery = $project->getEventsQuery();

					// add filters
					//
					$projectEventsQuery = EventDateFilter::apply($projectEventsQuery);
					$projectEventsQuery = LimitFilter::apply($projectEventsQuery);

					$num = $projectEventsQuery->count();
				}
			} else {
				$projectEvents = new Collection;

				// collect events of user's projects
				//
				$user = User::getIndex($userUid);
				if ($user) {
					$projects = $user->getProjects();
					for ($i = 0; $i < sizeOf($projects); $i++) {
						if ($projects[$i] != null && $projects[$i]->isReadableBy($user)) {
							$projectEventsQuery = $projects[$i]->getEventsQuery();

							// apply filters
							//
							$projectEventsQuery = EventDateFilter::apply($projectEventsQuery);
							$projectEventsQuery = LimitFilter::apply($projectEventsQuery);

							$num += $projectEventsQuery->count();
						}
					}
				}
			}
		}

		return $num;
	}

	// get user project events by id
	//
	public static function getUserProjectEvents($userUid) {
		$userProjectEvents = new Collection;

		// parse parameters
		//
		$projectUid = Input::get('project_uuid');

		// get user
		//
		$user = User::getIndex($userUid);
		if ($user) {
			if ($projectUid != '') {
				$project = Project::where('project_uid', '=', $projectUid)->first();
				if ($project && $project->isReadableBy($user) && !$project->isTrialProject()) {

					// get events for a specific project
					//
					$userProjectEventsQuery = $project->getUserEventsQuery();

					// apply filters
					//
					$userProjectEventsQuery = EventDateFilter::apply($userProjectEventsQuery);
					$userProjectEventsQuery = LimitFilter::apply($userProjectEventsQuery);

					$userProjectEvents = $userProjectEventsQuery->get();
				}
			} else {
				$userProjectEvents = new Collection;

				// collect events of user's projects
				//
				$user = User::getIndex($userUid);
				if ($user) {
					$projects = $user->getProjects();
					for ($i = 0; $i < sizeOf($projects); $i++) {
						$project = $projects[$i];
						if ($projects && $project->isReadableBy($user) && !$project->isTrialProject()) {
							$userProjectEventsQuery = $project->getUserEventsQuery();

							// apply filters
							//
							$userProjectEventsQuery = EventDateFilter::apply($userProjectEventsQuery);
							$userProjectEventsQuery = LimitFilter::apply($userProjectEventsQuery);

							$events = $userProjectEventsQuery->get();				
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
	public static function getNumUserProjectEvents($userUid) {
		$num = 0;

		// parse parameters
		//
		$projectUid = Input::get('project_uuid');

		// get user
		//
		$user = User::getIndex($userUid);
		if ($user) {
			if ($projectUid != '') {
				$project = Project::where('project_uid', '=', $projectUid)->first();
				if ($project && $project->isReadableBy($user) && !$project->isTrialProject()) {

					// get events for a specific project
					//
					$userProjectEventsQuery = $project->getUserEventsQuery();

					// apply filters
					//
					$userProjectEventsQuery = EventDateFilter::apply($userProjectEventsQuery);
					$userProjectEventsQuery = LimitFilter::apply($userProjectEventsQuery);

					$num = $userProjectEventsQuery->count();
				}
			} else {
				$userProjectEvents = new Collection;

				// collect events of user's projects
				//
				$user = User::getIndex($userUid);
				if ($user) {
					$projects = $user->getProjects();
					for ($i = 0; $i < sizeOf($projects); $i++) {
						$project = $projects[$i];
						if ($project && $project->isReadableBy($user) && !$project->isTrialProject()) {
							$userProjectEventsQuery = $project->getUserEventsQuery();

							// apply filters
							//
							$userProjectEventsQuery = EventDateFilter::apply($userProjectEventsQuery);
							$userProjectEventsQuery = LimitFilter::apply($userProjectEventsQuery);

							$num += $userProjectEventsQuery->count();
						}
					}
				}
			}
		}

		return $num;
	}
}