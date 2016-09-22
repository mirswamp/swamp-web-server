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
|        Copyright (C) 2012-2016 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Http\Controllers\Users;

use PDO;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Config;
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

class PermissionsController extends BaseController {

	// get projects by id
	//
	public function getPermissions($userUid) {
		$active_user = User::getIndex(Session::get('user_uid'));
		$user = User::getIndex($userUid);
		$permissions = Permission::all();
		$user_permissions = UserPermission::where('user_uid', '=', $userUid)->get();
		$results = array();
		foreach( $permissions as $p ){

			// Only show admin assigned permissions to users to already have them
			// Always show to admins
			//
			if( ! $active_user->isAdmin() && $p->admin_only_flag ){
				$user_owned = false;
				foreach( $user_permissions as $up )
					if( $up->permission_code == $p->permission_code )
						$user_owned = true;
				if( ! $user_owned && ! $user->isAdmin() )
					continue;
			}

			$item = array();
			$item['user_uid'] = $userUid;
			$item['permission_code'] = $p->permission_code;
			$item['title'] = $p->title;
			$item['description'] = $p->description;
			$item['policy_code'] = $p->policy_code;
			$item['status'] = null;
			foreach( $user_permissions as $up ){
				if( $up->permission_code == $p->permission_code ){
					$item['user_permission_uid'] = $up->user_permission_uid;
					$item['expiration_date'] = $up->expiration_date;
					$item['user_comment'] = $up->user_comment;
					$item['admin_comment'] = $up->admin_comment;
					$item['meta_information'] = $up->meta_information;
					$item['status'] = $up->getStatus();
				}
			}
			array_push( $results, $item );
		}
		return $results;
	}

	public function lookupPermission($userUid, $permissionCode){
		return UserPermission::where('user_uid', '=', $userUid)->where('permission_code', '=', $permissionCode)->first();
	}

	public function requestPermission($userUid, $permissionCode ){

		// Lookup relevant data
		//
		$active_user = User::getIndex(Session::get('user_uid'));
		$user = User::getIndex($userUid);
		$permissions = Permission::all();

		$valid_permissions = [];
		foreach( $permissions as $p )
			$valid_permissions[] = $p->permission_code;
		if( ! in_array($permissionCode, $valid_permissions ) )
			return response('Invalid permission code detected.', 400);

		if( UserPermission::where('user_uid', '=', $userUid)->where('permission_code','=',$permissionCode)->first() )
			return response('Permission entry already exists.', 400);

		$perm = new UserPermission(array(
			'user_permission_uid' => Guid::create(),
			'user_uid' => $userUid,
			'permission_code' => $permissionCode,
			'request_date' => gmdate('Y-m-d H:i:s'),
			'user_comment' => ''
		));
		$perm->save();

		// send notification to admins that permission has been requested
		//
		if (Config::get('mail.enabled')) {
			$admins = UserAccount::where('admin_flag', '=', 1)->get();
			foreach( $admins as $admin ){
				$admin = User::getIndex($admin->user_uid);
				if( $admin && $admin->email && $admin->getFullName() ){

					$cfg = array(
						'new_permissions' => array( $permissionCode ),
						'updated_permissions' => array(),
						'meta_information' => false,
						'url' => Config::get('app.cors_url') ?: '',
						'comment' => '',
						'user' => $user
					);

					Mail::send('emails.permission-request', $cfg, function($message) use ($admin) {
						$message->to($admin->email, $admin->getFullName());
						$message->subject('SWAMP Permission Request');
					});
				}
			}
		}

		return $perm;
	}

