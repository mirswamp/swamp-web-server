<?php
/******************************************************************************\
|                                                                              |
|                                ToolFilter.php                                |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a utility for filtering tools.                           |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        Copyright (C) 2012-2016 SWAMP - Software Assurance Marketplace        |
|        Morgridge Institute for Research                                      |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2020 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Utilities\Filters;

use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Tools\Tool;
use App\Models\Tools\ToolVersion;

class ToolFilter
{
	static function apply(Request $request, Builder $query) {

		// parse parameters
		//
		$toolName = $request->input('tool_name', null);
		$toolUuid = $request->input('tool_uuid', null);
		$toolVersion = $request->input('tool_version', null);
		$toolVersionUuid = $request->input('tool_version_uuid', null);

		// add tool to query
		//
		if ($toolName) {
			$query = $query->where('tool_name', '=', $toolName);
		}
		if ($toolUuid) {
			$query = $query->where('tool_uuid', '=', $toolUuid);
		}

		// add tool version to query
		//
		if ($toolVersion == 'latest') {
			$query = $query->whereNull('tool_version_uuid');
		} else if ($toolVersion) {
			$query = $query->where('tool_version_uuid', '=', $toolVersion);
		}
		if ($toolVersionUuid == 'latest') {
			$tool = Tool::where('tool_uuid', '=', $toolVersionUuid)->first();
			$query = $query->whereNull('tool_version_uuid');
		} else if ($toolVersionUuid) {
			$query = $query->where('tool_version_uuid', '=', $toolVersionUuid);
		}
		
		return $query;
	}
}
