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
|        Copyright (C) 2012-2016 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Http\Controllers\Admin;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Response;
use App\Utilities\Uuids\Guid;
use App\Models\Admin\AdminInvitation;
use App\Models\Users\User;
use App\Models\Users\UserAccount;
use App\Http\Controllers\BaseController;

class AdminInvitationsController extends BaseController {

	// create
	//
	public function postCreate() {
		if (Input::has('invitee_uid')) {

			// create a single admin invitation
			//
			$adminInvitation = new AdminInvitation(array(
				'invitation_key' => Guid::create(),
				'inviter_uid' => Input::get('inviter_uid'),
				'invitee_uid' => Input::get('invitee_uid'),
			));
			$adminInvitation->save();
			$adminInvitation->send(Input::get('invitee_name'), Input::get('confirm_route'));
			return $adminInvitation;
		} else {

			// create an array of admin invitations
			//
			$invitations = Input::all();
			$adminInvitations = new Collection;
			for ($i = 0; $i < sizeOf($invitations); $i++) {
				$invitation = $invitations[$i];
				$adminInvitation = new AdminInvitation(array(
					'invitation_key' => Guid::create(),
					'inviter_uid' => $invitation['inviter_uid'],
					'invitee_uid' => $invitation['invitee_uid'],
				));
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
	public function getIndex($invitationKey) {
		$adminInvitation = AdminInvitation::where('invitation_key', '=', $invitationKey)->get()->first();

		if( ! $adminInvitation ){
			return response('Could not load invitation.', 404);
		}

		$inviter = User::getIndex( $adminInvitation->inviter_uid );
		$inviter = ( ! $inviter || $inviter->enabled_flag != 1 ) ? false : $inviter;
		if( $inviter )
			$inviter['user_uid'] = $adminInvitation->inviter_uid;
		$adminInvitation->inviter = $inviter;

		$invitee = User::getIndex( $adminInvitation->invitee_uid );
		$invitee = ( ! $invitee || $invitee->enabled_flag != 1 ) ? false : $invitee;
		if( $invitee )
			$invitee['user_uid'] = $adminInvitation->invitee_uid;
		$adminInvitation->invitee = $invitee;

		return $adminInvitation;
	}

	// get pending by user
	//
	public function getPendingByUser($userUid) {
		return AdminInvitation::where('invitee_uid', '=', $userUid)
			->whereNull('accept_date')
			->whereNull('decline_date')
			->get();
	}

	public function getNumPendingByUser($userUid) {
		return AdminInvitation::where('invitee_uid', '=', $userUid)
			->whereNull('accept_date')
			->whereNull('decline_date')
			->count();
	}

	// get all
	//
	public function getAll() {
		$adminInvitations = AdminInvitation::all();
		return $adminInvitations;
	}

	// get invitees associated with invitations
	//
	public function getInvitees() {
		$adminInvitations = AdminInvitation::all();
		$users = new Collection;
		for ($i = 0; $i < sizeOf($adminInvitations); $i++) {
			$adminInvitation = $adminInvitations[$i];
			$user = User::getIndex($adminInvitation['invitee_uid']);
		}
		return $users;
	}

	// get inviters associated with invitations
	//
	public function getInviters() {
		$adminInvitations = AdminInvitation::all();
		$users = new Collection;
		for ($i = 0; $i < sizeOf($adminInvitations); $i++) {
			$adminInvitation = $adminInvitations[$i];
			$user = User::getIndex($adminInvitation['inviter_uid']);
		}
		return $users;
	}

	// update by key
	//
	public function updateIndex($invitationKey) {

		// get model
		//
		$adminInvitation = AdminInvitation::where('invitation_key', '=', $invitationKey)->get()->first();

		// update attributes
		//
		$adminInvitation->invitation_key = $invitationKey;
		$adminInvitation->inviter_uid = Input::get('inviter_uid');
		$adminInvitation->invitee_uid = Input::get('invitee_uid');

		// save and return changes
		//
		$changes = $adminInvitation->getDirty();
		$adminInvitation->save();
		return $changes;
	}

	// accept by key
	//
	public function acceptIndex($invitationKey) {
		$adminInvitation = AdminInvitation::where('invitation_key', '=', $invitationKey)->get()->first();
		if ($adminInvitation) {

			// accept invitation
			//
			$adminInvitation->accept_date = gmdate('Y-m-d H:i:s');
			$adminInvitation->save();

			// update user account
			//
			$userAccount = UserAccount::where('user_uid', '=', $adminInvitation->invitee_uid)->first();
			$userAccount->admin_flag = 1;
			$userAccount->save();
			
			return response()->json(array('success' => 'true'));
		} else {
			return response('Admin invitation not found', 404);
		}
	}

	// decline by key
	//
	public function declineIndex($invitationKey) {
		$adminInvitation = AdminInvitation::where('invitation_key', '=', $invitationKey)->get()->first();
		if ($adminInvitation) {

			// decline invitation
			//
			$adminInvitation->decline_date = gmdate('Y-m-d H:i:s');
			$adminInvitation->save();

			return response()->json(array('success' => 'true'));
		} else {
			return response('Admin invitation not found', 404);
		}
	}

	// delete by key
	//
	public function deleteIndex($invitationKey) {
		$adminInvitation = AdminInvitation::where('invitation_key', '=', $invitationKey)->first();
		$adminInvitation->delete();
		return $adminInvitation;
	}
}