	public function requestPermissions($userUid) {

		// Lookup relevant data
		//
		$active_user = User::getIndex(Session::get('user_uid'));
		$user = User::getIndex($userUid);
		$permissions = Permission::all();
		$user_permissions = UserPermission::where('user_uid', '=', $userUid)->get();

		// Permission classification holders
		//
		$new_permissions = array();
		$updated_permissions = array();

		// Requests for permissions the user already owns or do not exist should flag an error
		//
		$valid_permissions = [];
		foreach( $permissions as $p )
			$valid_permissions[] = $p->permission_code;

		if( ! in_array(Input::get('permission_code'), $valid_permissions ) ){
			return response('Invalid permission code detected.', 400);
		}

		$record = false;
		foreach( $user_permissions as $up ){
			if( $up->permission_code == Input::get('permission_code') ){
				$record = $up;
				break;
			}
		}

		// an existing entry did for the permission did not exist for the user
		//
		if( ! $record ){
			$record = new UserPermission(array(
				'user_permission_uid' => Guid::create(),
				'user_uid' => $userUid,
				'permission_code' => Input::get('permission_code'),
				'request_date' => gmdate('Y-m-d H:i:s'),
				'user_comment' => Input::get('comment')
			));

			if( $meta = $this->getMetaFields() ){
				$record->meta_information = $meta;
			}

			$record->save();
			$new_permissions[] = Input::get('title');

		// we found an existing entry and update the information
		//
		} else {

			if( $record->status == 'denied' )
				return response('You may not request denied permissions.  Please contact SWAMP support staff if you feel permissions have been denied in error.', 400);

			if( $meta = $this->getMetaFields() ){
				$record->meta_information = $meta;
			}

			$record->request_date = gmdate('Y-m-d H:i:s');
			$record->user_comment = Input::get('comment');
			$record->save();
			$updated_permissions[] = Input::get('title');
		}

		// send notification to admins that permission has been requested
		//
		if (Config::get('mail.enabled')) {
			$admins = UserAccount::where('admin_flag', '=', 1)->get();
			foreach( $admins as $admin ){
				$admin = User::getIndex($admin->user_uid);
				if( $admin && $admin->email && $admin->getFullName() ){

					$cfg = array(
						'new_permissions' => $new_permissions,
						'updated_permissions' => $updated_permissions,
						'url' => Config::get('app.cors_url') ?: '',
						'comment' => Input::get('comment'),
						'meta_information' => json_decode( $record->meta_information, true ),
						'user' => $user
					);

					Mail::send('emails.permission-request', $cfg, function($message) use ($admin) {
						$message->to($admin->email, $admin->getFullName());
						$message->subject('SWAMP Permission Request');
					});
				}
			}
		}

		// record accepted policy
		//
		$permission = Permission::where('permission_code','=',Input::get('permission_code'))->first();
		if( $permission->policy_code ){
			$up = UserPolicy::where('user_uid','=',$user->user_uid)->where('policy_code','=',$permission->policy_code)->first();
			if( ! $up ){
				$up = new UserPolicy(array(
					'user_policy_uid' => Guid::create(),
					'user_uid' => $user->user_uid,
					'policy_code' => $permission->policy_code
				));
			}
			$up->accept_flag = 1;
			$up->save();
		}
	}

	private function getMetaFields(){
		$meta_fields = array('user_type', 'name', 'email', 'organization', 'project_url');
		$input_has_meta = false;
		$found = array();
		foreach( $meta_fields as $field ){
			if( Input::has($field) ){
				$found[$field] = Input::get( $field );
			}
		}
		return sizeof( $found ) > 0 ? json_encode( $found ) : false;
	}

	public function getPending() {
		return UserPermission::whereNull('grant_date')
			->whereNull('denial_date')
			->whereNull('delete_date')
			->whereNull('expiration_date')
			->get();
	}

	public function getNumPending() {
		return UserPermission::whereNull('grant_date')
			->whereNull('denial_date')
			->whereNull('delete_date')
			->whereNull('expiration_date')
			->count();
	}

