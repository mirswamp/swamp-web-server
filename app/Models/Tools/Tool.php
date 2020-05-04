<?php
/******************************************************************************\
|                                                                              |
|                                   Tool.php                                   |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a model of an assessment tool.                           |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2020 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\Tools;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Session;
use App\Models\TimeStamps\TimeStamped;
use App\Models\Users\User;
use App\Models\Users\UserPolicy;
use App\Models\Users\Permission;
use App\Models\Users\UserPermission;
use App\Models\Users\Owner;
use App\Models\Projects\Project;
use App\Models\Projects\ProjectMembership;
use App\Models\Tools\ToolVersion;
use App\Models\Tools\ToolLanguage;
use App\Models\Tools\ToolPlatform;
use App\Models\Tools\ToolSharing;
use App\Models\Tools\ToolViewerIncompatibility;
use App\Models\Policies\Policy;
use App\Models\Viewers\Viewer;

class Tool extends TimeStamped
{
	/**
	 * The database connection to use.
	 *
	 * @var string
	 */
	protected $connection = 'tool_shed';

	/**
	 * The table associated with the model.
	 *
	 * @var string
	 */
	protected $table = 'tool';

	/**
	 * The primary key associated with the table.
	 *
	 * @var string
	 */
	protected $primaryKey = 'tool_uuid';

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
		'tool_uuid',
		'tool_owner_uuid',
		'name',
		'description',
		'is_build_needed',
		'tool_sharing_status'
	];

	/**
	 * The attributes that should be visible in serialization.
	 *
	 * @var array
	 */
	protected $visible = [
		'tool_uuid',
		'name',
		'description',
		'is_build_needed',
		'package_type_names',
		'version_strings',
		'platform_names',
		'viewer_names',
		'tool_sharing_status',
		'is_owned',
		'is_restricted',
		'policy_code',
		'policy'
	];

	/**
	 * The accessors to append to the model's array form.
	 *
	 * @var array
	 */
	protected $appends = [
		'package_type_names',
		'version_strings',
		'platform_names',
		'viewer_names',
		'is_owned',
		'is_restricted'
	];

	/**
	 * The attributes that should be cast to native types.
	 *
	 * @var array
	 */
	protected $casts = [
		'is_build_needed' => 'boolean',
		'is_owned' => 'boolean',
		'is_restricted' => 'boolean'
	];

	/**
	 * The tool names that are restricted (commercial).
	 *
	 * @var array
	 */
	protected $restrictedTools = [
		'parasoft',
		'codesonar'
	];

	/**
	 * Which permissions model to use.
	 *
	 * @const bool
	 */
	const ALLOW_PROJECT_OWNER_PERMISSION = false;

	//
	// accessor methods
	//

	public function getPackageTypeNamesAttribute() {
		$names = [];
		$toolLanguages = ToolLanguage::where('tool_uuid', '=', $this->tool_uuid)->get();
		for ($i = 0; $i < sizeOf($toolLanguages); $i++) {
			$name = $toolLanguages[$i]->package_type_name;
			if (!in_array($name, $names)) {
				array_push($names, $name);
			}
		}
		return $names;
	}

	public function getVersionStringsAttribute() {
		$versionStrings = [];
		$toolVersions = ToolVersion::where('tool_uuid', '=', $this->tool_uuid)->get();
		for ($i = 0; $i < sizeOf($toolVersions); $i++) {
			$versionString = $toolVersions[$i]->version_string;
			if (!in_array($versionString, $versionStrings)) {
				array_push($versionStrings, $versionString);
			}
		}
		rsort($versionStrings);
		return $versionStrings;
	}

	public function getPlatformNamesAttribute() {
		$platformNames = [];
		$toolPlatforms = ToolPlatform::where('tool_uuid', '=', $this->tool_uuid)->get();
		for ($i = 0; $i < sizeOf($toolPlatforms); $i++) {
			if ($toolPlatforms[$i]->platform) {
				$platformName = $toolPlatforms[$i]->platform->name;
				if (!in_array($platformName, $platformNames)) {
					array_push($platformNames, $platformName);
				}
			}
		}
		return $platformNames;
	}

	public function getViewerNamesAttribute() {
		$viewerNames = [];
		$viewers = Viewer::all();
		for ($i = 0; $i < sizeOf($viewers); $i++) {
			$viewer = $viewers[$i];
			if ($this->isCompatibleWith($viewer)) {
				if (!in_array($viewer->name, $viewerNames)) {
					array_push($viewerNames, $viewer->name);
				}
			}
		}
		return $viewerNames;
	}

	public function getToolOwnerAttribute() {

		// check to see if user is logged in
		//
		$user = User::current();
		if ($user) {

			// fetch owner information
			//
			$owner = Owner::getIndex($this->tool_owner_uuid);
			if ($owner) {
				return $owner->toArray();
			}
		}
	}

	public function getIsOwnedAttribute() {
		return session('user_uid') == $this->tool_owner_uuid;
	}

	public function getIsRestrictedAttribute() {
		return $this->isRestricted();
	}

	//
	// querying methods
	//

	public function getVersions(): Collection {
		return ToolVersion::where('tool_uuid', '=', $this->tool_uuid)->get();
	}

	public function getLatestVersion(): ?ToolVersion {
		return ToolVersion::where('tool_uuid', '=', $this->tool_uuid)->
			orderBy('version_no', 'DESC')->first();
	}

	public function getPolicy(): ?string {
		return Policy::where('policy_code', '=', $this->policy_code)->first()->policy;
	}

	public function supports(string $packageType): bool {
		return in_array($packageType, $this->package_type_names);
	}

	public function isCompatibleWith(Viewer $viewer): bool {
		return !ToolViewerIncompatibility::where('tool_uuid', '=', $this->tool_uuid)
			->where('viewer_uuid', '=', $viewer->viewer_uuid)->exists();
	}

	//
	// sharing querying methods
	//

	public function isPublic(): bool {
		return strcasecmp($this->getSharingStatus(), 'public') == 0;
	}

	public function isProtected(): bool {
		return strcasecmp($this->getSharingStatus(), 'protected') == 0;
	}

	public function isPrivate(): bool {
		return strcasecmp($this->getSharingStatus(), 'private') == 0;
	}

	public function getSharingStatus(): string {
		return strtolower($this->tool_sharing_status);
	}

	public function isSharedWith(Project $project): bool {
		return ToolSharing::where('project_uuid', '=', $project->project_uid)
			->where('tool_uuid', '=', $this->tool_uuid)->count() != 0;
	}

	public function isSharedBy(User $user): bool {
		foreach ($user->getProjects() as $project) {
			if ($this->isSharedWith($project)) {
				return true;
			}
		}
		return false;	
	}

	//
	// sharing methods
	//

	public function unshare() {
		ToolSharing::where('tool_uuid', '=', $this->tool_uuid)->delete();
	}

	public function shareWith($project): ToolSharing {
		if (!$this->isSharedWith($project)) {
			$sharing = new ToolSharing([
				'platform_uuid' => $this->platform_uuid,
				'project_uuid' => $project->project_uid
			]);
			$sharing->save();
			return $sharing;
		} else {
			return $this->getSharedWith($project);
		}
	}

	//
	// access control methods
	//

	public function isOwnedBy(User $user): bool {
		return ($this->tool_owner_uuid == $user->user_uid);
	}

	public function isReadableBy(User $user): bool {
		if ($this->isPublic() || ($this->isProtected() && $this->isRestricted())) {
			return true;
		} else if ($user && $user->isAdmin()) {
			return true;
		} else if ($user && $this->isOwnedBy($user)) {
			return true;
		} else if ($user && $this->isSharedBy($user)) {
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
		} else {
			return false;
		}
	}

	//
	// restricted tool methods
	//

	public function isRestricted(): bool {
		return $this->policy_code != null;		
	}

	public function isRestrictedByProject(): bool {

		// check for tool's name in list of restricted tool names
		//
		foreach ($this->restrictedTools as $value) {
			if (stripos($this->name, $value) !== false) {
				return true;
			}
		}

		return false;
	}

	public function isRestrictedByProjectOwner(): bool {

		// check for tool's name in list of restricted tool names
		//
		foreach ($this->restrictedTools as $value) {
			if (stripos($this->name, $value) !== false) {
				return true;
			}
		}
		
		return false;
	}

	public function getPermissionCode(): ?string {
		$permission = Permission::where('policy_code', '=', $this->policy_code)->first();
		if ($permission) {
			return $permission->permission_code;
		} else {
			return null;
		}
	}

	public function getProjectOwnerPermission(Project $project, User $user): string {
		$permissionCode = $this->getPermissionCode();
		$userPermission = $user->getPermission($permissionCode);

		// check user permission
		//
		if (!$userPermission || ($userPermission->status !== 'granted')) {
			return 'no_permission';
		}

		// check if user permission is bound to this project
		//
		if ($this->isRestrictedByProject()) {
			if (!$project->getUserPermissionProject($userPermission)) {
				return 'project_unbound';
			}
		}

		// return user policy permission
		//
		return $user->getPolicyPermission($permissionCode, $userPermission);
	}

	public function getProjectMemberPermission(Project $project, User $user): string {
		$permissionCode = $this->getPermissionCode();
		$ownerPermission = $project->getOwnerPermission($permissionCode);

		// check that current user is a project member
		//
		if (!$project->getMembership($user)) {
			return 'no_project_membership';
		}

		// check project owner permission
		//
		if ($this->isRestrictedByProjectOwner()) {
			if (!$ownerPermission || ($ownerPermission->status !== 'granted')) {
				return 'owner_no_permission';
			}
		}

		// check if user permission is bound to this project
		//
		if ($this->isRestrictedByProject()) {
			if (!$project->getUserPermissionProject($ownerPermission)) {
				return 'member_project_unbound';
			}
		}

		// return user policy permission
		//
		return $user->getPolicyPermission($permissionCode, $ownerPermission);
	}

	public function getPermission(Project $project, User $user): string {
		if (self::ALLOW_PROJECT_OWNER_PERMISSION) {

			// no project provided
			//
			if (!$project) {
				return 'no_project';
			}

			if ($project->isOwnedBy($user)) {

				// user is the project owner
				//
				return $this->getProjectOwnerPermission($project, $user);
			} else {

				// user is not the project owner
				//
				return $this->getProjectMemberPermission($project, $user);
			}
		} else {
			return $this->getPolicyPermission($user);
		}
	}

	public function getUserPolicy(User $user): ?UserPolicy {
		if ($user) {
			return $user->getPolicy($this->policy_code);
		}
		return null;
	}

	public function getPolicyPermission(User $user): string {
		$permissionCode = $this->getPermissionCode();
		$ownerPermission = null;
		return $user->getPolicyPermission($permissionCode, $ownerPermission);
	}

	public function getProjectOwnerPermissionStatus(Project $project, User $user) {
		$permissionCode = $this->getPermissionCode();
		if (!$permissionCode) {
			return response('Error - no permission code.', 500);
		}

		// get user permission from permission code
		//
		$userPermission = $user->getPermission($permissionCode);

		// check user permission
		//
		if (!$userPermission || ($userPermission->status !== 'granted')) {
			return response()->json([
				'status' => 'no_permission'
			], 401);
		}

		// check if user permission is bound to this project
		//
		if ($this->isRestrictedByProject()) {
			if (!$project->getUserPermissionProject($userPermission)) {
				return response()->json([
					'status' => 'project_unbound',
					'user_permission_uid' => $userPermission->user_permission_uid
				], 404);
			}
		}

		// return user policy permission status
		//
		return $user->getPolicyPermissionStatus($permissionCode, $userPermission);
	}

	public function getProjectMemberPermissionStatus(Project $project, User $user) {
		$permissionCode = $this->getPermissionCode();
		$ownerPermission = $project->getOwnerPermission($permissionCode);

		// check that current user is a project member
		//
		if (!$project->getMembership($user)) {
			return response()->json([
				'status' => 'no_project_membership'
			], 401);
		}

		// check owner permission
		//
		if ($this->isRestrictedByProjectOwner()) {
			if (!$ownerPermission || ($ownerPermission->status !== 'granted')) {
				return response()->json([
					'status' => 'owner_no_permission'
				], 401);
			}
		}

		// check if user permission is bound to this project
		//
		if ($this->isRestrictedByProject()) {
			if (!$project->getUserPermissionProject($ownerPermission)) {
				return response()->json([
					'status' => 'member_project_unbound',
					'user_permission_uid' => $ownerPermission->user_permission_uid
				], 404);
			}
		}

		// return user policy permission status
		//
		return $user->getPolicyPermissionStatus($permissionCode, $ownerPermission);
	}

	public function getPermissionStatus(Project $project, User $user) {
		if (self::ALLOW_PROJECT_OWNER_PERMISSION) {

			// no project provided
			//
			if (!$project) {
				return response()->json([
					'status' => 'no_project'
				], 404);
			}

			if ($project->isOwnedBy($user)) {

				// user is the project owner
				//
				return $this->getProjectOwnerPermissionStatus($project, $user);
			} else {

				// user is not the project owner
				//
				return $this->getProjectMemberPermissionStatus($project, $user);
			}
		} else {
			
			// get user permission from permission code
			//
			$permissionCode = $this->getPermissionCode();
			$userPermission = $user->getPermission($permissionCode);

			// check user permission
			//
			if (!$userPermission || ($userPermission->status !== 'granted')) {
				return response()->json([
					'status' => 'no_permission',
					'permission_code' => $permissionCode
				], 401);
			}

			// return user policy permission status
			//
			$ownerPermission = null;
			return $user->getPolicyPermissionStatus($permissionCode, $ownerPermission);
		}
	}

	//
	// deleting methods
	//

	public function deleteVersions() {
		$versions = $this->getVersions();
		for ($i = 0; $i < count($versions); $i++) {
			$versions[$i]->delete();
		}
		return $versions;
	}
}
