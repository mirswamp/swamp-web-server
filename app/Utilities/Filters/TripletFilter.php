<?php
/******************************************************************************\
|                                                                              |
|                               TripletFilter.php                              |
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
|        Copyright (C) 2012-2017 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Utilities\Filters;

use Illuminate\Support\Facades\Input;
use App\Utilities\Filters\PackageFilter;
use App\Utilities\Filters\ToolFilter;
use App\Utilities\Filters\PlatformFilter;

class TripletFilter {
	static function apply($query, $projectUuid) {
		$query = PackageFilter::apply($query, $projectUuid);
		$query = ToolFilter::apply($query);
		$query = PlatformFilter::apply($query);
		return $query;
	}
}
