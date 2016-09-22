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
|        Copyright (C) 2012-2016 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\Packages;

use Illuminate\Support\Facades\Response;
use App\Utilities\Files\Archive;
use App\Models\Packages\PackageVersion;

class CPackageVersion extends PackageVersion {

	//
	// querying methods
	//

	function getBuildSystem() {
		
		// create archive from package
		//
		$archive = new Archive($this->getPackagePath());

		// check for configure
		//
		$configPath = Archive::concatPaths($this->source_path, $this->config_dir);
		if ($archive->found($configPath, 'configure')) {

			// configure + make
			//
			return response("configure+make", 200);
		} else {

			// make
			//
			$buildPath = Archive::concatPaths($this->source_path, $this->build_dir);
			if ($archive->found($buildPath, 'makefile') || 
				$archive->found($buildPath, 'Makefile')) {
				return response("make", 200);
			} else {
				return response("Could not determine build system.", 404);
			}
		}
	}

	function checkBuildSystem() {
		switch ($this->build_system) {

			case 'make':

				// create archive from package
				//
				$archive = new Archive($this->getPackagePath());
				$buildPath = Archive::concatPaths($this->source_path, $this->build_dir);
				$buildFile = $this->build_file;

				// search archive for build file in build path
				//
				if ($buildFile != NULL) {
					if ($archive->contains($buildPath, $buildFile)) {
						return response("C/C++ package build system ok for make.", 200);
					} else {
						return response("Could not find a build file called '".$buildFile."' within the '".$buildPath."' directory.  You may need to set your build path or the path to your build file.", 404);
					}
				}

				// search archive for default build file in build path
				//
				if ($archive->contains($buildPath, 'makefile') || 
					$archive->contains($buildPath, 'Makefile')) {
					return response("C/C++ package build system ok for make.", 200);
				} else {
					return response("Could not find a build file called 'makefile' or 'Makefile' within '".$this->source_path."' directory. You may need to set your build path or the path to your build file.", 404);
				}
				break;

			case 'configure+make':
			case 'cmake+make':
				return response("C/C++ package build system ok for cmake+make.", 200);
				break;
		}
	}
}
