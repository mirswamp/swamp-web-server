<?php 
/******************************************************************************\
|                                                                              |
|                             VerifyRunRequest.php                             |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines middleware to verify a run request.                      |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2018 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Input;
use App\Models\RunRequests\RunRequest;
use App\Utilities\Filters\FiltersHelper;

class VerifyRunRequest {

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
				$runRequestUuid = $request->route('run_request_uuid');
				if ($runRequestUuid) {
					$runRequest = RunRequest::where('run_request_uuid', '=', $runRequestUuid)->first();
					if (!$runRequest) {
						return response('Run request not found.', 404);
					}
				}
				break;
		}

		return $next($request);
	}

}
