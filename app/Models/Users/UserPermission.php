<?php
/******************************************************************************\
|                                                                              |
|                             UserPermission.php                               |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a model of a permission requested by a patciular         |
|        user.                                                                 |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2016 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\Users;

use Illuminate\Support\Facades\Config;
use App\Models\TimeStamps\CreateStamped;
use App\Models\Users\User;

class UserPermission extends CreateStamped {

	/**
	 * database attributes
	 */
	protected $table = 'user_permission';
	protected $primaryKey = 'user_permission_uid';

	/**
	 * mass assignment policy
	 */
	protected $fillable = array(
		'user_uid',
		'user_permission_uid',
		'permission_code',
		'user_comment',
		'admin_comment',
		'request_date',
		'grant_date',
		'denial_date',
		'expiration_date',
		'delete_date',
		'meta_information'
	);

	/**
	 * array / json conversion whitelist
	 */
	protected $visible = array(
		'user_uid',
		'permission_code',
		'user_comment',
		'admin_comment',
		'request_date',
		'grant_date',
		'expiration_date',
		'delete_date',
		'meta_information',
		'status',
		'permission',
		'user_full_name'
	);

	/**
	 * array / json appended model attributes
	 */
	protected $appends = array(
		'status',
		'permission',
		'user_full_name'
	);

	/**
	 * accessor methods
	 */

	public function getStatusAttribute() {
		return $this->getStatus();
	}

	public function getPermissionAttribute() {
		$permission = Permission::where('permission_code', '=', $this->permission_code)->first();
		if ($permission) {
			return $permission->description;
		}
	}

	public function getUserFullNameAttribute() {
		$user = User::getIndex($this->user_uid);
		if ($user) {
			return $user->getFullName();
		}
	}

	/**
	 * querying methods
	 */

	public function getStatus() {
		if( $this->denial_date && ( gmdate('Y-m-d H:i:s') > $this->denial_date ) ){
			return 'denied';
		}
		else if( $this->expiration_date && ( gmdate('Y-m-d H:i:s') > $this->expiration_date ) ){
			return 'expired';
		}
		else if( $this->delete_date && ( gmdate('Y-m-d H:i:s') > $this->delete_date ) ){
			return 'revoked';
		}
		else if( $this->grant_date && ( gmdate('Y-m-d H:i:s') > $this->grant_date ) ){
			return 'granted';
		}
		else {
			return 'pending';
		}
	}
}
