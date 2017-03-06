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
|        Copyright (C) 2012-2017 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Utilities\Filters;

use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Request;
use App\Models\Users\User;

class FiltersHelper {

	static function method() {
		return strtolower( $_SERVER['REQUEST_METHOD'] );
	}

	static function whitelisted() {

		// detect API requests
		//
		if (Input::get('api_key') && Input::get('user_uid')) {
			if (Config::get('app.api_key') == Input::get('api_key')) {
				if (!User::getIndex(Input::get('user_uid')) ){
					return false;
				}
				Session::set('user_uid', Input::get('user_uid'));
				return true;
			}
			return false;
		}

		// detect whitelisted routes
		//
		if (Config::has('app.whitelist')) {
			foreach (Config::get('app.whitelist') as $pattern) {
				if (is_array($pattern)) {
					if (Request::is(key($pattern))) {
						return in_array( self::method(), current( $pattern ) );
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

	static function filterPassword($string) {
		return str_ireplace("<script>", "", $string);
	}
}
