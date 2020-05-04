<?php
/******************************************************************************\
|                                                                              |
|                         ToolVersionsController.php                           |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a controller for tool versions.                          |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2020 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Http\Controllers\Tools;

use PDO;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Utilities\Uuids\Guid;
use App\Utilities\Files\Filename;
use App\Models\Tools\ToolVersion;
use App\Http\Controllers\BaseController;

class ToolVersionsController extends BaseController
{
	// create
	//
	public function postCreate(Request $request): ToolVersion {

		// parse parameters
		//
		$toolUuid = $request->input('tool_uuid');
		$commentPublic = $request->input('comment_public');
		$versionString = $request->input('version_string');
		$releaseDate = $request->input('release_date');
		$retireDate = $request->input('retire_date');
		$toolPath = $request->input('tool_path');

		// create new tool version
		//
		$toolVersion = new ToolVersion([
			'tool_version_uuid' => Guid::create(),
			'tool_uuid' => $toolUuid,
			'comment_public' => $commentPublic,
			'version_string' => $versionString,

			'release_date' => $releaseDate,
			'retire_date' => $retireDate,

			'tool_path' => $toolPath
		]);
		$toolVersion->save();

		return $toolVersion;
	}

	// get by index
	//
	public function getIndex(string $toolVersionUuid): ?ToolVersion {
		return ToolVersion::find($toolVersionUuid);
	}

	// update by index
	//
	public function updateIndex(Request $request, string $toolVersionUuid) {

		// parse parameters
		//
		$toolUuid = $request->input('tool_uuid');
		$commentPublic = $request->input('comment_public');
		$versionString = $request->input('version_string');
		$releaseDate = $request->input('release_date');
		$retireDate = $request->input('retire_date');
		$toolPath = $request->input('tool_path');

		// find tool version
		//
		$toolVersion = ToolVersion::find($toolVersionUuid);
		if (!$toolVersion) {
			return response("Tool version not found.", 404);
		}

		// retain existing tool uuid if not specified
		//
		if (!$toolUuid) {
			$toolUuid = $toolVersion->tool_uuid;
		}

		// update attributes
		//
		$toolVersion->tool_version_uuid = $toolVersionUuid;
		$toolVersion->tool_uuid = $toolUuid;
		$toolVersion->comment_public = $commentPublic;
		$toolVersion->version_string = $versionString;

		$toolVersion->release_date = $releaseDate;
		$toolVersion->retire_date = $retireDate;

		$toolVersion->tool_path = $toolPath;

		// save and return changes
		//
		$changes = $toolVersion->getDirty();
		$toolVersion->save();
		return $changes;
	}

	// delete by index
	//
	public function deleteIndex(string $toolVersionUuid) {

		// find tool version
		//
		$toolVersion = ToolVersion::find($toolVersionUuid);
		if (!$toolVersion) {
			return response("Tool version not found.", 404);
		}

		$toolVersion->delete();
		return $toolVersion;
	}
}
