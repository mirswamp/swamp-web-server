<?php
/******************************************************************************\
|                                                                              |
|                         ToolViewerIncompatibility.php                        |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a model that associates tools with incompatible          |
|        viewers (viewers are assumed to be compatible by default).            |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2020 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\Tools;

use App\Models\BaseModel;
use App\Models\Tools\Tool;
use App\Models\Viewers\Viewer;

class ToolViewerIncompatibility extends BaseModel
{
	/**
	 * The database connection to use.
	 *
	 * @var string
	 */
	protected $connection = 'tool_shed';

	/**
	 * The table associated with the model.
	 *
	 * @var string
	 */
	protected $table = 'tool_viewer_incompatibility';

	/**
	 * The primary key associated with the table.
	 *
	 * @var string
	 */
	protected $primaryKey = 'tool_viewer_id';

	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array
	 */
	protected $fillable = [
		'tool_uuid',
		'viewer_uuid'
	];

	/**
	 * The attributes that should be visible in serialization.
	 *
	 * @var array
	 */
	protected $visible = [
		'tool_uuid',
		'viewer_uuid'
	];

	//
	// querying methods
	//

	public function getTool() {
		return Tool::where('tool_uuid', '=', $this->tool_uuid)->first();
	}

	public function getViewer() {
		return Viewer::where('viewer_uuid', '=', $this->viewer_uuid)->first();
	}
}
