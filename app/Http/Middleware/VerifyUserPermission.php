<?php 
/******************************************************************************\
|                                                                              |
|                          VerifyUserPermission.php                            |
|                                                                              |
|******************************************************************************|
|                                                                              |
|        This defines middleware to verify a user permission.                  |
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
use Illuminate\Support\Facades\Input;
use App\Models\Users\UserPermission;
use App\Utilities\Filters\FiltersHelper;

class VerifyUserPermission {

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
				$userPermissionUuid = $request->route()->getParameter('user_permission_uid');
				if ($userPermissionUuid) {
					$userPermission = UserPermission::where('user_permission_uid', '=', $userPermissionUuid);
					if (!$userPermissionUuid) {
						return response('User permission not found.', 404);
					}	
				}
				break;
		}

		return $next($request);
	}

}
