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

class CPackageVersion extends PackageVersion {

	//
	// querying methods
	//

	function getBuildSystem() {

		// check in build path
		//
		$archive = new Archive($this->getPackagePath());
		$buildPath = Archive::concatPaths($this->source_path, $this->build_dir);

		// perform top down search of archive
		//
		$found = $archive->search($buildPath, ['makefile', 'Makefile', 'configure', 'configure.ac']);

		if ($found) {
			switch (basename($found)) {
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
