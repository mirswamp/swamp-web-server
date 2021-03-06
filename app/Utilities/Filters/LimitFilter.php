<?php
/******************************************************************************\
|                                                                              |
|                               LimitFilter.php                                |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a utility for filtering by limit (number).               |
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

class LimitFilter
{
	static function apply(Request $request, Builder $query) {

		// parse parameters
		//
		$limit = $request->input('limit', null);

		// add limit to query
		//
		if ($limit) {
			$query = $query->take($limit);
		}

		return $query;
	}
}
