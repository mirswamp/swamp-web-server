<?php 
/******************************************************************************\
|                                                                              |
|                         VerifyRunRequestSchedule.php                         |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines middleware to verify a run request schedule.             |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2017 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Input;
use App\Models\Assessments\RunRequestSchedule;
use App\Utilities\Filters\FiltersHelper;

class VerifyRunRequestSchedule {

	/**
	 * Handle an incoming request.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @param  \Closure  $next
	 * @return mixed
	 */
	public function handle($request, Closure $next)
	{
		// check request by method
		//
		switch (FiltersHelper::method()) {
			case 'post':
				break;

			case 'get':
			case 'put':
			case 'delete':
				$runRequestScheduleUuid = $request->route()->getParameter('run_request_schedule_uuid');
				if ($runRequestScheduleUuid) {
					$runRequestSchedule = RunRequestSchedule::where('run_request_schedule_uuid', '=', $runRequestScheduleUuid)->first();
					if (!$runRequestSchedule) {
						return response('Run request schedule not found.', 404);
					}
				}
				break;
		}

		return $next($request);
	}

}
