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
|        Copyright (C) 2012-2020 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Http\Controllers\Utilities;

use App\Http\Controllers\BaseController;

class RoutesController extends BaseController
{
	// get actual routes
	//
	public function getActual() {
		$paths = [];
		$routeCollection = \Route::getRoutes();
		foreach ($routeCollection as $route) {
			$method = $route->methods[0];
			array_push($paths,  $method . ' ' . $route->uri());
		}
		return $paths;
	}
}