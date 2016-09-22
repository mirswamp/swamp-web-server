<?php
/******************************************************************************\
|                                                                              |
|                               ViewerVersion.php                              |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a model of an assessment results viewer's version        |
|        information.                                                          |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2016 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\Viewers;

use Illuminate\Database\Eloquent\Model;
use App\Models\TimeStamps\UserStamped;

class ViewerVersion extends UserStamped {

	/**
	 * database attributes
	 */
	protected $connection = 'viewer_store';
	protected $table = 'viewer_version';
	protected $primaryKey = 'viewer_version_uuid';

	/**
	 * mass assignment policy
	 */
	protected $fillable = array(
		'viewer_uuid', 
		'version_no', 
		'version_string', 
		'invocation_cmd', 
		'sign_in_cmd', 
		'add_user_cmd',
		'add_result_cmd',
		'viewer_path',
		'viewer_checksum',
		'viewer_db_path',
		'viewer_db_checksum',
		'viewer_sharing_status'
	);

	/**
	 * array / json conversion whitelist
	 */
	protected $visible = array(
		'name',
		'viewer_uuid',
		'version_string'
	);
}
