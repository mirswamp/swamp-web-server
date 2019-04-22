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
|        Copyright (C) 2012-2019 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Utilities\Filters;

use Illuminate\Support\Facades\Input;
use App\Models\Tools\Tool;
use App\Models\Tools\ToolVersion;

class ToolFilter
{
	static function apply($query) {

		// parse parameters
		//
		$toolName = Input::get('tool_name', null);
		$toolUuid = Input::get('tool_uuid', null);
		$toolVersion = Input::get('tool_version', null);
		$toolVersionUuid = Input::get('tool_version_uuid', null);

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
