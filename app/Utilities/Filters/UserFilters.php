<?php
/******************************************************************************\
|                                                                              |
|                               UserFilters.php                                |
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

use Illuminate\Support\Collection;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Users\UserAccount;
use App\Utilities\Strings\StringUtils;

class UserFilters
{
	//
	// item filtering methods
	//

	static function filterByName(Request $request, Collection $items): Collection {
		$collection = collect();

		// parse parameters
		//
		$name = $request->input('name');
		if ($name == null || $name == '') {
			return $items;
		}

		foreach ($items as $item) {
			if (StringUtils::contains($item->first_name, $name, false) ||
				StringUtils::contains($item->last_name, $name, false) ||
				StringUtils::contains($item->preferred_name, $name, false)) {
				$collection->push($item);
			}
		}

		return $collection;
	}

	static function filterByUsername(Request $request, Collection $items): Collection {
		$collection = collect();

		// parse parameters
		//
		$username = $request->input('username');
		if ($username == null || $username == '') {
			return $items;
		}

		foreach ($items as $item) {
			if (StringUtils::contains($item->username, $username, false)) {
				$collection->push($item);
			}
		}

		return $collection;
	}

	static function filterByUserType(Request $request, Collection $items): Collection {

		// parse parameters
		//
		$userType = $request->input('type');
		if ($userType == null || $userType == '') {
			return $items;
		}

		// filter users
		//
		$collection = collect();
		foreach ($items as $item) {
			$userAccount = UserAccount::where('user_uid', '=', $item->user_uid)->first();
			if ($userAccount && $userAccount->user_type == $userType) {
				$collection->push($item);
			}
		}

		return $collection;
	}

	static function filterBySignedIn(Collection $items): Collection {
		$collection = collect();
		foreach ($items as $item) {
			if ($item->isSignedIn()) {
				$collection->push($item);
			}
		}

		return $collection;
	}

	static function filterByEnabled(Collection $items): Collection {
		$collection = collect();
		foreach ($items as $item) {
			$userAccount = UserAccount::where('user_uid', '=', $item->user_uid)->first();
			if ($userAccount && $userAccount->isEnabled()) {
				$collection->push($item);
			}
		}

		return $collection;
	}

	//
	// date filtering utilities
	//

	static function filterByAfterDate(Collection $items, ?string $after, string $attributeName) {

		// check filter parameter
		//
		if ($after == null|| $after == '') {
			return $items;
		}

		// filter items
		//
		$collection = collect();
		$afterDate = new \DateTime($after);
		$afterDate->setTime(0, 0);
		foreach ($items as $item) {
			if ($item[$attributeName] != null) {
				$date = new \DateTime($item[$attributeName]);
				if ($date->getTimestamp() >= $afterDate->getTimestamp()) {
					$collection->push($item);
				}
			}
		}

		return $collection;
	}

	static function filterByBeforeDate(Collection $items, ?string $before, string $attributeName) {

		// check filter parameter
		//
		if ($before == null || $before == '') {
			return $items;
		}

		// filter items
		//
		$collection = collect();
		$beforeDate = new \DateTime($before);
		$beforeDate->setTime(0, 0);
		foreach ($items as $item) {
			if ($item[$attributeName] != null) {
				$date = new \DateTime($item[$attributeName]);
				if ($date->getTimestamp() <= $beforeDate->getTimestamp()) {
					$collection->push($item);
				}
			}
		}

		return $collection;
	}

	static function filterByDate(Request $request, Collection $items): Collection {

		// parse parameters
		//
		$after = $request->input('after');
		$before = $request->input('before');

		// perform filtering
		//
		$items = self::filterByAfterDate($items, $after, 'create_date');
		$items = self::filterByBeforeDate($items, $before, 'create_date');
		return $items;
	}

	static function filterByLastLoginDate(Request $request, Collection $items): Collection {

		// parse parameters
		//
		$after = $request->input('login-after');
		$before = $request->input('login-before');

		// perform filtering
		//
		$items = self::filterByAfterDate($items, $after, 'ultimate_login_date');
		$items = self::filterByBeforeDate($items, $before, 'ultimate_login_date');
		return $items;
	}
}
