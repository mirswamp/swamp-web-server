<?php 
/******************************************************************************\
|                                                                              |
|                           VerifyPackageVersion.php                           |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines middleware to verify a package version.                  |
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
use App\Models\Packages\PackageVersion;
use App\Utilities\Filters\FiltersHelper;
use App\Models\Utilities\Configuration;

class VerifyPackageVersion
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
				$packageVersionUuid = $request->route('package_version_uuid');
				if ($packageVersionUuid && $packageVersionUuid != 'all') {
					$packageVersion = PackageVersion::where('package_version_uuid', '=', $packageVersionUuid)->first();
					if (!$packageVersion) {
						return response('Package version not found', 404);
					} else if (!$packageVersion->isReadableBy($currentUser)) {
						return response('Insufficient priveleges to access package version.', 403);
					}
				}
				break;

			case 'put':
			case 'delete':
				$packageVersionUuid = $request->route('package_version_uuid');
				if ($packageVersionUuid && $packageVersionUuid != 'all') {
					$packageVersion = PackageVersion::where('package_version_uuid', '=', $packageVersionUuid)->first();
					if (!$packageVersion) {
						return response('Package version not found', 404);
					} else if ($packageVersion && !$packageVersion->isWriteableBy($currentUser)) {
						return response('Insufficient priveleges to modify package version.', 403);
					}
				}
				break;
		}

		return $next($request);
	}
}