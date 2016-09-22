<?php
/******************************************************************************\
|                                                                              |
|                                ToolSharing.php                               |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a model that associates a tool with a project.           |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2016 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\Tools;

use Illuminate\Support\Collection;
use App\Models\BaseModel;
use App\Models\Tools\Tool;

class ToolSharing extends BaseModel {

	/**
	 * database attributes
	 */
	protected $connection = 'tool_shed';
	protected $table = 'tool_sharing';
	protected $primaryKey = 'tool_sharing_id';

	/**
	 * mass assignment policy
	 */
	protected $fillable = array(
		'tool_uuid',
		'project_uuid'
	);

	/**
	 * relations
	 */
	public function tool() {
		return $this->belongsTo('Models\Tools\Tool', 'tool_uuid');
	}

	/**
	 * static methods
	 */

	public static function getToolsByProject($projectUuid) {
		$tools = new Collection;

		// collect tools shared with a single project
		//
		$toolSharings = ToolSharing::where('project_uuid', '=', $projectUuid)->get();
		for ($i = 0; $i < sizeof($toolSharings); $i++) {
			$tool = Tool::where('tool_uuid', '=', $toolSharings[$i]->tool_uuid)->first();
			if ($tool && !$tools->contains($tool)) {
				$tools->push($tool);

				// add to tools query
				//
				if (!isset($toolsQuery)) {
					$toolsQuery = Tool::where('tool_uuid', '=', $tool->tool_uuid);
				} else {
					$toolsQuery = $toolsQuery->orWhere('tool_uuid', '=', $tool->tool_uuid);
				}
			}
		}

		// perform query
		//
		if (isset($toolsQuery)) {
			return $toolsQuery->get();
		} else {
			return array();
		}
	}

	public static function getToolsByProjects($projectUuid) {
		$tools = new Collection;

		// collect tools shared with multiple projects
		//
		$projectUuids = explode('+', $projectUuid);
		foreach ($projectUuids as $projectUuid) {
			$toolSharings = ToolSharing::where('project_uuid', '=', $projectUuid)->get();
			for ($i = 0; $i < sizeof($toolSharings); $i++) {
				$tool = Tool::where('tool_uuid', '=', $toolSharings[$i]->tool_uuid)->first();
				if ($tool && !$tools->contains($tool)) {
					$tools->push($tool);

					// add to tools query
					//
					if (!isset($toolsQuery)) {
						$toolsQuery = Tool::where('tool_uuid', '=', $tool->tool_uuid);
					} else {
						$toolsQuery = $toolsQuery->orWhere('tool_uuid', '=', $tool->tool_uuid);
					}
				}
			}
		}

		// perform query
		//
		if (isset($toolsQuery)) {
			return $toolsQuery->get();
		} else {
			return array();
		}
	}
}