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
|        Copyright (C) 2012-2020 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\Viewers;

use Illuminate\Database\Eloquent\Model;
use App\Models\TimeStamps\TimeStamped;

class ProjectDefaultViewer extends TimeStamped
{
	/**
	 * The database connection to use.
	 *
	 * @var string
	 */
	protected $connection = 'viewer_store';

	/**
	 * The table associated with the model.
	 *
	 * @var string
	 */
	protected $table = 'project_default_viewer';

	/**
	 * The primary key associated with the table.
	 *
	 * @var string
	 */
	protected $primaryKey = 'project_uuid';

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
		'project_uuid',
		'viewer_uuid'
	];

	/**
	 * The attributes that should be visible in serialization.
	 *
	 * @var array
	 */
	protected $visible = [
		'project_uuid',
		'viewer_uuid'
	];
}
