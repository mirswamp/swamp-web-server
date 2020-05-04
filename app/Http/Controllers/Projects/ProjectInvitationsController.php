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
|        Copyright (C) 2012-2020 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Http\Controllers\Projects;

use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use App\Utilities\Uuids\Guid;
use App\Models\Projects\Project;
use App\Models\Projects\ProjectInvitation;
use App\Models\Projects\ProjectMembership;
use App\Models\Users\User;
use App\Models\Utilities\Configuration;
use App\Http\Controllers\BaseController;

class ProjectInvitationsController extends BaseController
{
	// create
	//
	public function postCreate(Request $request) {

		// parse parameters
		//
		$projectUid = $request->input('project_uid');
		$inviterUid = $request->input('inviter_uid');
		$inviteeName = $request->input('invitee_name');
		$inviteeEmail = filter_var($request->input('invitee_email'), FILTER_VALIDATE_EMAIL);
		$inviteeUsername = $request->input('invitee_username');
		$confirmRoute = $request->input('confirm_route');
		$registerRoute = $request->input('register_route');

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
				->exists()
			) {
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
				->exists()
			) {
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
	public function getIndex(string $invitationKey): ?ProjectInvitation {
		$projectInvitation = ProjectInvitation::where('invitation_key', '=', $invitationKey)->first();

		// add sender
		//
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
	public function getByProject(string $projectUid): Collection {

		// find project
		//
		$project = Project::find($projectUid);
		if (!$project) {
			return respone("Project not found.", 404);
		}
		
		return $project->getInvitations();
	}

	// get by user
	//
	public function getByUser(string $userUid): Collection {
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
			return collect();
		}
	}

	public function getNumByUser(string $userUid): int {
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

	// accept by key
	//
	public function acceptIndex(string $invitationKey) {
		$projectInvitation = ProjectInvitation::where('invitation_key', '=', $invitationKey)->first();
		$projectInvitation->accept();
		$projectInvitation->save();

		// log the invitation event
		//
		Log::info("Project invitation accepted.", $projectInvitation->toArray());

		return $projectInvitation;
	}

	// decline by key
	//
	public function declineIndex(string $invitationKey) {
		$projectInvitation = ProjectInvitation::where('invitation_key', '=', $invitationKey)->first();
		$projectInvitation->decline();
		$projectInvitation->save();

		// log the invitation event
		//
		Log::info("Project invitation declined.", $projectInvitation->toArray());

		return $projectInvitation;
	}

	// delete by key
	//
	public function deleteIndex(string $invitationKey) {
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
	public function getInviter(string $invitationKey) {
		$projectInvitation = ProjectInvitation::where('invitation_key', '=', $invitationKey)->first();

		// get inviter from invitation
		//
		$inviter = User::getIndex($projectInvitation->inviter_uid);

		return $inviter;
	}

	// get a invitee by key
	//
	public function getInvitee(string $invitationKey) {
		$projectInvitation = ProjectInvitation::where('invitation_key', '=', $invitationKey)->first();

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
