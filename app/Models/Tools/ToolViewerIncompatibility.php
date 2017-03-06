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
|        Copyright (C) 2012-2017 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\Tools;

use App\Models\BaseModel;
use App\Models\Tools\Tool;
use App\Models\Viewers\Viewer;

class ToolViewerIncompatibility extends BaseModel {

	/**
	 * database attributes
	 */
	protected $connection = 'tool_shed';
	protected $table = 'tool_viewer_incompatibility';
	protected $primaryKey = 'tool_viewer_id';

	/**
	 * mass assignment policy
	 */
	protected $fillable = array(
		'tool_uuid',
		'viewer_uuid'
	);

	/**
	 * array / json conversion whitelist
	 */
	protected $visible = array(
		'tool_uuid',
		'viewer_uuid'
	);

	/**
	 * querying methods
	 */

	public function getTool() {
		return Tool::where('tool_uuid', '=', $this->tool_uuid)->first();
	}

	public function getViewer() {
		return Viewer::where('viewer_uuid', '=', $this->viewer_uuid)->first();
	}
}
