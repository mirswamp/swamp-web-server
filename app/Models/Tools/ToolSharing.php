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
|        Copyright (C) 2012-2020 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\Tools;

use Illuminate\Support\Collection;
use App\Models\BaseModel;
use App\Models\Tools\Tool;

class ToolSharing extends BaseModel
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
	protected $table = 'tool_sharing';

	/**
	 * The primary key associated with the table.
	 *
	 * @var string
	 */
	protected $primaryKey = 'tool_sharing_id';

	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array
	 */
	protected $fillable = [
		'tool_uuid',
		'project_uuid'
	];

	//
	// relation methods
	//

	public function tool() {
		return $this->belongsTo('Models\Tools\Tool', 'tool_uuid');
	}

	//
	// static methods
	//

	public static function getToolsByProject($projectUuid): Collection {
		$tools = collect();

		// collect tools shared with a single project
		//
		$toolSharings = ToolSharing::where('project_uuid', '=', $projectUuid)->get();
		for ($i = 0; $i < sizeof($toolSharings); $i++) {
			$tool = Tool::where('tool_uuid', '=', $toolSharings[$i]->tool_uuid)->first();
			if ($tool && !$tools->contains($tool)) {
				$tools->push($tool);

				// add to query
				//
				if (!isset($query)) {
					$query = Tool::where('tool_uuid', '=', $tool->tool_uuid);
				} else {
					$query = $query->orWhere('tool_uuid', '=', $tool->tool_uuid);
				}
			}
		}

		// perform query
		//
		if (isset($query)) {
			return $query->get();
		} else {
			return collect();
		}
	}

	public static function getToolsByProjects(string $projectUuid): Collection {
		$tools = collect();

		// collect tools shared with multiple projects
		//
		$projectUuids = explode('+', $projectUuid);
		foreach ($projectUuids as $projectUuid) {
			$toolSharings = ToolSharing::where('project_uuid', '=', $projectUuid)->get();
			for ($i = 0; $i < sizeof($toolSharings); $i++) {
				$tool = Tool::where('tool_uuid', '=', $toolSharings[$i]->tool_uuid)->first();
				if ($tool && !$tools->contains($tool)) {
					$tools->push($tool);

					// add to query
					//
					if (!isset($query)) {
						$query = Tool::where('tool_uuid', '=', $tool->tool_uuid);
					} else {
						$query = $query->orWhere('tool_uuid', '=', $tool->tool_uuid);
					}
				}
			}
		}

		// perform query
		//
		if (isset($query)) {
			return $query->get();
		} else {
			return collect();
		}
	}
}