<?php
/******************************************************************************\
|                                                                              |
|                              FiltersHelper.php                               |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a set of of utility functions used to protect            |
|        access to routes.                                                     |
|                                                                              |
|        Author(s): Eric Dunning                                               |
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
use Illuminate\Support\Facades\Session;
use App\Models\Users\User;

class FiltersHelper
{
	static function method() {
		return strtolower( $_SERVER['REQUEST_METHOD'] );
	}

	static function whitelisted() {

		// parse parameters
		//
		$apiKey = $request->input('api_key', null);
		$userUid = $request->input('user_uid', null);

		// detect API requests
		//
		if ($apiKey && $userUid) {
			if (config('app.api_key') == $apiKey) {
				if (!User::getIndex($userUid)) {
					return false;
				}
				session('user_uid', $userUid);
				return true;
			}
			return false;
		}

		// detect whitelisted routes
		//
		if (config('app.whitelist')) {
			foreach (config('app.whitelist') as $pattern) {
				if (is_array($pattern)) {
					if (Request::is(key($pattern))) {
						return in_array(self::method(), current($pattern));
					}
				} else {
					if (Request::is($pattern)) { 
						return true;
					}
				}
			}
		} else {
			return true;
		}

		return false;
	}

	static function filterPassword(string $string) {
		return str_ireplace("<script>", "", $string);
	}
}
