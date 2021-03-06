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
|        Copyright (C) 2012-2020 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\Users;

use App\Models\TimeStamps\CreateStamped;
use App\Models\Users\User;

class UserPermission extends CreateStamped
{
	/**
	 * The table associated with the model.
	 *
	 * @var string
	 */
	protected $table = 'user_permission';

	/**
	 * The primary key associated with the table.
	 *
	 * @var string
	 */
	protected $primaryKey = 'user_permission_uid';

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
	];

	/**
	 * The attributes that should be visible in serialization.
	 *
	 * @var array
	 */
	protected $visible = [
		'user_uid',
		'permission_code',
		'auto_approve_flag',
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
	];

	/**
	 * The accessors to append to the model's array form.
	 *
	 * @var array
	 */
	protected $appends = [
		'status',
		'permission',
		'auto_approve_flag',
		'user_full_name'
	];

	//
	// accessor methods
	//

	public function getStatusAttribute() {
		return $this->getStatus();
	}

	public function getPermissionAttribute() {
		$permission = Permission::where('permission_code', '=', $this->permission_code)->first();
		if ($permission) {
			return $permission->description;
		}
	}

	public function getAutoApproveFlagAttribute() {
		$permission = Permission::where('permission_code', '=', $this->permission_code)->first();
		if ($permission) {
			return $permission->auto_approve_flag;
		}
	}

	public function getUserFullNameAttribute() {
		$user = User::getIndex($this->user_uid);
		if ($user) {
			return $user->getFullName();
		}
	}

	//
	// setting methods
	//

	public function setStatus(string $status) {
		switch ($status) {
			case 'revoked':
				$this->delete_date = gmdate('Y-m-d H:i:s');
				$this->expiration_date = null;
				$this->grant_date = null;
				$this->denial_date = null;
			break;
			case 'denied':
				$this->delete_date = null;
				$this->expiration_date = null;
				$this->grant_date = null;
				$this->denial_date = gmdate('Y-m-d H:i:s');
			break;
			case 'granted':
				$this->delete_date = null;
				$this->expiration_date = gmdate('Y-m-d H:i:s', time() + ( 60 * 60 * 24 * 365 ));
				$this->grant_date = gmdate('Y-m-d H:i:s');
				$this->denial_date = null;
			break;
			case 'expired':
				$this->expiration_date = gmdate('Y-m-d H:i:s', time() - 60);
				$this->denial_date = null;
			break;
			case 'pending':
				$this->delete_date = null;
				$this->expiration_date = null;
				$this->grant_date = null;
				$this->denial_date = null;
				$this->request_date = gmdate('Y-m-d H:i:s');
			break;
		}
	}

	//
	// querying methods
	//

	public function isDenied() {
		return $this->denial_date && (gmdate('Y-m-d H:i:s') >= $this->denial_date);
	}

	public function isExpired() {
		return $this->expiration_date && (gmdate('Y-m-d H:i:s') >= $this->expiration_date);
	}

	public function isRevoked() {
		return $this->delete_date && (gmdate('Y-m-d H:i:s') >= $this->delete_date);
	}

	public function isGranted() {
		return $this->grant_date && (gmdate('Y-m-d H:i:s') >= $this->grant_date);
	}

	public function getStatus() {
		if ($this->isDenied()) {
			return 'denied';
		} else if ($this->isExpired()) {
			return 'expired';
		} else if ($this->isRevoked()) {
			return 'revoked';
		} else if ($this->isGranted()) {
			return 'granted';
		} else {
			return 'pending';
		}
	}
}