	public function setPermissions($userUid) {

		// lookup relevant data
		//
		$active_user = User::getIndex(Session::get('user_uid'));
		if (!$active_user->isAdmin()) {
			return response('Non administrators may not alter permissions!', 401);
		}
		$user = User::getIndex($userUid);
		$permissions = Permission::all();
		$user_permissions = UserPermission::where('user_uid', '=', $userUid)->get();

		// requests for permissions the user already owns or do not exist should flag an error
		//
		$valid_permissions = [];
		foreach ($permissions as $p) {
			$valid_permissions[] = $p->permission_code;
		}
		if (!in_array(Input::get('permission_code'), $valid_permissions)) {
			return response('Invalid permission code detected.', 400);
		}
		$record = false;
		foreach($user_permissions as $up) {
			if( $up->permission_code == Input::get('permission_code') ){
				$record = $up;
				break;
			}
		}

		// an existing entry did for the permission did not exist for the user
		//
		if (Input::has('status')) {
			if (!$record) {
				$record = new UserPermission(array(
					'user_permission_uid' => Guid::create(),
					'user_uid' => $userUid,
					'permission_code' => Input::get('permission_code'),
					'request_date' => gmdate('Y-m-d H:i:s'),
					'update_date' => gmdate('Y-m-d H:i:s'),
					'admin_comment' => Input::get('comment')
				));

			// we found an existing entry and update the information
			//
			} else {
				$record->request_date = gmdate('Y-m-d H:i:s');
				$record->delete_date = null;
				$record->admin_comment = Input::get('comment');
			}

			// status application
			//
			switch (Input::get('status')) {
				case 'revoked':
					$record->delete_date = gmdate('Y-m-d H:i:s');
					$record->expiration_date = null;
					$record->grant_date = null;
					$record->denial_date = null;
				break;
				case 'denied':
					$record->delete_date = null;
					$record->expiration_date = null;
					$record->grant_date = null;
					$record->denial_date = gmdate('Y-m-d H:i:s');
				break;
				case 'granted':
					$record->delete_date = null;
					$record->expiration_date = gmdate('Y-m-d H:i:s', time() + ( 60 * 60 * 24 * 365 ));
					$record->grant_date = gmdate('Y-m-d H:i:s');
					$record->denial_date = null;
				break;
				case 'expired':
					$record->expiration_date = gmdate('Y-m-d H:i:s', time() - 60);
					$record->denial_date = null;
				break;
				case 'pending':
					$record->delete_date = null;
					$record->expiration_date = null;
					$record->grant_date = null;
					$record->denial_date = null;
					$record->request_date = gmdate('Y-m-d H:i:s');
				break;
			}

			// status application
			//
			$record->save();
		}

		// send notification email that permission was requested
		//
		if (Config::get('mail.enabled')) {
			if ($user && $user->email && $user->getFullName()) {
				$data = array(
					'url' => Config::get('app.cors_url') ?: '',
					'user' => $user,
					'comment' => Input::get('comment')
				);
				Mail::send('emails.permission-reviewed', $data, function($message) use ($user) {
					$message->to($user->email, $user->getFullName());
					$message->subject('SWAMP Permission Request');
				});
			}
		}
	}

	public function deletePermission( $userPermissionUid ){

		$active_user = User::getIndex(Session::get('user_uid'));
		$user_permission = UserPermission::where('user_permission_uid', '=', $userPermissionUid)->first();
		$user = User::getIndex($user_permission->user_uid);

		if( ( $user->user_uid == $active_user->user_uid ) || $active_user->isAdmin() ){
			$user_permission->delete_date = gmdate('Y-m-d H:i:s');
			$user_permission->expiration_date = null;
			$user_permission->save();
			return response('The user permission has been deleted.', 204);
		} else {
			return response('Unable to revoke this permission.  Insufficient privileges.', 400);
		}

	}

	public function designateProject( $userPermissionUid, $projectUid ){

		$up = UserPermission::where('user_permission_uid','=',$userPermissionUid)->first();
		$p = Project::where('project_uid','=',$projectUid)->first();
		$user = User::getIndex(Session::get('user_uid'));

		if( ! ( $up && $p && $user ) ){
			return response('Unable to find permission information.', 404);
		}

		if( ! $user->isAdmin() && ( $user->user_uid != $p->owner['user_uid'] ) ){
			return response('User does not have permission to designate a project.', 401);
		}

		$upp = new UserPermissionProject(array(
			'user_permission_project_uid' => Guid::create(),
			'user_permission_uid' => $userPermissionUid,
			'project_uid' => $projectUid
		));
		$upp->save();

		return $upp;

	}

	public function designatePackage( $userPermissionUid, $packageUid ){
	}
}
