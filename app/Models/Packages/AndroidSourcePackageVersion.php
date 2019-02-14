<?php
/******************************************************************************\
|                                                                              |
|                       AndroidSourcePackageVersion.php                        |
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
use App\Utilities\Files\Archive;
use App\Models\Packages\JavaSourcePackageVersion;
use App\Utilities\Strings\StringUtils;

class AndroidSourcePackageVersion extends JavaSourcePackageVersion {

	//
	// attributes
	//

	const BUILD_FILES = ['build.xml', 'pom.xml', 'build.gradle'];
	const SOURCE_FILES = '/\.(j|java)$/';

	//
	// querying methods
	//

	function getBuildSystem() {
		
		// search archive for build files
		//
		$archive = Archive::create($this->getPackagePath()); 
		$searchPath = $archive->concatPaths($this->source_path, $this->build_dir);
		$path = $archive->search($searchPath, self::BUILD_FILES);

		// deduce build system from build file
		//
		switch (basename($path)) {

			case 'build.xml':
				return 'android+ant';

			case 'pom.xml':
				return 'android+maven';

			case 'build.gradle':

				// check for gradle wrapper
				//
				if ($archive->found($searchPath, 'gradlew')) {
					return 'android+gradle-wrapper';
				} else {
					return 'android+gradle';
				}

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
		$buildCmd = null;
		$noBuildCmd = null;

		// search archive for build files
		//
		$archive = Archive::create($this->getPackagePath());
		$searchPath = $archive->concatPaths($this->source_path, $this->build_dir);
		
		// find path to source files for no build
		//
		$sourceFilePath = $archive->find($searchPath, self::SOURCE_FILES, true);
		$sourcePath = dirname($sourceFilePath) . '/';

		// find build file path
		//
		$buildFilePath = $archive->search($searchPath, self::BUILD_FILES);
		$buildFile = basename($buildFilePath);
		$buildPath = dirname($buildFilePath) . '/';

		// find build dir if not specified
		//
		$buildDir = $this->build_dir;
		if (!$buildDir) {

			// find build dir relative to source path
			//
			$buildDir = $buildFile? $buildPath : $sourcePath;
			if (StringUtils::startsWith($buildDir . '/', $this->source_path)) {
				$buildDir = substr($buildDir, strlen($this->source_path));
			}
		} else {
			if ($buildDir == '.') {
				$buildDir = null;
			}
		}

		// deduce build system from build file
		//
		switch ($buildFile) {

			case 'build.xml':
				$buildSystem = 'android+ant';
				break;

			case 'pom.xml':
				$buildSystem = 'android+maven';
				break;

			case 'build.gradle':

				// check for gradle wrapper
				//
				if ($archive->found($searchPath, 'gradlew')) {
					$buildSystem = 'android+gradle-wrapper';
				} else {
					$buildSystem = 'android+gradle';
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
			'build_cmd' => $buildCmd,
			'no_build_cmd' => $noBuildCmd
		];
	}

	function checkBuildSystem() {

		// find build file
		//
		$buildFile = $this->build_file;
		if ($buildFile == null) {
			switch ($this->build_system) {

				case 'android+ant':
					$buildFile = 'build.xml';
					break;

				case 'android+maven':
					$buildFile = 'pom.xml';
					break;

				case 'android+gradle':
					$buildFile = 'build.gradle';
					break;
			}
		}

		switch ($this->build_system) {

			case 'android+ant':
			case 'android+maven':
			case 'android+gradle':

				// search archive for build file
				//
				$archive = Archive::create($this->getPackagePath());
				$searchPath = $archive->concatPaths($this->source_path, $this->build_dir);

				if ($archive->contains($searchPath, $buildFile)) {
					return response("Android source package version is ok for " . $this->build_system . ".", 200);
				} else {
					return response("Could not find a build file called '" . $buildFile . "' within the '" . $searchPath . "' directory. You may need to set your build path or the path to your build file.", 404);
				}
				break;

			case 'none':
				$archive = Archive::create($this->getPackagePath());
				$searchPath = $archive->concatPaths($this->source_path, $this->build_dir);

				// find path to source files for no build
				//
				$noBuildPath = $archive->find($searchPath, self::SOURCE_FILES, true);
				$noBuildDir = dirname($noBuildPath);

				// check for source files
				//
				$sourceFiles = $archive->getListing($noBuildDir, self::SOURCE_FILES, false);
				if (count($sourceFiles) > 0) {
					return response("Android source package build system ok for no-build.", 200);
				} else {
					return response("No assessable Android source code files were found directly in the selected build path ($searchPath).", 404);
				}

			default:
				return response("Android package build system ok for " . $this->build_system . ".", 200);
		}
	}
}
