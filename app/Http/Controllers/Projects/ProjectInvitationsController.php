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
|        Copyright (C) 2012-2019 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Http\Controllers\Projects;

use DateTime;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use App\Utilities\Uuids\Guid;
use App\Models\Projects\Project;
use App\Models\Projects\ProjectInvitation;
use App\Models\Projects\ProjectMembership;
use App\Models\Users\User;
use App\Models\Utilities\Configuration;
use App\Http\Controllers\BaseController;

class ProjectInvitationsController extends BaseController {

	// create
	//
	public function postCreate() {

		// parse parameters
		//
		$projectUid = Input::get('project_uid');
		$inviterUid = Input::get('inviter_uid');
		$inviteeName = Input::get('invitee_name');
		$inviteeEmail = filter_var(Input::get('invitee_email'), FILTER_VALIDATE_EMAIL);
		$inviteeUsername = Input::get('invitee_username');
		$confirmRoute = Input::get('confirm_route');
		$registerRoute = Input::get('register_route');

		// create a single model
		//
		$projectInvitation = new ProjectInvitation([
			'invitation_key' => Guid::create(),
			'project_uid' => $projectUid,
			'inviter_uid' => $inviterUid,
			'invitee_name' => $inviteeName,
			'invitee_email' => $inviteeEmail,
			'invitee_username' => $inviteeUsername
		]);

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
			if (!$user) {

				// If LDAP is set read-only, make sure that there is an LDAP user
				// with a matching email address. Otherwise, return an error
				// response so that a "Sign Up with SWAMP" email link is not
				// generated (since read-only LDAP cannot create new users).
				//
				$configuration = new Configuration();
				if ($configuration->getLdapReadOnlyAttribute()) {
					return response([
						"The email address '" . htmlentities($projectInvitation->invitee_email) . "' does not match an existing user."
					], 409);
				}
			}
		} else if ($projectInvitation->invitee_username) {
			$user = User::getByUsername($projectInvitation->invitee_username);
			if (!$user) {
				return response([
					"The username '" . htmlentities($projectInvitation->invitee_username) . "' does not match an existing user."
				], 409);
			}
		} else {
			return response("Project invitee must be specified by either an email address or a username.", 409);
		}

		// check if user is already a member
		//
		if ($user && $project->hasMember($user)) {
			return response()->json([
				'error' => [
					'message' => $inviteeName . ' is already a member'
				]
			], 409);
		}

		// check for pending invitations
		//
		if ($projectInvitation->invitee_email) {

			// check by invitee email
			//
			if (ProjectInvitation::where('project_uid', '=', $projectUid)
				->where('invitee_email', '=', $projectInvitation->invitee_email)
				->where('accept_date', '=', null)
				->where('decline_date', '=', null)
				->exists()) {
				return response()->json([
					'error' => [
						'message' => $inviteeName . ' already has a pending invitation'
					]
				], 409);
			}
		} else if ($projectInvitation->invitee_username) {

			// check by invitee username
			//
			if (ProjectInvitation::where('project_uid', '=', $projectUid)
				->where('invitee_username', '=', $projectInvitation->invitee_username)
				->where('accept_date', '=', null)
				->where('decline_date', '=', null)
				->exists()) {
				return response()->json([
					'error' => [
						'message' => $inviteeName . ' already has a pending invitation'
					]
				], 409);
			}
		}

		// check if invitation is valid
		//
		if ($projectInvitation->isValid()) {
			$projectInvitation->save();

			// send invitation if email is specified
			//
			if ($projectInvitation->invitee_email) {
				$projectInvitation->send($confirmRoute, $registerRoute);
			}

			// log the invitation event
			//
			Log::info("Project invitation created.", $projectInvitation->toArray());

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
		$sender = User::getIndex($projectInvitation->inviter_uid);
		$sender = (!$sender || !$sender->isEnabled()) ? false : $sender;
		if ($sender) {
			$sender['user_uid'] = $projectInvitation->inviter_uid;
		}
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
			if (config('mail.enabled')) {
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
			return [];
		}
	}

	public function getNumByUser($userUid) {
		$user = User::getIndex($userUid);
		if ($user) {
			if (config('mail.enabled')) {
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

		// parse parameters
		//
		$projectUid = Input::get('project_uid');
		$inviterUid = Input::get('inviter_uid');
		$inviteeName = Input::get('invitee_name');
		$inviteeEmail = filter_var(Input::get('invitee_email'), FILTER_VALIDATE_EMAIL);
		$inviteeUsername = Input::get('invitee_username');

		// get model
		//
		$projectInvitation = ProjectInvitation::where('invitation_key', '=', $invitationKey)->get()->first();
		
		// update attributes
		//
		$projectInvitation->project_uid = $projectUid;
		$projectInvitation->invitation_key = $invitationKey;
		$projectInvitation->inviter_uid = $inviterUid;
		$projectInvitation->invitee_name = $inviteeName;
		$projectInvitation->invitee_email = $inviteeEmail;
		$projectInvitation->invitee_username = $inviteeUsername;

		// save and return changes
		//
		$changes = $projectInvitation->getDirty();
		$projectInvitation->save();

		// log the invitation event
		//
		Log::info("Project invitation updated.", $projectInvitation->toArray());

		return $changes;
	}

	// accept by key
	//
	public function acceptIndex($invitationKey) {
		$projectInvitation = ProjectInvitation::where('invitation_key', '=', $invitationKey)->get()->first();
		$projectInvitation->accept();
		$projectInvitation->save();

		// log the invitation event
		//
		Log::info("Project invitation accepted.", $projectInvitation->toArray());

		return $projectInvitation;
	}

	// decline by key
	//
	public function declineIndex($invitationKey) {
		$projectInvitation = ProjectInvitation::where('invitation_key', '=', $invitationKey)->get()->first();
		$projectInvitation->decline();
		$projectInvitation->save();

		// log the invitation event
		//
		Log::info("Project invitation declined.", $projectInvitation->toArray());

		return $projectInvitation;
	}

	// update multiple
	//
	public function updateAll() {
		$invitations = Input::all();
		$projectInvitations = new Collection;
		for ($i = 0; $i < sizeOf($invitations); $i++) {
			$invitation = $invitations[$i];
			$projectInvitation = new ProjectInvitation([
				'project_uid' => $invitation['project_uid'],
				'invitation_key' => Guid::create(),
				'inviter_uid' => $invitation['inviter_uid'],
				'invitee_name' => $invitation['invitee_name'],
				'invitee_email' => $invitation['invitee_email'],
				'invitee_username' => $invitation['invitee_username']
			]);
			$projectInvitations->push($projectInvitation);
			$projectInvitation->save();
		}

		// log the invitation event
		//
		Log::info("Project invitation update all.");

		return $projectInvitations;
	}

	// delete by key
	//
	public function deleteIndex($invitationKey) {
		$projectInvitation = ProjectInvitation::where('invitation_key', '=', $invitationKey)->first();

		if ($projectInvitation) {

			// log the invitation event
			//
			Log::info("Project invitation deleted.", $projectInvitation->toArray());
		}

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
