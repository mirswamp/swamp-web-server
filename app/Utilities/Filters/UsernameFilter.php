<?php
/******************************************************************************\
|                                                                              |
|                              UsernameFilter.php                              |
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
|        Copyright (C) 2012-2019 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Utilities\Filters;

use Illuminate\Support\Facades\Input;

class UsernameFilter
{
	// check for before and after date
	//
	static function apply($query) {

		// parse parameters
		//
		$username = Input::get('username', null);

		// add to query
		//
		if ($username) {
			$query = $query->where('username', 'like', '%' . $username . '%');
		}

		return $query;
	}
}
