<?php
/******************************************************************************\
|                                                                              |
|                      RubyPackageVersionsController.php                       |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a controller for Ruby package versions.                  |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2019 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Http\Controllers\Packages;

use Illuminate\Support\Facades\Input;
use App\Models\Packages\RubyPackageVersion;
use App\Http\Controllers\Packages\PackageVersionsController;

class RubyPackageVersionsController extends PackageVersionsController
{
	// get ruby gem information for new packages
	//
	public function getNewRubyGemInfo() {

		// parse parameters
		//
		$dirname = Input::get('dirname');

		// create new package version
		//
		$packageVersion = new RubyPackageVersion([
			'package_path' => Input::get('package_path')
		]);

		return $packageVersion->getGemInfo($dirname);
	}

	// get ruby gem information for existing packages
	//
	public function getRubyGemInfo($packageVersionUuid) {

		// parse parameters
		//
		$dirname = Input::get('dirname');

		// find package version
		//
		$packageVersion = RubyPackageVersion::where('package_version_uuid', '=', $packageVersionUuid)->first();

		return $packageVersion->getGemInfo($dirname);
	}
}
