<?php
/******************************************************************************\
|                                                                              |
|                          JavaSourcePackageVersion.php                        |
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

class JavaSourcePackageVersion extends PackageVersion {

	//
	// querying methods
	//

	function getBuildSystem() {
	
		// search archive for build files
		//
		$archive = new Archive($this->getPackagePath());
		$searchPath = Archive::concatPaths($this->source_path, $this->build_dir);
		$path = $archive->search($searchPath, ['build.xml', 'pom.xml', 'build.gradle']);

		// deduce build system from build file
		//
		switch (basename($path)) {

			case 'build.xml':
				return 'ant';

			case 'pom.xml':
				return 'maven';

			case 'build.gradle':

				// check for gradle wrapper
				//
				if ($archive->found($searchPath, 'gradlew')) {
					return 'gradle-wrapper';
				} else {
					return 'gradle';
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

		// search archive for build files
		//
		$archive = new Archive($this->getPackagePath());
		$searchPath = Archive::concatPaths($this->source_path, $this->build_dir);
		$path = $archive->search($searchPath, ['build.xml', 'pom.xml', 'build.gradle']);

		// strip off leading source path
		//
		if (StringUtils::startsWith($path, $this->source_path)) {
			$path = substr($path, strlen($this->source_path));
		}


		// deduce build system from build file
		//
		switch (basename($path)) {

			case 'build.xml':
				$buildSystem = 'ant';
				$buildDir = dirname($path);
				if ($buildDir == '.') {
					$buildDir = null;
				}
				break;

			case 'pom.xml':
				$buildSystem = 'maven';
				$buildDir = dirname($path);
				if ($buildDir == '.') {
					$buildDir = null;
				}
				break;

			case 'build.gradle':

				// check for gradle wrapper
				//
				if ($archive->found($searchPath, 'gradlew')) {
					$buildSystem = 'gradle-wrapper';
				} else {
					$buildSystem = 'gradle';
				}

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

		// find build file
		//
		$buildFile = $this->build_file;
		if ($buildFile == null) {
			switch ($this->build_system) {

				case 'ant':
					$buildFile = 'build.xml';
					break;

				case 'ant+ivy':
					$buildFile = 'build.xml';
					break;

				case 'maven':
					$buildFile = 'pom.xml';
					break;

				case 'gradle':
					$buildFile = 'build.gradle';
					break;
			}
		}

		if ($this->build_system) {

			// search archive for build file
			//
			$archive = new Archive($this->getPackagePath());
			$searchPath = Archive::concatPaths($this->source_path, $this->build_dir);

			if ($archive->contains($searchPath, $buildFile)) {
				return response("Java source package version is ok for ".$this->build_system.".", 200);
			} else {
				return response("Could not find a build file called '" . $buildFile . "' within the '" . $searchPath . "' directory. You may need to set your build path or the path to your build file.", 404);
			}
		}
	}
}
