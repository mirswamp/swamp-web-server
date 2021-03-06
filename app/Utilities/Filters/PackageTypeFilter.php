<?php
/******************************************************************************\
|                                                                              |
|                            PackageTypeFilter.php                             |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a utility for filtering packages by type.                |
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
use App\Models\Packages\PackageType;

class PackageTypeFilter
{
	static function apply(Request $request, Builder $query) {

		// parse parameters
		//
		$type = $request->input('type', null);

		// add to query
		//
		if ($type) {
			$packageType = PackageType::where('name', '=', $type)->first();
			if ($packageType) {
				$query = $query->where('package_type_id', '=', $packageType->package_type_id); 
			}
		}

		return $query;
	}
}
