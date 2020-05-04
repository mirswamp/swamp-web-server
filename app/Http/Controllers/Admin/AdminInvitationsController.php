<?php
/******************************************************************************\
|                                                                              |
|                        AdminInvitationsController.php                        |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a controller for administrator invitations.              |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2020 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use App\Utilities\Uuids\Guid;
use App\Models\Admin\AdminInvitation;
use App\Models\Users\User;
use App\Models\Users\UserAccount;
use App\Http\Controllers\BaseController;

class AdminInvitationsController extends BaseController
{
	// create
	//
	public function postCreate(Request $request) {

		// parse parameters
		//
		$inviterUid = $request->input('inviter_uid');
		$inviteeUid = $request->input('invitee_uid');
		$inviteeName = $request->input('invitee_name');
		$confirmRoute = $request->input('confirm_route');

		// create invitation(s)
		//
		if ($inviteeUid) {

			// create a single admin invitation
			//
			$adminInvitation = new AdminInvitation([
				'invitation_key' => Guid::create(),
				'inviter_uid' => $inviterUid,
				'invitee_uid' => $inviteeUid,
			]);
			$adminInvitation->save();
			$adminInvitation->send($inviteeName, $confirmRoute);
			return $adminInvitation;
		} else {

			// create a collection of admin invitations
			//
			$invitations = $request->all();
			$adminInvitations = collect();
			for ($i = 0; $i < sizeOf($invitations); $i++) {
				$invitation = $invitations[$i];
				$adminInvitation = new AdminInvitation([
					'invitation_key' => Guid::create(),
					'inviter_uid' => $invitation['inviter_uid'],
					'invitee_uid' => $invitation['invitee_uid'],
				]);
				$adminInvitations->push($adminInvitation);
				$adminInvitation->save();
				$adminInvitation->send();
				$adminInvitation->send($invitation['invitee_name'], $invitation['confirm_route']);
			}
			return $adminInvitations;
		}
	}

	// get by index
	//
	public function getIndex(string $invitationKey): ?AdminInvitation {
		$adminInvitation = AdminInvitation::where('invitation_key', '=', $invitationKey)->first();

		if (!$adminInvitation) {
			return null;
		}

		// add inviter
		//
		$inviter = User::getIndex($adminInvitation->inviter_uid);
		$inviter = (!$inviter || !$inviter->isEnabled()) ? false : $inviter;
		if ($inviter) {
			$inviter['user_uid'] = $adminInvitation->inviter_uid;
		}
		$adminInvitation->inviter = $inviter;

		// add invitee
		//
		$invitee = User::getIndex($adminInvitation->invitee_uid);
		$invitee = (!$invitee || !$invitee->isEnabled()) ? false : $invitee;
		if ($invitee) {
			$invitee['user_uid'] = $adminInvitation->invitee_uid;
		}
		$adminInvitation->invitee = $invitee;

		return $adminInvitation;
	}

	// get pending by user
	//
	public function getPendingByUser(string $userUid): Collection {
		return AdminInvitation::where('invitee_uid', '=', $userUid)
			->whereNull('accept_date')
			->whereNull('decline_date')
			->get();
	}

	public function getNumPendingByUser(string $userUid): int {
		return AdminInvitation::where('invitee_uid', '=', $userUid)
			->whereNull('accept_date')
			->whereNull('decline_date')
			->count();
	}

	// get all
	//
	public function getAll(): Collection {
		return AdminInvitation::all();
	}

	// accept by key
	//
	public function acceptIndex(string $invitationKey) {
		$adminInvitation = AdminInvitation::where('invitation_key', '=', $invitationKey)->first();
		if ($adminInvitation) {

			// accept invitation
			//
			$adminInvitation->accept_date = gmdate('Y-m-d H:i:s');
			$adminInvitation->save();

			// update user account
			//
			$userAccount = UserAccount::where('user_uid', '=', $adminInvitation->invitee_uid)->first();
			$userAccount->admin_flag = true;
			$userAccount->save();
			
			return response()->json([
				'success' => 'true'
			]);
		} else {
			return response('Admin invitation not found', 404);
		}
	}

	// decline by key
	//
	public function declineIndex(string $invitationKey) {
		$adminInvitation = AdminInvitation::where('invitation_key', '=', $invitationKey)->first();
		if ($adminInvitation) {

			// decline invitation
			//
			$adminInvitation->decline_date = gmdate('Y-m-d H:i:s');
			$adminInvitation->save();

			return response()->json([
				'success' => 'true'
			]);
		} else {
			return response('Admin invitation not found', 404);
		}
	}

	// delete by key
	//
	public function deleteIndex(string $invitationKey) {
		$adminInvitation = AdminInvitation::where('invitation_key', '=', $invitationKey)->first();
		$adminInvitation->delete();
		return $adminInvitation;
	}
}
