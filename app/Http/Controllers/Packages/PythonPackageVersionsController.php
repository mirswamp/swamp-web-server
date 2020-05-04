<?php

/******************************************************************************\
|                                                                              |
|                     PythonPackageVersionsController.php                      |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a controller for Python package versions.                |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2020 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Http\Controllers\Packages;

use Illuminate\Http\Request;
use App\Models\Packages\PythonPackageVersion;
use App\Http\Controllers\Packages\PackageVersionsController;

class PythonPackageVersionsController extends PackageVersionsController
{
	// get wheel information for new packages
	//
	public function getNewPythonWheelInfo(Request $request) {

		// parse parameters
		//
		$dirname = $request->input('dirname');

		// create new package version
		//
		$packageVersion = new PythonPackageVersion([
			'package_path' => $request->input('package_path')
		]);

		return $packageVersion->getWheelInfo($dirname);
	}

	// get wheel information for existing packages
	//
	public function getPythonWheelInfo(Request $request, string $packageVersionUuid) {

		// parse parameters
		//
		$dirname = $request->input('dirname');

		// find package version
		//
		$packageVersion = PythonPackageVersion::find($packageVersionUuid);
		if (!$packageVersion) {
			return response("Package version not found.", 404);
		}

		return $packageVersion->getWheelInfo($dirname);
	}
}
