<?php
/******************************************************************************\
|                                                                              |
|                             RoutesController.php                             |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a controller for reporting route information             |
|        using introspection.                                                  |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2017 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Http\Controllers\Utilities;

use Illuminate\Support\Facades\Config;
use App\Http\Controllers\BaseController;

class RoutesController extends BaseController {

	// get actual routes
	//
	public function getActual() {
		if (Config::get('app.api_explorer_enabled')) {
			$paths = [];
			$routeCollection = \Route::getRoutes();
			foreach ($routeCollection as $route) {
				$method = $route->getMethods()[0];
				array_push($paths, $method.' '.$route->getPath());
			}
			return $paths;
		} else {
			return response('Error - API explorer not enabled.', 400);
		}
	}
}