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
|        Copyright (C) 2012-2020 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Http\Controllers\Packages;

use Illuminate\Http\Request;
use App\Models\Packages\RubyPackageVersion;
use App\Http\Controllers\Packages\PackageVersionsController;

class RubyPackageVersionsController extends PackageVersionsController
{
	// get ruby gem information for new packages
	//
	public function getNewRubyGemInfo(Request $request) {

		// parse parameters
		//
		$dirname = $request->input('dirname');

		// create new package version
		//
		$packageVersion = new RubyPackageVersion([
			'package_path' => $request->input('package_path')
		]);

		return $packageVersion->getGemInfo($dirname);
	}

	// get ruby gem information for existing packages
	//
	public function getRubyGemInfo(Request $request, string $packageVersionUuid) {

		// parse parameters
		//
		$dirname = $request->input('dirname');

		// find package version
		//
		$packageVersion = RubyPackageVersion::where('package_version_uuid', '=', $packageVersionUuid)->first();

		return $packageVersion->getGemInfo($dirname);
	}
}
