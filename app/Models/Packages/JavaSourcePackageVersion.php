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
|        Copyright (C) 2012-2017 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\Packages;

use Illuminate\Support\Facades\Response;
use App\Utilities\Files\Archive;
use App\Models\Packages\PackageVersion;

class JavaSourcePackageVersion extends PackageVersion {

	//
	// querying methods
	//

	function getBuildSystem() {
	
		// create archive from package
		//
		$archive = new Archive($this->getPackagePath());
		$buildPath = Archive::concatPaths($this->source_path, $this->build_dir);

		// check for ant
		//
		if ($archive->found($buildPath, 'build.xml')) {
			return response("ant", 200);

		// check for maven
		//
		} else if ($archive->found($buildPath, 'pom.xml')) {
			return response("maven", 200);

		// check for gradle
		//
		} else if ($archive->found($buildPath, 'build.gradle')) {

			// check for gradle wrapper
			//
			if ($archive->found($buildPath, 'gradlew')) {
				return response("gradle-wrapper", 200);
			} else {
				return response("gradle", 200);
			}

		// default case
		//
		} else {
			return response("Could not determine build system.", 404);
		}
	}

	function checkBuildSystem() {

		// create archive from package
		//
		$archive = new Archive($this->getPackagePath());

		// find build path and file
		//
		$buildPath = Archive::concatPaths($this->source_path, $this->build_dir);
		$buildFile = $this->build_file;

		// set default build file name if not set
		//
		if ($buildFile == NULL) {
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

			// search archive for build file in build path
			//
			if ($archive->contains($buildPath, $buildFile)) {
				return response("Java source package version is ok for ".$this->build_system.".", 200);
			} else {
				return response("Could not find a build file called '".$buildFile."' within the '".$buildPath."' directory. You may need to set your build path or the path to your build file.", 404);
			}
		}
	}
}
