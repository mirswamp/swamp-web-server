<?php
/******************************************************************************\
|                                                                              |
|                         WebScriptingPackageVersion.php                       |
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
use Illuminate\Support\Facades\Log;
use App\Utilities\Files\Archive;
use App\Models\Packages\PackageVersion;

class WebScriptingPackageVersion extends PackageVersion {

	//
	// querying methods
	//

	function getBuildSystem() {
		return 'no-build';
	}

	function checkBuildSystem() {
		return response("Web scripting package build system ok.", 200);
	}
}
