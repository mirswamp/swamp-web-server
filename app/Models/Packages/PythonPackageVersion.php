<?php
/******************************************************************************\
|                                                                              |
|                           PythonPackageVersion.php                           |
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
|        Copyright (C) 2012-2020 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\Packages;

use App\Utilities\Files\Archive;
use App\Models\Packages\PackageVersion;
use App\Utilities\Strings\StringUtils;

class PythonPackageVersion extends PackageVersion
{
	//
	// attributes
	//

	const BUILD_FILES = ['setup.py'];
	const SOURCE_FILES = '/\.(py)$/';
	//
	// querying methods
	//

	function getBuildSystem(): string {

		// check for wheels
		//
		$packagePath = $this->getPackagePath();
		if (StringUtils::endsWith($packagePath, '.whl')) {
			return 'wheels';
		} else {

			// search archive for build files
			//
			$archive = Archive::create($packagePath);
			$searchPath = $archive->concatPaths($this->source_path, $this->build_dir);

			// deduce build system from build file
			//
			if ($archive->found($searchPath, 'setup.py')) {
				return 'python-setuptools';
			} else {

				// build system not found
				//
				return 'none';
			}
		}
	}

	function getBuildInfo(): array {

		// initialize build info
		//
		$buildSystem = null;
		$configDir = null;
		$configCmd = null;
		$buildFile = null;
		$buildDir = null;
		$buildCmd = null;
		$noBuildCmd = null;
		$sourceFiles = [];

		// check for wheels
		//
		$packagePath = $this->getPackagePath();
		if (StringUtils::endsWith($packagePath, '.whl')) {
			$buildSystem = 'wheels';
			$archive = null;
		} else {

			// search archive for build files
			//
			$archive = Archive::create($packagePath);
			$searchPath = $archive->concatPaths($this->source_path, $this->build_dir);

			// find build file path
			//
			$buildFilePath = $archive->search($searchPath, self::BUILD_FILES);
			$buildFile = basename($buildFilePath);
			$buildPath = dirname($buildFilePath) . '/';

			// find path to source files for no build
			//
			$sourcePath = $searchPath;
			/*
			$sourceFilePath = $archive->find($searchPath, self::SOURCE_FILES, true);
			$sourcePath = dirname($sourceFilePath) . '/';
			*/

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

				case 'setup.py':
					$buildSystem = 'python-setuptools';
					break;

				default:
					$buildSystem = null;
					break;
			}
		}

		// find and sort source files
		//
		if ($archive) {
			$sourceFiles = $archive->getListing($sourcePath, self::SOURCE_FILES, true);
			sort($sourceFiles);
		}
		
		// compose no build command
		//
		if ($sourceFiles && count($sourceFiles) > 0) {

			// add cd to build directory
			//
			if ($buildDir && $buildDir != '.' && $buildDir != './') {
				$noBuildCmd = 'cd ' .  $archive->toPathName($buildDir) . ';';
			}
		}

		return [
			'build_system' => $buildSystem,
			'config_dir' => $configDir,
			'config_cmd' => $configCmd,
			'build_file' => $buildFile,
			'build_dir' => $buildDir,
			'build_cmd' => null,
			'no_build_cmd' => $noBuildCmd,
			'source_files' => $sourceFiles
		];
	}

	function checkBuildSystem(): string {
		switch ($this->build_system) {

			case 'wheels';
				return "ok";
				break;

			case 'distutils':
			case 'python-setuptools':

				// search archive for build file
				//
				$archive = Archive::create($this->getPackagePath());
				$searchPath = $archive->concatPaths($this->source_path, $this->build_dir);
				$buildFile = $this->build_file;

				if ($buildFile != null) {
					if ($archive->contains($searchPath, $buildFile)) {
						return "ok";
					} else {
						return "Could not find a build file called '" . $buildFile . "' within the '" . $searchPath . "' directory. You may need to set your build path or the path to your build file.";
					}
				}

			case 'none':
				$archive = Archive::create($this->getPackagePath());
				$searchPath = $archive->concatPaths($this->source_path, $this->build_dir);

				// check for source files
				//
				$sourceFiles = $archive->getListing($searchPath, self::SOURCE_FILES, true);
				if (count($sourceFiles) > 0) {
					return "ok";
				} else {
					return "No assessable Python code files were found in the selected build path ($searchPath).";
				}
				break;

			default:
				return "ok";
		}
	}

	//
	// Python wheel querying methods
	//

	function getWheelPackageName() {
		$names = explode('-', $this->filename);
		return $names[0];
	}

	function getWheelPackageVersion() {
		$names = explode('-', $this->filename);
		return $names[1];
	}

	function getWheelDirname() {
		return $this->getWheelPackageName() . '-' . $this->getWheelPackageVersion() . '.dist-info/';
	}

	function getWheelInfo($dirname) {
		$dirname = str_replace('./', '', $dirname);
		$contents = $this->getFileContents('WHEEL', $dirname);

		if ($contents) {

			// parse file
			//
			return self::parseKeyValueInfo(explode("\n", $contents));
		} else {
			return response("Could not get WHEEL contents.", 404);
		}
	}
}
