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
|        Copyright (C) 2012-2019 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Http\Controllers\Tools;

use PDO;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Response;
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
	public function postCreate() {

		// parse parameters
		//
		$name = Input::get('name');
		$toolOwnerUuid = Input::get('tool_owner_uuid');
		$isBuildNeeded = filter_var(Input::get('is_build_needed'), FILTER_VALIDATE_BOOLEAN);
		$toolSharingStatus = Input::get('tool_sharing_status');

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
	public function getAll() {
		$user = User::getIndex(session('user_uid'));
		if ($user && $user->isAdmin()) {
			$toolsQuery = Tool::orderBy('create_date', 'DESC');

			// add filters
			//
			$toolsQuery = DateFilter::apply($toolsQuery);
			$toolsQuery = LimitFilter::apply($toolsQuery);

			return $toolsQuery->get();
		}
		return '';
	}

	// get by index
	//
	public function getIndex($toolUuid) {
		$tool = Tool::where('tool_uuid', '=', $toolUuid)->first();
		return $tool;
	}

	// get by user
	//
	public function getByUser($userUuid) {

		// create stored procedure call
		//
		/*
		$connection = DB::connection('tool_shed');
		$pdo = $connection->getPdo();

		$stmt = $pdo->prepare("CALL list_tools_by_owner(:userUuidIn, @returnString)");
		$stmt->bindParam(':userUuidIn', $userUuid, PDO::PARAM_STR, 45);
		$stmt->execute();
		$results = [];

		do {
			foreach( $stmt->fetchAll( PDO::FETCH_ASSOC ) as $row )
				$results[] = $row;
		} while ($stmt->nextRowset());

		$select = $pdo->query('SELECT @returnString');
		$returnString = $select->fetchAll( PDO::FETCH_ASSOC )[0]['@returnString'];
		$select->nextRowset();

		if ($returnString == 'SUCCESS') {
			return $results;
		} else {
			return response( $returnString, 500 );
		}
		*/
		
		$user = User::getIndex(session('user_uid'));


		if ($user->isAdmin() || (session('user_uid')) == $userUuid) {
			$toolsQuery = Tool::where('tool_owner_uuid', '=', $userUuid)->orderBy('create_date', 'DESC');
		}
		else {
			return response('Cannot access given user.', 400);
		}

		// add filters
		//
		$toolsQuery = DateFilter::apply($toolsQuery);
		$toolsQuery = LimitFilter::apply($toolsQuery);

		return $toolsQuery->get();
	}

	// get number by user
	//
	public function getNumByUser($userUuid) {

		// create SQL query
		//
		$toolsQuery = Tool::where('tool_owner_uuid', '=', $userUuid);

		// add filters
		//
		$toolsQuery = DateFilter::apply($toolsQuery);

		// perform query
		//
		return $toolsQuery->count();
	}

	// get by public scoping
	//
	public function getPublic() {
		return Tool::where('tool_sharing_status', '=', 'public')->orderBy('name', 'ASC')->get();
	}

	// get by policy restriction
	//
	public function getRestricted() {
		return Tool::whereNotNull('policy_code')->orderBy('name', 'ASC')->get();
	}

	// get by protected scoping
	//
	public function getProtected($projectUuid) {
		if (!strpos($projectUuid, '+')) {

			// check permissions
			//
			$user = User::getIndex(session('user_uid'));
			$project = Project::where('project_uid', '=', $projectUuid)->first();
			if ($project && !$project->isReadableBy($user)) {
				return response('Cannot access given project.', 400);
			}

			// get tools shared with a single project
			//
			return ToolSharing::getToolsByProject($projectUuid);
		} else {

			// check permissions
			//
			$user = User::getIndex(session('user_uid'));
			$projectUuids = explode('+', $projectUuid);
			foreach ($projectUuids as $projectUuid) {
				$project = Project::where('project_uid', '=', $projectUuid)->first();
				if ($project && !$project->isReadableBy($user)) {
					return response('Cannot access given project.', 400);
				}
			}

			// get tools shared with a multiple projects
			//
			return ToolSharing::getToolsByProjects($projectUuid);
		}
	}

	// get by project
	//
	public function getByProject($projectUuid) {
		/*
		$userUid = session('user_uid');
		$connection = DB::connection('tool_shed');
		$pdo = $connection->getPdo();
		$stmt = $pdo->prepare("CALL list_tools_by_project_user(:userUid, :projectUuid, @returnString);");
		$stmt->bindParam(':userUid', $userUid, PDO::PARAM_STR, 45);
		$stmt->bindParam(':projectUuid', $projectUuid, PDO::PARAM_STR, 45);
		$stmt->execute();
		$results = [];

		do {
		    foreach( $stmt->fetchAll( PDO::FETCH_ASSOC ) as $row ){
				unset( $row['notes'] );
				$results[] = $row;
			}
		} while ($stmt->nextRowset());

		$select = $pdo->query('SELECT @returnString;');
		$returnString = $select->fetchAll( PDO::FETCH_ASSOC )[0]['@returnString'];
		$select->nextRowset();

		if ($returnString == 'SUCCESS') {
		    return $results;
		} else {
		    return response( $returnString, 500 );
		}
		*/
		    
		$publicTools = $this->getPublic();
		$protectedTools = $this->getProtected($projectUuid);
		return $publicTools->merge($protectedTools);
	}

	// get versions
	//
	public function getVersions($toolUuid) {

		// parse parameters
		//
		$packageTypeName = Input::get('package-type');

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
	public function getSharing($toolUuid) {
		$toolSharing = ToolSharing::where('tool_uuid', '=', $toolUuid)->get();
		$projectUuids = [];
		for ($i = 0; $i < sizeof($toolSharing); $i++) {
			array_push($projectUuids, $toolSharing[$i]->project_uuid);
		}
		return $projectUuids;
	}

	// get policy
	//
	public function getPolicy($toolUuid) {
		$tool = Tool::where('tool_uuid', '=', $toolUuid)->first();
		if ($tool) {
			return $tool->getPolicy();
		}
	}

	// get permission status
	//
	public function getToolPermissionStatus($toolUuid) {

		// parse parameters
		//
		$packageUuid = Input::get('package_uuid', null);
		$projectUid = Input::get('project_uid', null);
		$userUid = Input::get('user_uid', null);

		// fetch models
		//
		$tool = Tool::where('tool_uuid', '=', $toolUuid)->first();
		$package = $packageUuid? Package::where('package_uuid','=', $packageUuid)->first() : null;
		$project = $projectUid? Project::where('project_uid','=', $projectUid)->first() : null;
		$user = $userUid ? User::getIndex($userUid) : User::getIndex(session('user_uid'));

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
			'success', true
		]);
	}

	// update by index
	//
	public function updateIndex($toolUuid) {

		// parse parameters
		//
		$name = Input::get('name');
		$toolOwnerUuid = Input::get('tool_owner_uuid');
		$isBuildNeeded = filter_var(Input::get('is_build_needed'), FILTER_VALIDATE_BOOLEAN);
		$toolSharingStatus = Input::get('tool_sharing_status');

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
	public function updateSharing($toolUuid) {

		// parse parameters
		//
		$projects = Input::get('projects');

		// remove previous sharing
		//
		$toolSharings = ToolSharing::where('tool_uuid', '=', $toolUuid)->get();
		for ($i = 0; $i < sizeof($toolSharings); $i++) {
			$toolSharing = $toolSharings[$i];
			$toolSharing->delete();
		}

		// create new sharing
		//
		$input = $projects;
		$toolSharings = new Collection;
		for ($i = 0; $i < sizeOf($input); $i++) {
			$project = $input[$i];
			$projectUid = $project['project_uid'];
			$toolSharing = new ToolSharing([
				'tool_uuid' => $toolUuid,
				'project_uuid' => $projectUid
			]);
			$toolSharing->save();
			$toolSharings->push($toolSharing);
		}
		return $toolSharings;
	}

	// delete by index
	//
	public function deleteIndex($toolUuid) {
		$tool = Tool::where('tool_uuid', '=', $toolUuid)->first();
		$tool->delete();
		return $tool;
	}

	// delete versions
	//
	public function deleteVersions($toolUuid) {
		$toolVersions = $this->getVersions($toolUuid);
		for ($i = 0; $i < sizeof($toolVersions); $i++) {
			$toolVersions[$i]->delete();
		}
		return $toolVersions;
	}
}
