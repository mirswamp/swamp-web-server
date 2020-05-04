<?php 
/******************************************************************************\
|                                                                              |
|                              VerifyPlatform.php                              |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines middleware to verify a platform.                         |
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
use App\Models\Platforms\Platform;
use App\Utilities\Filters\FiltersHelper;
use App\Models\Utilities\Configuration;

class VerifyPlatform
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
			case 'put':
			case 'delete':
				$platformUuid = $request->route('platform_uuid');
				if ($platformUuid && $platformUuid != 'all') {
					$platform = Platform::where('platform_uuid', '=', $platformUuid)->first();
					if (!$platform) {
						return response('Platform not found.', 404);
					}
				}
				break;
		}

		return $next($request);
	}
}