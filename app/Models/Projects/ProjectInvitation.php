<?php
/******************************************************************************\
|                                                                              |
|                             ProjectInvitation.php                            |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a model of an invitation to join a project.              |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2019 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\Projects;

use DateTime;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use App\Utilities\Uuids\Guid;
use App\Models\TimeStamps\CreateStamped;
use App\Models\Projects\Project;
use App\Models\Projects\ProjectMembership;
use App\Models\Users\User;

class ProjectInvitation extends CreateStamped {

	// database attributes
	//
	protected $table = 'project_invitation';
	protected $primaryKey = 'invitation_id';

	// mass assignment policy
	//
	protected $fillable = [
		'project_uid', 
		'invitation_key',
		'inviter_uid',
		'invitee_name',
		'invitee_email',
		'invitee_username',

		// timestamp attributes
		//
		'accept_date',
		'decline_date'
	];

	// array / json conversion whitelist
	//
	protected $visible = [
		'project_uid', 
		'invitation_key',
		'inviter_uid',
		'invitee_name',
		'invitee_email',
		'invitee_username',
		'inviter_name',
		'project_name',

		// timestamp attributes
		//
		'accept_date',
		'decline_date'
	];

	// array / json appended model attributes
	//
	protected $appends = [
		'inviter_name',
		'project_name'
	];

	private $validator;

	//
	// accessor methods
	//

	public function getInviterNameAttribute() {
		$inviter = User::getIndex($this->inviter_uid);
		if ($inviter) {
			return $inviter->getFullName();
		}
	}

	public function getProjectNameAttribute() {
		$project = Project::where('project_uid', '=', $this->project_uid)->first();
		if ($project) {
			return $project->full_name;
		}
	}

	//
	// invitation sending / emailing method
	//

	public function send($confirmRoute, $registerRoute) {

		// return an error if email has not been enabled
		//
		if (!config('mail.enabled')) {
			return response('Email has not been enabled.', 400); 
		}

		// get invitee user
		//
		if ($this->invitee_email) {
			$user = User::getByEmail($this->invitee_email);
		} else if ($this->invitee_username) {
			$user = User::getByUsername($this->invitee_username);
		}

		if ($user != null) {

			// send invitation to existing user
			//
			if ($this->invitee_email) {
				$data = [
					'invitation' => $this,
					'inviter' => User::getIndex($this->inviter_uid),
					'project' => Project::where('project_uid', '=', $this->project_uid)->first(),
					'confirm_url' => config('app.cors_url').'/'.$confirmRoute
				];

				Mail::send('emails.project-invitation', $data, function($message) {
				    $message->to($this->invitee_email, $this->invitee_name);
				    $message->subject('SWAMP Project Invitation');
				});
			}
		} else {
			
			// send invitation to new / future user
			//
			if ($this->invitee_email) {
				$data = [
					'invitation' => $this,
					'inviter' => User::getIndex($this->inviter_uid),
					'project' => Project::where('project_uid', '=', $this->project_uid)->first(),
					'confirm_url' => config('app.cors_url').'/'.$confirmRoute,
					'register_url' => config('app.cors_url').'/'.$registerRoute
				];

				Mail::send('emails.project-new-user-invitation', $data, function($message) {
				    $message->to($this->invitee_email, $this->invitee_name);
				    $message->subject('SWAMP Project Invitation');
				});
			}
		}
	}

	//
	// status changing methods
	//

	public function accept() {
		$this->accept_date = new DateTime();

		// get user by email or username
		//
		if ($this->invitee_email) {
			$invitee = User::getByEmail($this->invitee_email);
		} else if ($this->invitee_username) {
			$invitee = User::getByUsername($this->invitee_username);
		}

		// create new project membership
		//
		$projectMembership = new ProjectMembership([
			'membership_uid' => Guid::create(),
			'project_uid' => $this->project_uid,
			'user_uid' => $invitee->user_uid,
			'admin_flag' => false
		]);
		$projectMembership->save();
	}

	public function decline() {
		$this->decline_date = new DateTime();
	}

	//
	// querying methods
	//

	public function isAccepted() {
		return $this->accept_date != null;
	}

	public function isDeclined() {
		return $this->decline_date != null;
	}

	public function getProject() {
		return Project::where('project_uid', '=', $this->project_uid)->first();
	}

	//
	// validation methods
	//

	public function isValid() {
		$rules = [
			'invitee_name' => 'required|min:1',
			'invitee_email' => 'required_without:invitee_username',
			'invitee_username' => 'required_without:invitee_email'
		];		

		$this->validator = Validator::make($this->getAttributes(), $rules);		

		return $this->validator->passes();
	}

	public function errors() {
		return $this->validator->errors();
	}
}
