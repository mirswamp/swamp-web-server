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
|        Copyright (C) 2012-2018 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\Packages;

use Illuminate\Support\Facades\Response;
use App\Utilities\Files\Archive;
use App\Models\Packages\PackageVersion;
use App\Utilities\Strings\StringUtils;

class PythonPackageVersion extends PackageVersion {
	
	//
	// querying methods
	//

	function getBuildSystem() {

		// check for wheels
		//
		$packagePath = $this->getPackagePath();
		if (StringUtils::endsWith($packagePath, '.whl')) {
			return 'wheels';
		} else {

			// search archive for build files
			//
			$archive = new Archive($packagePath);
			$searchPath = Archive::concatPaths($this->source_path, $this->build_dir);

			// deduce build system from build file
			//
			if ($archive->found($searchPath, 'setup.py')) {
				return 'python-setuptools';
			} else {

				// build system not found
				//
				return null;
			}
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

		// check for wheels
		//
		$packagePath = $this->getPackagePath();
		if (StringUtils::endsWith($packagePath, '.whl')) {
			$buildSystem = 'wheels';
		} else {

			// search archive for build files
			//
			$archive = new Archive($packagePath);
			$searchPath = Archive::concatPaths($this->source_path, $this->build_dir);
			$path = $archive->search($searchPath, ['setup.py']);

			// strip off leading source path
			//
			if (StringUtils::startsWith($path, $this->source_path)) {
				$path = substr($path, strlen($this->source_path));
			}

			// deduce build system from build file
			//
			switch (basename($path)) {

				case 'setup.py':
					$buildSystem = 'python-setuptools';
					$buildDir = dirname($path);
					if ($buildDir == '.') {
						$buildDir = null;
					}
					break;

				default:
					$buildSystem = null;
					break;
			}
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

			case 'wheels';
				return response("Python package ok for build with wheels.", 200);
				break;

			case 'distutils':
			case 'python-setuptools':

				// search archive for build file
				//
				$archive = new Archive($this->getPackagePath());
				$searchPath = Archive::concatPaths($this->source_path, $this->build_dir);
				$buildFile = $this->build_file;

				if ($buildFile != NULL) {
					if ($archive->contains($searchPath, $buildFile)) {
						return response("Python package build system ok for build with ". $this->build_system . ".", 200);
					} else {
						return response("Could not find a build file called '".$buildFile."' within the '" . $searchPath . "' directory. You may need to set your build path or the path to your build file.", 404);
					}
				}

			default:
				return response("Python package ok for no build.", 200);
				break;
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
		return $this->getWheelPackageName().'-'.$this->getWheelPackageVersion().'.dist-info/';
	}

	function getWheelInfo($dirname) {
		//$wheelDirname = $this->getWheelDirname();
		//$dirname = Archive::concatPaths($dirname, $wheelDirname);

		$dirname = str_replace('./', '', $dirname);
		$contents = self::getFileContents('WHEEL', $dirname);

		if ($contents) {

			// parse file
			//
			return self::parseKeyValueInfo(explode("\n", $contents));
		} else {
			return response("Could not get WHEEL contents.", 404);
		}
	}
}
