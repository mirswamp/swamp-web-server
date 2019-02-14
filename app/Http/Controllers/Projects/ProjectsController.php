<?php
/******************************************************************************\
|                                                                              |
|                            ProjectsController.php                            |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a controller for projects.                               |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2019 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Http\Controllers\Projects;

use DateTime;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Session;
use App\Utilities\Uuids\Guid;
use App\Models\Projects\Project;
use App\Models\Projects\ProjectMembership;
use App\Models\Projects\ProjectInvitation;
use App\Models\Packages\Package;
use App\Models\Users\User;
use App\Http\Controllers\BaseController;
use App\Utilities\Filters\DateFilter;
use App\Utilities\Filters\LimitFilter;

class ProjectsController extends BaseController {

	// create
	//
	public function postCreate() {

		// parse parameters
		//
		$fullName = Input::get('full_name');
		$description = Input::get('description');
		$affiliation = Input::get('affiliation');
		$trialProjectFlag = filter_var(Input::get('trial_project_flag'), FILTER_VALIDATE_BOOLEAN);
		$excludePublicToolsFlag = filter_var(Input::get('exclude_public_tools_flag'), FILTER_VALIDATE_BOOLEAN);
		$denialDate = Input::get('denial_date');
		$deactivationDate = Input::get('deactivation_date');

		// create new project
		//
		$project = new Project([
			'project_uid' => Guid::create(),
			'project_owner_uid' => session('user_uid'),
			'full_name' => $fullName,
			'description' => $description,
			'affiliation' => $affiliation,
			'trial_project_flag' => $trialProjectFlag,
			'exclude_public_tools_flag' => $excludePublicToolsFlag,
			'denial_date' => $denialDate,
			'deactivation_date' => $deactivationDate
		]);
		$project->save();

		// automatically create new project membership for owner
		//
		$projectMembership = new ProjectMembership([
			'membership_uid' => Guid::create(),
			'project_uid' => $project->project_uid,
			'user_uid' => $project->project_owner_uid,
			'admin_flag' => true
		]);
		$projectMembership->save();

		// log the project event
		//
		Log::info("Project created.", [
			'project_uid' => $project->project_uid,
			'project_owner_uid' => $project->project_owner_uid,
		]);

		return $project;
	}

	// get by index
	//
	public function getIndex($projectUid) {
		return Project::where('project_uid', '=', $projectUid)->first();
	}

	// get all
	//
	public function getAll($userUid) {
		$user = User::getIndex($userUid);
		if ($user) {
			if ($user->isAdmin()) {
				$projectsQuery = Project::orderBy('create_date', 'DESC');

				// add filters
				//
				$projectsQuery = DateFilter::apply($projectsQuery);
				$projectsQuery = LimitFilter::apply($projectsQuery);

				return $projectsQuery->get();
			} else {
				return response('This user is not an administrator.', 400);
			}
		} else {
			return response('Administrator authorization is required.', 400);
		}
	}

	public function getByPackage($packageUuid) {
		$package = Package::where('package_uuid', '=', $packageUuid)->first();
		if ($package) {
			return $package->getProjects();
		} else {
			return response('Package not found.', 404);
		}
	}

	public function getUserTrialProject($userUid) {
		return Project::where('project_owner_uid', '=', $userUid)->where('trial_project_flag', '=', 1)->first();
	}

	// update by index
	//
	public function updateIndex($projectUid) {

		// get parameters
		//
		$fullName = Input::get('full_name');
		$description = Input::get('description');
		$affiliation = Input::get('affiliation');
		$trialProjectFlag = filter_var(Input::get('trial_project_flag'), FILTER_VALIDATE_BOOLEAN);
		$excludePublicToolsFlag = filter_var(Input::get('exclude_public_tools_flag'), FILTER_VALIDATE_BOOLEAN);
		$denialDate = Input::has('denial_date')? DateTime::createFromFormat('d-m-Y H:i:s', Input::get('denial_date')) : null;
		$deactivationDate = Input::has('deactivation_date')? DateTime::createFromFormat('d-m-Y H:i:s', Input::get('deactivation_date')) : null;

		// get model
		//
		$project = Project::where('project_uid', '=', $projectUid)->first();
		$project->full_name = $fullName;

		// send email notifications of changes in project status
		//
		if (config('mail.enabled')) {

			// send an email to the project owner if it's revoked
			//
			if (!$project->denial_date && $denialDate) {
				$this->user = User::getIndex($project->project_owner_uid);
				if ($this->user && $this->user->email && filter_var($this->user->email, FILTER_VALIDATE_EMAIL)) {
					Mail::send('emails.project-denied', [
						'project' => [
							'owner'	=> $this->user->getFullName(),
							'full_name'	=> $project->full_name
						]
					], function($message) {
						$message->to($this->user->email, $this->user->getFullName());
						$message->subject('SWAMP Project Denied');
					});
				}
			}

			// send an email to the project owner if it's reactivated
			//
			if ($project->deactivation_date != null && $deactivationDate == '') {
				if ($project->project_owner_uid) {
					$this->user = User::getIndex($project->project_owner_uid);
					if ($this->user && $this->user->email && filter_var($this->user->email, FILTER_VALIDATE_EMAIL)) {
						Mail::send('emails.user-project-reactivated', [
							'user' => $this->user,
							'project' => [
								'owner' => $this->user->getFullName(),
								'full_name' => $project->full_name
							]
						], function($message){
							$message->to($this->user->email, $this->user->getFullName());
							$message->subject('SWAMP User Project Reactivated');
						});
					}
				}
			}
		}

		// update attributes
		//
		$changes = $project->change([
			'full_name' => $fullName,
			'description' => $description,
			'affiliation' => $affiliation,
			'trial_project_flag' => $trialProjectFlag,
			'exclude_public_tools_flag' => $excludePublicToolsFlag,
			'denial_date' => $denialDate? $denialDate->format('Y-m-d H:i:s') : null,
			'deactivation_date' => $deactivationDate? $deactivationDate->format('Y-m-d H:i:s') : null
		]);

		// log the project event
		//
		Log::info("Project updated.", [
			'project_uid' => $projectUid,
		]);

		return $changes;
	}

	// update multiple
	//
	public function updateAll()  {
		$input = Input::all();
		$collection = new Collection;
		for ($i = 0; $i < sizeOf($input); $i++) {

			// get project
			//
			$item = $input[$i];
			$projectUid = $item['project_uid'];
			$project = Project::where('project_uid', '=', $projectUid)->first();
			$collection->push($project);
			
			// update project fields
			//
			$project->project_owner_uid = $item['project_owner_uid'];
			$project->full_name = $item['full_name'];
			$project->description = $item['description'];
			$project->affiliation = $item['affiliation'];
			$project->trial_project_flag = filter_var($item['trial_project_flag'], FILTER_VALIDATE_BOOLEAN);
			$project->denial_date = $item['denial_date'];
			$project->deactivation_date = $item['deactivation_date'];

			// save updated project
			//
			$project->save();
		}

		// log the project event
		//
		Log::info("Project update all.");

		return response("Projects successfully updated.", 200);
	}
	
	// delete by index
	//
public function deleteIndex($projectUid) {
		$project = Project::where('project_uid', '=', $projectUid)->first();
		
		if ($project) {
			$project->deactivation_date = gmdate('Y-m-d H:i:s');
			$project->save();

			// send notification email to project owner that project was deactivated
			//
			if (config('mail.enabled')) {
				if ($project->project_owner_uid) {
					$this->user = User::getIndex($project->project_owner_uid);
					if ($this->user && $this->user->email && filter_var($this->user->email, FILTER_VALIDATE_EMAIL)) {
						Mail::send('emails.user-project-deactivated', [
							'user' => $this->user,
							'project' => [
								'owner' => $this->user->getFullName(),
								'full_name' => $project->full_name
							]
						], function($message) {
							$message->to($this->user->email, $this->user->getFullName());
							$message->subject('SWAMP User Project Deactivated');
						});
					}
				}
			}
			
			// log the project event
			//
			Log::info("Project deleted.", [
				'project_uid' => $project->project_uid,
				'deactivation_date' => $project->deactivation_date,
			]);

			return $project;
		} else {
			return response('Project not found.', 404);
		}
	}

	// get project users by index
	//
	public function getUsers($projectUid) {
		$users = new Collection;
		$projectMemberships = ProjectMembership::where('project_uid', '=', $projectUid)->get();
		$project = Project::where('project_uid', '=', $projectUid)->first();
		for ($i = 0; $i < sizeOf($projectMemberships); $i++) {
			$projectMembership = $projectMemberships[$i];
			$userUid = $projectMembership['user_uid'];
			$user = User::getIndex($userUid);

			if ($user) {

				// set public fields
				//
				$user = [
					'first_name' => $user->first_name,
					'last_name' => $user->last_name,
					'email' => $user->email,
					'username' => $user->username,
					'affiliation' => $user->affiliation
				];

				// set metadata
				//
				if ($project->project_owner_uid == $userUid) {
					$user['owner'] = true;
				}
			}
			
			$users[] = $user;
		}

		return $users;
	}

	public function confirm($projectUuid) {
		$project = Project::where('project_uid', '=', $projectUuid)->first();
		return [
			'full_name' => $project->full_name
		];
	}

	// get project memberships by index
	//
	public function getMemberships($projectUid) {
		$project = Project::where('project_uid', '=', $projectUid)->first();
		return $project->getMemberships();
	}

	// delete by project memberships by index
	//
	public function deleteMembership($projectUid, $userUid) {
		$projectMembership = ProjectMembership::where('project_uid', '=', $projectUid)->where('user_uid', '=', $userUid)->first();
		return ProjectMembershipsController::deleteIndex($projectMembership->membership_uid);
	}

	// get project events by index
	//
	public function getEvents($projectUid) {
		$project = Project::where('project_uid', '=', $projectUid)->first();
		return $project->getEvents();
	}
}
