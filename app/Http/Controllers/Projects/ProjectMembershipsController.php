<?php
/******************************************************************************\
|                                                                              |
|                       ProjectMembershipsController.php                       |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a controller for project memberships.                    |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2020 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Http\Controllers\Projects;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Utilities\Uuids\Guid;
use App\Models\Users\User;
use App\Models\Projects\Project;
use App\Models\Projects\ProjectMembership;
use App\Http\Controllers\BaseController;

class ProjectMembershipsController extends BaseController
{
	// create
	//
	public function postCreate(Request $request): ProjectMembership {

		// parse params
		//
		$projectUid = $request->input('project_uid');
		$userUid = $request->input('user_uid');
		$adminFlag = filter_var($request->input('admin_flag'), FILTER_VALIDATE_BOOLEAN);

		// create new project membership
		//
		$projectMembership = new ProjectMembership([
			'membership_uid' => Guid::create(),
			'project_uid' => $projectUid,
			'user_uid' => $userUid,
			'admin_flag' => $adminFlag
		]);
		$projectMembership->save();

		// log the project membership event
		//
		Log::info("Project membership created.", $projectMembership->toArray());

		return $projectMembership;
	}

	// get by index
	//
	public function getIndex(string $membershipUid): ?ProjectMembership {
		return ProjectMembership::find($membershipUid);
	}

	// get by project and user index
	//
	public function getMembership(string $projectUid, string $userUid): ?ProjectMembership {
		return ProjectMembership::where('project_uid', '=', $projectUid)->where('user_uid', '=', $userUid)->first();
	}

	// update by index
	//
	public function updateIndex(Request $request, string $membershipUid) {

		// parse parameters
		//
		$adminFlag = filter_var($request->input('admin_flag'), FILTER_VALIDATE_BOOLEAN);

		// get model
		//
		$projectMembership = ProjectMembership::where('membership_uid', '=', $membershipUid)->first();
		
		// update attributes
		//
		$projectMembership->admin_flag = $adminFlag;

		// save and return changes
		//
		$changes = $projectMembership->getDirty();
		$projectMembership->save();

		// log the project membership event
		//
		Log::info("Project membership updated.", $projectMembership->toArray());

		return $changes;
	}

	// delete by index
	//
	public function deleteIndex(string $membershipUid) {
		$projectMembership = ProjectMembership::where('membership_uid', '=', $membershipUid)->first();

		// get user and project associated with this email
		//
		$user = User::getIndex($projectMembership->user_uid);
		$project = Project::where('project_uid', '=', $projectMembership->project_uid)->first();

		// send notification email that membership was deleted
		//
		if (config('mail.enabled')) {
			if ($user && $user->email && filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
				$this->user = $user;
				Mail::send('emails.project-membership-deleted', [
					'user' => $user,
					'project' => $project
				], function($message) {
					$message->to($this->user->email, $this->user->getFullName());
					$message->subject('SWAMP Project Membership Deleted');
				});
			}
		}

		if ($projectMembership) {

			// log the project membership event
			//
			Log::info("Project membership deleted.", $projectMembership->toArray());
		}

		$projectMembership->delete();
		return $projectMembership;
	}
	
	// delete by project and user id
	//
	public function deleteMembership(string $projectUid, string $userUid) {
		$projectMembership = ProjectMembership::where('project_uid', '=', $projectUid)->where('user_uid', '=', $userUid)->first();
		$projectMembership->delete();
		return $projectMembership;
	}
}
