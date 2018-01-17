<?php 
/******************************************************************************\
|                                                                              |
|                          VerifyPlatformVersion.php                           |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines middleware to verify a platform version.                 |
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
use App\Models\Users\User;
use App\Models\Platforms\PlatformVersion;
use App\Utilities\Filters\FiltersHelper;
use App\Models\Utilities\Configuration;


class VerifyPlatformVersion {

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
			$currentUser = User::getIndex(session('user_uid'));
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
			case 'put':
			case 'delete':
				$platformVersionUuid = $request->route('platform_version_uuid');
				if ($platformVersionUuid && $platformVersionUuid != 'all') {
					$platformVersion = PlatformVersion::where('platform_version_uuid', '=', $platformVersionUuid)->first();
					if (!$platformVersion) {
						return response('Platform version not found.', 404);
					}
				}
				break;
		}

		return $next($request);
	}

}
