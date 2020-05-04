<?php
/******************************************************************************\
|                                                                              |
|                           PlatformsController.php                            |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a controller for platforms.                              |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2020 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Http\Controllers\Platforms;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Session;
use App\Utilities\Uuids\Guid;
use App\Utilities\Filters\DateFilter;
use App\Utilities\Filters\LimitFilter;
use App\Models\Projects\Project;
use App\Models\Platforms\Platform;
use App\Models\Platforms\PlatformVersion;
use App\Models\Platforms\PlatformSharing;
use App\Models\Users\User;
use App\Http\Controllers\BaseController;

class PlatformsController extends BaseController
{
	// create
	//
	public function postCreate(Request $request): Platform {

		// parse parameters
		//
		$name = $request->input('name');
		$platformOwnerUuid = $request->input('platform_owner_uuid');
		$platformSharingStatus = $request->input('platform_sharing_status');

		// create new platform
		//
		$platform = new Platform([
			'platform_uuid' => Guid::create(),
			'name' => $name,
			'platform_owner_uuid' => $platformOwnerUuid,
			'platform_sharing_status' => $platformSharingStatus
		]);
		$platform->save();

		return $platform;
	}
	
	// get by index
	//
	public function getIndex(string $platformUuid): ?Platform {
		return Platform::find($platformUuid);
	}

	// get by user
	//
	public function getByUser(string $userUuid): Collection {
		return Platform::where('platform_owner_uuid', '=', $userUuid)->orderBy('name', 'ASC')->get();
	}

	// get all for admin user
	//
	public function getAll(Request $request): Collection {
		$user = User::current();
		if ($user && $user->isAdmin()) {

			// create query
			//
			$query = Platform::orderBy('create_date', 'DESC');

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

	// get by public scoping
	//
	public function getPublic(Request $request) {

		// create query
		//
		$query = Platform::where('platform_sharing_status', '=', 'public')->orderBy('name', 'ASC');

		// add filters
		//
		$query = DateFilter::apply($request, $query);
		$query = LimitFilter::apply($request, $query);

		// perform query
		//
		return $query->get();
	}

	// get by protected scoping
	//
	public function getProtected(Request $request, string $projectUuid) {
		$platformTable = with(new Platform)->getTable();
		$platformSharingTable = with(new PlatformSharing)->getTable();

		// create query
		//
		$query = PlatformSharing::where('project_uuid', '=', $projectUuid)
			->join($platformTable, $platformSharingTable.'.platform_uuid', '=', $platformTable.'.platform_uuid')
			->orderBy('name', 'ASC');

		// add filters
		//
		$query = DateFilter::apply($request, $query);
		$query = LimitFilter::apply($request, $query);

		// perform query
		//
		return $query->get();
	}

	// get by project
	//
	public function getByProject(Request $request, string $projectUuid) {
		$publicPlatforms = $this->getPublic($request);
		$protectedPlatforms = $this->getProtected($request, $projectUuid);
		return $publicPlatforms->merge($protectedPlatforms);
	}

	// get versions
	//
	public function getVersions(string $platformUuid) {
		$platformVersions = PlatformVersion::where('platform_uuid', '=', $platformUuid)->get();
		foreach( $platformVersions as $p ){
			unset( $p->create_date );
			unset( $p->update_date );
			unset( $p->release_date );
			unset( $p->retire_date );
			unset( $p->notes );
			unset( $p->platform_path );
			unset( $p->checksum );
			unset( $p->invocation_cmd );
			unset( $p->deployment_cmd );
		}
		return $platformVersions;
	}

	// get sharing
	//
	public function getSharing(string $platformUuid) {
		$platformSharing = PlatformSharing::where('platform_uuid', '=', $platformUuid)->get();
		$projectUuids = [];
		for ($i = 0; $i < sizeof($platformSharing); $i++) {
			array_push($projectUuids, $platformSharing[$i]->project_uuid);
		}
		return $projectUuids;
	}

	// update by index
	//
	public function updateIndex(Request $request, string $platformUuid) {

		// parse parameters
		//
		$name = $request->input('name');
		$platformOwnerUuid = $request->input('platform_owner_uuid');
		$platformSharingStatus = $request->input('platform_sharing_status');

		// get model
		//
		$platform = $this->getIndex($platformUuid);

		// update attributes
		//
		$platform->name = $name;
		$platform->platform_owner_uuid = $platformOwnerUuid;
		$platform->platform_sharing_status = $platformSharingStatus;

		// save and return changes
		//
		$changes = $platform->getDirty();
		$platform->save();
		return $changes;
	}

	// update sharing by index
	//
	public function updateSharing(Request $request, string $platformUuid) {

		// parse input
		//
		$projects = $request->input('projects');
		$projectUuids = $request->input('project_uuids');

		// find platform
		//
		$platform = Platform::find($platformUuid);
		if (!$platform) {
			return response('Platform not found.', 404);
		}

		// remove previous sharing
		//
		$platform->unshare();

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
					$sharing[] = $platform->shareWith($project);
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
					$sharing[] = $platform->shareWith($project);
				}
			}
		}

		return $sharing;
	}

	// delete by index
	//
	public function deleteIndex(string $platformUuid) {
		$platform = Platform::where('platform_uuid', '=', $platformUuid)->first();
		$platform->delete();
		return $platform;
	}

	// delete versions
	//
	public function deleteVersions(string $platformUuid) {
		$platformVersions = $this->getVersions($platformUuid);
		for ($i = 0; $i < sizeof($platformVersions); $i++) {
			$platformVersions[$i]->delete();
		}
		return $platformVersions;
	}
}
