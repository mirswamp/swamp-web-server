<?php 
/******************************************************************************\
|                                                                              |
|                              VerifyPackage.php                               |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines middleware to verify a package.                          |
|                                                                              |
|        Author(s): Abe Megahed                                                |
|                                                                              |
|        This file is subject to the terms and conditions defined in           |
|        'LICENSE.txt', which is part of this source code distribution.        |
|                                                                              |
|******************************************************************************|
|        Copyright (C) 2012-2019 Software Assurance Marketplace (SWAMP)        |
\******************************************************************************/

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Input;
use App\Models\Users\User;
use App\Models\Packages\Package;
use App\Utilities\Filters\FiltersHelper;
use App\Models\Utilities\Configuration;


class VerifyPackage {

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
				$packageUuid = $request->route('package_uuid');
				if ($packageUuid && $packageUuid != 'all') {
					$package = Package::where('package_uuid', '=', $packageUuid)->first();
					if (!$package) {
						return response('Package not found.', 404);
					} else if (!$package->isReadableBy($currentUser)) {
						return response('Insufficient priveleges to access package.', 403);
					}
				}
				break;

			case 'put':
			case 'delete':
				$packageUuid = $request->route('package_uuid');
				if ($packageUuid && $packageUuid != 'all') {
					$package = Package::where('package_uuid', '=', $packageUuid)->first();
					if (!$package) {
						return response('Package not found.', 404);
					} else if (!$package->isWriteableBy($currentUser)) {
						return response('Insufficient priveleges to modify package.', 403);
					}
				}
				break;
		}

		return $next($request);
	}

}
