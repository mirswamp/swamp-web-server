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
|        Copyright (C) 2012-2016 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Models\Packages;

use Illuminate\Support\Facades\Response;
use App\Utilities\Files\Archive;
use App\Models\Packages\JavaBytecodePackageVersion;

class AndroidBytecodePackageVersion extends JavaBytecodePackageVersion {

	//
	// querying methods
	//

	function getBuildSystem() {
		return response("android-apk", 200);
	}

	function checkBuildSystem() {
		response("Android bytecode package version is ok for APK.", 200);
	}
}
