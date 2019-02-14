<?php
/******************************************************************************\
|                                                                              |
|                              ViewerInstance.php                              |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a model of an instance of an assessment viewer.          |
|        When viewers are launched, they run within a virtual machine          |
|        and so each viewer instance has information specific to the           |
|        particular virtual machine that was used to launch and run it.        |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2019 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\Viewers;

use Illuminate\Database\Eloquent\Model;
use App\Models\BaseModel;

class ViewerInstance extends BaseModel {

    // viewer status codes
    // reconcile with VIEWER_STATE* values in vmu_ViewerSupport.pm
	//
    const VIEWER_STATE_NO_RECORD        = 0;
    const VIEWER_STATE_LAUNCHING        = 1;
    const VIEWER_STATE_READY            = 2;
    const VIEWER_STATE_STOPPING         = -1; 
    const VIEWER_STATE_SHUTDOWN         = -3; 
    const VIEWER_STATE_ERROR            = -5; 
    const VIEWER_STATE_TERMINATING      = -6; 
    const VIEWER_STATE_TERMINATED       = -7; 
    const VIEWER_STATE_TERMINATE_FAILED = -8; 

	// database attributes
	//
	protected $connection = 'viewer_store';
	protected $table = 'viewer_instance';
	protected $primaryKey = 'viewer_instance_uuid';
	public $incrementing = false;

	// mass assignment policy
	//
	protected $fillable = [
		'viewer_instance_uuid',
		'viewer_version_uuid',
		'project_uuid',
		'reference_count',
		'viewer_db_path',
		'viewer_db_checksum',
		'api_key',
		'vm_ip_address',
		'proxy_url',
		'create_user',
		'create_date',
		'update_user',
		'update_date'
	];

	// static function for use in SWAMPStatus.php
	//
	public static function state_to_name($state) {
		switch ($state) {
			case 0:
			return "no record";
			break;
			case 1:
			return "launching";
			break;
			case 2:
			return "ready";
			break;
			case -1:
			return "stopping";
			break;
			case -3:
			return "shutdown";
			break;
			case -5:
			return "error";
			break;
			case -6:
			return "terminating";
			break;
			case -7:
			return "terminated";
			break;
			case -8:
			return "terminate failed";
			break;
		}
	}

	//
	// querying methods
	//

	// convert state value to text
	//
	public function stateToName() {
		return self::state_to_name($this->state);
	}

	// current viewer vm has been launched and is on its way up - not yet ready
	//
	public function isLaunching() {
		$state = intval($this->state);
		return ($state == self::VIEWER_STATE_LAUNCHING);
	}

	// current viewer vm has been launched and is ready for redirect
	//
	public function isReady() {
		$state = intval($this->state);
		return (
			(
				$state == self::VIEWER_STATE_READY || 
				$state == self::VIEWER_STATE_TERMINATE_FAILED
			) && 
			$this->proxy_url
		);
	}

	// previous viewer vm is in shutdown or termination and blocks current viewer vm
	//
	public function isBlocked() {
		$state = intval($this->state);
		return (
			$state == self::VIEWER_STATE_STOPPING ||
			$state == self::VIEWER_STATE_ERROR ||
			$state == self::VIEWER_STATE_TERMINATING
		);
	}

	// previous viewer vm is being terminated
	//
	public function isBeingTerminated() {
		$state = intval($this->state);
		return (
			$state == self::VIEWER_STATE_TERMINATING ||
			$state == self::VIEWER_STATE_TERMINATED
		);
	}

	// there is no current or previous viewer vm extant
	//
	public function isOKToLaunch() {
		$state = intval($this->state);
		return (
			$state == self::VIEWER_STATE_NO_RECORD ||
			$state == self::VIEWER_STATE_SHUTDOWN ||
			$state == self::VIEWER_STATE_TERMINATED
		);
	}

	// current viewer launch has timed out
	//
	public function hasTimedOut() {
		$state = intval($this->state);
		return ($state == self::VIEWER_STATE_NO_RECORD);
	}
}
