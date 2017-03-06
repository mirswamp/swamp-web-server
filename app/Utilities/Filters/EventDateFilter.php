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
|        Copyright (C) 2012-2017 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Utilities\Filters;

use Illuminate\Support\Facades\Input;

class EventDateFilter {

	// check for after date
	//
	static function after($query) {
		$after = Input::get('after');
		if ($after != '') {
			$afterDate = new \DateTime($after);
			$afterDate->setTime(0, 0);
			$query = $query->where('event_date', '>=', $afterDate);
		}
		return $query;
	}

	// check for before date
	//
	static function before($query) {
		$before = Input::get('before');
		if ($before != '') {
			$beforeDate = new \DateTime($before);
			$beforeDate->setTime(24, 0);
			$query = $query->where('event_date', '<=', $beforeDate);
		}
		return $query;
	}

	// check for before and after date
	//
	static function apply($query) {
		$query = EventDateFilter::after($query);
		$query = EventDateFilter::before($query);
		return $query;
	}
}
