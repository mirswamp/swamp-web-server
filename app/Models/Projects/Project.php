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
|        Copyright (C) 2012-2020 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\Projects;

use Illuminate\Support\Collection;
use App\Models\TimeStamps\CreateStamped;
use App\Models\Events\ProjectEvent;
use App\Models\Events\UserProjectEvent;
use App\Models\Users\User;
use App\Models\Users\Owner;
use App\Models\Users\UserPolicy;
use App\Models\Users\Permission;
use App\Models\Users\UserPermission;
use App\Models\Users\UserPermissionProject;
use App\Models\Projects\ProjectMembership;
use App\Models\Projects\ProjectInvitation;

class Project extends CreateStamped
{
	/**
	 * The table associated with the model.
	 *
	 * @var string
	 */
	protected $table = 'project';

	/**
	 * The primary key associated with the table.
	 *
	 * @var string
	 */
	protected $primaryKey = 'project_uid';

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
		'project_uid', 
		'project_owner_uid', 
		'full_name', 
		'description', 
		'affiliation', 
		'trial_project_flag',
		'exclude_public_tools_flag',
		'denial_date',
		'deactivation_date'
	];

	/**
	 * The attributes that should be visible in serialization.
	 *
	 * @var array
	 */
	protected $visible = [
		'project_uid', 
		'project_owner_uid', 
		'full_name', 
		'description', 
		'affiliation', 
		'trial_project_flag',
		'exclude_public_tools_flag',
		'denial_date',
		'deactivation_date',
		'owner'
	];

	/**
	 * The accessors to append to the model's array form.
	 *
	 * @var array
	 */
	protected $appends = [
		'owner'
	];

	/**
	 * The attributes that should be cast to native types.
	 *
	 * @var array
	 */
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

	public function getEvents(): Collection {
		return $this->getEventsQuery()->get();
	}

	public function getEventsQuery() {
		return ProjectEvent::where('project_uid', '=', $this->project_uid);
	}

	public function getUserEvents(): Collection {
		return $this->getUserEventsQuery()->get();
	}

	public function getUserEventsQuery() {
		return UserProjectEvent::where('project_uid', '=', $this->project_uid);
	}

	public function isActive(): bool {
		return $this->deactivation_date == null;
	}

	public function getMembership(User $user): ?ProjectMembership {
		return ProjectMembership::where('user_uid', '=', $user->user_uid)->where('project_uid', '=', $this->project_uid)->first();
	}
	
	public function getMemberships(): Collection {
		return ProjectMembership::where('project_uid', '=', $this->project_uid)->get();
	}

	public function getInvitations(): Collection {
		return ProjectInvitation::where('project_uid', '=', $this->project_uid)->get();
	}

	public function hasMember(User $user): bool {
		return ProjectMembership::where('user_uid', '=', $user->user_uid)
			->where('project_uid', '=', $this->project_uid)
			->where('delete_date', '=', null)
			->exists();
	}

	public function isTrialProject(): bool {
		return $this->trial_project_flag;
	}

	//
	// permission methods
	//

	public function getOwnerPermission(string $permissionCode): ?UserPermission {
		return UserPermission::where('user_uid', '=', $this->owner['user_uid'])->where('permission_code', '=', $permissionCode)->first();
	}

	public function getUserPermissionProject(UserPermission $userPermission): ?UserPermissionProject {
		return UserPermissionProject::where('user_permission_uid', '=', $userPermission->user_permission_uid)->where('project_uid', '=', $this->project_uid)->first();
	}

	//
	// access control methods
	//

	public function isOwnedBy(User $user): bool {
		return $user && $this->project_owner_uid == $user->user_uid;
	}

	public function isReadableBy(User $user): bool {
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
	
	public function isWriteableBy(User $user): bool {
		if ($user->isAdmin()) {
			return true;
		} else if ($this->isOwnedBy($user)) {
			return true;
		}
	}
}