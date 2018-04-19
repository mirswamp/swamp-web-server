<?php
/******************************************************************************\
|                                                                              |
|                         WebScriptingPackageVersion.php                       |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a model of type of package version.                      |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2018 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\Packages;

use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Log;
use App\Utilities\Files\Archive;
use App\Models\Packages\PackageVersion;
use App\Utilities\Strings\StringUtils;

class WebScriptingPackageVersion extends PackageVersion {

	//
	// querying methods
	//

	function getBuildSystem() {

		// search archive for build files
		//
		$archive = new Archive($this->getPackagePath());
		$searchPath = Archive::concatPaths($this->source_path, $this->build_dir);
		$path = $archive->search($searchPath, ['package.json', 'composer.json']);

		// deduce build system from build file
		//
		switch (basename($path)) {

			case 'package.json':
				return 'npm';

			case 'composer.json':
				return 'composer';

			default:
				return null;
		}
	}

	function getBuildInfo() {

		// initialize build info
		//
		$buildSystem = null;
		$configDir = null;
		$configCmd = null;
		$buildDir = null;
		$buildFile = null;

		// search archive for build files
		//
		$archive = new Archive($this->getPackagePath());
		$searchPath = Archive::concatPaths($this->source_path, $this->build_dir);
		$path = $archive->search($searchPath, ['package.json', 'composer.json']);

		// strip off leading source path
		//
		if (StringUtils::startsWith($path, $this->source_path)) {
			$path = substr($path, strlen($this->source_path));
		}

		// deduce build system from build file
		//
		switch (basename($path)) {

			case 'package.json':
				$buildSystem = 'npm';
				$buildDir = dirname($path);
				if ($buildDir == '.') {
					$buildDir = null;
				}
				break;

			case 'composer.json':
				$buildSystem = 'composer';
				$buildDir = dirname($path);
				if ($buildDir == '.') {
					$buildDir = null;
				}
				break;

			default:
				$buildSystem = null;
				break;
		}

		return [
			'build_system' => $buildSystem,
			'config_dir' => $configDir,
			'config_cmd' => $configCmd,
			'build_dir' => $buildDir,
			'build_file' => $buildFile
		];
	}

	function checkBuildSystem() {
		switch ($this->build_system) {

			case 'npm':

				// search archive for package.json
				//
				$archive = new Archive($this->getPackagePath());
				$searchPath = Archive::concatPaths($this->source_path, $this->build_dir);

				if ($archive->contains($searchPath, 'package.json')) {
					return response("C/C++ package build system ok for npm.", 200);
				} else {
					return response("Could not find a build file called 'package.json' within '" . $searchPath . "' directory. You may need to set your build path or the path to your build file.", 404);
				}
				break;

			case 'composer':

				// search archive for composer.json
				//
				$archive = new Archive($this->getPackagePath());
				$searchPath = Archive::concatPaths($this->source_path, $this->build_dir);

				if ($archive->contains($searchPath, 'composer.json')) {
					return response("C/C++ package build system ok for composer.", 200);
				} else {
					return response("Could not find a build file called 'composer.json' within '" . $searchPath . "' directory. You may need to set your build path or the path to your build file.", 404);
				}
				break;

			case 'no-build':
			default:
				return response("Web scripting package build system ok.", 200);
				break;
		}
	}
}
