<?php
/******************************************************************************\
|                                                                              |
|                             ToolsController.php                              |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a controller for tools.                                  |
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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\DB;
use App\Utilities\Uuids\Guid;
use App\Utilities\Filters\DateFilter;
use App\Utilities\Filters\LimitFilter;
use App\Models\Users\User;
use App\Models\Tools\Tool;
use App\Models\Packages\Package;
use App\Models\Packages\PackageType;
use App\Models\Projects\Project;
use App\Models\Projects\ProjectMembership;
use App\Models\Tools\ToolVersion;
use App\Models\Tools\ToolLanguage;
use App\Models\Tools\ToolSharing;
use App\Http\Controllers\BaseController;

class ToolsController extends BaseController
{
	// create
	//
	public function postCreate(Request $request): Tool {

		// parse parameters
		//
		$name = $request->input('name');
		$toolOwnerUuid = $request->input('tool_owner_uuid');
		$isBuildNeeded = filter_var($request->input('is_build_needed'), FILTER_VALIDATE_BOOLEAN);
		$toolSharingStatus = $request->input('tool_sharing_status');

		// create new tool
		//
		$tool = new Tool([
			'tool_uuid' => Guid::create(),
			'name' => $name,
			'tool_owner_uuid' => $toolOwnerUuid,
			'is_build_needed' => $isBuildNeeded,
			'tool_sharing_status' => $toolSharingStatus
		]);
		$tool->save();

		return $tool;
	}

	// get all for admin user
	//
	public function getAll(Request $request): Collection {
		$user = User::current();
		if ($user && $user->isAdmin()) {

			// create query
			//
			$query = Tool::orderBy('create_date', 'DESC');

			// add filters
			//
			$query = DateFilter::apply($request, $query);
			$query = LimitFilter::apply($request, $query);

			// perform query
			//
			return $query->get();
		} else {
			return collect();
		}
	}

	// get by index
	//
	public function getIndex(string $toolUuid): ?Tool {
		return Tool::find($toolUuid);
	}

	// get by user
	//
	public function getByUser(Request $request, string $userUuid): Collection {
		$user = User::current();
		if ($user->isAdmin() || (session('user_uid')) == $userUuid) {

			// create query
			//
			$query = Tool::where('tool_owner_uuid', '=', $userUuid)->orderBy('create_date', 'DESC');

			// add filters
			//
			$query = DateFilter::apply($request, $query);
			$query = LimitFilter::apply($request, $query);

			// perform query
			//
			return $query->get();
		} else {
			return collect();
		}
	}

	// get number by user
	//
	public function getNumByUser(Request $request, string $userUuid): int {

		// create query
		//
		$query = Tool::where('tool_owner_uuid', '=', $userUuid);

		// add filters
		//
		$query = DateFilter::apply($request, $query);

		// perform query
		//
		return $query->count();
	}

	// get by public scoping
	//
	public function getPublic(): Collection {
		return Tool::where('tool_sharing_status', '=', 'public')->orderBy('name', 'ASC')->get();
	}

	// get by policy restriction
	//
	public function getRestricted(): Collection {
		return Tool::whereNotNull('policy_code')->orderBy('name', 'ASC')->get();
	}

	// get by protected scoping
	//
	public function getProtected(string $projectUuid): Collection {
		if (!strpos($projectUuid, '+')) {

			// check permissions
			//
			$user = User::current();
			$project = Project::where('project_uid', '=', $projectUuid)->first();
			if ($project && !$project->isReadableBy($user)) {
				return collect();
			}

			// get tools shared with a single project
			//
			return ToolSharing::getToolsByProject($projectUuid);
		} else {

			// check permissions
			//
			$user = User::current();
			$projectUuids = explode('+', $projectUuid);
			foreach ($projectUuids as $projectUuid) {
				$project = Project::where('project_uid', '=', $projectUuid)->first();
				if ($project && !$project->isReadableBy($user)) {
					return collect();
				}
			}

			// get tools shared with a multiple projects
			//
			return ToolSharing::getToolsByProjects($projectUuid);
		}
	}

	// get by project
	//
	public function getByProject(string $projectUuid): Collection {

		// return public and protected tools
		//
		return $this->getPublic()->merge($this->getProtected($projectUuid));
	}

	// get versions
	//
	public function getVersions(Request $request, string $toolUuid) {

		// parse parameters
		//
		$packageTypeName = $request->input('package-type');

		// get tool versioins
		//
		if (!$packageTypeName) {

			// return all tool versions associated with this tool
			//
			$toolVersions = ToolVersion::where('tool_uuid', '=', $toolUuid)->get();
		} else {

			// return tool versions associated with this tool and package type
			//
			$toolVersions = [];
			$packageType = PackageType::where('name', '=', $packageTypeName)->first();
			if ($packageType) {

				// find tool language entries
				//
				$toolLanguages = ToolLanguage::where('tool_uuid', '=', $toolUuid)->where('package_type_id', '=', $packageType->package_type_id)->get();

				// append tool versions associated with this tool language
				//
				for ($i = 0; $i < sizeof($toolLanguages); $i++) {
					$toolLanguageVersion = ToolVersion::where('tool_version_uuid', '=', $toolLanguages[$i]->tool_version_uuid)->first();
					if ($toolLanguageVersion) {
						array_push($toolVersions, $toolLanguageVersion);
					}
				}	
			}
		}

		return $toolVersions;
	}

	// get sharing
	//
	public function getSharing(string $toolUuid) {
		$toolSharing = ToolSharing::where('tool_uuid', '=', $toolUuid)->get();
		$projectUuids = [];
		for ($i = 0; $i < sizeof($toolSharing); $i++) {
			array_push($projectUuids, $toolSharing[$i]->project_uuid);
		}
		return $projectUuids;
	}

	// get policy
	//
	public function getPolicy(string $toolUuid) {
		$tool = Tool::where('tool_uuid', '=', $toolUuid)->first();
		if ($tool) {
			return $tool->getPolicy();
		}
	}

	// get permission status
	//
	public function getToolPermissionStatus(Request $request, string $toolUuid) {

		// parse parameters
		//
		$packageUuid = $request->input('package_uuid', null);
		$projectUid = $request->input('project_uid', null);
		$userUid = $request->input('user_uid', null);

		// fetch models
		//
		$tool = Tool::where('tool_uuid', '=', $toolUuid)->first();
		$package = $packageUuid? Package::where('package_uuid','=', $packageUuid)->first() : null;
		$project = $projectUid? Project::where('project_uid','=', $projectUid)->first() : null;
		$user = $userUid ? User::getIndex($userUid) : User::current();

		// check models
		//
		if (!$tool) {
			return response('Error - tool not found', 404);
		}
		if (!$package) {
			return response('Error - package not found', 404);
		}
		if (!$project) {
			return response('Error - project not found', 404);
		}
		if (!$user) {
			return response('Error - user not found', 404);
		}

		// restricted tools
		//
		if ($tool->isRestricted()) {
			return $tool->getPermissionStatus($project, $user);
		}

		return response()->json([
			'status' => 'approved'
		]);
	}

	// update by index
	//
	public function updateIndex(Request $request, string $toolUuid) {

		// parse parameters
		//
		$name = $request->input('name');
		$toolOwnerUuid = $request->input('tool_owner_uuid');
		$isBuildNeeded = filter_var($request->input('is_build_needed'), FILTER_VALIDATE_BOOLEAN);
		$toolSharingStatus = $request->input('tool_sharing_status');

		// get model
		//
		$tool = $this->getIndex($toolUuid);

		// update attributes
		//
		$tool->name = $name;
		if ($toolOwnerUuid) {
			$tool->tool_owner_uuid = $toolOwnerUuid;	
		}
		$tool->is_build_needed = $isBuildNeeded;
		$tool->tool_sharing_status = $toolSharingStatus;

		// save and return changes
		//
		$changes = $tool->getDirty();
		$tool->save();
		return $changes;
	}

	// update sharing by index
	//
	public function updateSharing(Request $request, string $toolUuid) {

		// parse input
		//
		$projects = $request->input('projects');
		$projectUuids = $request->input('project_uuids');

		// find tool
		//
		$tool = Tool::find($toolUuid);
		if (!$tool) {
			return response('Tool not found.', 404);
		}

		// remove previous sharing
		//
		$tool->unshare();

		// create new sharing
		//
		$sharing = [];

		// create sharing from array of objects
		//
		// Note: this is support for the old way of specifying sharing
		// which is needed for backwards compatibility with API plugins).
		//
		if ($projects) {
			foreach ($projects as $project) {
				$projectUid = $project['project_uid'];

				// find project
				//
				$project = Project::where('project_uid', '=', $projectUid)->first();

				// add sharing
				//
				if ($project) {
					$sharing[] = $tool->shareWith($project);
				}
			}
		} 

		// create sharing from array of project uuids
		//
		if ($projectUuids) {
			foreach ($projectUuids as $projectUuid) {

				// find project
				//
				$project = Project::where('project_uid', '=', $projectUuid)->first();

				// add sharing
				//
				if ($project) {
					$sharing[] = $tool->shareWith($project);
				}
			}
		}

		return $sharing;
	}

	// delete by index
	//
	public function deleteIndex(string $toolUuid) {

		// find tool
		//
		$tool = Tool::find($toolUuid);
		if (!$tool) {
			return response('Tool not found.', 404);
		}

		$tool->delete();
		return $tool;
	}

	// delete versions by index
	//
	public function deleteVersions(string $toolUuid) {

		// find tool
		//
		$tool = Tool::find($toolUuid);
		if (!$tool) {
			return response('Tool not found.', 404);
		}
		
		return $tool->deleteVersions();
	}
}
