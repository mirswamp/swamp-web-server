<?php
/******************************************************************************\
|                                                                              |
|                              EventDateFilter.php                             |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a utility for filtering by event date.                   |
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

class EventDateFilter
{
	// check for after date
	//
	static function after(Request $request, Builder $query) {

		// parse parameters
		//
		$after = $request->input('after', null);

		// add to query
		//
		if ($after) {
			$afterDate = new \DateTime($after);
			$afterDate->setTime(0, 0);
			$query = $query->where('event_date', '>=', $afterDate);
		}
		return $query;
	}

	// check for before date
	//
	static function before(Request $request, Builder $query) {

		// parse parameters
		//
		$before = $request->input('before', null);

		// add to query
		//
		if ($before) {
			$beforeDate = new \DateTime($before);
			$beforeDate->setTime(24, 0);
			$query = $query->where('event_date', '<=', $beforeDate);
		}
		return $query;
	}

	// check for before and after date
	//
	static function apply(Request $request, Builder $query) {
		$query = EventDateFilter::after($request, $query);
		$query = EventDateFilter::before($request, $query);
		return $query;
	}
}
