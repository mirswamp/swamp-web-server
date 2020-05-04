<?php
/******************************************************************************\
|                                                                              |
|                             StatusController.php                             |
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
|        Copyright (C) 2012-2020 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Http\Controllers\Utilities;

use Illuminate\Http\Request;
use App\Http\Controllers\BaseController;
use App\Services\SWAMPStatus;

class StatusController extends BaseController
{
	// return sample JSON results data
	//
	// const sampleStatus = 'status.json';
	const sampleStatus = null;

	//
	// querying methods
	//

	public function getCurrent(Request $request) {

		// parse parameters
		//
		$interval = $request->input('database-record-interval');

		// read from local JSON file (testing)
		//
		if (self::sampleStatus) {
			return response()->json(json_decode(file_get_contents(__DIR__ .'/'.self::sampleStatus)));
		}

		$options = array();
		$options['database-record-interval'] = 0;
		if (!empty($interval)) {
			$options['database-record-interval'] = $interval;
		}
		return SWAMPStatus::getCurrent($options);
	}
}
