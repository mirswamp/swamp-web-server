<?php
/******************************************************************************\
|                                                                              |
|                       ProjectInvitationsController.php                       |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a controller for project invitations.                    |
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

use DateTime;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Config;
use App\Utilities\Uuids\Guid;
use App\Models\Projects\Project;
use App\Models\Projects\ProjectInvitation;
use App\Models\Projects\ProjectMembership;
use App\Models\Users\User;
use App\Http\Controllers\BaseController;

class ProjectInvitationsController extends BaseController {

	// create
	//
	public function postCreate() {

		// create a single model
		//
		$projectInvitation = new ProjectInvitation(array(
			'project_uid' => Input::get('project_uid'),
			'invitation_key' => Guid::create(),
			'inviter_uid' => Input::get('inviter_uid'),
			'invitee_name' => Input::get('invitee_name'),
			'invitee_email' => Input::get('invitee_email'),
			'invitee_username' => Input::get('invitee_username')
		));

		// check if project exists
		//
		$project = $projectInvitation->getProject();
		if (!$project) {
			return response('Project does not exist.', 409);
		}

		// check for invitee user
		//
		if ($projectInvitation->invitee_email) {
			$user = User::getByEmail($projectInvitation->invitee_email);
		} else if ($projectInvitation->invitee_username) {
			if (User::where('username', '=', $projectInvitation->invitee_username)->count() > 0) {
				$user = User::getByUsername($projectInvitation->invitee_username);
			} else {
				return response(array("The username '".$projectInvitation->invitee_username."' does not match an existing user."), 409);
			}
		} else {
			return response("Project invitee must be specified by either an email address or a username.", 409);
		}

		// check if user is already a member
		//
		if ($user && $project->hasMember($user)) {
			return response()->json(array('error' => array('message' => Input::get('invitee_name').' is already a member')), 409);
		}

		// check for pending invitations
		//
		if ($projectInvitation->invitee_email) {

			// check by invitee email
			//
			if (ProjectInvitation::where('project_uid','=',Input::get('project_uid'))
				->where('invitee_email', '=', $projectInvitation->invitee_email)
				->where('accept_date', '=', null)
				->where('decline_date', '=', null)
				->exists()) {
				return response()->json(array('error' => array('message' => Input::get('invitee_name').' already has a pending invitation')), 409);
			}
		} else if ($projectInvitation->invitee_username) {

			// check by invitee username
			//
			if (ProjectInvitation::where('project_uid','=',Input::get('project_uid'))
				->where('invitee_username', '=', $projectInvitation->invitee_username)
				->where('accept_date', '=', null)
				->where('decline_date', '=', null)
				->exists()) {
				return response()->json(array('error' => array('message' => Input::get('invitee_name').' already has a pending invitation')), 409);
			}
		}

		// check if invitation is valid
		//
		if ($projectInvitation->isValid()) {
			$projectInvitation->save();

			// send invitation if email is specified
			//
			if ($projectInvitation->invitee_email) {
				$projectInvitation->send(Input::get('confirm_route'), Input::get('register_route'));
			}

			return $projectInvitation;
		} else {

			// return project invitation errors
			//
			$errors = $projectInvitation->errors();
			return response($errors->toJson(), 409);
		}
	}

	// get by key
	//
	public function getIndex($invitationKey) {
		$projectInvitation = ProjectInvitation::where('invitation_key', '=', $invitationKey)->get()->first();
		$sender = User::getIndex( $projectInvitation->inviter_uid );
		$sender = ( ! $sender || $sender->enabled_flag != 1 ) ? false : $sender;
		if( $sender )
			$sender['user_uid'] = $projectInvitation->inviter_uid;
		$projectInvitation->sender = $sender;
		return $projectInvitation;
	}

	// get by project
	//
	public function getByProject($projectUid) {
		$project = Project::where('project_uid', '=', $projectUid)->first();
		return $project->getInvitations();
	}

	// get by user
	//
	public function getByUser($userUid) {
		$user = User::getIndex($userUid);
		if ($user) {
			if (Config::get('mail.enabled')) {
				return ProjectInvitation::where('invitee_email', '=', $user->email)
					->whereNull('accept_date')
					->whereNull('decline_date')
					->get();
			} else {
				return ProjectInvitation::where('invitee_username', '=', $user->username)
					->whereNull('accept_date')
					->whereNull('decline_date')
					->get();
			}
		} else {
			return array();
		}
	}

	public function getNumByUser($userUid) {
		$user = User::getIndex($userUid);
		if ($user) {
			if (Config::get('mail.enabled')) {
				return ProjectInvitation::where('invitee_email', '=', $user->email)
					->whereNull('accept_date')
					->whereNull('decline_date')
					->count();
			} else {
				return ProjectInvitation::where('invitee_username', '=', $user->username)
					->whereNull('accept_date')
					->whereNull('decline_date')
					->count();
			}
		} else {
			return 0;
		}
	}

	// update by key
	//
	public function updateIndex($invitationKey) {

		// get model
		//
		$projectInvitation = ProjectInvitation::where('invitation_key', '=', $invitationKey)->get()->first();
		
		// update attributes
		//
		$projectInvitation->project_uid = Input::get('project_uid');
		$projectInvitation->invitation_key = $invitationKey;
		$projectInvitation->inviter_uid = Input::get('inviter_uid');
		$projectInvitation->invitee_name = Input::get('invitee_name');
		$projectInvitation->invitee_email = Input::get('invitee_email');
		$projectInvitation->invitee_username = Input::get('invitee_username');

		// save and return changes
		//
		$changes = $projectInvitation->getDirty();
		$projectInvitation->save();
		return $changes;
	}

	// accept by key
	//
	public function acceptIndex($invitationKey) {
		$projectInvitation = ProjectInvitation::where('invitation_key', '=', $invitationKey)->get()->first();
		$projectInvitation->accept();
		$projectInvitation->save();
		return $projectInvitation;
	}

	// decline by key
	//
	public function declineIndex($invitationKey) {
		$projectInvitation = ProjectInvitation::where('invitation_key', '=', $invitationKey)->get()->first();
		$projectInvitation->decline();
		$projectInvitation->save();
		return $projectInvitation;
	}

	// update multiple
	//
	public function updateAll() {
		$invitations = Input::all();
		$projectInvitations = new Collection;
		for ($i = 0; $i < sizeOf($invitations); $i++) {
			$invitation = $invitations[$i];
			$projectInvitation = new ProjectInvitation(array(
				'project_uid' => $invitation['project_uid'],
				'invitation_key' => Guid::create(),
				'inviter_uid' => $invitation['inviter_uid'],
				'invitee_name' => $invitation['invitee_name'],
				'invitee_email' => $invitation['invitee_email'],
				'invitee_username' => $invitation['invitee_username']
			));
			$projectInvitations->push($projectInvitation);
			$projectInvitation->save();
		}
		return $projectInvitations;
	}

	// delete by key
	//
	public function deleteIndex($invitationKey) {
		$projectInvitation = ProjectInvitation::where('invitation_key', '=', $invitationKey)->first();
		$projectInvitation->delete();
		return $projectInvitation;
	}

	// get a inviter by key
	//
	public function getInviter($invitationKey) {
		$projectInvitation = ProjectInvitation::where('invitation_key', '=', $invitationKey)->get()->first();

		// get inviter from invitation
		//
		$inviter = User::getIndex($projectInvitation->inviter_uid);

		return $inviter;
	}

	// get a invitee by key
	//
	public function getInvitee($invitationKey) {
		$projectInvitation = ProjectInvitation::where('invitation_key', '=', $invitationKey)->get()->first();

		// get invitee from invitation
		//
		if ($projectInvitation->invitee_email) {
			$invitee = User::getByEmail($projectInvitation->invitee_email);
		} else if ($projectInvitation->invitee_username) {
			$invitee = User::getByUsername($projectInvitation->invitee_username);
		}

		return $invitee;
	}
}
