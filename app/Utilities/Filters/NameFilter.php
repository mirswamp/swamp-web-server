<?php
/******************************************************************************\
|                                                                              |
|                                NameFilter.php                                |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a utility for filtering users by name.                   |
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

class NameFilter
{
	// check for before and after date
	//
	static function apply(Request $request, Builder $query) {

		// parse parameters
		//
		$name = $request->input('name', null);

		// add to query
		//
		if ($name) {
			$query = $query->where('first_name', 'like', '%' . $name . '%')
				->orWhere('last_name', 'like', '%' . $name . '%')
				->orWhere('preferred_name', 'like', '%' . $name . '%');
		}

		return $query;
	}
}
