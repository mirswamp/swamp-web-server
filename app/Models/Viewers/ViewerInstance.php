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
|        Copyright (C) 2012-2018 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\Viewers;

use Illuminate\Database\Eloquent\Model;
use App\Models\BaseModel;

class ViewerInstance extends BaseModel {

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
}
