<?php 
/******************************************************************************\
|                                                                              |
|                            VerifyToolVersion.php                             |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines middleware to verify a tool version.                     |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2020 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Session;
use App\Models\Users\User;
use App\Models\Tools\ToolVersion;
use App\Utilities\Filters\FiltersHelper;
use App\Models\Utilities\Configuration;

class VerifyToolVersion
{
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
			$currentUser = User::current();
		} else {
			return response([
				'status' => 'NO_SESSION',
				'config' => new Configuration()
			], 401);
		}

		// check request by method
		//
		switch (FiltersHelper::method()) {
			case 'post':
				break;

			case 'get':
				$toolVersionUuid = $request->route('tool_version_uuid');
				if ($toolVersionUuid && $toolVersionUuid != 'all') {
					$toolVersion = ToolVersion::where('tool_version_uuid', '=', $toolVersionUuid)->first();
					if (!$toolVersion) {
						return response('Tool version not found.', 404);
					} else if (!$toolVersion->isReadableBy($currentUser)) {
						return response('Insufficient priveleges to access tool version.', 403);
					}
				}
				break;

			case 'put':
			case 'delete':
				$toolVersionUuid = $request->route('tool_version_uuid');
				if ($toolVersionUuid && $toolVersionUuid != 'all') {
					$toolVersion = ToolVersion::where('tool_version_uuid', '=', $toolVersionUuid)->first();
					if (!$toolVersion) {
						return response('Tool version not found.', 404);
					} else if (!$toolVersion->isWriteableBy($currentUser)) {
						return response('Insufficient priveleges to modify tool version.', 403);
					}
				}
				break;
		}

		return $next($request);
	}
}