<?php
/******************************************************************************\
|                                                                              |
|                                DateFilter.php                                |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a utility for filtering by create date.                  |
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
|        Copyright (C) 2012-2016 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Utilities\Filters;

use Illuminate\Support\Facades\Input;

class DateFilter {

	// check for after date
	//
	static function after($query) {
		$after = Input::get('after');
		if ($after != '') {
			$afterDate = new \DateTime($after);
			$query = $query->where('create_date', '>=', $afterDate);
		}
		return $query;
	}

	// check for before date
	//
	static function before($query) {
		$before = Input::get('before');
		if ($before != '') {
			$beforeDate = new \DateTime($before);
			$query = $query->where('create_date', '<=', $beforeDate);
		}
		return $query;
	}

	// check for before and after date
	//
	static function apply($query) {
		$query = DateFilter::after($query);
		$query = DateFilter::before($query);
		return $query;
	}
}
