<?php
/******************************************************************************\
|                                                                              |
|                     AndroidBytecodePackageVersion.php                        |
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
use App\Models\Packages\JavaBytecodePackageVersion;

class AndroidBytecodePackageVersion extends JavaBytecodePackageVersion
{
	//
	// querying methods
	//

	function getBuildSystem(): string {
		return 'android-apk';
	}

	function getBuildInfo(): array {
		return [
			'build_system' => $this->getBuildSystem(),
			'config_dir' => null,
			'config_cmd' => null,
			'build_dir' => null,
			'build_file' => null
		];
	}

	function checkBuildSystem(): string {
		return "ok";
	}
}
