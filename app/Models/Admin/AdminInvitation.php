<?php
/******************************************************************************\
|                                                                              |
|                             AdminInvitation.php                              |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a model of an administrator invitation.                  |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2016 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\Admin;

use DateTime;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use App\Models\TimeStamps\CreateStamped;
use App\Models\Users\User;

class AdminInvitation extends CreateStamped {

	/**
	 * database attributes
	 */
	protected $table = 'admin_invitation';
	protected $primaryKey = 'admin_invitation_id';

	/**
	 * mass assignment policy
	 */
	protected $fillable = array(
		'invitation_key',
		'inviter_uid',
		'invitee_uid',
		'accept_date',
		'decline_date'
	);

	/**
	 * array / json conversion whitelist
	 */
	protected $visible = array(
		'invitation_key',
		'inviter_uid',
		'invitee_uid',
		'accept_date',
		'decline_date',
		'inviter',
		'invitee'
	);

	/**
	 * array / json appended model attributes
	 */
	protected $appends = array(
		'inviter',
		'invitee'
	);

	/**
	 * invitation sending / emailing method
	 */
	
	public function send($inviteeName, $confirmRoute) {

		// return an error if email has not been enabled
		//
		if (!Config::get('mail.enabled')) {
			return response('Email has not been enabled.', 400);
		}

		// send invitation to the invitee
		//
		$data = array(
			'invitation' => $this,
			'inviter' => $this->getInviter(),
			'invitee' => $this->getInvitee(),
			'invitee_name' => $inviteeName,
			'confirm_url' => Config::get('app.cors_url').'/'.$confirmRoute
		);
		Mail::send('emails.admin-invitation', $data, function($message) {
			$message->to($this->invitee['email'], $this->invitee_name);
			$message->subject('SWAMP Admin Invitation');
		});
	}

	/**
	 * status changing methods
	 */

	public function accept() {
		$this->accept_date = new DateTime();
	}

	public function decline() {
		$this->decline_date = new DateTime();
	}

	/**
	 * querying methods
	 */

	public function isAccepted() {
		return $this->accept_date != null;
	}

	public function isDeclined() {
		return $this->decline_date != null;
	}

	public function getInviter() {
		return User::getIndex($this->inviter_uid);
	}

	public function getInvitee() {
		return User::getIndex($this->invitee_uid);
	}

	/**
	 * accessor methods
	 */

	public function getInviterAttribute() {
		$inviter = $this->getInviter();
		if ($inviter) {
			return $inviter->toArray();
		}
	}

	public function getInviteeAttribute() {
		$invitee = $this->getInvitee();
		if ($invitee) {
			return $invitee->toArray();
		}
	}
}
