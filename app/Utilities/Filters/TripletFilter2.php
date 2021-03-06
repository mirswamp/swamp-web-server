<?php
/******************************************************************************\
|                                                                              |
|                               TripletFilter2.php                             |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a utility for filtering by package, tool, and            |
|        platform.                                                             |
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
use App\Utilities\Filters\PackageFilter2;
use App\Utilities\Filters\ToolFilter2;
use App\Utilities\Filters\PlatformFilter2;

class TripletFilter2
{
	static function apply(Request $request, Builder $query, ?string $projectUuid) {
		$query = PackageFilter2::apply($request, $query, $projectUuid);
		$query = ToolFilter2::apply($request, $query);
		$query = PlatformFilter2::apply($request, $query);
		return $query;
	}
}
