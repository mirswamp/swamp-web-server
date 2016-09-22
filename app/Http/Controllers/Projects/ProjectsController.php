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
|        Copyright (C) 2012-2016 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Http\Controllers\Projects;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Config;
use App\Utilities\Uuids\Guid;
use App\Models\Projects\Project;
use App\Models\Projects\ProjectMembership;
use App\Models\Projects\ProjectInvitation;
use App\Models\Users\User;
use App\Http\Controllers\BaseController;
use App\Utilities\Filters\DateFilter;
use App\Utilities\Filters\LimitFilter;

class ProjectsController extends BaseController {

	// create
	//
	public function postCreate() {
		$project = new Project(array(
			'project_uid' => Guid::create(),
			'project_owner_uid' => Input::get('project_owner_uid'),
			'full_name' => Input::get('full_name'),
			'short_name' => Input::get('short_name'),
			'description' => Input::get('description'),
			'affiliation' => Input::get('affiliation'),
			'trial_project_flag' => Input::get('trial_project_flag') ? true : false,
			'exclude_public_tools_flag' => Input::get('exclude_public_tools_flag') ? true : false,
			'denial_date' => Input::get('denial_date'),
			'deactivation_date' => Input::get('deactivation_date')
		));
		$project->save();

		// automatically create new project membership for owner
		//
		$projectMembership = new ProjectMembership(array(
			'membership_uid' => Guid::create(),
			'project_uid' => $project->project_uid,
			'user_uid' => $project->project_owner_uid,
			'admin_flag' => true
		));
		$projectMembership->save();

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

	public function getUserTrialProject($userUid) {
		return Project::where('project_owner_uid', '=', $userUid)->where('trial_project_flag', '=', 1)->first();
	}

	// update by index
	//
	public function updateIndex($projectUid) {

		// get model
		//
		$project = Project::where('project_uid', '=', $projectUid)->first();
		$project->full_name = Input::get('full_name');

		// send email notifications of changes in project status
		//
		if (Config::get('mail.enabled')) {

			// send an email to the project owner if it's revoked
			//
			if (!$project->denial_date && Input::get('denial_date')) {
				$this->user = User::getIndex($project->project_owner_uid);
				$data = array(
					'project' => array(
					'owner'		=> $this->user->getFullName(),
					'full_name'	=> $project->full_name
					)
				);
				Mail::send('emails.project-denied', $data, function($message) {
					$message->to($this->user->email, $this->user->getFullName());
					$message->subject('SWAMP Project Denied');
				});
			}

			// send an email to the project owner if it's reactivated
			//
			if ($project->deactivation_date != null && Input::get('deactivation_date') == '') {
				if ($project->project_owner_uid) {
					$this->user = User::getIndex($project->project_owner_uid);
					$data = array(
						'user' => $this->user,
						'project' => array(
						'owner'     => $this->user->getFullName(),
						'full_name' => $project->full_name
						)
					);
					Mail::send('emails.user-project-reactivated', $data, function($message){
						$message->to( $this->user->email, $this->user->getFullName() );
						$message->subject('SWAMP User Project Reactivated');
					});
				}
			}
		}

		// update attributes
		//
		$project->full_name = Input::get('full_name');
		$project->short_name = Input::get('short_name');
		$project->description = Input::get('description');
		$project->affiliation = Input::get('affiliation');
		$project->trial_project_flag = Input::get('trial_project_flag');
		$project->exclude_public_tools_flag = Input::get('exclude_public_tools_flag');
		$project->denial_date = Input::get('denial_date');
		$project->deactivation_date = Input::get('deactivation_date');

		// save and return changes
		//
		$changes = $project->getDirty();
		$project->save();
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
			$project->short_name = $item['short_name'];
			$project->description = $item['description'];
			$project->affiliation = $item['affiliation'];
			$project->trial_project_flag = $item['trial_project_flag'];
			$project->denial_date = $item['denial_date'];
			$project->deactivation_date = $item['deactivation_date'];

			// save updated project
			//
			$project->save();
		}
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
			if (Config::get('mail.enabled')) {
				if ($project->project_owner_uid) {
					$this->user = User::getIndex($project->project_owner_uid);
					$data = array(
						'user' => $this->user,
						'project' => array(
						'owner'     => $this->user->getFullName(),
						'full_name' => $project->full_name
						)
					);
					Mail::send('emails.user-project-deactivated', $data, function($message){
						$message->to( $this->user->email, $this->user->getFullName() );
						$message->subject('SWAMP User Project Deactivated');
					});
				}
			}
			
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
				$user = array(
					'first_name' => $user->first_name,
					'last_name' => $user->last_name,
					'email' => $user->email,
					'username' => $user->username,
					'affiliation' => $user->affiliation
				);

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
		return array(
			'full_name' => $project->full_name
		);
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
