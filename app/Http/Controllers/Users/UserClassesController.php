<?php
/******************************************************************************\
|                                                                              |
|                          UserClassesController.php                           |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a controller for policies.                               |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2020 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Http\Controllers\Users;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use App\Models\Users\UserClass;
use App\Models\Users\UserClassMembership;
use App\Http\Controllers\BaseController;
use App\Utilities\Uuids\Guid;

class UserClassesController extends BaseController
{
	//
	// get methods
	//

	public function getAll(Request $request): Collection {
		return UserClass::where(function($query) {
			$now = date('Y-m-d H:i:s');
			$query->where('start_date', '<', $now)->orWhereNull('start_date');
		})->where(function($query) {
			$now = date('Y-m-d H:i:s');
			$query->where('end_date', '>', $now)->orWhereNull('end_date');
		})->get();
	}

	public function getByUser(Request $request, string $userUid): array {

		// get current user
		//
		if ($userUid == 'current') {
			$userUid = session('user_uid');
		}

		$memberships = UserClassMembership::where('user_uid', '=', $userUid)->get();

		// get classes corresponding to class memeberships
		//
		$classes = [];
		for ($i = 0; $i < sizeof($memberships); $i++) {
			$class = UserClass::where('class_code', '=', $memberships[$i]->class_code)->first();
			array_push($classes, $class);
		}
		return $classes;
	}

	public function postByUser(Request $request, string $userUid, string $classCode) {

		// check if membership has already been created
		//
		$membership = UserClassMembership::where('user_uid', '=', $userUid)
			->where('class_code', '=', $classCode)->first();
		if ($membership) {
			return $membership;
		}

		// create new class membership
		//
		$membership = new UserClassMembership([
			'class_user_uuid' => Guid::create(),
			'user_uid' => $userUid,
			'class_code' => $classCode
		]);

		// save new class membership
		//
		$membership->save();

		return $membership;
	}

	public function deleteByUser(Request $request, string $userUid, string $classCode) {
		$membership = UserClassMembership::where('user_uid', '=', $userUid)
			->where('class_code', '=', $classCode)->first();

		// delete class membership
		//		
		$membership->delete();

		return $membership;
	}
}
