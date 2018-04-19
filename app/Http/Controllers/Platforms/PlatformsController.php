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
|        Copyright (C) 2012-2018 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Http\Controllers\Platforms;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Session;
use App\Utilities\Uuids\Guid;
use App\Utilities\Filters\DateFilter;
use App\Utilities\Filters\LimitFilter;
use App\Models\Platforms\Platform;
use App\Models\Platforms\PlatformVersion;
use App\Models\Platforms\PlatformSharing;
use App\Models\Users\User;
use App\Http\Controllers\BaseController;

class PlatformsController extends BaseController {

	// create
	//
	public function postCreate() {

		// parse parameters
		//
		$name = Input::get('name');
		$platformOwnerUuid = Input::get('platform_owner_uuid');
		$platformSharingStatus = Input::get('platform_sharing_status');

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
	public function getIndex($platformUuid) {
		$platform = Platform::where('platform_uuid', '=', $platformUuid)->first();
		return $platform;
	}

	// get by user
	//
	public function getByUser($userUuid) {
		$platforms = Platform::where('platform_owner_uuid', '=', $userUuid)->orderBy('name', 'ASC')->get();
		return $platforms;
	}

	// get all for admin user
	//
	public function getAll() {
		$user = User::getIndex(session('user_uid'));
		if ($user && $user->isAdmin()) {
			$platformsQuery = Platform::orderBy('create_date', 'DESC');

			// add filters
			//
			$platformsQuery = DateFilter::apply($platformsQuery);
			$platformsQuery = LimitFilter::apply($platformsQuery);

			return $platformsQuery->get();
		}
		return '';
	}

	// get by public scoping
	//
	public function getPublic() {
		$platformsQuery = Platform::where('platform_sharing_status', '=', 'public')->orderBy('name', 'ASC');

		// add filters
		//
		$platformsQuery = DateFilter::apply($platformsQuery);
		$platformsQuery = LimitFilter::apply($platformsQuery);

		return $platformsQuery->get();
	}

	// get by protected scoping
	//
	public function getProtected($projectUuid) {
		$platformTable = with(new Platform)->getTable();
		$platformSharingTable = with(new PlatformSharing)->getTable();
		$platformsQuery = PlatformSharing::where('project_uuid', '=', $projectUuid)
			->join($platformTable, $platformSharingTable.'.platform_uuid', '=', $platformTable.'.platform_uuid')
			->orderBy('name', 'ASC');

		// add filters
		//
		$platformsQuery = DateFilter::apply($platformsQuery);
		$platformsQuery = LimitFilter::apply($platformsQuery);

		return $platformsQuery->get();
	}

	// get by project
	//
	public function getByProject($projectUuid) {
		$publicPlatforms = $this->getPublic();
		$protectedPlatforms = $this->getProtected($projectUuid);
		return $publicPlatforms->merge($protectedPlatforms);
	}

	// get versions
	//
	public function getVersions($platformUuid) {
		$platformVersions = PlatformVersion::where('platform_uuid', '=', $platformUuid)->get();
		foreach( $platformVersions as $p ){
			unset( $p->create_user );
			unset( $p->update_user );
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
	public function getSharing($platformUuid) {
		$platformSharing = PlatformSharing::where('platform_uuid', '=', $platformUuid)->get();
		$projectUuids = [];
		for ($i = 0; $i < sizeof($platformSharing); $i++) {
			array_push($projectUuids, $platformSharing[$i]->project_uuid);
		}
		return $projectUuids;
	}

	// update by index
	//
	public function updateIndex($platformUuid) {

		// parse parameters
		//
		$name = Input::get('name');
		$platformOwnerUuid = Input::get('platform_owner_uuid');
		$platformSharingStatus = Input::get('platform_sharing_status');

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
	public function updateSharing($platformUuid) {

		// remove previous sharing
		//
		$platformSharings = PlatformSharing::where('platform_uuid', '=', $platformUuid)->get();
		for ($i = 0; $i < sizeof($platformSharings); $i++) {
			$platformSharing = $platformSharings[$i];
			$platformSharing->delete();
		}

		// create new sharing
		//
		$input = Input::get('projects');
		$platformSharings = new Collection;
		for ($i = 0; $i < sizeOf($input); $i++) {
			$project = $input[$i];
			$projectUid = $project['project_uid'];
			$platformSharing = new PlatformSharing([
				'platform_uuid' => $platformUuid,
				'project_uuid' => $projectUid
			]);
			$platformSharing->save();
			$platformSharings->push($platformSharing);
		}
		return $platformSharings;
	}

	// delete by index
	//
	public function deleteIndex($platformUuid) {
		$platform = Platform::where('platform_uuid', '=', $platformUuid)->first();
		$platform->delete();
		return $platform;
	}

	// delete versions
	//
	public function deleteVersions($platformUuid) {
		$platformVersions = $this->getVersions($platformUuid);
		for ($i = 0; $i < sizeof($platformVersions); $i++) {
			$platformVersions[$i]->delete();
		}
		return $platformVersions;
	}
}
