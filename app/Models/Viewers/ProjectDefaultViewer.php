<?php
/******************************************************************************\
|                                                                              |
|                           ProjectDefaultViewer.php                           |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a model of the default assessment results viewer         |
|        to use by default with a particular project.                          |
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
use App\Models\TimeStamps\UserStamped;

class ProjectDefaultViewer extends UserStamped {

	// database attributes
	//
	protected $connection = 'viewer_store';
	protected $table = 'project_default_viewer';
	protected $primaryKey = 'project_uuid';	
	public $incrementing = false;

	// mass assignment policy
	//
	protected $fillable = [
		'project_uuid',
		'viewer_uuid'
	];

	// array / json conversion whitelist
	//
	protected $visible = [
		'project_uuid',
		'viewer_uuid'
	];
}
