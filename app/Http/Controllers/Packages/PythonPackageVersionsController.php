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
|        Copyright (C) 2012-2016 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Http\Controllers\Packages;

use Illuminate\Support\Facades\Input;
use App\Models\Packages\PythonPackageVersion;
use App\Http\Controllers\Packages\PackageVersionsController;

class PythonPackageVersionsController extends PackageVersionsController {

	// get wheel information for new packages
	//
	public function getNewPythonWheelInfo() {
		$dirname = Input::get('dirname');

		// create new package version
		//
		$packageVersion = new PythonPackageVersion(array(
			'package_path' => Input::get('package_path')
		));

		return $packageVersion->getWheelInfo($dirname);
	}

	// get wheel information for existing packages
	//
	public function getPythonWheelInfo($packageVersionUuid) {
		$dirname = Input::get('dirname');

		// find package version
		//
		$packageVersion = PythonPackageVersion::where('package_version_uuid', '=', $packageVersionUuid)->first();

		return $packageVersion->getWheelInfo($dirname);
	}
}
