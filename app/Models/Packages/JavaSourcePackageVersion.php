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
|        Copyright (C) 2012-2019 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\Packages;

use Illuminate\Support\Facades\Response;
use App\Utilities\Files\Archive;
use App\Models\Packages\PackageVersion;
use App\Utilities\Strings\StringUtils;

class JavaSourcePackageVersion extends PackageVersion
{
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
		$buildFilePath = $archive->search($searchPath, self::BUILD_FILES);
		$buildFile = basename($buildFilePath);

		// deduce build system from build file
		//
		switch ($buildFile) {

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
		$buildCmd = null;
		$noBuildCmd = null;

		// search archive for build files
		//
		$archive = Archive::create($this->getPackagePath());
		$searchPath = $archive->concatPaths($this->source_path, $this->build_dir);
	
		// find build file path
		//
		$buildFilePath = $archive->search($searchPath, self::BUILD_FILES);
		$buildFile = basename($buildFilePath);
		$buildPath = dirname($buildFilePath) . '/';

		// find path to source files for no build
		//
		$sourceFilePath = $archive->find($searchPath, self::SOURCE_FILES, $this->build_dir == null);
		$sourcePath = dirname($sourceFilePath) . '/';

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
				$buildSystem = 'ant';
				break;

			case 'pom.xml':
				$buildSystem = 'maven';
				break;

			case 'build.gradle':

				// check for gradle wrapper
				//
				if ($archive->found($searchPath, 'gradlew')) {
					$buildSystem = 'gradle-wrapper';
				} else {
					$buildSystem = 'gradle';
				}
				break;

			default:
				$buildSystem = null;
				break;
		}

		// find and sort source files
		//
		$sourceFiles = $archive->getListing($sourcePath, self::SOURCE_FILES, false);
		sort($sourceFiles);
		
		// compose no build command
		//
		if ($sourceFiles && count($sourceFiles) > 0) {

			// add cd to build directory
			//
			if ($buildDir && $buildDir != '.' && $buildDir != './') {
				$noBuildCmd = 'cd ' .  $archive->toPathName($buildDir) . ';';
			}

			foreach ($sourceFiles as $sourceFile) {

				// strip off leading source path
				//
				if (StringUtils::startsWith($sourceFile, $this->source_path)) {
					$sourceFile = substr($sourceFile, strlen($this->source_path));
				}

				// strip off leading build dir
				//
				if (StringUtils::startsWith($sourceFile, $buildDir)) {
					$sourceFile = substr($sourceFile, strlen($buildDir));
				}

				// add quotation marks if necessary
				//
				if (StringUtils::contains($sourceFile, ' ')) {
					$sourceFile = '"' . $sourceFile . '"';
				}
				
				$noBuildCmd .= 'javac '.$sourceFile.';';
			}
		}

		return [
			'build_system' => $buildSystem,
			'config_dir' => $configDir,
			'config_cmd' => $configCmd,
			'build_dir' => $buildDir,
			'build_cmd' => $buildCmd,
			'no_build_cmd' => $noBuildCmd,
			'source_files' => $sourceFiles
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

		switch ($this->build_system) {
			case 'ant':
			case 'ant+ivy':
			case 'maven':
			case 'gradle':

				// search archive for build file
				//
				$archive = Archive::create($this->getPackagePath());
				$searchPath =  $archive->concatPaths($this->source_path, $this->build_dir);
				if ($this->build_file) {
					$buildFile = $this->build_file;
				}

				if ($archive->contains($searchPath, $buildFile)) {
					return response("Java source package version is ok for ".$this->build_system.".", 200);
				} else {
					return response("Could not find a build file called '" . $buildFile . "' within the '" . $searchPath . "' directory. You may need to set your build path or the path to your build file.", 404);
				}
				break;

			case 'no-build':
				$archive = Archive::create($this->getPackagePath());
				$searchPath =  $archive->concatPaths($this->source_path, $this->build_dir);

				// check for source files
				//
				$sourceFiles = $archive->getListing($searchPath, self::SOURCE_FILES, false);
				if (count($sourceFiles) > 0) {
					return response("Java source package build system ok for no-build.", 200);
				} else {
					return response("No assessable Java source code files were found directly in the selected build path ($searchPath).", 404);
				}

			default:
				return response("Java package build system ok for " . $this->build_system . ".", 200);
		}
	}
}
