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
|        Copyright (C) 2012-2020 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\Viewers;

use Illuminate\Database\Eloquent\Model;
use App\Models\TimeStamps\TimeStamped;

class Viewer extends TimeStamped
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
	protected $table = 'viewer';

	/**
	 * The primary key associated with the table.
	 *
	 * @var string
	 */
	protected $primaryKey = 'viewer_uuid';

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
		'viewer_owner_uuid', 
		'name', 
		'viewer_sharing_status'
	];

	/**
	 * The attributes that should be visible in serialization.
	 *
	 * @var array
	 */
	protected $visible = [
		'viewer_uuid',
		'name',
		'viewer_sharing_status'
	];

	//
	// querying methods
	//

	public function getLatestVersion() {
		return ViewerVersion::where('viewer_uuid', '=', $this->viewer_uuid)->orderBy('version_string', 'DESC')->first();	
	}
}
