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
|        Copyright (C) 2012-2020 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\Admin;

use DateTime;
use Illuminate\Support\Facades\Mail;
use App\Models\TimeStamps\CreateStamped;
use App\Models\Users\User;

class AdminInvitation extends CreateStamped
{
	/**
	 * The table associated with the model.
	 *
	 * @var string
	 */
	protected $table = 'admin_invitation';

	/**
	 * The primary key associated with the table.
	 *
	 * @var string
	 */
	protected $primaryKey = 'admin_invitation_id';

	/**
	 * Indicates if the IDs are auto-incrementing.
	 *
	 * @var bool
	 */
	public $incrementing = false;

	/**
	 * The "type" of the auto-incrementing ID.
	 *
	 * @var string
	 */
	protected $keyType = 'string';

	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array
	 */
	protected $fillable = [
		'invitation_key',
		'inviter_uid',
		'invitee_uid',
		'accept_date',
		'decline_date'
	];

	/**
	 * The attributes that should be visible in serialization.
	 *
	 * @var array
	 */
	protected $visible = [
		'invitation_key',
		'inviter_uid',
		'invitee_uid',
		'accept_date',
		'decline_date',
		'inviter',
		'invitee'
	];

	/**
	 * The accessors to append to the model's array form.
	 *
	 * @var array
	 */
	protected $appends = [
		'inviter',
		'invitee'
	];

	//
	// accessor methods
	//

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

	//
	// invitation sending / emailing method
	//
	
	public function send(string $inviteeName, string $confirmRoute) {

		// check to see that mail is enabled
		//
		if (!config('mail.enabled')) {
			return;
		}

		if ($this->invitee && $this->invitee['email']) {

			// send invitation to the invitee
			//
			$data = [
				'invitation' => $this,
				'inviter' => $this->getInviter(),
				'invitee' => $this->getInvitee(),
				'invitee_name' => $inviteeName,
				'confirm_url' => config('app.cors_url').'/'.$confirmRoute
			];
			Mail::send('emails.admin-invitation', $data, function($message) {
				$message->to($this->invitee['email'], $this->invitee_name);
				$message->subject('SWAMP Admin Invitation');
			});
		}
	}

	//
	// status changing methods
	//

	public function accept() {
		$this->accept_date = new DateTime();
	}

	public function decline() {
		$this->decline_date = new DateTime();
	}

	//
	// querying methods
	//

	public function isAccepted(): bool {
		return $this->accept_date != null;
	}

	public function isDeclined(): bool {
		return $this->decline_date != null;
	}

	public function getInviter(): ?User {
		return User::getIndex($this->inviter_uid);
	}

	public function getInvitee(): ?User {
		return User::getIndex($this->invitee_uid);
	}
}
