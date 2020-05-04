<?php
/******************************************************************************\
|                                                                              |
|                              UsageController.php                             |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a controller for returning usage statistics.             |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2020 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Http\Controllers\Utilities;

use App\Http\Controllers\BaseController;
use App\Models\Utilities\Usage;

class UsageController extends BaseController
{
	//
	// querying methods
	//

	public function getUsage() {
		return Usage::all();
	}

	public function getLatestUsage() {
		return Usage::all()->last();
	}
}
