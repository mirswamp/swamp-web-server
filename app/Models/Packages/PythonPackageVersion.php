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

		// check in package path and build path
		//
		$archive = new Archive($this->getPackagePath());
		$packagePath = $this->getPackagePath();
		$buildPath = Archive::concatPaths($this->source_path, $this->build_dir);

		// check for setuptools
		//
		if (StringUtils::endsWith($packagePath, '.whl')) {
			return 'wheels';

		// check for ant
		//
		} else if ($archive->found($buildPath, 'setup.py')) {
			return 'setuptools';

		// build system not found
		//
		} else {
			return null;
		}
	}

	function checkBuildSystem() {
		switch ($this->build_system) {

			case 'none':
				return response("Python package ok for no build.", 200);
				break;

			case 'distutils':

				// create archive from package
				//
				$archive = new Archive($this->getPackagePath());
				$buildPath = Archive::concatPaths($this->source_path, $this->build_dir);
				$buildFile = $this->build_file;

				// search archive for build file in build path
				//
				if ($buildFile != NULL) {
					if ($archive->contains($buildPath, $buildFile)) {
						return response("Python package build system ok for build with distutils.", 200);
					} else {
						return response("Could not find a build file called '".$buildFile."' within the '".$buildPath."' directory. You may need to set your build path or the path to your build file.", 404);
					}
				}
				break;

			case 'other':
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
