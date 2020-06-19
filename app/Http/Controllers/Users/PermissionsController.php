<?php
/******************************************************************************\
|                                                                              |
|                          PermissionsController.php                           |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a controller for permissions.                            |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2020 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Http\Controllers\Users;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\DB;
use App\Utilities\Uuids\Guid;
use App\Models\Projects\Project;
use App\Models\Users\Policy;
use App\Models\Users\User;
use App\Models\Users\UserAccount;
use App\Models\Users\UserPolicy;
use App\Models\Users\UserPermission;
use App\Models\Users\UserPermissionProject;
use App\Models\Users\UserPermissionPackage;
use App\Models\Users\Permission;
use App\Http\Controllers\BaseController;

class PermissionsController extends BaseController
{
	// get projects by id
	//
	public function getPermissions(Request $request, $userUid) {
		$currentUser = User::current();
		$user = User::getIndex($userUid);
		$permissions = Permission::orderBy('title', 'ASC')->get();
		$userPermissions = UserPermission::where('user_uid', '=', $userUid)->get();
		$results = [];

		foreach ($permissions as $permission) {

			// Only show admin assigned permissions to users to already have them
			// Always show to admins
			//
			if (!$currentUser->isAdmin() && $permission->admin_only_flag) {
				$userOwned = false;
				foreach ($userPermissions as $userPermission) {
					if ($userPermission->permission_code == $permission->permission_code) {
						$userOwned = true;
					}
				}
				if (!$userOwned && !$user->isAdmin()) {
					continue;
				}
			}

			$item = [];
			$item['user_uid'] = $userUid;
			$item['permission_code'] = $permission->permission_code;
			$item['auto_approve_flag'] = $permission->auto_approve_flag;
			$item['title'] = $permission->title;
			$item['description'] = $permission->description;
			$item['user_info'] = $permission->user_info;
			$item['user_info_policy_text'] = $permission->user_info_policy_text;
			$item['policy_code'] = $permission->policy_code;
			$item['status'] = null;

			foreach ($userPermissions as $userPermission) {
				if ($userPermission->permission_code == $permission->permission_code) {
					$item['user_permission_uid'] = $userPermission->user_permission_uid;
					$item['auto_approve_flag'] = $permission->auto_approve_flag;
					$item['expiration_date'] = $userPermission->expiration_date;
					$item['user_comment'] = $userPermission->user_comment;
					$item['admin_comment'] = $userPermission->admin_comment;
					$item['meta_information'] = $userPermission->meta_information;
					$item['status'] = $userPermission->getStatus();
				}
			}

			array_push($results, $item);
		}
		
		// log the permissions request event
		//
		Log::info("Get permissions for user.", [
			'requested_user_uid' => $userUid,
		]);

		return $results;
	}

	public function lookupPermission(Request $request, string $userUid, string $permissionCode): ?UserPermission {
		return UserPermission::where('user_uid', '=', $userUid)->where('permission_code', '=', $permissionCode)->first();
	}

	public function requestPermission(Request $request, string $userUid, string $permissionCode): UserPermission {

		// Lookup relevant data
		//
		$currentUser = User::current();
		$user = User::getIndex($userUid);
		$permissions = Permission::all();
		$permission = Permission::where('permission_code', '=', $permissionCode)->first();

		// check for valid permission
		//
		if (!$permission) {
			return response('No permission found corresponding to permission code.', 404);
		}

		// find valid permissions - requests for permissions that 
		// the user already owns or do not exist should flag an error.
		//
		$validPermissions = [];
		foreach ($permissions as $permission) {
			$validPermissions[] = $permission->permission_code;
		}

		// report errors
		//
		if (!in_array($permissionCode, $validPermissions)) {
			return response('Invalid permission code detected.', 400);
		}
		if (UserPermission::where('user_uid', '=', $userUid)->where('permission_code', '=', $permissionCode)->first()) {
			return response('Permission entry already exists.', 400);
		}

		$userPermission = new UserPermission([
			'user_permission_uid' => Guid::create(),
			'user_uid' => $userUid,
			'permission_code' => $permissionCode,
			'request_date' => gmdate('Y-m-d H:i:s')
		]);
		$userPermission->save();

		// send notification to admins that permission has been requested
		//
		if (!$currentUser->isAdmin() && !$permission->isAutoApprove() && config('mail.enabled')) {
			$admins = UserAccount::where('admin_flag', '=', 1)->get();
			foreach ($admins as $admin) {
				$admin = User::getIndex($admin->user_uid);
				if ($admin && $admin->email && filter_var($admin->email, FILTER_VALIDATE_EMAIL)) {
					Mail::send('emails.permission-request', [
						'new_permissions' => [$permissionCode],
						'updated_permissions' => [],
						'meta_information' => false,
						'url' => config('app.cors_url') ?: '',
						'comment' => '',
						'user' => $user
					], function($message) use ($admin) {
						$message->to($admin->email, $admin->getFullName());
						$message->subject('SWAMP Permission Request');
					});
				}
			}
		}

		// log the permissions request event
		//
		Log::info("Request permission for user with permission code.", [
			'requested_user_uid' => $userUid,
			'user_permission_uid' => $userPermission->user_permission_uid,
			'permission_code' => $permissionCode,
		]);

		return $userPermission;
	}

	public function requestPermissions(Request $request, string $userUid) {

		// parse parameters
		//
		$permissionCode = $request->input('permission_code');
		$title = $request->input('title');
		$comment = $request->input('comment');

		// Lookup relevant data
		//
		$currentUser = User::current();
		$user = User::getIndex($userUid);
		$permissions = Permission::all();
		$userPermissions = UserPermission::where('user_uid', '=', $userUid)->get();
		$permission = Permission::where('permission_code', '=', $permissionCode)->first();
		$notifyAdmins = false;

		// check for valid permission
		//
		if (!$permission) {
			return response('No permission found corresponding to permission code.', 404);
		}

		// Permission classification holders
		//
		$newPermissions = [];
		$updatedPermissions = [];

		// find valid permissions - requests for permissions that 
		// the user already owns or do not exist should flag an error.
		//
		$validPermissions = [];
		foreach ($permissions as $item) {
			$validPermissions[] = $item->permission_code;
		}

		// report errors
		//
		if (!in_array($permissionCode, $validPermissions)) {
			return response('Invalid permission code detected.', 400);
		}

		$userPermission = null;
		foreach ($userPermissions as $item) {
			if ($item->permission_code == $permissionCode) {
				$userPermission = $item;
				break;
			}
		}

		// check if user permission exists
		//
		if (!$userPermission) {

			// create new user permission
			//
			$userPermission = new UserPermission([
				'user_permission_uid' => Guid::create(),
				'user_uid' => $userUid,
				'permission_code' => $permissionCode,
				'request_date' => gmdate('Y-m-d H:i:s'),
				'user_comment' => !$currentUser->isAdmin()? $comment : null,
				'admin_comment' => $currentUser->isAdmin()? $comment : null
			]);

			if ($currentUser->isAdmin()) {
				$userPermission->setStatus('granted');
			}

			if ($meta = $this->getMetaFields($request)) {
				$userPermission->meta_information = $meta;
			}

			$userPermission->save();
			$newPermissions[] = $title;
			$notifyAdmins = !$currentUser->isAdmin() && !$permission->isAutoApprove();

		// we found an existing entry and update the information
		//
		} else {
			if ($userPermission->status == 'denied') {
				return response('You may not request denied permissions.  Please contact SWAMP support staff if you feel permissions have been denied in error.', 400);
			}
			if ($meta = $this->getMetaFields($request)) {
				$userPermission->meta_information = $meta;
			}

			$userPermission->request_date = gmdate('Y-m-d H:i:s');
			$userPermission->user_comment = $comment;
			$userPermission->save();
			$updatedPermissions[] = $title;
			$notifyAdmins = !$currentUser->isAdmin();
		}

		// send notification email to user that permission was requested
		//
		if ($currentUser->isAdmin() && config('mail.enabled')) {
			if ($user && $user->email && filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
				Mail::send('emails.permission-granted', [
					'status' => $userPermission->status,
					'new_permissions' => $newPermissions,
					'url' => config('app.cors_url') ?: '',
					'comment' => $comment,
					'meta_information' => json_decode($userPermission->meta_information, true),
					'user' => $user
				], function($message) use ($user, $userPermission) {
					$message->to($user->email, $user->getFullName());
					$message->subject('SWAMP Permission '. ucwords($userPermission->status));
				});
			}
		}

		// send notification to admins that permission has been requested
		//
		if ($notifyAdmins && config('mail.enabled')) {
			$admins = UserAccount::where('admin_flag', '=', 1)->get();
			foreach ($admins as $admin) {
				$admin = User::getIndex($admin->user_uid);
				if ($admin && $admin->email && filter_var($admin->email, FILTER_VALIDATE_EMAIL)) {
					Mail::send('emails.permission-request', [
						'new_permissions' => $newPermissions,
						'updated_permissions' => $updatedPermissions,
						'url' => config('app.cors_url') ?: '',
						'comment' => $comment,
						'meta_information' => json_decode($userPermission->meta_information, true),
						'user' => $user
					], function($message) use ($admin) {
						$message->to($admin->email, $admin->getFullName());
						$message->subject('SWAMP Permission Request');
					});
				}
			}
		}

		// log the permissions request event
		//
		Log::info("Request permissions for user.", [
			'requested_user_uid' => $userUid,
			'user_permission_uid' => $userPermission->user_permission_uid,
			'permission_code' => $permissionCode,
			'new_permissions' => !empty($newPermissions)? $newPermissions[0] : null,
			'updated_permissions' => !empty($updatedPermissions)? $updatedPermissions[0] : null
		]);

		// record accepted policy
		//
		if (!$currentUser->isAdmin()) {
			$permission = Permission::where('permission_code', '=', $permissionCode)->first();
			if ($permission->policy_code) {

				// get user policy
				//
				$userPolicy = UserPolicy::where('user_uid','=',$user->user_uid)->where('policy_code','=',$permission->policy_code)->first();

				// create new user policy, if necessary
				//
				if (!$userPolicy) {
					$userPolicy = new UserPolicy([
						'user_policy_uid' => Guid::create(),
						'user_uid' => $user->user_uid,
						'policy_code' => $permission->policy_code
					]);
				}

				$userPolicy->accept_flag = 1;
				$userPolicy->save();
			}
		}
	}

	public function getPending(Request $request): Collection {
		return UserPermission::whereNull('grant_date')
			->whereNull('denial_date')
			->whereNull('delete_date')
			->whereNull('expiration_date')
			->get();
	}

	public function getNumPending(Request $request): int {
		return UserPermission::whereNull('grant_date')
			->whereNull('denial_date')
			->whereNull('delete_date')
			->whereNull('expiration_date')
			->count();
	}

	public function setPermissions(Request $request, string $userUid) {

		// parse parameters
		//
		$permissionCode = $request->input('permission_code', null);
		$comment = $request->input('comment', null);
		$status = $request->input('status', null);

		// lookup relevant data
		//
		$currentUser = User::current();
		if (!$currentUser->isAdmin()) {
			return response('Non administrators may not alter permissions!', 401);
		}
		$user = User::getIndex($userUid);
		$permissions = Permission::all();
		$userPermissions = UserPermission::where('user_uid', '=', $userUid)->get();
		$permission = Permission::where('permission_code', '=', $permissionCode)->first();

		// check for valid permission
		//
		if (!$permission) {
			return response('No permission found corresponding to permission code.', 404);
		}

		// find valid permissions - requests for permissions that 
		// the user already owns or do not exist should flag an error.
		//
		$validPermissions = [];
		foreach ($permissions as $item) {
			$validPermissions[] = $item->permission_code;
		}
		if (!in_array($permissionCode, $validPermissions)) {
			return response('Invalid permission code detected.', 400);
		}
		$userPermission = null;
		foreach ($userPermissions as $item) {
			if ($item->permission_code == $permissionCode) {
				$userPermission = $item;
				break;
			}
		}

		// check if new user permission must be created
		//
		if (!$userPermission) {
			$userPermission = new UserPermission([
				'user_permission_uid' => Guid::create(),
				'user_uid' => $userUid,
				'permission_code' => $permissionCode,
				'request_date' => gmdate('Y-m-d H:i:s'),
				'update_date' => gmdate('Y-m-d H:i:s'),
				'admin_comment' => $comment
			]);

		// update existing user permission
		//
		} else {
			$userPermission->admin_comment = $comment;
		}

		// status application
		//
		$userPermission->setStatus($status);
		$userPermission->save();

		// send notification email that permission was set
		//
		if (config('mail.enabled')) {
			if ($user && $user->email && filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
				Mail::send('emails.permission-reviewed', [
					'url' => config('app.cors_url') ?: '',
					'user' => $user,
					'comment' => $comment
				], function($message) use ($user) {
					$message->to($user->email, $user->getFullName());
					$message->subject('SWAMP Permission Request');
				});
			}
		}

		// log the permissions event
		//
		Log::info("Set permissions.", [
			'status' => $status,
			'requested_user_uid' => $userUid,
			'user_permission_uid' => $userPermission->user_permission_uid,
			'permission_code' => $userPermission->permission_code,
		]);
	}

	public function deletePermission(Request $request, string $userPermissionUid) {

		// get models
		//
		$currentUser = User::current();
		$userPermission = UserPermission::where('user_permission_uid', '=', $userPermissionUid)->first();
		$user = User::getIndex($userPermission->user_uid);

		if (($user->user_uid == $currentUser->user_uid ) || $currentUser->isAdmin()) {
			$userPermission->delete_date = gmdate('Y-m-d H:i:s');
			$userPermission->expiration_date = null;
			$userPermission->save();

			// log the permissions delete event
			//
			Log::info("Delete permission.", [
				'user_permission_uid' => $userPermissionUid,
			]);

			return $userPermission;
		} else {
			return response('Unable to revoke this permission.  Insufficient privileges.', 400);
		}
	}

	public function designateProject(Request $request, string $userPermissionUid, string $projectUid) {

		// get models
		//
		$userPermission = UserPermission::where('user_permission_uid','=',$userPermissionUid)->first();
		$project = Project::where('project_uid','=',$projectUid)->first();
		$user = User::current();

		// check for valid permissions
		//
		if (!($userPermission && $project && $user)) {
			return response('Unable to find permission information.', 404);
		}
		if (!$user->isAdmin() && ($user->user_uid != $project->owner['user_uid'])) {
			return response('User does not have permission to designate a project.', 401);
		}

		// create new user permission project
		//
		$userPermissionProject = new UserPermissionProject([
			'user_permission_project_uid' => Guid::create(),
			'user_permission_uid' => $userPermissionUid,
			'project_uid' => $projectUid
		]);
		$userPermissionProject->save();

		// log the designate project event
		//
		Log::info("Designate project.", [
			'user_permission_uid' => $userPermissionUid,
			'project_uid' => $projectUid,
		]);

		return $userPermissionProject;
	}

	//
	// private utility methods
	//

	private function getMetaFields(Request $request) {
		$meta_fields = ['user_type', 'name', 'email', 'organization', 'project_url'];
		$input_has_meta = false;
		$found = [];
		foreach ($meta_fields as $field) {
			if ($request->has($field)) {
				$found[$field] = $request->input($field);
			}
		}
		return sizeof($found) > 0 ? json_encode($found) : false;
	}
}
