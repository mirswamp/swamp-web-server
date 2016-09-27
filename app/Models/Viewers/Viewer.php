<?php
/******************************************************************************\
|                                                                              |
|                                  Viewer.php                                  |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a model of an assessment results viewer.                 |
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

class Viewer extends UserStamped {

	/**
	 * database attributes
	 */
	protected $connection = 'viewer_store';
	protected $table = 'viewer';
	protected $primaryKey = 'viewer_uuid';

	/**
	 * mass assignment policy
	 */
	protected $fillable = array(
		'viewer_owner_uuid', 
		'name', 
		'viewer_sharing_status'
	);

	/**
	 * array / json conversion whitelist
	 */
	protected $visible = array(
		'viewer_uuid',
		'name',
		'viewer_sharing_status'
	);

	/**
	 * methods
	 */

	public function getLatestVersion() {
		return ViewerVersion::where('viewer_uuid', '=', $this->viewer_uuid)->orderBy('version_string', 'DESC')->first();	
	}
}