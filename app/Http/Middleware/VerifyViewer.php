<?php 
/******************************************************************************\
|                                                                              |
|                               VerifyViewer.php                               |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines middleware to verify a viewer.                           |
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
use App\Models\Assessments\Viewer;
use App\Utilities\Filters\FiltersHelper;

class VerifyViewer {

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
				$viewerUuid = $request->route()->getParameter('viewer_uuid');
				if ($viewerUuid) {
					$viewer = Viewer::where('viewer_uuid', '=', $viewerUuid)->first();
					if (!$viewer) {
						return response('Viewer not found.', 404);
					}
				}
				break;
		}

		return $next($request);
	}

}
