<?php
/******************************************************************************\
|                                                                              |
|                            PoliciesController.php                            |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a controller for policies.                               |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2020 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Http\Controllers\Users;

use Illuminate\Http\Request;
use App\Models\Users\Policy;
use App\Http\Controllers\BaseController;

class PoliciesController extends BaseController
{
	//
	// get methods
	//

	public function getByCode(Request $request, string $policyCode) {
		return Policy::where('policy_code','=', $policyCode)->first();
	}
}
