<?php 
/******************************************************************************\
|                                                                              |
|                          VerifyAssessmentResult.php                          |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines middleware to verify an assessment result.               |
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
use App\Models\Assessments\AssessmentResult;
use App\Utilities\Filters\FiltersHelper;

class VerifyAssessmentResult {

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
				$assessmentResultUuid = $request->route()->getParameter('assessment_result_uuid');
				if ($assessmentResultUuid && $assessmentResultUuid != 'none') {
					if (!strpos($assessmentResultUuid, ',')) {

						// check a single result
						//
						$assessmentResult = AssessmentResult::where('assessment_result_uuid', '=', $assessmentResultUuid)->first();
						if (!$assessmentResult) {
							return response('Assessment result not found.', 404);
						}
					} else {

						// check multiple results
						//
						$assessmentResultUuids = explode(',', $assessmentResultUuid);
						foreach ($assessmentResultUuids as $assessmentResultUuid) {
							$assessmentResult = AssessmentResult::where('assessment_result_uuid', '=', $assessmentResultUuid)->first();
							if (!$assessmentResult) {
								return response('Assessment result not found.', 404);
							}
						}
					}
				}
				break;
		}

		return $next($request);
	}

}