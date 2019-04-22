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
|        Copyright (C) 2012-2019 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\Packages;

use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Log;
use App\Utilities\Files\Archive;
use App\Models\Packages\PackageVersion;
use App\Utilities\Strings\StringUtils;

class WebScriptingPackageVersion extends PackageVersion
{
	//
	// attributes
	//

	const BUILD_FILES = ['package.json', 'composer.json'];
	const SOURCE_FILES = '/\.(htm|html|tpl|js|css|php|xml)$/';

	//
	// querying methods
	//

	function getBuildSystem() {

		// search archive for build files
		//
		$archive = Archive::create($this->getPackagePath());

		// find build file paths
		//
		$buildFilePath = $archive->search($this->source_path, self::BUILD_FILES, false);
		$buildFile = basename($buildFilePath);

		// deduce build system from build file
		//
		switch ($buildFile) {

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
		$archive = Archive::create($this->getPackagePath());
		$buildFilePath = $archive->search($this->source_path, self::BUILD_FILES, false);
		$buildPath = dirname($buildFilePath);
		$buildFile = basename($buildFilePath);

		// deduce build system from build file
		//
		switch ($buildFile) {

			case 'package.json':
				$buildSystem = 'npm';
				$buildDir = $buildPath;
				if ($buildDir == '.') {
					$buildDir = null;
				}
				break;

			case 'composer.json':
				$buildSystem = 'composer';
				$buildDir = $buildPath;
				if ($buildDir == '.') {
					$buildDir = null;
				}
				break;

			default:
				$buildSystem = null;
				break;
		}

		// find and sort source files
		//
		$searchPath =  $archive->concatPaths($this->source_path, $this->build_dir);
		$sourceFiles = $archive->getListing($searchPath, self::SOURCE_FILES, true);
		sort($sourceFiles);

		return [
			'build_system' => $buildSystem,
			'config_dir' => $configDir,
			'config_cmd' => $configCmd,
			'build_dir' => $buildDir,
			'source_files' => $sourceFiles
		];
	}

	function checkBuildSystem() {
		switch ($this->build_system) {

			case 'npm':

				// search archive for package.json
				//
				$archive = Archive::create($this->getPackagePath());

				if ($archive->contains($this->source_path, 'package.json')) {
					return response("Web scripting package build system ok for npm.", 200);
				} else {
					return response("Could not find a build file called 'package.json' within '" . $this->source_path . "' directory. You may need to set your package path.", 404);
				}
				break;

			case 'composer':

				// search archive for composer.json
				//
				$archive = Archive::create($this->getPackagePath());

				if ($archive->contains($this->source_path, 'composer.json')) {
					return response("Web scripting package build system ok for composer.", 200);
				} else {
					return response("Could not find a build file called 'composer.json' within '" . $this->source_path . "' directory. You may need to set your package path.", 404);
				}
				break;

			default:
				return response("Web scripting package build system ok.", 200);
				break;
		}
	}
}
