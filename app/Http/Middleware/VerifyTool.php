<?php 
/******************************************************************************\
|                                                                              |
|                                VerifyTool.php                                |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines middleware to verify a tool.                             |
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
use App\Models\Users\User;
use App\Models\Tools\Tool;
use App\Utilities\Filters\FiltersHelper;
use App\Models\Utilities\Configuration;


class VerifyTool {

	/**
	 * Handle an incoming request.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @param  \Closure  $next
	 * @return mixed
	 */
	public function handle($request, Closure $next)
	{
		// get current user
		//
		if (Session::has('user_uid')) {
			$currentUser = User::getIndex(Session::get('user_uid'));
		} else {
			return response(array(
				'status' => 'NO_SESSION',
				'config' => new Configuration()
			), 401);
		}
		
		// check request by method
		//
		switch (FiltersHelper::method()) {
			case 'post':
				break;

			case 'get':
				$toolUuid = $request->route()->getParameter('tool_uuid');
				if ($toolUuid && $toolUuid != 'all') {
					$tool = Tool::where('tool_uuid', '=', $toolUuid)->first();
					if (!$tool) {
						return response('Tool not found.', 404);
					} else if (!$tool->isReadableBy($currentUser)) {
						return response('Insufficient priveleges to access tool.', 403);
					}
				}
				break;

			case 'put':
			case 'delete':
				$toolUuid = $request->route()->getParameter('tool_uuid');
				if ($toolUuid && $toolUuid != 'all') {
					$tool = Tool::where('tool_uuid', '=', $toolUuid)->first();
					if (!$tool) {
						return response('Tool not found.', 404);
					} else if (!$tool->isWriteableBy($currentUser)) {
						return response('Insufficient priveleges to modify tool.', 403);
					}
				}
				break;
		}

		return $next($request);
	}

}
