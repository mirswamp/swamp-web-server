<?php
/******************************************************************************\
|                                                                              |
|                              CPackageVersion.php                             |
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
use App\Utilities\Files\Archive;
use App\Models\Packages\PackageVersion;
use App\Utilities\Strings\StringUtils;

class CPackageVersion extends PackageVersion {

	//
	// querying methods
	//

	function getBuildSystem() {
		
		// search archive for build files
		//
		$archive = new Archive($this->getPackagePath());
		$searchPath = Archive::concatPaths($this->source_path, $this->build_dir);
		$path = $archive->search($searchPath, ['makefile', 'Makefile', 'configure', 'configure.ac']);

		// deduce build system from build file
		//
		switch (basename($path)) {
			
			case 'makefile':
			case 'Makefile':
				return 'make';

			case 'configure':
				return 'configure+make';

			case 'configure.ac':
				return 'autotools+configure+make';

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
		$path = $archive->search($searchPath, ['makefile', 'Makefile', 'cmake', 'configure', 'configure.ac']);

		// strip off leading source path
		//
		if (StringUtils::startsWith($path, $this->source_path)) {
			$path = substr($path, strlen($this->source_path));
		}

		// deduce build system from build file
		//
		switch (basename($path)) {

			case 'makefile':
			case 'Makefile':
				$buildSystem = 'make';
				$buildDir = dirname($path);
				if ($buildDir == '.') {
					$buildDir = null;
				}
				break;

			case 'cmake':
				$buildSystem = 'cmake+make';
				$configDir = dirname($path);
				if ($configDir == '.') {
					$configDir = null;
				}
				$configCmd = 'cmake .';
				break;

			case 'configure':
				$buildSystem = 'configure+make';
				$configDir = dirname($path);
				if ($configDir == '.') {
					$configDir = null;
				}
				$configCmd = './configure';
				$buildDir = $configDir;
				break;

			case 'configure.ac':
				$buildSystem = 'autotools+configure+make';
				$configDir = dirname($path);
				if ($configDir == '.') {
					$configDir = null;
				}
				$configCmd = 'mkdir -p m4 && autoreconf --install --force || ./autogen.sh && ./configure';
				$buildDir = $configDir;
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

			case 'make':

				// search archive for build file
				//
				$archive = new Archive($this->getPackagePath());
				$searchPath = Archive::concatPaths($this->source_path, $this->build_dir);
				$buildFile = $this->build_file;

				if ($buildFile != NULL) {
					if ($archive->found($searchPath, $buildFile)) {
						return response("C/C++ package build system ok for make.", 200);
					} else {
						return response("Could not find a build file called '" . $buildFile . "' within the '" . $searchPath . "' directory.  You may need to set your build path or the path to your build file.", 404);
					}
				}

				// search archive for default build file
				//
				if ($archive->found($searchPath, 'makefile') || 
					$archive->found($searchPath, 'Makefile')) {
					return response("C/C++ package build system ok for make.", 200);
				} else {
					return response("Could not find a build file called 'makefile' or 'Makefile' within '" . $searchPath . "' directory. You may need to set your build path or the path to your build file.", 404);
				}
				break;

			case 'configure+make':
			case 'cmake+make':
				return response("C/C++ package build system ok for cmake+make.", 200);
				break;
		}
	}
}
