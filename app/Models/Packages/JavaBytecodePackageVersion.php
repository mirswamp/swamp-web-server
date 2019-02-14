<?php
/******************************************************************************\
|                                                                              |
|                        JavaBytecodePackageVersion.php                        |
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

class JavaBytecodePackageVersion extends PackageVersion {

	//
	// querying methods
	//

	function getBuildSystem() {
		return 'java-bytecode';
	}

	function getBuildInfo() {
		return [
			'build_system' => $this->getBuildSystem(),
			'config_dir' => null,
			'config_cmd' => null,
			'build_dir' => null,
			'build_file' => null
		];
	}

	function checkBuildSystem() {
		return response("Build system ok.", 200);
	}
}
