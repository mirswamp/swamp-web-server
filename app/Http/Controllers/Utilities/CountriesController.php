<?php
/******************************************************************************\
|                                                                              |
|                           CountriesController.php                            |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines a controller for countries.                              |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2019 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Http\Controllers\Utilities;

use App\Http\Controllers\BaseController;
use App\Models\Utilities\Country;

class CountriesController extends BaseController
{
	// get all
	//
	public function getAll() {
		$countries = Country::all();
		return $countries;
	}
}