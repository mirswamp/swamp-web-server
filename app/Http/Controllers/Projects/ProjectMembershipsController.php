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
|        Copyright (C) 2012-2017 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Http\Controllers\Projects;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Config;
use App\Utilities\Uuids\Guid;
use App\Models\Users\User;
use App\Models\Projects\Project;
use App\Models\Projects\ProjectMembership;
use App\Http\Controllers\BaseController;

class ProjectMembershipsController extends BaseController {

	// create
	//
	public function postCreate() {
		$projectMembership = new ProjectMembership(array(
			'membership_uid' => Guid::create(),
			'project_uid' => Input::get('project_uid'),
			'user_uid' => Input::get('user_uid'),
			'admin_flag' => Input::get('admin_flag') == 'true'
		));
		$projectMembership->save();
		return $projectMembership;
	}

	// get by index
	//
	public function getIndex($membershipUid) {
		$projectMembership = ProjectMembership::where('membership_uid', '=', $membershipUid)->get()->first();
		return $projectMembership;
	}

	// get by project and user index
	//
	public function getMembership($projectUid, $userUid) {
		$projectMembership = ProjectMembership::where('project_uid', '=', $projectUid)->where('user_uid', '=', $userUid)->first();
		return $projectMembership;
	}

	// update by index
	//
	public function updateIndex($membershipUid) {

		// get model
		//
		$projectMembership = ProjectMembership::where('membership_uid', '=', $membershipUid)->get()->first();
		
		// update attributes
		//
		$projectMembership->admin_flag = Input::get('admin_flag');

		// save and return changes
		//
		$changes = $projectMembership->getDirty();
		$projectMembership->save();
		return $changes;
	}

	// update multiple
	//
	public function updateAll() {
		$input = Input::all();
		$collection = new Collection;
		for ($i = 0; $i < sizeOf($input); $i++) {

			// get project membership
			//
			$item = $input[$i];
			$projectMembership = ProjectMembership::where('membership_uid', '=', $item['membership_uid'])->first();
			$collection->push($projectMembership);

			// update project membership fields
			//
			$projectMembership->project_uid = $item['project_uid'];
			$projectMembership->user_uid = $item['user_uid'];
			$projectMembership->admin_flag = $item['admin_flag'];
			
			// save updated project membership
			//
			$projectMembership->save();
		}
		return $collection;
	}

	// delete by index
	//
	public function deleteIndex($membershipUid) {
		$projectMembership = ProjectMembership::where('membership_uid', '=', $membershipUid)->first();

		// get user and project associated with this email
		//
		$user = User::getIndex($projectMembership->user_uid);
		$project = Project::where('project_uid', '=', $projectMembership->project_uid)->first();

		// send notification email that membership was deleted
		//
		if (Config::get('mail.enabled')) {
			$data = array(
				'user' => $user,
				'project' => $project
			);
			$this->user = $user;
			Mail::send('emails.project-membership-deleted', $data, function($message) {
				$message->to($this->user->email, $this->user->getFullName());
				$message->subject('SWAMP Project Membership Deleted');
			});
		}

		$projectMembership->delete();
		return $projectMembership;
	}
	
	// delete by project and user id
	//
	public function deleteMembership($projectUid, $userUid) {
		$projectMembership = ProjectMembership::where('project_uid', '=', $projectUid)->where('user_uid', '=', $userUid)->first();
		$projectMembership->delete();
		return $projectMembership;
	}
}
