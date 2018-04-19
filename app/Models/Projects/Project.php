<?php
/******************************************************************************\
|                                                                              |
|                                  Project.php                                 |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a model of a project.                                    |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2018 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\Projects;

use App\Models\TimeStamps\CreateStamped;
use App\Models\Events\ProjectEvent;
use App\Models\Events\UserProjectEvent;
use App\Models\Users\Owner;
use App\Models\Users\UserPolicy;
use App\Models\Users\Permission;
use App\Models\Users\UserPermission;
use App\Models\Users\UserPermissionProject;
use App\Models\Projects\ProjectMembership;
use App\Models\Projects\ProjectInvitation;

class Project extends CreateStamped {

	// database attributes
	//
	protected $table = 'project';
	protected $primaryKey = 'project_id';

	// mass assignment policy
	//
	protected $fillable = [
		'project_uid', 
		'project_owner_uid', 
		'full_name', 
		'short_name', 
		'description', 
		'affiliation', 
		'trial_project_flag',
		'exclude_public_tools_flag',
		'denial_date',
		'deactivation_date'
	];

	// array / json conversion whitelist
	//
	protected $visible = [
		'project_uid', 
		'full_name', 
		'short_name', 
		'description', 
		'affiliation', 
		'trial_project_flag',
		'exclude_public_tools_flag',
		'denial_date',
		'deactivation_date',
		'owner'
	];

	// array / json appended model attributes
	//
	protected $appends = [
		'owner'
	];

	// attribute types
	//
	protected $casts = [
		'trial_project_flag' => 'boolean',
		'exclude_public_tools_flag' => 'boolean',
		'deactivation_date' => 'datetime'
	];

	//
	// accessor methods
	//

	public function getOwnerAttribute() {
		$owner = Owner::getIndex($this->project_owner_uid);
		if ($owner) {
			return $owner->toArray();
		}
	}

	//
	// querying methods
	//

	public function getEvents() {
		return $this->getEventsQuery()->get();
	}

	public function getEventsQuery() {
		return ProjectEvent::where('project_uid', '=', $this->project_uid);
	}

	public function getUserEvents() {
		return $this->getUserEventsQuery()->get();
	}

	public function getUserEventsQuery() {
		return UserProjectEvent::where('project_uid', '=', $this->project_uid);
	}

	public function isActive() {
		return $this->deactivation_date == null;
	}

	public function getMembership($user) {
		return ProjectMembership::where('user_uid', '=', $user->user_uid)->where('project_uid', '=', $this->project_uid)->first();
	}
	
	public function getMemberships() {
		return ProjectMembership::where('project_uid', '=', $this->project_uid)->get();
	}

	public function getInvitations() {
		return ProjectInvitation::where('project_uid', '=', $this->project_uid)->get();
	}

	public function hasMember($user) {
		return ProjectMembership::where('user_uid', '=', $user->user_uid)
			->where('project_uid', '=', $this->project_uid)
			->where('delete_date', '=', null)
			->exists();
	}

	public function isTrialProject() {
		return $this->trial_project_flag;
	}

	//
	// permission methods
	//

	public function getOwnerPermission($permissionCode) {
		return UserPermission::where('user_uid', '=', $this->owner['user_uid'])->where('permission_code', '=', $permissionCode)->first();
	}

	public function getUserPermissionProject($userPermission) {
		return UserPermissionProject::where('user_permission_uid', '=', $userPermission->user_permission_uid)->where('project_uid', '=', $this->project_uid)->first();
	}

	//
	// access control methods
	//

	public function isOwnedBy($user) {
		return $user && $this->project_owner_uid == $user->user_uid;
	}

	public function isReadableBy($user) {
		if ($user->isAdmin()) {
			return true;
		} else if ($this->isOwnedBy($user)) {
			return true;
		} else if ($user->isMemberOf($this)) {
			return true;
		} else {
			return false;
		}
	}
	
	public function isWriteableBy($user) {
		if ($user->isAdmin()) {
			return true;
		} else if ($this->isOwnedBy($user)) {
			return true;
		}
	}
}
