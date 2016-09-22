<?php 
/******************************************************************************\
|                                                                              |
|                          VerifyExecutionRecord.php                           |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines middleware to verify an execution record.                |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2016 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Input;
use App\Models\Executions\ExecutionRecord;
use App\Utilities\Filters\FiltersHelper;

class VerifyExecutionRecord {

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
				$executionRecordUuid = $request->route()->getParameter('execution_record_uuid');
				if ($executionRecordUuid) {
					$executionRecord = ExecutionRecord::where('execution_record_uuid', '=', $executionRecordUuid)->first();
					if (!$executionRecord) {
						return response('Execution record not found.', 404);
					}
				}
				break;
		}

		return $next($request);
	}

}
